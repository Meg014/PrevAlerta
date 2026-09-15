(() => {
  document.querySelectorAll('[data-ack-dialog]').forEach(link => {
    const dialog = document.getElementById(link.dataset.ackDialog);
    if (!dialog || typeof dialog.showModal !== 'function') return;
    link.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      dialog.showModal();
    });
    dialog.querySelector('[data-ack-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => link.focus());
  });
  document.querySelectorAll('[data-ack-submit]').forEach(form => {
    const submit = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', event => {
      if (form.dataset.sending === 'true') { event.preventDefault(); return; }
      form.dataset.sending = 'true';
      if (submit) { submit.disabled = true; submit.textContent = 'Registrando…'; }
    });
    window.addEventListener('pageshow', () => {
      delete form.dataset.sending;
      if (submit) { submit.disabled = false; submit.textContent = 'Confirmar CIENTE'; }
    });
  });
})();
