  </div><!-- /.app-content -->
</div><!-- /.app-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  // ---- Mobile sidebar toggle ----
  var sidebar = document.getElementById('appSidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  var toggleBtn = document.getElementById('sidebarToggleBtn');
  function closeSidebar(){ sidebar.classList.remove('show'); backdrop.classList.remove('show'); }
  function openSidebar(){ sidebar.classList.add('show'); backdrop.classList.add('show'); }
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  // ---- Dark / light mode toggle (persisted, no build step required) ----
  var themeBtn = document.getElementById('themeToggleBtn');
  var themeIcon = document.getElementById('themeIcon');
  function applyThemeIcon(theme) {
    if (!themeIcon) return;
    themeIcon.className = 'fa-solid ' + (theme === 'dark' ? 'fa-sun' : 'fa-moon');
  }
  applyThemeIcon(document.documentElement.getAttribute('data-bs-theme'));
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
      var next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', next);
      localStorage.setItem('b2b-theme', next);
      applyThemeIcon(next);
    });
  }

  // ---- Toast-style flash messages: auto-dismiss ----
  document.querySelectorAll('#toastStack .app-toast').forEach(function (toast, idx) {
    setTimeout(function () {
      toast.classList.add('fading');
      setTimeout(function () { toast.remove(); }, 260);
    }, 5000 + idx * 300);
  });

  // ---- Generic "loading" state on form submit (prevents double-submits) ----
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      if (form.hasAttribute('data-no-loading')) return;
      var btn = form.querySelector('button[type="submit"], button:not([type])');
      if (btn && !btn.classList.contains('is-loading')) {
        btn.classList.add('is-loading');
        btn.insertAdjacentHTML('afterbegin', '<span class="btn-spinner"></span>');
      }
    });
  });
})();
</script>
</body>
</html>
