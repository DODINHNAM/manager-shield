document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('endpointRotationModal');
  const openers = [document.getElementById('open-endpoint-config'), document.getElementById('open-endpoint-config-secondary')].filter(Boolean);
  if (!modal || !openers.length) return;
  const closeButtons = modal.querySelectorAll('.endpoint-modal-close');
  const firstControl = modal.querySelector('input, select');
  const close = function () {
    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');
  };
  openers.forEach(function (button) {
    button.addEventListener('click', function () {
      modal.classList.add('is-open');
      document.body.classList.add('modal-open');
      if (firstControl) firstControl.focus();
    });
  });
  closeButtons.forEach(function (button) { button.addEventListener('click', close); });
  modal.addEventListener('click', function (event) { if (event.target === modal) close(); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
});
