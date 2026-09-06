(() => {
  const workspace = document.querySelector('.developer-workspace');
  const button = workspace?.querySelector('[data-panel-menu]');
  const navigation = document.getElementById('developer-panel-navigation');
  if (!workspace || !button || !navigation) return;
  const mobile = window.matchMedia('(max-width: 760px)');
  const setOpen = open => {
    button.setAttribute('aria-expanded', String(open));
    navigation.hidden = mobile.matches && !open;
  };
  workspace.classList.add('is-panel-enhanced');
  button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
  workspace.addEventListener('keydown', event => {
    if (event.key === 'Escape' && mobile.matches && button.getAttribute('aria-expanded') === 'true') {
      setOpen(false);
      button.focus();
    }
  });
  mobile.addEventListener('change', () => setOpen(false));
  setOpen(false);
})();
