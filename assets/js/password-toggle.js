document.querySelectorAll('.password-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.target);
    if (!input) return;
    const willShow = input.type === 'password';
    input.type = willShow ? 'text' : 'password';
    btn.textContent = willShow ? '🙈' : '👁';
  });
});
