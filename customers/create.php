<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('customers.create');
$pdo = Database::get();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $data = [
        'company_name' => clean_str($_POST['company_name'] ?? ''),
        'contact_person' => clean_str($_POST['contact_person'] ?? ''),
        'email' => clean_str($_POST['email'] ?? ''),
        'phone' => clean_str($_POST['phone'] ?? ''),
        'address' => clean_str($_POST['address'] ?? ''),
        'tax_number' => clean_str($_POST['tax_number'] ?? ''),
        'registration_number' => clean_str($_POST['registration_number'] ?? ''),
        'notes' => clean_str($_POST['notes'] ?? ''),
        'status' => in_array(
            $_POST['status'] ?? '',
            ['active', 'inactive'],
            true
        ) ? $_POST['status'] : 'active',
    ];

    if ($data['company_name'] === '') {
        $errors[] = 'اسم الشركة مطلوب.';
    }

    if (
        $data['email'] !== '' &&
        !filter_var($data['email'], FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'البريد الإلكتروني غير صالح.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO customers (
                company_name,
                contact_person,
                email,
                phone,
                address,
                tax_number,
                registration_number,
                notes,
                status,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :company_name,
                :contact_person,
                :email,
                :phone,
                :address,
                :tax_number,
                :registration_number,
                :notes,
                :status,
                :created_by,
                :created_at,
                :updated_at
            )'
        );

        $now = date('Y-m-d H:i:s');

        $stmt->execute([
            'company_name'        => $data['company_name'],
            'contact_person'      => $data['contact_person'],
            'email'               => $data['email'],
            'phone'               => $data['phone'],
            'address'             => $data['address'],
            'tax_number'          => $data['tax_number'],
            'registration_number' => $data['registration_number'],
            'notes'               => $data['notes'],
            'status'              => $data['status'],
            'created_by'          => Auth::id(),
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $id = (int)$pdo->lastInsertId();

        AuditLog::record(
            'customer.created',
            'customer',
            $id,
            'تم إنشاء العميل ' . $data['company_name']
        );

        flash('success', 'تم إنشاء العميل بنجاح.');
        clear_old();

        redirect(base_path('customers/view.php?id=' . $id));
    }

    keep_old($data);
}

$pageTitle = 'عميل جديد';
$activeMenu = 'customers';

require __DIR__ . '/../includes/layout_header.php';
?>

<div class="card border-0 shadow-sm" style="max-width:760px;">
  <div class="card-body p-4">
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger py-2"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>

      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">اسم الشركة *</label>
          <input
            type="text"
            name="company_name"
            class="form-control"
            required
            value="<?= e(old('company_name')) ?>"
          >
        </div>

        <div class="col-md-6">
          <label class="form-label">جهة الاتصال</label>
          <input
            type="text"
            name="contact_person"
            class="form-control"
            value="<?= e(old('contact_person')) ?>"
          >
        </div>

        <div class="col-md-6">
          <label class="form-label">البريد الإلكتروني</label>
          <input
            type="email"
            name="email"
            class="form-control"
            value="<?= e(old('email')) ?>"
          >
        </div>

        <div class="col-md-6">
          <label class="form-label">الهاتف</label>
          <input
            type="text"
            name="phone"
            class="form-control"
            value="<?= e(old('phone')) ?>"
          >
        </div>

        <div class="col-12">
          <label class="form-label">العنوان</label>
          <input
            type="text"
            name="address"
            class="form-control"
            value="<?= e(old('address')) ?>"
          >
        </div>

        <div class="col-md-6">
          <label class="form-label">الرقم الضريبي</label>
          <input
            type="text"
            name="tax_number"
            class="form-control"
            value="<?= e(old('tax_number')) ?>"
          >
        </div>

        <div class="col-md-6">
          <label class="form-label">رقم السجل التجاري</label>
          <input
            type="text"
            name="registration_number"
            class="form-control"
            value="<?= e(old('registration_number')) ?>"
          >
        </div>

        <div class="col-md-6">
          <label class="form-label">الحالة</label>
          <select name="status" class="form-select">
            <option
              value="active"
              <?= old('status', 'active') === 'active' ? 'selected' : '' ?>
            >
              نشط
            </option>

            <option
              value="inactive"
              <?= old('status') === 'inactive' ? 'selected' : '' ?>
            >
              غير نشط
            </option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">ملاحظات</label>
          <textarea
            name="notes"
            class="form-control"
            rows="3"
          ><?= e(old('notes')) ?></textarea>
        </div>

      </div>

      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">
          <i class="fa-solid fa-check me-1"></i>
          حفظ العميل
        </button>

        <a
          href="<?= e(base_path('customers/index.php')) ?>"
          class="btn btn-outline-secondary"
        >
          إلغاء
        </a>
      </div>
    </form>
  </div>
</div>

<?php
clear_old();
require __DIR__ . '/../includes/layout_footer.php';
?>
