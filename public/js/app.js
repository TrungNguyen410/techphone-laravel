/*
 * TechPhone - tương tác phía trình duyệt (JavaScript thuần, không cần build).
 * Mọi chức năng vẫn chạy được bằng form HTML thông thường khi tắt JavaScript;
 * script này chỉ bổ sung trải nghiệm: modal, toast, AJAX, gợi ý tìm kiếm...
 */
(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const csrf = $('meta[name="csrf-token"]')?.content;
  const money = (value) => `${new Intl.NumberFormat('vi-VN').format(Math.round(Number(value) || 0))} ₫`;
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Toast thông báo ---------- */
  const toastStack = $('[data-toast-stack]');
  const icons = {
    success: '<svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" height="1em" width="1em"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    error: '<svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" height="1em" width="1em"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  };
  const dismissToast = (toast) => {
    toast.classList.add('is-leaving');
    setTimeout(() => toast.remove(), 250);
  };
  const scheduleToast = (toast) => setTimeout(() => dismissToast(toast), 4500);
  const showToast = (type, message) => {
    if (!toastStack || !message) return;
    const toast = document.createElement('div');
    toast.className = `app-toast ${type}`;
    toast.setAttribute('role', 'status');
    toast.innerHTML = `${icons[type] || icons.error}<span></span><button type="button" aria-label="Đóng thông báo" data-toast-close>×</button>`;
    toast.querySelector('span').textContent = message;
    toastStack.append(toast);
    scheduleToast(toast);
  };
  $$('[data-toast]').forEach(scheduleToast);
  document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-toast-close]');
    if (close) dismissToast(close.closest('.app-toast'));
  });

  /* ---------- Dialog (modal) ---------- */
  const openDialog = (dialog) => {
    if (!dialog || dialog.open) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  };
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-dialog]');
    if (opener) openDialog(document.getElementById(opener.dataset.openDialog));
    const closer = event.target.closest('[data-close-dialog]');
    if (closer) closer.closest('dialog')?.close();
    // Bấm ra vùng nền tối bên ngoài thì đóng dialog.
    if (event.target instanceof HTMLDialogElement) event.target.close();
  });
  // Dialog mở theo URL (?edit=, ?view=): khi người dùng đóng thì quay về trang danh sách.
  // Bỏ qua khi trang đang rời đi (trình duyệt tự đóng dialog) để không chặn điều hướng.
  let leavingPage = false;
  window.addEventListener('beforeunload', () => { leavingPage = true; });
  window.addEventListener('pageshow', () => { leavingPage = false; });
  $$('dialog').forEach((dialog) => {
    dialog.addEventListener('close', () => {
      if (dialog.dataset.closeUrl && !leavingPage) window.location.href = dialog.dataset.closeUrl;
    });
  });
  $$('dialog[data-autoshow]').forEach(openDialog);

  /* ---------- Hộp thoại xác nhận cho form có data-confirm ---------- */
  const confirmDialog = $('#confirm-dialog');
  let pendingConfirm = null;
  const askConfirm = (title, message, onAccept) => {
    if (!confirmDialog) {
      if (window.confirm(message)) onAccept();
      return;
    }
    $('[data-confirm-title]', confirmDialog).textContent = title || 'Xác nhận thao tác';
    $('[data-confirm-message]', confirmDialog).textContent = message || '';
    pendingConfirm = onAccept;
    openDialog(confirmDialog);
  };
  $('[data-confirm-accept]', confirmDialog || document)?.addEventListener('click', () => {
    const action = pendingConfirm;
    pendingConfirm = null;
    confirmDialog.close();
    action?.();
  });
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('[data-confirm]') || form.dataset.confirmed) return;
    event.preventDefault();
    askConfirm(form.dataset.confirmTitle, form.dataset.confirm, () => {
      form.dataset.confirmed = '1';
      form.requestSubmit ? form.requestSubmit() : form.submit();
    });
  });

  /* ---------- Tự gửi form khi đổi select ---------- */
  document.addEventListener('change', (event) => {
    const field = event.target;
    if (field.matches('select[data-auto-submit]')) {
      const original = [...field.options].find((option) => option.defaultSelected)?.value;
      if (field.dataset.confirmCancel && field.value === 'cancelled') {
        askConfirm('Hủy đơn hàng?', field.dataset.confirmCancel, () => field.form.submit());
        field.value = original;
        return;
      }
      field.form.submit();
    }
    if (field.closest('form[data-auto-submit-form]') && field.matches('select')) field.form.submit();
  });

  /* ---------- Chuẩn hóa input ---------- */
  document.addEventListener('input', (event) => {
    const field = event.target;
    if (field.matches('[data-uppercase]')) field.value = field.value.toUpperCase();
    if (field.matches('[data-digits-only]')) field.value = field.value.replace(/\D/g, '').slice(0, 6);
  });

  /* ---------- AJAX: yêu thích, so sánh, thêm giỏ hàng ---------- */
  const setBadge = (selector, count) => {
    $$(selector).forEach((badge) => {
      badge.textContent = count;
      badge.hidden = Number(count) === 0;
    });
  };
  document.addEventListener('submit', async (event) => {
    const form = event.target;
    const kind = form.dataset.ajax;
    if (!kind) return;
    event.preventDefault();
    const button = form.querySelector('button');
    button.disabled = true;
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: new FormData(form),
      });
      const data = await response.json();
      if (kind === 'cart') {
        showToast(response.ok ? 'success' : 'error', data.message);
        setBadge('[data-cart-count]', data.count);
        $$('[data-cart-count-text]').forEach((node) => { node.textContent = `${data.count} sản phẩm`; });
      } else if (response.ok) {
        button.classList.toggle('active', data.active);
        button.setAttribute('aria-pressed', String(data.active));
        if (kind === 'wishlist') setBadge('[data-wishlist-count]', data.count);
        showToast(data.active || kind === 'wishlist' ? 'success' : 'info', data.message);
      } else {
        showToast('error', data.message || 'Không thể cập nhật, vui lòng thử lại');
      }
    } catch {
      form.removeAttribute('data-ajax');
      form.submit();
    } finally {
      if (kind !== 'cart' || !button.closest('.product-card')?.querySelector('.stock-badge')) button.disabled = false;
    }
  });

  /* ---------- Gợi ý tìm kiếm ở header ---------- */
  const searchForm = $('[data-search-suggest]');
  if (searchForm) {
    const input = $('input[name="q"]', searchForm);
    const list = $('.search-suggestions', searchForm);
    let timer;
    let active = -1;
    let controller;
    const options = () => $$('[role="option"]', list);
    const highlight = (index) => {
      active = index;
      options().forEach((option, i) => option.setAttribute('aria-selected', String(i === index)));
    };
    const hide = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    input.addEventListener('input', () => {
      clearTimeout(timer);
      const term = input.value.trim();
      if (term.length < 2) return hide();
      timer = setTimeout(async () => {
        controller?.abort();
        controller = new AbortController();
        try {
          const response = await fetch(`${searchForm.dataset.searchSuggest}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
          });
          const items = await response.json();
          list.innerHTML = '';
          if (!items.length) {
            list.innerHTML = '<p>Không tìm thấy sản phẩm phù hợp.</p>';
          }
          items.forEach((item, index) => {
            const link = document.createElement('a');
            link.href = item.url;
            link.id = `header-suggestion-${index}`;
            link.setAttribute('role', 'option');
            link.innerHTML = '<img alt=""><span><strong></strong><small></small></span>';
            link.querySelector('img').src = item.image;
            link.querySelector('strong').textContent = item.name;
            link.querySelector('small').textContent = `${item.brand} · ${item.price}`;
            link.addEventListener('mouseenter', () => highlight(index));
            list.append(link);
          });
          list.hidden = false;
          input.setAttribute('aria-expanded', 'true');
          active = -1;
        } catch {
          /* bỏ qua khi request bị hủy */
        }
      }, 250);
    });
    input.addEventListener('keydown', (event) => {
      const items = options();
      if (event.key === 'Escape') return hide();
      if (list.hidden || !items.length) return;
      if (event.key === 'ArrowDown') { event.preventDefault(); highlight((active + 1) % items.length); }
      if (event.key === 'ArrowUp') { event.preventDefault(); highlight(active <= 0 ? items.length - 1 : active - 1); }
      if (event.key === 'Enter' && active >= 0) { event.preventDefault(); items[active].click(); }
    });
    input.addEventListener('blur', () => setTimeout(hide, 150));
  }

  /* ---------- Nút hỗ trợ nổi ---------- */
  const support = $('[data-support-launcher]');
  if (support) {
    const panel = $('.support-panel', support);
    const toggle = () => {
      const open = panel.hidden;
      panel.hidden = !open;
      support.classList.toggle('is-open', open);
      const button = $('.support-toggle', support);
      button.setAttribute('aria-expanded', String(open));
      button.setAttribute('aria-label', open ? 'Đóng kênh hỗ trợ' : 'Mở kênh hỗ trợ');
      $('[data-support-icon="open"]', support).hidden = open;
      $('[data-support-icon="close"]', support).hidden = !open;
      $('[data-support-label]', support).textContent = open ? 'Đóng' : 'Hỗ trợ';
    };
    $$('[data-support-toggle]', support).forEach((button) => button.addEventListener('click', toggle));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !panel.hidden) toggle(); });
  }

  /* ---------- Banner trang chủ ---------- */
  const slider = $('[data-hero-slider]');
  if (slider) {
    const slides = $$('[data-slide]', slider);
    const dots = $$('[data-slide-dot]', slider);
    const pauseButton = $('[data-slider-pause]', slider);
    let current = 0;
    let userPaused = false;
    let hoverPaused = false;
    const show = (index) => {
      current = index;
      slides.forEach((slide, i) => {
        const active = i === index;
        slide.classList.toggle('active', active);
        slide.style.visibility = active ? '' : 'hidden';
        slide.style.pointerEvents = active ? '' : 'none';
        slide.toggleAttribute('aria-hidden', !active);
        if (active) slide.removeAttribute('tabindex'); else slide.setAttribute('tabindex', '-1');
      });
      dots.forEach((dot, i) => { dot.classList.toggle('active', i === index); dot.setAttribute('aria-pressed', String(i === index)); });
    };
    dots.forEach((dot) => dot.addEventListener('click', () => show(Number(dot.dataset.slideDot))));
    slider.addEventListener('mouseenter', () => { hoverPaused = true; });
    slider.addEventListener('mouseleave', () => { hoverPaused = false; });
    pauseButton?.addEventListener('click', () => {
      userPaused = !userPaused;
      pauseButton.setAttribute('aria-label', userPaused ? 'Tiếp tục banner' : 'Tạm dừng banner');
      $('[data-pause-icon]', pauseButton).hidden = userPaused;
      $('[data-play-icon]', pauseButton).hidden = !userPaused;
    });
    if (slides.length > 1 && !reducedMotion) {
      setInterval(() => { if (!userPaused && !hoverPaused) show((current + 1) % slides.length); }, 5000);
    }
  }

  /* ---------- Danh mục chạy ngang ---------- */
  const marquee = $('[data-category-marquee]');
  if (marquee) {
    const track = $('.category-marquee-track', marquee);
    const button = $('[data-marquee-pause]', marquee);
    if (reducedMotion) {
      marquee.classList.add('is-static');
      track.classList.remove('is-looping');
      button.hidden = true;
      $$('.category-marquee-set[aria-hidden]', marquee).forEach((clone) => clone.remove());
    }
    let manual = false;
    const sync = (paused) => track.classList.toggle('is-paused', paused || manual);
    marquee.addEventListener('mouseenter', () => sync(true));
    marquee.addEventListener('mouseleave', () => sync(false));
    marquee.addEventListener('focusin', () => sync(true));
    marquee.addEventListener('focusout', () => sync(false));
    button?.addEventListener('click', () => {
      manual = !manual;
      sync(false);
      button.setAttribute('aria-label', manual ? 'Tiếp tục danh mục' : 'Tạm dừng danh mục');
      $('[data-pause-icon]', button).hidden = manual;
      $('[data-play-icon]', button).hidden = !manual;
    });
  }

  /* ---------- Bộ lọc dạng ngăn kéo trên mobile ---------- */
  const filterPanel = $('[data-filter-panel]');
  const filterOverlay = $('.drawer-overlay[data-filter-close]');
  const setFilterOpen = (open) => {
    filterPanel?.classList.toggle('open', open);
    if (filterOverlay) filterOverlay.hidden = !open;
  };
  $$('[data-filter-open]').forEach((button) => button.addEventListener('click', () => setFilterOpen(true)));
  $$('[data-filter-close]').forEach((button) => button.addEventListener('click', () => setFilterOpen(false)));

  /* ---------- Gallery ảnh sản phẩm ---------- */
  $$('[data-gallery]').forEach((gallery) => {
    const main = $('[data-gallery-main]', gallery);
    $$('[data-gallery-thumb]', gallery).forEach((thumb) => thumb.addEventListener('click', () => {
      main.src = thumb.dataset.galleryThumb;
      $$('[data-gallery-thumb]', gallery).forEach((item) => item.classList.toggle('active', item === thumb));
    }));
  });

  /* ---------- Chọn số lượng ---------- */
  $$('[data-quantity-stepper]').forEach((stepper) => {
    const input = $('[data-quantity-input]', stepper);
    const label = $('[data-quantity-label]', stepper);
    const [minus, plus] = $$('[data-step]', stepper);
    const max = Number(stepper.dataset.max) || 1;
    const set = (value) => {
      const next = Math.min(Math.max(1, value), max);
      input.value = next;
      label.textContent = next;
      minus.disabled = next <= 1;
      plus.disabled = next >= max;
    };
    $$('[data-step]', stepper).forEach((button) => button.addEventListener('click', () => set(Number(input.value) + Number(button.dataset.step))));
    if (Number(input.value) > 0) set(Number(input.value));
  });

  /* ---------- Đánh giá: chọn sao, ảnh, lọc theo số sao ---------- */
  $$('[data-review-form]').forEach((form) => {
    const ratingInput = $('[data-rating-input]', form);
    const stars = $$('[data-star]', form);
    stars.forEach((star) => star.addEventListener('click', () => {
      ratingInput.value = star.dataset.star;
      stars.forEach((item) => item.classList.toggle('active', Number(item.dataset.star) <= Number(star.dataset.star)));
    }));
    const fileInput = $('[data-review-images]', form);
    const preview = $('[data-review-image-preview]', form);
    const count = $('[data-review-image-count]', form);
    fileInput?.addEventListener('change', () => {
      if (fileInput.files.length > 5) {
        showToast('error', 'Bạn chỉ có thể tải tối đa 5 ảnh cho một đánh giá');
        fileInput.value = '';
      }
      preview.innerHTML = '';
      [...fileInput.files].forEach((file) => {
        const wrap = document.createElement('div');
        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.alt = 'Ảnh đánh giá';
        wrap.append(image);
        preview.append(wrap);
      });
      preview.hidden = !fileInput.files.length;
      count.textContent = `${fileInput.files.length}/5 ảnh`;
    });
  });
  $$('[data-review-section]').forEach((section) => {
    const reset = $('[data-review-filter-reset]', section);
    const buttons = $$('[data-review-filter]', section);
    const apply = (star) => {
      $$('.review-item[data-rating]', section).forEach((item) => { item.hidden = Boolean(star) && item.dataset.rating !== String(star); });
      buttons.forEach((button) => {
        const active = button.dataset.reviewFilter === String(star);
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', String(active));
      });
      reset.disabled = !star;
      reset.textContent = star ? `Bỏ lọc ${star} sao` : 'Đang xem tất cả';
    };
    buttons.forEach((button) => button.addEventListener('click', () => {
      apply(button.classList.contains('active') ? 0 : Number(button.dataset.reviewFilter));
    }));
    reset?.addEventListener('click', () => apply(0));
  });

  /* ---------- Câu hỏi thường gặp ---------- */
  $$('[data-faq-toggle]').forEach((button) => button.addEventListener('click', () => {
    const item = button.closest('[data-faq]');
    const wasOpen = item.classList.contains('open');
    $$('[data-faq]').forEach((faq) => faq.classList.remove('open'));
    item.classList.toggle('open', !wasOpen);
  }));

  /* ---------- Đăng nhập: điền tài khoản thử, hiện mật khẩu ---------- */
  $$('[data-fill-login]').forEach((button) => button.addEventListener('click', () => {
    const form = button.closest('form');
    $('input[name="identifier"]', form).value = button.dataset.fillLogin;
    $('input[name="password"]', form).focus();
  }));
  $$('[data-toggle-password]').forEach((button) => button.addEventListener('click', () => {
    const input = $('[data-password]', button.parentElement);
    input.type = input.type === 'password' ? 'text' : 'password';
    button.setAttribute('aria-label', input.type === 'password' ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
  }));

  /* ---------- Thanh toán ---------- */
  const checkout = $('[data-checkout]');
  if (checkout) {
    const subtotal = Number(checkout.dataset.subtotal);
    const quotes = JSON.parse(checkout.dataset.quotes);
    const defaultQuote = JSON.parse(checkout.dataset.defaultQuote);
    const bank = JSON.parse(checkout.dataset.bank || '{}');
    const momo = JSON.parse(checkout.dataset.momo || '{}');
    const submit = $('[data-checkout-submit]', checkout);
    const back = $('[data-payment-back]', checkout);
    const pendingNote = $('[data-payment-pending-note]', checkout);
    const reference = submit.dataset.paymentReference;
    let confirmStep = false;

    const method = () => $('input[name="payment_method"]:checked', checkout)?.value || 'cod';
    const quote = () => quotes[$('[data-province]', checkout).value] || defaultQuote;
    const total = () => Math.max(0, subtotal + quote().fee - quote().discount);
    const transferContent = () => `${reference} ${$('input[name="phone"]', checkout).value.trim() || 'TECHPHONE'}`.toUpperCase();

    const render = () => {
      const current = quote();
      $('[data-shipping-fee]', checkout).textContent = current.fee ? money(current.fee) : 'Miễn phí';
      $('[data-shipping-eta]', checkout).textContent = current.eta;
      $('[data-discount]', checkout).textContent = `-${money(current.discount)}`;
      $$('[data-order-total]', checkout).forEach((node) => { node.textContent = money(total()); });
      $$('[data-transfer-content]', checkout).forEach((node) => { node.textContent = transferContent(); });
      const bankQr = $('[data-qr="bank"]', checkout);
      if (bankQr) {
        const params = new URLSearchParams({ amount: String(total()), addInfo: transferContent(), accountName: bank.account_name || '' });
        bankQr.src = `https://img.vietqr.io/image/${bank.bin}-${bank.account_number}-compact2.png?${params}`;
      }
      const momoQr = $('[data-qr="momo"]', checkout);
      if (momoQr) {
        momoQr.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(`MOMO|${momo.phone || ''}|${total()}|${transferContent()}`)}`;
      }
      $$('[data-payment-confirm]', checkout).forEach((section) => { section.hidden = !(confirmStep && section.dataset.paymentConfirm === method()); });
      back.hidden = !confirmStep;
      pendingNote.hidden = !confirmStep;
      submit.textContent = confirmStep
        ? 'Tôi đã thanh toán'
        : method() === 'card' ? 'Thanh toán qua VNPay'
          : ['bank', 'momo'].includes(method()) ? 'Tiếp tục thanh toán' : 'Xác nhận đặt hàng';
    };

    checkout.addEventListener('change', (event) => {
      if (event.target.name === 'payment_method') {
        confirmStep = false;
        $$('[data-payment-option]', checkout).forEach((option) => option.classList.toggle('active', option.contains(event.target)));
      }
      render();
    });
    $('input[name="phone"]', checkout).addEventListener('input', render);
    back.addEventListener('click', () => { confirmStep = false; render(); });

    checkout.addEventListener('submit', (event) => {
      const required = ['full_name', 'phone', 'email', 'address', 'province', 'ward'];
      const missing = required.find((name) => !String(checkout.elements[name].value).trim());
      if (missing) {
        event.preventDefault();
        checkout.elements[missing].focus();
        showToast('error', 'Vui lòng kiểm tra lại thông tin nhận hàng');
        return;
      }
      if (['bank', 'momo'].includes(method()) && !confirmStep) {
        event.preventDefault();
        confirmStep = true;
        render();
        showToast('info', 'Hoàn tất thanh toán và bấm "Tôi đã thanh toán" để tạo đơn hàng.');
        $(`[data-payment-confirm="${method()}"]`, checkout)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      $('[data-transfer-input]', checkout).value = ['bank', 'momo'].includes(method()) ? transferContent() : '';
      submit.disabled = true;
      submit.textContent = method() === 'card' ? 'Đang mở VNPay…' : 'Đang tạo đơn hàng...';
    });
    render();
  }

  /* ---------- Khu vực quản trị ---------- */
  const sidebar = $('[data-admin-sidebar]');
  const sidebarOverlay = $('.admin-sidebar-overlay');
  const setSidebar = (open) => {
    sidebar?.classList.toggle('open', open);
    if (sidebarOverlay) sidebarOverlay.hidden = !open;
  };
  $$('[data-admin-sidebar-open]').forEach((button) => button.addEventListener('click', () => setSidebar(true)));
  $$('[data-admin-sidebar-close]').forEach((button) => button.addEventListener('click', () => setSidebar(false)));

  const popovers = $$('.admin-header-popover');
  $$('[data-popover-toggle]').forEach((button) => button.addEventListener('click', () => {
    const target = document.getElementById(button.dataset.popoverToggle);
    const open = target.hidden;
    popovers.forEach((popover) => { popover.hidden = true; });
    $$('[data-popover-toggle]').forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
    target.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    if (open) $('input', target)?.focus();
  }));
  $$('[data-popover-close]').forEach((button) => button.addEventListener('click', () => { button.closest('.admin-header-popover').hidden = true; }));
  const pageSearch = $('[data-admin-page-search]');
  pageSearch?.addEventListener('input', () => {
    const term = pageSearch.value.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').toLowerCase().trim();
    let visible = 0;
    $$('[data-admin-page]').forEach((link) => {
      const match = link.dataset.adminPage.includes(term);
      link.hidden = !match;
      visible += match ? 1 : 0;
    });
    $('[data-admin-page-empty]').hidden = visible > 0;
  });

  // Quản lý ảnh sản phẩm: xóa ảnh cũ, chọn ảnh đại diện, xem trước ảnh mới.
  $$('[data-image-manager]').forEach((grid) => {
    grid.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-remove-image]');
      if (!remove) return;
      const card = remove.closest('article');
      const wasPrimary = $('input[type="radio"]', card)?.checked;
      card.remove();
      if (wasPrimary) {
        const first = $('input[type="radio"]', grid);
        if (first) first.checked = true;
      }
    });
    grid.addEventListener('change', (event) => {
      if (event.target.type !== 'radio') return;
      $$('article', grid).forEach((card) => card.classList.toggle('primary', card.contains(event.target)));
    });
  });
  $$('[data-image-input]').forEach((input) => input.addEventListener('change', () => {
    const label = input.closest('.admin-image-actions').querySelector('[data-image-input-label]');
    const kept = $$('[data-image-manager] article').length;
    if (kept + input.files.length > 5) {
      showToast('error', 'Mỗi sản phẩm tối đa 5 ảnh. Hãy xóa bớt ảnh cũ trước.');
      input.value = '';
    }
    label.textContent = input.files.length ? `Đã chọn ${input.files.length} ảnh mới (lưu để tải lên)` : 'JPG, PNG, WEBP hoặc GIF · mỗi ảnh tối đa 5MB';
  }));
  $$('[data-single-image-input]').forEach((input) => input.addEventListener('change', () => {
    const preview = input.closest('.admin-image-upload').querySelector('[data-single-image-preview]');
    const [file] = input.files;
    if (!file) return;
    preview.innerHTML = '';
    const image = document.createElement('img');
    image.src = URL.createObjectURL(file);
    image.alt = 'Xem trước ảnh';
    preview.append(image);
  }));
})();
