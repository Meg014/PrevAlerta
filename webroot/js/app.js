(() => {
 const body = document.body, sidebar = document.getElementById('sidebar'), toggle = document.getElementById('sidebar-toggle'), overlay = document.getElementById('sidebar-overlay');
 if (!toggle || !sidebar) return;
 const mobile = window.matchMedia('(max-width: 1023px)');
 try { if (localStorage.getItem('sidebar-collapsed') === 'true') body.classList.add('sidebar-collapsed'); } catch {}
 const sync = () => {
   const open = body.classList.contains('drawer-open');
   overlay.hidden = !mobile.matches || !open;
   sidebar.inert = mobile.matches && !open;
   toggle.setAttribute('aria-expanded', String(mobile.matches ? open : !body.classList.contains('sidebar-collapsed')));
 };
 const close = () => { body.classList.remove('drawer-open'); sync(); toggle.focus(); };
 toggle.addEventListener('click', () => {
   if (mobile.matches) {
     body.classList.toggle('drawer-open'); sync();
     if (body.classList.contains('drawer-open')) sidebar.querySelector('a, button')?.focus();
   } else {
     body.classList.toggle('sidebar-collapsed');
     try { localStorage.setItem('sidebar-collapsed', String(body.classList.contains('sidebar-collapsed'))); } catch {}
     sync();
   }
 });
 overlay.addEventListener('click', close);
 document.getElementById('sidebar-close')?.addEventListener('click', close);
 document.addEventListener('keydown', event => {
   if (!mobile.matches || !body.classList.contains('drawer-open')) return;
   if (event.key === 'Escape') close();
   if (event.key === 'Tab') {
     const items = [...sidebar.querySelectorAll('a, button')].filter(el => el.offsetParent !== null);
     const first = items[0], last = items.at(-1);
     if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
     if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
   }
 });
 mobile.addEventListener('change', () => { body.classList.remove('drawer-open'); sync(); }); sync();
})();
