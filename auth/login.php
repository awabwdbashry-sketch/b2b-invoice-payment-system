<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (Auth::check()) {
    redirect(base_path('index.php'));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $identifier = clean_str($_POST['identifier'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'يرجى إدخال اسم المستخدم/البريد الإلكتروني وكلمة المرور.';
    } else {
        $result = Auth::attempt($identifier, $password);
        if ($result['ok']) {
            redirect(base_path('index.php'));
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول · <?= e(APP_NAME_AR) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --brand-500:#1a5fa8; --brand-600:#0f4c81; --brand-700:#0a3760;
    --radius-lg:18px; --radius-sm:10px;
  }
  *{ box-sizing:border-box; }
  html, body{ height:100%; margin:0; }
  body{
    display:flex; align-items:center; justify-content:center; padding:1.25rem;
    font-family:'Cairo','Segoe UI',system-ui,sans-serif;
    background:
      radial-gradient(circle at 15% 20%, rgba(255,255,255,.08), transparent 40%),
      radial-gradient(circle at 85% 80%, rgba(255,255,255,.06), transparent 45%),
      linear-gradient(135deg, var(--brand-700), var(--brand-600) 55%, #123a5e);
    background-attachment: fixed;
  }
  .auth-wrap{ width:100%; max-width:920px; display:grid; grid-template-columns:1.05fr 1fr; border-radius:var(--radius-lg); overflow:hidden; box-shadow:0 30px 70px rgba(0,0,0,.35); animation: riseIn .5s cubic-bezier(.2,.8,.2,1) both; }
  @keyframes riseIn{ from{ opacity:0; transform:translateY(14px);} to{ opacity:1; transform:none; } }
  .auth-side{
    background: linear-gradient(160deg, rgba(255,255,255,.10), rgba(255,255,255,.02));
    color:#fff; padding:2.6rem 2.2rem; display:flex; flex-direction:column; justify-content:space-between;
    border-left:1px solid rgba(255,255,255,.08);
  }
  .auth-side .badge-icon{ width:54px; height:54px; border-radius:16px; background:rgba(255,255,255,.14); display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:1.1rem; }
  .auth-side h1{ font-size:1.55rem; font-weight:800; margin-bottom:.5rem; }
  .auth-side p{ color:rgba(255,255,255,.75); font-size:.92rem; line-height:1.8; }
  .auth-side ul{ list-style:none; padding:0; margin:1.4rem 0 0; display:flex; flex-direction:column; gap:.7rem; }
  .auth-side li{ display:flex; align-items:center; gap:.6rem; font-size:.86rem; color:rgba(255,255,255,.85); }
  .auth-side li i{ color:#7fd3a3; }
  .auth-card{ background:#fff; padding:2.6rem 2.4rem; display:flex; flex-direction:column; justify-content:center; }
  .auth-card h4{ font-weight:800; margin-bottom:.15rem; color:#101828; }
  .auth-card .sub{ color:#667085; font-size:.9rem; margin-bottom:1.6rem; }
  .form-control{ border-radius:var(--radius-sm); padding:.65rem .9rem; border:1px solid #e4e7ec; }
  .form-control:focus{ border-color:var(--brand-500); box-shadow:0 0 0 .2rem rgba(15,76,129,.15); }
  .input-group-text{ background:#f8f9fc; border:1px solid #e4e7ec; border-left:none; color:#98a2b3; }
  .btn-login{
    border-radius:var(--radius-sm); padding:.7rem 1rem; font-weight:700; border:none; color:#fff;
    background:linear-gradient(135deg, var(--brand-500), var(--brand-700)); transition:.2s;
  }
  .btn-login:hover{ transform:translateY(-1px); box-shadow:0 10px 24px rgba(15,76,129,.35); color:#fff; }
  @media (max-width: 767.98px){
    .auth-wrap{ grid-template-columns:1fr; }
    .auth-side{ display:none; }
  }
</style>
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-side">
      <div>
        <div class="badge-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <h1>نظام فوترة B2B</h1>
        <p>منصة متكاملة لإدارة الفواتير والمدفوعات والعملاء لشركتك، بأمان وسهولة.</p>
        <ul>
          <li><i class="fa-solid fa-circle-check"></i> متابعة الفواتير والمدفوعات الجزئية والكاملة</li>
          <li><i class="fa-solid fa-circle-check"></i> تقارير مالية وحسابات متأخرة فورية</li>
          <li><i class="fa-solid fa-circle-check"></i> إرسال الفواتير بالبريد الإلكتروني كملف PDF</li>
        </ul>
      </div>
      <div class="small" style="color:rgba(255,255,255,.55);">&copy; <?= date('Y') ?> <?= e(APP_NAME_AR) ?></div>
    </div>

    <div class="auth-card">
      <h4>تسجيل الدخول</h4>
      <div class="sub">أدخل بياناتك للوصول إلى لوحة التحكم</div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 d-flex align-items-center gap-2">
          <i class="fa-solid fa-circle-exclamation"></i><span><?= e($error) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($_GET['deactivated'])): ?>
        <div class="alert alert-warning py-2">تم إيقاف حسابك. يرجى التواصل مع مدير النظام.</div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold small">اسم المستخدم أو البريد الإلكتروني</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
            <input type="text" name="identifier" class="form-control border-end-0" required autofocus value="<?= e(old('identifier')) ?>">
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold small">كلمة المرور</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" name="password" id="passwordInput" class="form-control border-end-0" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1"><i class="fa-solid fa-eye" id="toggleIcon"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-login w-100"><i class="fa-solid fa-right-to-bracket me-1"></i> تسجيل الدخول</button>
      </form>
    </div>
  </div>
<script>
  // Page-specific JS: password visibility toggle + clear field on error, for safety.
  document.addEventListener('DOMContentLoaded', function () {
    var errBox = document.querySelector('.alert-danger');
    var pwd = document.getElementById('passwordInput');
    if (errBox && pwd) pwd.value = '';

    var toggle = document.getElementById('togglePassword');
    var icon = document.getElementById('toggleIcon');
    if (toggle && pwd) {
      toggle.addEventListener('click', function () {
        var isPwd = pwd.type === 'password';
        pwd.type = isPwd ? 'text' : 'password';
        icon.className = 'fa-solid ' + (isPwd ? 'fa-eye-slash' : 'fa-eye');
      });
    }
  });
</script>
</body>
</html>
