<?php
/**
 * Shared layout chrome (sidebar + topbar) — Arabic / RTL, premium B2B SaaS
 * design system. Reusable UI component; page-specific CSS/JS still lives
 * inside each page file per the project's architecture rule.
 */
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME_AR;
$activeMenu = $activeMenu ?? '';
$unread = Auth::check() ? Notifier::unreadCount(Auth::id()) : 0;

$menuItems = [
    ['key' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'href' => 'index.php', 'perm' => null],
    ['key' => 'customers', 'label' => 'العملاء', 'icon' => 'fa-building', 'href' => 'customers/index.php', 'perm' => 'customers.view'],
    ['key' => 'invoices', 'label' => 'الفواتير', 'icon' => 'fa-file-invoice', 'href' => 'invoices/index.php', 'perm' => 'invoices.view'],
    ['key' => 'overdue', 'label' => 'الحسابات المتأخرة', 'icon' => 'fa-triangle-exclamation', 'href' => 'invoices/overdue.php', 'perm' => 'invoices.view'],
    ['key' => 'payments', 'label' => 'المدفوعات', 'icon' => 'fa-money-bill-wave', 'href' => 'payments/index.php', 'perm' => 'payments.view'],
    ['key' => 'reports', 'label' => 'التقارير', 'icon' => 'fa-chart-line', 'href' => 'reports/index.php', 'perm' => 'reports.view'],
    ['key' => 'notifications', 'label' => 'الإشعارات', 'icon' => 'fa-bell', 'href' => 'notifications/index.php', 'perm' => 'notifications.view'],
    ['key' => 'users', 'label' => 'المستخدمون', 'icon' => 'fa-users-gear', 'href' => 'admin/users.php', 'perm' => 'users.view'],
    ['key' => 'audit', 'label' => 'سجل التدقيق', 'icon' => 'fa-clipboard-list', 'href' => 'admin/audit_logs.php', 'perm' => 'audit_logs.view'],
    ['key' => 'settings', 'label' => 'الإعدادات', 'icon' => 'fa-gear', 'href' => 'settings/index.php', 'perm' => 'settings.manage'],
];

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME_AR) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>
  // Apply saved theme before first paint to avoid a light/dark flash.
  (function () {
    var saved = localStorage.getItem('b2b-theme');
    document.documentElement.setAttribute('data-bs-theme', saved === 'dark' ? 'dark' : 'light');
  })();
