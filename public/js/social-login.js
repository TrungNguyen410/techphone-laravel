/*
 * Đăng nhập Google/Facebook qua Firebase Authentication (popup).
 * Firebase chỉ chứng minh danh tính: ID token được gửi lên máy chủ để kiểm tra và tạo phiên đăng nhập,
 * phiên Firebase chỉ giữ trong bộ nhớ và được đăng xuất ngay sau khi lấy token.
 */
const FIREBASE_VERSION = '11.0.2';
const root = document.querySelector('[data-social-login]');

const ERRORS = {
  'auth/popup-closed-by-user': '',
  'auth/cancelled-popup-request': '',
  'auth/user-cancelled': 'Bạn đã từ chối cấp quyền đăng nhập.',
  'auth/popup-blocked': 'Trình duyệt đã chặn cửa sổ đăng nhập. Hãy cho phép cửa sổ bật lên rồi thử lại.',
  'auth/account-exists-with-different-credential': 'Email này đã đăng nhập bằng cách khác (Google hoặc Facebook). Hãy chọn đúng cách bạn đã dùng lần đầu.',
  'auth/operation-not-allowed': 'Firebase chưa bật cách đăng nhập này: hãy bật Google/Facebook trong Sign-in method.',
  'auth/unauthorized-domain': 'Tên miền này chưa được thêm vào Authorized domains của Firebase.',
  'auth/invalid-credential': 'Thông tin đăng nhập từ Google/Facebook không hợp lệ. Vui lòng thử lại.',
  'auth/user-disabled': 'Tài khoản này đã bị vô hiệu hóa trên Firebase.',
  'auth/too-many-requests': 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau ít phút.',
  'auth/network-request-failed': 'Không kết nối được dịch vụ đăng nhập. Vui lòng kiểm tra mạng.',
};

const toast = (type, message) => window.TechPhone?.showToast(type, message);

let firebasePromise = null;
// Tải SDK sớm: popup phải mở ngay trong lần bấm, nếu chờ tải thư viện trình duyệt sẽ chặn popup.
const loadFirebase = () => {
  firebasePromise ||= Promise.all([
    import(`https://www.gstatic.com/firebasejs/${FIREBASE_VERSION}/firebase-app.js`),
    import(`https://www.gstatic.com/firebasejs/${FIREBASE_VERSION}/firebase-auth.js`),
  ]).then(([appModule, authModule]) => {
    const app = appModule.initializeApp(JSON.parse(root.dataset.firebase));
    const auth = authModule.initializeAuth(app, {
      persistence: authModule.inMemoryPersistence,
      popupRedirectResolver: authModule.browserPopupRedirectResolver,
    });
    auth.languageCode = 'vi';
    return { auth, authModule };
  }).catch((error) => {
    firebasePromise = null;
    throw error;
  });
  return firebasePromise;
};

const providerFor = (authModule, name) => {
  if (name === 'facebook') {
    const facebook = new authModule.FacebookAuthProvider();
    facebook.addScope('email');
    return facebook;
  }
  const google = new authModule.GoogleAuthProvider();
  google.setCustomParameters({ prompt: 'select_account' });
  return google;
};

if (root) {
  loadFirebase().catch(() => {});
  const buttons = [...root.querySelectorAll('[data-provider]')];
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

  buttons.forEach((button) => button.addEventListener('click', async () => {
    if (buttons.some((item) => item.disabled)) return;
    const label = button.querySelector('span');
    const original = label.textContent;
    buttons.forEach((item) => { item.disabled = true; });
    label.textContent = 'Đang kết nối…';
    try {
      let idToken;
      const { auth, authModule } = await loadFirebase();
      try {
        const credential = await authModule.signInWithPopup(auth, providerFor(authModule, button.dataset.provider));
        idToken = await credential.user.getIdToken();
      } finally {
        await authModule.signOut(auth).catch(() => {});
      }

      const response = await fetch(root.dataset.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ id_token: idToken }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.message || 'Không đăng nhập được. Vui lòng thử lại.');
      window.location.assign(data.redirect || '/');
    } catch (error) {
      const message = error?.code ? (ERRORS[error.code] ?? `Không đăng nhập được (${error.code}).`) : error?.message;
      if (message) toast('error', message);
      buttons.forEach((item) => { item.disabled = false; });
      label.textContent = original;
    }
  }));
}
