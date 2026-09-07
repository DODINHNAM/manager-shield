document.addEventListener('DOMContentLoaded', function () {
  const menuButton = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const mobile = window.matchMedia('(max-width: 900px)');
  function setMenu(open) {
    document.body.classList.toggle('sidebar-open', open);
    if (menuButton) menuButton.setAttribute('aria-expanded', String(open));
    if (sidebar) sidebar.inert = mobile.matches && !open;
  }
  if (menuButton) menuButton.addEventListener('click', () => setMenu(!document.body.classList.contains('sidebar-open')));
  const backdrop = document.querySelector('.sidebar-backdrop');
  if (backdrop) backdrop.addEventListener('click', () => setMenu(false));
  mobile.addEventListener('change', () => setMenu(false));
  setMenu(false);

  document.querySelectorAll('.data-table').forEach(table => {
    if (!table.parentElement.classList.contains('table-scroll')) {
      const wrapper = document.createElement('div');
      wrapper.className = 'table-scroll';
      wrapper.tabIndex = 0;
      wrapper.setAttribute('role', 'region');
      wrapper.setAttribute('aria-label', 'Bảng dữ liệu, cuộn ngang để xem thêm');
      table.before(wrapper);
      wrapper.append(table);
    }
    const body = table.tBodies[0];
    if (body && !body.rows.length) {
      const cell = body.insertRow().insertCell();
      cell.colSpan = table.tHead ? table.tHead.rows[0].cells.length : 1;
      cell.textContent = 'Chưa có dữ liệu. Thêm mới để bắt đầu.';
    }
  });
  const modal = document.getElementById('configModal');
  let opener = null;
  let movedForm = null;
  let formHome = null;
  if (modal) {
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'modalTitle');
    const close = modal.querySelector('.close');
    if (close && close.tagName !== 'BUTTON') {
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'close'; button.innerHTML = '&times;';
      close.replaceWith(button);
    }
    const closeButton = modal.querySelector('.close');
    if (closeButton) {
      closeButton.setAttribute('aria-label', 'Đóng cấu hình');
      closeButton.addEventListener('click', closeModal);
    }
    modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
  }
  function closeModal() {
    if (!modal) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');
    if (movedForm && formHome) formHome.append(movedForm);
    movedForm = null; formHome = null;
    if (opener) opener.focus();
  }
  document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-toggle="modal"]');
    if (!trigger || !modal) return;
    const source = document.querySelector(trigger.getAttribute('data-target'));
    if (!source) return;
    event.preventDefault();
    opener = trigger; movedForm = source; formHome = source.parentElement;
    document.getElementById('modalTitle').textContent = trigger.getAttribute('data-title') || 'Cấu hình thanh toán';
    document.getElementById('modalBody').append(source);
    modal.classList.add('is-open'); document.body.classList.add('modal-open');
    const first = source.querySelector('input:not([type="hidden"]),select,textarea,button');
    if (first) first.focus();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { closeModal(); setMenu(false); }
    if (event.key !== 'Tab' || !modal || !modal.classList.contains('is-open')) return;
    const controls = Array.from(modal.querySelectorAll('button,input:not([type="hidden"]),select,textarea,a[href]')).filter(el => !el.disabled && el.getClientRects().length);
    if (!controls.length) return;
    const first = controls[0], last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
});