</script>
<style>
  :root{
    --sidebar-w: 264px;
    --brand-50:  #eef4fc;
    --brand-100: #d7e6f7;
    --brand-500: #1a5fa8;
    --brand-600: #0f4c81;
    --brand-700: #0a3760;
    --brand-glow: rgba(15,76,129,.18);
    --radius-lg: 16px;
    --radius-md: 12px;
    --radius-sm: 8px;
    --shadow-sm: 0 1px 2px rgba(16,24,40,.06);
    --shadow-md: 0 4px 16px rgba(16,24,40,.08);
    --shadow-lg: 0 12px 32px rgba(16,24,40,.12);
    --bg-app: #f4f6fa;
    --bg-surface: #ffffff;
    --bg-subtle: #f8f9fc;
    --text-primary: #101828;
    --text-muted: #667085;
    --border-soft: #e7eaf0;
    --transition-base: .18s cubic-bezier(.4,0,.2,1);
  }
  [data-bs-theme="dark"]{
    --bg-app: #0f1420;
    --bg-surface: #161c2c;
    --bg-subtle: #1b2233;
    --text-primary: #eef1f8;
    --text-muted: #96a0b5;
    --border-soft: #262f45;
    --brand-glow: rgba(58,130,201,.28);
    --shadow-sm: 0 1px 2px rgba(0,0,0,.3);
    --shadow-md: 0 4px 18px rgba(0,0,0,.35);
    --shadow-lg: 0 14px 36px rgba(0,0,0,.45);
  }
  *{ box-sizing:border-box; }
  html, body{ height:100%; }
  body{
    background: var(--bg-app); color: var(--text-primary);
    font-family: 'Cairo', 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    transition: background var(--transition-base), color var(--transition-base);
  }
  ::selection{ background: var(--brand-100); }

  /* ---------- Sidebar ---------- */
  .app-sidebar{
    position: fixed; top:0; right:0; bottom:0; width: var(--sidebar-w);
    background: linear-gradient(165deg, var(--brand-600) 0%, var(--brand-700) 100%);
    color:#fff; overflow-y:auto; z-index: 1040; transition: transform var(--transition-base);
    box-shadow: var(--shadow-lg);
  }
  .app-sidebar::-webkit-scrollbar{ width:6px; }
  .app-sidebar::-webkit-scrollbar-thumb{ background: rgba(255,255,255,.15); border-radius:10px; }
  .app-sidebar .brand{
    display:flex; align-items:center; gap:.6rem; padding: 1.35rem 1.35rem;
    font-weight:800; font-size:1.02rem; letter-spacing:.2px;
    border-bottom:1px solid rgba(255,255,255,.10);
  }
  .app-sidebar .brand .brand-icon{
    width:38px; height:38px; border-radius:11px; background: rgba(255,255,255,.14);
    display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0;
  }
  .app-sidebar .nav{ padding: .85rem .7rem; gap:.2rem; }
  .app-sidebar .nav-link{
    color: rgba(255,255,255,.78); padding: .6rem .85rem; border-radius: var(--radius-sm);
    font-size:.9rem; font-weight:500; display:flex; align-items:center; gap:.65rem;
    transition: background var(--transition-base), color var(--transition-base), transform var(--transition-base);
  }
  .app-sidebar .nav-link i{ width:1.25rem; text-align:center; font-size:.95rem; opacity:.9; }
  .app-sidebar .nav-link:hover{ background: rgba(255,255,255,.09); color:#fff; transform: translateX(-2px); }
  .app-sidebar .nav-link.active{
    background: rgba(255,255,255,.16); color:#fff; font-weight:700;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
  }
  .app-sidebar .nav-link.active i{ opacity:1; }

  /* ---------- Main / Topbar ---------- */
  .app-main{ margin-right: var(--sidebar-w); min-height:100vh; }
  .app-topbar{
    background: var(--bg-surface); border-bottom:1px solid var(--border-soft);
    padding:.7rem 1.5rem; position:sticky; top:0; z-index:1030;
    backdrop-filter: saturate(180%) blur(6px);
  }
  .app-content{ padding: 1.5rem; animation: fadeInUp .35s ease; }
  /* IMPORTANT — do not add `both`/`forwards` fill-mode or a `transform` back
     into this animation, and do not add filter/perspective/will-change to
     .app-content or any ancestor of it.
     Root cause of a real bug found in production: this element is a direct
     ancestor of EVERY <div class="modal"> on every page (Bootstrap 5 does
     not relocate .modal into <body> — only .modal-backdrop gets appended
     there by its JS). When this animation used `transform` and/or
     `fill-mode: both`, Chromium kept .app-content permanently promoted to
     its own compositing layer/stacking context even after the animation
     finished, which silently trapped every modal inside a stacking context
     separate from the body-level .modal-backdrop. The backdrop then
     visually and functionally sat on top of the modal regardless of the
     modal's own z-index (1055 vs the backdrop's 1050), because the two were
     never compared within the same stacking context to begin with — so
     tweaking z-index anywhere had no effect. Verified with a real browser
     (Playwright/Chromium) using Bootstrap's actual modal/backdrop CSS:
     transform, and opacity+fill-mode:both, both reproduced the click-through
     bug; opacity-only with the default (non "both") fill-mode does not. */
  @keyframes fadeInUp{ from{ opacity:0; } to{ opacity:1; } }
  .sidebar-toggle-btn{ display:none; }
  @media (max-width: 991.98px){
    .app-sidebar{ transform: translateX(100%); }
    .app-sidebar.show{ transform: translateX(0); }
    .app-main{ margin-right:0; }
    .sidebar-toggle-btn{ display:inline-flex; }
  }
  .sidebar-backdrop{
    display:none; position:fixed; inset:0; background:rgba(16,24,40,.45); z-index:1035;
  }
  .sidebar-backdrop.show{ display:block; }

  .theme-toggle-btn, .icon-btn{
    width:38px; height:38px; border-radius:50%; border:1px solid var(--border-soft);
    background: var(--bg-subtle); color: var(--text-primary); display:flex; align-items:center; justify-content:center;
    transition: background var(--transition-base), transform var(--transition-base);
  }
  .theme-toggle-btn:hover, .icon-btn:hover{ background: var(--brand-50); transform: translateY(-1px); }
  [data-bs-theme="dark"] .theme-toggle-btn:hover, [data-bs-theme="dark"] .icon-btn:hover{ background: rgba(255,255,255,.08); }

  /* ---------- Cards / Surfaces ---------- */
  .card{
    background: var(--bg-surface); border:1px solid var(--border-soft); border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm); transition: box-shadow var(--transition-base), transform var(--transition-base);
  }
  a.list-group-item-action.card, .stat-card{ }
  .card-header{ background: transparent; border-bottom:1px solid var(--border-soft); font-weight:700; }
  .hover-lift:hover{ box-shadow: var(--shadow-md); transform: translateY(-2px); }

  /* ---------- Buttons ---------- */
  .btn{ border-radius: var(--radius-sm); font-weight:600; padding:.5rem 1rem; transition: all var(--transition-base); }
  .btn:focus, .form-control:focus, .form-select:focus{ box-shadow: 0 0 0 .2rem var(--brand-glow); }
  .btn-primary{ background: linear-gradient(135deg, var(--brand-500), var(--brand-600)); border-color: var(--brand-600); }
  .btn-primary:hover{ background: linear-gradient(135deg, var(--brand-600), var(--brand-700)); border-color: var(--brand-700); transform: translateY(-1px); box-shadow: var(--shadow-md); }
  .btn-sm{ border-radius: var(--radius-sm); }
  .btn-outline-primary{ color: var(--brand-600); border-color: var(--brand-500); }
  .btn-outline-primary:hover{ background: var(--brand-500); }

  /* ---------- Forms ---------- */
  .form-control, .form-select{
    background: var(--bg-surface); color: var(--text-primary); border:1px solid var(--border-soft);
    border-radius: var(--radius-sm);
  }
  .form-label{ font-weight:600; font-size:.88rem; color: var(--text-primary); }

  /* ---------- Tables ---------- */
  .table{ color: var(--text-primary); }
  .table thead.table-light th, table thead th{
    background: var(--bg-subtle) !important; color: var(--text-muted); font-size:.78rem;
    text-transform:uppercase; letter-spacing:.03em; font-weight:700; border-bottom:1px solid var(--border-soft);
  }
  .table > :not(caption) > * > *{ background-color: transparent; color: inherit; border-color: var(--border-soft); }
  .table-hover > tbody > tr:hover > *{ background-color: var(--brand-50); }
  [data-bs-theme="dark"] .table-hover > tbody > tr:hover > *{ background-color: rgba(255,255,255,.04); }

  /* ---------- Badges / Status ---------- */
  .badge{ font-weight:600; border-radius:999px; padding:.42em .85em; letter-spacing:.01em; }
  .badge-status{ font-size:.75rem; padding:.4em .65em; }

  /* ---------- Modals / Dropdowns ---------- */
  .modal-content{ border-radius: var(--radius-lg); border:1px solid var(--border-soft); background: var(--bg-surface); }
  .dropdown-menu{ border-radius: var(--radius-md); border:1px solid var(--border-soft); box-shadow: var(--shadow-md); background: var(--bg-surface); }
  .dropdown-item{ color: var(--text-primary); border-radius: var(--radius-sm); margin:2px 6px; width:calc(100% - 12px); }
  .dropdown-item:hover{ background: var(--brand-50); }
  [data-bs-theme="dark"] .dropdown-item:hover{ background: rgba(255,255,255,.06); }

  .table-responsive{ overflow-x:auto; }

  /* ---------- Toast-style flash messages ---------- */
  #toastStack{
    position: fixed; top: 1.1rem; left:50%; transform: translateX(-50%); z-index: 2000;
    display:flex; flex-direction:column; gap:.6rem; width:min(92vw, 420px);
  }
  #toastStack .app-toast{
    background: var(--bg-surface); border:1px solid var(--border-soft); border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg); padding:.85rem 1rem; display:flex; align-items:flex-start; gap:.65rem;
    animation: toastIn .3s ease both; font-size:.9rem;
  }
  @keyframes toastIn{ from{ opacity:0; transform: translateY(-10px);} to{ opacity:1; transform:none; } }
  .app-toast.fading{ animation: toastOut .25s ease forwards; }
  @keyframes toastOut{ to{ opacity:0; transform: translateY(-8px); } }
  .app-toast .toast-icon{ font-size:1.1rem; margin-top:.1rem; }
  .app-toast.toast-success .toast-icon{ color:#12b76a; }
  .app-toast.toast-danger .toast-icon{ color:#f04438; }
  .app-toast.toast-warning .toast-icon{ color:#f79009; }
  .app-toast.toast-info .toast-icon{ color: var(--brand-500); }
  .app-toast .toast-close{ margin-inline-start:auto; background:none; border:none; color: var(--text-muted); cursor:pointer; }

  /* ---------- Loading (submit) state ---------- */
  .btn.is-loading{ pointer-events:none; opacity:.75; position:relative; }
  .btn.is-loading .btn-spinner{
    display:inline-block; width:.85rem; height:.85rem; border-radius:50%;
    border:2px solid rgba(255,255,255,.5); border-top-color:#fff; animation: spin .6s linear infinite; margin-inline-end:.4rem;
  }
  .btn-outline-primary.is-loading .btn-spinner, .btn-outline-secondary.is-loading .btn-spinner, .btn-outline-dark.is-loading .btn-spinner{
    border-color: var(--border-soft); border-top-color: var(--brand-600);
  }
  @keyframes spin{ to{ transform: rotate(360deg); } }

  /* ---------- Empty states ---------- */
  .empty-state{ text-align:center; padding: 2.5rem 1rem; color: var(--text-muted); }
  .empty-state i{ font-size:2.2rem; opacity:.35; margin-bottom:.75rem; display:block; }
</style>
</head>
<body>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<nav class="app-sidebar" id="appSidebar">
  <div class="brand">
    <span class="brand-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
    <span>نظام فوترة B2B</span>
  </div>
  <div class="nav flex-column">
    <?php foreach ($menuItems as $item): ?>
      <?php if ($item['perm'] === null || Auth::can($item['perm'])): ?>
        <a class="nav-link <?= $activeMenu === $item['key'] ? 'active' : '' ?>" href="<?= e(base_path($item['href'])) ?>">
          <i class="fa-solid <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</nav>

<div class="app-main">
  <div class="app-topbar d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-outline-secondary sidebar-toggle-btn icon-btn" id="sidebarToggleBtn" aria-label="فتح القائمة">
        <i class="fa-solid fa-bars"></i>
      </button>
      <h5 class="mb-0 fw-bold"><?= e($pageTitle) ?></h5>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button class="theme-toggle-btn" id="themeToggleBtn" type="button" aria-label="تبديل الوضع الليلي/النهاري" title="الوضع الليلي/النهاري">
        <i class="fa-solid fa-moon" id="themeIcon"></i>
      </button>
      <?php if (Auth::can('notifications.view')): ?>
      <a href="<?= e(base_path('notifications/index.php')) ?>" class="icon-btn position-relative text-decoration-none">
        <i class="fa-solid fa-bell"></i>
        <?php if ($unread > 0): ?>
          <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger" style="font-size:.58rem;"><?= $unread ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>
      <div class="dropdown">
        <a class="d-flex align-items-center text-decoration-none dropdown-toggle" style="color:inherit;" href="#" data-bs-toggle="dropdown">
          <i class="fa-solid fa-circle-user fs-4 me-2" style="color:var(--brand-500);"></i>
          <span class="d-none d-sm-inline"><?= e(Auth::fullName() ?? '') ?> <small class="text-muted">(<?= e(role_label(Auth::roleName() ?? '')) ?>)</small></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= e(base_path('auth/logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>تسجيل الخروج</a></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div id="toastStack">
      <?php foreach ($flashes as $f): ?>
        <?php
          $icon = match ($f['type']) {
              'success' => 'fa-circle-check',
              'danger'  => 'fa-circle-exclamation',
              'warning' => 'fa-triangle-exclamation',
              default   => 'fa-circle-info',
          };
        ?>
        <div class="app-toast toast-<?= e($f['type']) ?>">
          <i class="fa-solid <?= $icon ?> toast-icon"></i>
          <div><?= e($f['message']) ?></div>
          <button type="button" class="toast-close" onclick="this.closest('.app-toast').remove()">&times;</button>
        </div>
      <?php endforeach; ?>
    </div>
