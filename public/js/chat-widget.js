/*
 * Khung chat "Tư vấn AI" (JavaScript thuần).
 * - Câu trả lời được stream qua Server-Sent Events; nếu stream lỗi trước khi máy chủ nhận câu hỏi thì gửi lại qua JSON.
 * - Lịch sử hội thoại lưu trên máy chủ; trình duyệt chỉ nhớ mã cuộc trò chuyện và mã khách (localStorage).
 * - Nội dung từ mô hình chỉ được hiển thị bằng textContent (in đậm **...**, gạch đầu dòng "- "), không chèn HTML.
 */
(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const money = (value) => `${new Intl.NumberFormat('vi-VN').format(Math.round(Number(value) || 0))} ₫`;
  const csrf = $('meta[name="csrf-token"]')?.content;

  /* ---------- Hiển thị tập con markdown chatbot dùng ---------- */
  const renderInline = (target, text) => {
    String(text).split(/(\*\*[^*]+\*\*)/g).filter(Boolean).forEach((part) => {
      if (part.startsWith('**') && part.endsWith('**') && part.length > 4) {
        const strong = document.createElement('strong');
        strong.textContent = part.slice(2, -2);
        target.append(strong);
      } else {
        target.append(document.createTextNode(part));
      }
    });
  };
  const renderRichText = (container, text) => {
    container.innerHTML = '';
    const wrapper = document.createElement('div');
    wrapper.className = 'chat-rich-text';
    let list = null;
    String(text || '').split('\n').forEach((line) => {
      const trimmed = line.trim();
      if (/^[-*•]\s+/.test(trimmed)) {
        if (!list) {
          list = document.createElement('ul');
          wrapper.append(list);
        }
        const item = document.createElement('li');
        renderInline(item, trimmed.replace(/^[-*•]\s+/, ''));
        list.append(item);
        return;
      }
      list = null;
      if (trimmed) {
        const paragraph = document.createElement('p');
        renderInline(paragraph, trimmed);
        wrapper.append(paragraph);
      }
    });
    container.append(wrapper);
  };
  // Trang quản trị xem lại hội thoại dùng chung cách hiển thị.
  document.querySelectorAll('[data-rich-text]').forEach((node) => renderRichText(node, node.textContent.trim()));

  const widget = $('[data-chat-widget]');
  if (!widget) return;

  const config = JSON.parse(widget.dataset.config || '{}');
  const endpoints = JSON.parse(widget.dataset.endpoints || '{}');
  const panel = $('[data-chat-panel]', widget);
  const list = $('[data-chat-messages]', widget);
  const form = $('[data-chat-form]', widget);
  const input = $('[data-chat-input]', widget);
  const sendButton = $('[data-chat-send]', widget);
  const toggle = $('[data-chat-toggle]', widget);
  const robotTemplate = $('[data-chat-robot]', widget);
  const icon = (name) => $(`[data-icon="${name}"]`, $('[data-chat-icons]', widget).content).innerHTML;
  const robot = (className = '') => {
    const svg = robotTemplate.content.firstElementChild.cloneNode(true);
    if (className) className.split(' ').forEach((name) => svg.classList.add(name));
    return svg;
  };
  const toast = (type, message) => window.TechPhone?.showToast(type, message);

  const KEYS = { visitor: 'chat_visitor_id', session: 'chat_session_id' };
  const store = {
    get: (key) => { try { return localStorage.getItem(key); } catch { return null; } },
    set: (key, value) => { try { localStorage.setItem(key, value); } catch { /* trình duyệt chặn lưu trữ: chat vẫn chạy trong trang này */ } },
    remove: (key) => { try { localStorage.removeItem(key); } catch { /* bỏ qua */ } },
  };
  let visitorId = store.get(KEYS.visitor);
  if (!/^[A-Za-z0-9_-]{8,100}$/.test(visitorId || '')) {
    visitorId = `v-${window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`}`;
    store.set(KEYS.visitor, visitorId);
  }
  let sessionId = Number(store.get(KEYS.session)) || null;
  const rememberSession = (id) => {
    sessionId = id || null;
    if (sessionId) store.set(KEYS.session, String(sessionId));
    else store.remove(KEYS.session);
  };

  let sending = false;
  let historyLoaded = false;
  let messageCount = 0;

  const scrollToEnd = () => { list.scrollTop = list.scrollHeight; };

  const productCard = (product) => {
    const card = document.createElement('article');
    card.className = 'chat-product-card';
    if (product.image) {
      const image = document.createElement('img');
      image.src = product.image;
      image.alt = '';
      image.loading = 'lazy';
      card.append(image);
    }
    const info = document.createElement('div');
    const link = document.createElement('a');
    link.href = product.url;
    link.textContent = product.name;
    const price = document.createElement('strong');
    price.textContent = money(product.price);
    info.append(link, price);
    if (product.oldPrice > product.price) {
      const old = document.createElement('del');
      old.textContent = money(product.oldPrice);
      info.append(old);
    }
    // Dùng chung xử lý AJAX "thêm vào giỏ" của app.js (data-ajax="cart"): toast + cập nhật số trên giỏ hàng.
    const cart = document.createElement('form');
    cart.method = 'POST';
    cart.action = `${endpoints.cart}/${product.type === 'accessory' ? 'accessory' : 'product'}/${product.id}`;
    cart.dataset.ajax = 'cart';
    cart.innerHTML = `<input type="hidden" name="_token"><input type="hidden" name="quantity" value="1"><button type="submit">${icon('cart')}</button>`;
    $('input[name="_token"]', cart).value = csrf || '';
    const button = $('button', cart);
    button.setAttribute('aria-label', product.stock ? `Thêm ${product.name} vào giỏ` : `${product.name} tạm hết hàng`);
    button.disabled = !product.stock;
    card.append(info, cart);
    return card;
  };

  const feedbackButtons = (message) => {
    const wrap = document.createElement('div');
    wrap.className = 'chat-feedback';
    [['up', 'Câu trả lời hữu ích'], ['down', 'Câu trả lời chưa tốt']].forEach(([value, label]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.innerHTML = icon(value);
      button.setAttribute('aria-label', label);
      button.setAttribute('aria-pressed', String(message.feedback === value));
      button.addEventListener('click', async () => {
        const next = message.feedback === value ? null : value;
        message.feedback = next;
        wrap.querySelectorAll('button').forEach((item, index) => item.setAttribute('aria-pressed', String(next === ['up', 'down'][index])));
        try {
          const response = await fetch(`${endpoints.feedback}/${message.id}/feedback`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ value: next, visitor_id: visitorId }),
          });
          if (!response.ok) throw new Error('feedback');
        } catch {
          toast('error', 'Không gửi được đánh giá');
        }
      });
      wrap.append(button);
    });
    return wrap;
  };

  // Tin nhắn: user, assistant (kèm thẻ sản phẩm, nút đánh giá), lỗi, hoặc bong bóng đang stream.
  const addMessage = (message, { withFeedback = true, streaming = false } = {}) => {
    const row = document.createElement('div');
    row.className = `chat-message ${message.role}${message.error ? ' error' : ''}`;
    if (message.role === 'assistant') {
      row.classList.add('with-avatar');
      const avatar = document.createElement('span');
      avatar.className = 'chat-message-avatar';
      avatar.append(robot());
      row.append(avatar);
    }
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    if (message.role === 'assistant') renderRichText(bubble, message.content);
    else bubble.textContent = message.content;
    row.append(bubble);
    if (message.products?.length) {
      const products = document.createElement('div');
      products.className = 'chat-products';
      message.products.forEach((product) => products.append(productCard(product)));
      row.append(products);
    }
    if (message.role === 'assistant' && message.id && !message.error && !streaming && withFeedback) row.append(feedbackButtons(message));
    list.append(row);
    messageCount += 1;
    scrollToEnd();
    return { row, bubble };
  };

  const quickQuestions = () => {
    if (!config.quickQuestions?.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'chat-quick';
    wrap.dataset.chatQuick = '';
    config.quickQuestions.forEach((question) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = question;
      button.addEventListener('click', () => send(question));
      wrap.append(button);
    });
    list.append(wrap);
  };

  const renderWelcome = () => {
    list.innerHTML = '';
    messageCount = 0;
    const hero = document.createElement('div');
    hero.className = 'chat-hero';
    const heroRobot = document.createElement('span');
    heroRobot.className = 'chat-hero-robot';
    heroRobot.append(robot('is-floating'));
    const title = document.createElement('strong');
    title.textContent = `Trợ lý AI ${widget.dataset.storeName || ''}`.trim();
    const subtitle = document.createElement('small');
    subtitle.textContent = 'Đang trực tuyến · Trả lời theo dữ liệu cửa hàng';
    hero.append(heroRobot, title, subtitle);
    list.append(hero);
    addMessage({ role: 'assistant', content: config.greeting }, { withFeedback: false });
    quickQuestions();
  };

  const typing = () => {
    const row = document.createElement('div');
    row.className = 'chat-message assistant with-avatar';
    const avatar = document.createElement('span');
    avatar.className = 'chat-message-avatar';
    avatar.append(robot('is-thinking'));
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble chat-typing';
    bubble.setAttribute('aria-label', 'Trợ lý đang trả lời');
    bubble.innerHTML = '<span></span><span></span><span></span>';
    row.append(avatar, bubble);
    list.append(row);
    scrollToEnd();
    return row;
  };

  const setSending = (value) => {
    sending = value;
    sendButton.disabled = value || !input.value.trim();
    $('.chat-avatar svg', widget)?.classList.toggle('is-thinking', value);
  };

  const failure = (message, status) => Object.assign(new Error(message), { status });

  /** Đọc luồng SSE: session → delta* → done | error. */
  const streamMessage = async (payload, onEvent) => {
    const response = await fetch(endpoints.stream, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(payload),
    });
    if (!response.ok || !response.body || !(response.headers.get('content-type') || '').includes('text/event-stream')) {
      const body = await response.json().catch(() => ({}));
      throw failure(body.message || 'Trợ lý đang gặp sự cố', response.status);
    }
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let result = null;
    const handle = (block) => {
      const data = block.split('\n').filter((line) => line.startsWith('data:')).map((line) => line.slice(5).trim()).join('');
      if (!data) return;
      const event = JSON.parse(data);
      if (event.type === 'error') throw failure(event.message, 500);
      if (event.type === 'done') result = event;
      onEvent(event);
    };
    for (;;) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true }).replace(/\r\n/g, '\n');
      let boundary = buffer.indexOf('\n\n');
      while (boundary !== -1) {
        handle(buffer.slice(0, boundary));
        buffer = buffer.slice(boundary + 2);
        boundary = buffer.indexOf('\n\n');
      }
    }
    if (buffer.trim()) handle(buffer);
    if (!result) throw failure('Kết nối bị gián đoạn', 502);
    return result;
  };

  const sendJson = async (payload) => {
    const response = await fetch(endpoints.send, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(payload),
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw failure(body.message || 'Trợ lý đang gặp sự cố', response.status);
    return body;
  };

  const errorText = (status) => {
    if (status === 429) return 'Bạn gửi hơi nhanh, vui lòng chờ một chút rồi thử lại nhé.';
    if (status === 503) return 'Chatbot đang tạm ngưng. Bạn vui lòng liên hệ hotline để được hỗ trợ nhé.';
    if (status === 419) return 'Phiên làm việc đã hết hạn. Bạn tải lại trang rồi hỏi lại giúp mình nhé.';
    return 'Xin lỗi, trợ lý đang gặp sự cố. Bạn thử lại sau hoặc gọi hotline nhé.';
  };

  async function send(text) {
    const message = String(text || '').trim();
    if (!message || sending) return;
    if (message.length > 1000) {
      toast('error', 'Tin nhắn tối đa 1000 ký tự');
      return;
    }
    $('[data-chat-quick]', list)?.remove();
    input.value = '';
    addMessage({ role: 'user', content: message });
    setSending(true);
    const payload = { message, session_id: sessionId, visitor_id: visitorId };
    let waiting = typing();
    let streamingBubble = null;
    let streamedText = '';
    // Máy chủ đã lưu câu hỏi (sự kiện session) thì không gửi lại nữa, tránh lưu trùng.
    let serverStored = false;
    const ensureStreamingBubble = () => {
      if (streamingBubble) return streamingBubble;
      waiting?.remove();
      waiting = null;
      streamingBubble = addMessage({ role: 'assistant', content: '' }, { streaming: true });
      return streamingBubble;
    };
    try {
      let result;
      try {
        result = await streamMessage(payload, (event) => {
          if (event.type === 'session') {
            serverStored = true;
            if (event.sessionId !== sessionId) rememberSession(event.sessionId);
          } else if (event.type === 'delta') {
            streamedText += event.text;
            renderRichText(ensureStreamingBubble().bubble, streamedText);
            scrollToEnd();
          } else if (event.type === 'reset') {
            streamedText = '';
            if (streamingBubble) renderRichText(streamingBubble.bubble, '');
          }
        });
      } catch (error) {
        if (serverStored || streamedText || [404, 419, 422, 429, 503].includes(error.status)) throw error;
        result = await sendJson(payload);
      }
      waiting?.remove();
      streamingBubble?.row.remove();
      if (result.sessionId !== sessionId) rememberSession(result.sessionId);
      addMessage(result.message);
    } catch (error) {
      waiting?.remove();
      streamingBubble?.row.remove();
      if (error.status === 404 && sessionId) rememberSession(null);
      addMessage({ role: 'assistant', error: true, content: errorText(error.status) });
    } finally {
      setSending(false);
      input.focus();
    }
  }

  const loadHistory = async () => {
    if (historyLoaded) return;
    historyLoaded = true;
    if (!sessionId) return;
    try {
      const response = await fetch(`${endpoints.session}/${sessionId}?visitor_id=${encodeURIComponent(visitorId)}`, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('history');
      const data = await response.json();
      if (data.messages?.length && messageCount <= 1) {
        $('[data-chat-quick]', list)?.remove();
        data.messages.forEach((message) => addMessage(message));
      }
    } catch {
      rememberSession(null);
    }
  };

  const setOpen = (open) => {
    panel.hidden = !open;
    widget.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Đóng trợ lý AI' : 'Mở trợ lý AI tư vấn');
    $('[data-chat-toggle-open]', toggle).hidden = open;
    $('[data-chat-toggle-close]', toggle).hidden = !open;
    $('[data-chat-toggle-label]', toggle).textContent = open ? 'Đóng' : 'Tư vấn AI';
    if (open) {
      if (!list.childElementCount) renderWelcome();
      loadHistory();
      input.focus();
      scrollToEnd();
    }
  };

  toggle.addEventListener('click', () => setOpen(panel.hidden));
  $('[data-chat-close]', widget).addEventListener('click', () => setOpen(false));
  $('[data-chat-reset]', widget).addEventListener('click', () => {
    rememberSession(null);
    renderWelcome();
    input.focus();
  });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !panel.hidden) setOpen(false); });
  input.addEventListener('input', () => { sendButton.disabled = sending || !input.value.trim(); });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    send(input.value);
  });
})();
