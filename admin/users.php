<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('users.view');

$pdo = Database::get();

$roles = $pdo->query(
    'SELECT id, name FROM roles ORDER BY id'
)->fetchAll();

$errors = [];

// ---- Handle create/edit/deactivate actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // ============================================================
    // Create User
    // ============================================================
    if ($action === 'create' && Auth::can('users.create')) {

        $fullName = clean_str($_POST['full_name'] ?? '');
        $username = clean_str($_POST['username'] ?? '');
        $email = clean_str($_POST['email'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');

        // Validation
        if ($fullName === '' || $username === '' || $email === '') {
            $errors[] = 'جميع الحقول مطلوبة.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'البريد الإلكتروني غير صالح.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'يجب ألا تقل كلمة المرور عن 8 أحرف.';
        }

        $roleIds = array_column($roles, 'id');

        if (!in_array($roleId, $roleIds, true)) {
            $errors[] = 'الدور غير صالح.';
        }

        // Create user
        if (!$errors) {
            try {
                $now = date('Y-m-d H:i:s');

                $stmt = $pdo->prepare(
                    'INSERT INTO users (
                        role_id,
                        full_name,
                        username,
                        email,
                        password_hash,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :rid,
                        :fn,
                        :un,
                        :em,
                        :ph,
                        "active",
                        :created_at,
                        :updated_at
                    )'
                );

                $stmt->execute([
                    'rid' => $roleId,
                    'fn' => $fullName,
                    'un' => $username,
                    'em' => $email,
                    'ph' => password_hash($password, PASSWORD_BCRYPT),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $newId = (int)$pdo->lastInsertId();

                AuditLog::record(
                    'user.created',
                    'user',
                    $newId,
                    "تم إنشاء المستخدم $username"
                );

                flash(
                    'success',
                    'تم إنشاء المستخدم بنجاح.'
                );

            } catch (PDOException $e) {

                // MySQL duplicate entry
                if (
                    isset($e->errorInfo[1]) &&
                    (int)$e->errorInfo[1] === 1062
                ) {
                    $errors[] = 'اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل.';
                } else {
                    $errors[] = 'حدث خطأ أثناء إنشاء المستخدم. يرجى المحاولة مرة أخرى.';
                }
            }
        }

    // ============================================================
    // Toggle User Status
    // ============================================================
    } elseif (
        $action === 'toggle_status' &&
        Auth::can('users.edit')
    ) {

        $uid = (int)($_POST['user_id'] ?? 0);

        if ($uid === Auth::id()) {

            flash(
                'danger',
                'لا يمكنك إيقاف حسابك الخاص.'
            );

        } else {

            $stmt = $pdo->prepare(
                'SELECT status FROM users WHERE id = :id'
            );

            $stmt->execute([
                'id' => $uid
            ]);

            $row = $stmt->fetch();

            if ($row) {

                $newStatus = $row['status'] === 'active'
                    ? 'inactive'
                    : 'active';

                $pdo->prepare(
                    'UPDATE users
                     SET status = :s
                     WHERE id = :id'
                )->execute([
                    's' => $newStatus,
                    'id' => $uid
                ]);

                AuditLog::record(
                    'user.status_changed',
                    'user',
                    $uid,
                    "تم تغيير حالة المستخدم إلى $newStatus"
                );

                flash(
                    'success',
                    'تم تحديث حالة المستخدم.'
                );
            }
        }

    // ============================================================
    // Change User Role
    // ============================================================
    } elseif (
        $action === 'change_role' &&
        Auth::can('users.edit')
    ) {

        $uid = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 0);

        $roleIds = array_column($roles, 'id');

        if (in_array($roleId, $roleIds, true)) {

            $pdo->prepare(
                'UPDATE users
                 SET role_id = :rid
                 WHERE id = :id'
            )->execute([
                'rid' => $roleId,
                'id' => $uid
            ]);

            AuditLog::record(
                'user.role_changed',
                'user',
                $uid,
                "تم تغيير الدور إلى role_id=$roleId"
            );

            flash(
                'success',
                'تم تحديث الدور.'
            );
        }
    }

    // Redirect only when there are no validation/errors
    if (!$errors) {
        redirect(base_path('admin/users.php'));
    }
}

// ============================================================
// Load Users
// ============================================================

$users = $pdo->query(
    'SELECT
        u.*,
        r.name AS role_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC'
)->fetchAll();

$pageTitle = 'المستخدمون';
$activeMenu = 'users';

require __DIR__ . '/../includes/layout_header.php';
?>

<style>
.role-select {
    max-width: 160px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">

    <h6 class="text-muted mb-0">
        <?= count($users) ?> مستخدم
    </h6>

    <?php if (Auth::can('users.create')): ?>

        <button
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#createUserModal"
        >
            <i class="fa-solid fa-user-plus me-1"></i>
            مستخدم جديد
        </button>

    <?php endif; ?>

</div>

<?php foreach ($errors as $err): ?>

    <div class="alert alert-danger py-2">
        <?= e($err) ?>
    </div>

<?php endforeach; ?>

<div class="card border-0 shadow-sm">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead class="table-light">

                <tr>
                    <th>الاسم</th>
                    <th>اسم المستخدم</th>
                    <th>البريد الإلكتروني</th>
                    <th>الدور</th>
                    <th>الحالة</th>
                    <th>آخر تسجيل دخول</th>
                    <th class="text-end">إجراءات</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($users as $u): ?>

                    <tr>

                        <td class="fw-semibold">
                            <?= e($u['full_name']) ?>
                        </td>

                        <td>
                            <?= e($u['username']) ?>
                        </td>

                        <td>
                            <?= e($u['email']) ?>
                        </td>

                        <td>

                            <?php if (Auth::can('users.edit')): ?>

                                <form
                                    method="post"
                                    class="d-inline"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="change_role"
                                    >

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= $u['id'] ?>"
                                    >

                                    <select
                                        name="role_id"
                                        class="form-select form-select-sm role-select"
                                        onchange="this.form.submit()"
                                    >

                                        <?php foreach ($roles as $r): ?>

                                            <option
                                                value="<?= $r['id'] ?>"
                                                <?= (int)$u['role_id'] === (int)$r['id'] ? 'selected' : '' ?>
                                            >
                                                <?= e(role_label($r['name'])) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </form>

                            <?php else: ?>

                                <?= e(role_label($u['role_name'])) ?>

                            <?php endif; ?>

                        </td>

                        <td>

                            <span
                                class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>"
                            >
                                <?= e(account_status_label($u['status'])) ?>
                            </span>

                        </td>

                        <td class="small text-muted">
                            <?= e(format_datetime($u['last_login_at'])) ?>
                        </td>

                        <td class="text-end">

                            <?php if (
                                Auth::can('users.edit') &&
                                (int)$u['id'] !== Auth::id()
                            ): ?>

                                <form
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('هل تريد تغيير حالة هذا المستخدم؟');"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= $u['id'] ?>"
                                    >

                                    <button
                                        class="btn btn-sm btn-outline-<?= $u['status'] === 'active' ? 'danger' : 'success' ?>"
                                    >
                                        <?= $u['status'] === 'active' ? 'إيقاف' : 'تفعيل' ?>
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- ============================================================
     Create User Modal
============================================================ -->

<div
    class="modal fade"
    id="createUserModal"
    tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">

            <form method="post">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="create"
                >

                <div class="modal-header">

                    <h5 class="modal-title">
                        مستخدم جديد
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">
                            الاسم الكامل
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            class="form-control"
                            required
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            اسم المستخدم
                        </label>

                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            required
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            البريد الإلكتروني
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            required
                        >

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            الدور
                        </label>

                        <select
                            name="role_id"
                            class="form-select"
                            required
                        >

                            <?php foreach ($roles as $r): ?>

                                <option value="<?= $r['id'] ?>">
                                    <?= e(role_label($r['name'])) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            كلمة المرور (8 أحرف على الأقل)
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="8"
                            required
                        >

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        إلغاء
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        إنشاء المستخدم
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>