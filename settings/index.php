<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('settings.manage');
$pdo = Database::get();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fields = [
        'company_name' => clean_str($_POST['company_name'] ?? ''),
        'company_address' => clean_str($_POST['company_address'] ?? ''),
        'company_email' => clean_str($_POST['company_email'] ?? ''),
        'company_phone' => clean_str($_POST['company_phone'] ?? ''),
        'company_website' => clean_str($_POST['company_website'] ?? ''),
        'company_tax_number' => clean_str($_POST['company_tax_number'] ?? ''),
        'default_currency' => clean_str($_POST['default_currency'] ?? 'USD') ?: 'USD',
        'invoice_prefix' => clean_str($_POST['invoice_prefix'] ?? 'INV') ?: 'INV',
        'default_tax_percent' => (string)to_money($_POST['default_tax_percent'] ?? 0),
        'invoice_due_days' => (string)max(0, (int)($_POST['invoice_due_days'] ?? 30)),
    ];

    if ($fields['company_email'] !== '' && !filter_var($fields['company_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني للشركة غير صالح.';
    }

    // ---- Safe logo upload ----
    if (!empty($_FILES['logo']['name'])) {
        $file = $_FILES['logo'];
        $allowedExt = ['png', 'jpg', 'jpeg'];
        $allowedMime = ['image/png', 'image/jpeg'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'فشل رفع الشعار.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'يجب ألا يتجاوز حجم الشعار 2 ميجابايت.';
        } elseif (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'يجب أن يكون الشعار ملف PNG أو JPG.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowedMime, true)) {
                $errors[] = 'الملف المرفوع ليس صورة صالحة.';
            } else {
                $safeName = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $destRelative = 'uploads/logos/' . $safeName;
                $destAbsolute = __DIR__ . '/../' . $destRelative;
                if (move_uploaded_file($file['tmp_name'], $destAbsolute)) {
                    $fields['company_logo'] = $destRelative;
                } else {
                    $errors[] = 'تعذر حفظ الشعار المرفوع.';
                }
            }
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`, updated_at) VALUES (:k, :v, :now)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)');
        // SQLite doesn't support ON DUPLICATE KEY UPDATE; branch per driver.
        foreach ($fields as $key => $value) {
            if (DB_DRIVER === 'mysql') {
                $stmt->execute(['k' => $key, 'v' => $value, 'now' => date('Y-m-d H:i:s')]);
            } else {
                $pdo->prepare('INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (:k, :v, :now)')
                    ->execute(['k' => $key, 'v' => $value, 'now' => date('Y-m-d H:i:s')]);
            }
        }
        AuditLog::record('settings.updated', 'settings', null, 'تم تحديث إعدادات الشركة');
        flash('success', 'تم حفظ الإعدادات بنجاح.');
        redirect(base_path('settings/index.php'));
    }
}

$settings = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'الإعدادات';
$activeMenu = 'settings';
require __DIR__ . '/../includes/layout_header.php';
?>

<div class="card border-0 shadow-sm" style="max-width:800px;">
  <div class="card-body p-4">
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <h6 class="fw-semibold mb-3">بيانات الشركة</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-6"><label class="form-label">اسم الشركة</label><input type="text" name="company_name" class="form-control" value="<?= e($settings['company_name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="company_email" class="form-control" value="<?= e($settings['company_email'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">الهاتف</label><input type="text" name="company_phone" class="form-control" value="<?= e($settings['company_phone'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">الموقع الإلكتروني</label><input type="text" name="company_website" class="form-control" value="<?= e($settings['company_website'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">العنوان</label><input type="text" name="company_address" class="form-control" value="<?= e($settings['company_address'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">الرقم الضريبي</label><input type="text" name="company_tax_number" class="form-control" value="<?= e($settings['company_tax_number'] ?? '') ?>"></div>
        <div class="col-md-6">
          <label class="form-label">الشعار (PNG/JPG، بحد أقصى 2 ميجابايت)</label>
          <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg">
          <?php if (!empty($settings['company_logo'])): ?>
            <div class="mt-2"><img src="<?= e(base_path($settings['company_logo'])) ?>" style="max-height:50px;"></div>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-semibold mb-3">إعدادات الفواتير الافتراضية</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-3"><label class="form-label">العملة الافتراضية</label><input type="text" name="default_currency" class="form-control" value="<?= e($settings['default_currency'] ?? 'USD') ?>"></div>
        <div class="col-md-3"><label class="form-label">بادئة رقم الفاتورة</label><input type="text" name="invoice_prefix" class="form-control" value="<?= e($settings['invoice_prefix'] ?? 'INV') ?>"></div>
        <div class="col-md-3"><label class="form-label">نسبة الضريبة الافتراضية %</label><input type="number" step="0.01" name="default_tax_percent" class="form-control" value="<?= e($settings['default_tax_percent'] ?? '0') ?>"></div>
        <div class="col-md-3"><label class="form-label">عدد أيام الاستحقاق الافتراضي</label><input type="number" name="invoice_due_days" class="form-control" value="<?= e($settings['invoice_due_days'] ?? '30') ?>"></div>
      </div>

      <button class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>حفظ الإعدادات</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
