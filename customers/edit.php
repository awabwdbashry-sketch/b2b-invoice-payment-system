<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('customers.edit');

$pdo = Database::get();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT * FROM customers WHERE id = :id'
);

$stmt->execute([
    'id' => $id
]);

$customer = $stmt->fetch();

if (!$customer) {
    flash('danger', 'العميل غير موجود.');
    redirect(base_path('customers/index.php'));
}

$errors = [];

// ============================================================
// Handle Update
// ============================================================

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
        )
            ? $_POST['status']
            : 'active',
    ];

    // ========================================================
    // Validation
    // ========================================================

    if ($data['company_name'] === '') {
        $errors[] = 'اسم الشركة مطلوب.';
    }

    if (
        $data['email'] !== '' &&
        !filter_var($data['email'], FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'البريد الإلكتروني غير صالح.';
    }

    // ========================================================
    // Update Customer
    // ========================================================

    if (!$errors) {

        try {

            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare(
                'UPDATE customers
                 SET
                    company_name = :company_name,
                    contact_person = :contact_person,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    tax_number = :tax_number,
                    registration_number = :registration_number,
                    notes = :notes,
                    status = :status,
                    updated_at = :updated_at
                 WHERE id = :id'
            );

            $stmt->execute([
                'company_name' => $data['company_name'],
                'contact_person' => $data['contact_person'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'tax_number' => $data['tax_number'],
                'registration_number' => $data['registration_number'],
                'notes' => $data['notes'],
                'status' => $data['status'],
                'updated_at' => $now,
                'id' => $id,
            ]);

            AuditLog::record(
                'customer.updated',
                'customer',
                $id,
                'تم تحديث بيانات العميل ' . $data['company_name']
            );

            flash(
                'success',
                'تم تحديث بيانات العميل بنجاح.'
            );

            redirect(
                base_path('customers/view.php?id=' . $id)
            );

        } catch (PDOException $e) {

            $errors[] = 'حدث خطأ أثناء تحديث بيانات العميل. يرجى المحاولة مرة أخرى.';
        }
    }

    // Keep submitted values if validation fails
    $customer = array_merge(
        $customer,
        $data
    );
}

// ============================================================
// Page
// ============================================================

$pageTitle = 'تعديل العميل';
$activeMenu = 'customers';

require __DIR__ . '/../includes/layout_header.php';
?>

<div
    class="card border-0 shadow-sm"
    style="max-width:760px;"
>

    <div class="card-body p-4">

        <?php foreach ($errors as $err): ?>

            <div class="alert alert-danger py-2">
                <?= e($err) ?>
            </div>

        <?php endforeach; ?>

        <form method="post" novalidate>

            <?= csrf_field() ?>

            <div class="row g-3">

                <!-- Company Name -->
                <div class="col-md-6">

                    <label class="form-label">
                        اسم الشركة *
                    </label>

                    <input
                        type="text"
                        name="company_name"
                        class="form-control"
                        required
                        value="<?= e($customer['company_name']) ?>"
                    >

                </div>

                <!-- Contact Person -->
                <div class="col-md-6">

                    <label class="form-label">
                        جهة الاتصال
                    </label>

                    <input
                        type="text"
                        name="contact_person"
                        class="form-control"
                        value="<?= e($customer['contact_person']) ?>"
                    >

                </div>

                <!-- Email -->
                <div class="col-md-6">

                    <label class="form-label">
                        البريد الإلكتروني
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?= e($customer['email']) ?>"
                    >

                </div>

                <!-- Phone -->
                <div class="col-md-6">

                    <label class="form-label">
                        الهاتف
                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="<?= e($customer['phone']) ?>"
                    >

                </div>

                <!-- Address -->
                <div class="col-12">

                    <label class="form-label">
                        العنوان
                    </label>

                    <input
                        type="text"
                        name="address"
                        class="form-control"
                        value="<?= e($customer['address']) ?>"
                    >

                </div>

                <!-- Tax Number -->
                <div class="col-md-6">

                    <label class="form-label">
                        الرقم الضريبي
                    </label>

                    <input
                        type="text"
                        name="tax_number"
                        class="form-control"
                        value="<?= e($customer['tax_number']) ?>"
                    >

                </div>

                <!-- Registration Number -->
                <div class="col-md-6">

                    <label class="form-label">
                        رقم السجل التجاري
                    </label>

                    <input
                        type="text"
                        name="registration_number"
                        class="form-control"
                        value="<?= e($customer['registration_number']) ?>"
                    >

                </div>

                <!-- Status -->
                <div class="col-md-6">

                    <label class="form-label">
                        الحالة
                    </label>

                    <select
                        name="status"
                        class="form-select"
                    >

                        <option
                            value="active"
                            <?= $customer['status'] === 'active' ? 'selected' : '' ?>
                        >
                            نشط
                        </option>

                        <option
                            value="inactive"
                            <?= $customer['status'] === 'inactive' ? 'selected' : '' ?>
                        >
                            غير نشط
                        </option>

                    </select>

                </div>

                <!-- Notes -->
                <div class="col-12">

                    <label class="form-label">
                        ملاحظات
                    </label>

                    <textarea
                        name="notes"
                        class="form-control"
                        rows="3"
                    ><?= e($customer['notes']) ?></textarea>

                </div>

            </div>

            <div class="mt-4 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="fa-solid fa-check me-1"></i>
                    حفظ التغييرات
                </button>

                <a
                    href="<?= e(base_path('customers/view.php?id=' . $id)) ?>"
                    class="btn btn-outline-secondary"
                >
                    إلغاء
                </a>

            </div>

        </form>

    </div>

</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>