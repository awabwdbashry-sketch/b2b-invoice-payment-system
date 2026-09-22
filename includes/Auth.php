<?php
declare(strict_types=1);

/**
 * Authentication + server-side permission-based authorization.
 * All authorization decisions MUST be enforced here (or via Auth::requirePermission)
 * on every protected page — never rely on hiding UI elements alone.
 */
final class Auth
{
    /**
     * Attempt to log a user in by username or email.
     * Returns ['ok' => bool, 'error' => string|null]
     */
    public static function attempt(string $usernameOrEmail, string $password): array
    {
        $pdo = Database::get();

        // Use separate named parameters because the same PDO named
        // parameter cannot safely be reused with native prepared statements.
        $stmt = $pdo->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username OR u.email = :email
             LIMIT 1'
        );

        $stmt->execute([
            'username' => $usernameOrEmail,
            'email'    => $usernameOrEmail,
        ]);

        $user = $stmt->fetch();

        if (!$user) {
            // Do not reveal whether the account exists.
            return ['ok' => false, 'error' => 'اسم المستخدم/البريد الإلكتروني أو كلمة المرور غير صحيحة.'];
        }

        // Lockout check
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $mins = (int)ceil((strtotime($user['locked_until']) - time()) / 60);

            return [
                'ok' => false,
                'error' => "الحساب مقفل مؤقتًا. حاول مرة أخرى بعد {$mins} دقيقة."
            ];
        }

        if ($user['status'] !== 'active') {
            return [
                'ok' => false,
                'error' => 'هذا الحساب غير نشط. يرجى التواصل مع مدير النظام.'
            ];
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::registerFailedAttempt(
                $pdo,
                (int)$user['id'],
                (int)$user['failed_login_attempts']
            );

            return [
                'ok' => false,
                'error' => 'اسم المستخدم/البريد الإلكتروني أو كلمة المرور غير صحيحة.'
            ];
        }

        // Success: reset failed attempts, update last login, rehash if needed
        $newHash = null;

        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
        }

        $update = $pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0,
             locked_until = NULL,
             last_login_at = :now,
             last_login_ip = :ip' .
            ($newHash ? ', password_hash = :hash' : '') .
            ' WHERE id = :id'
        );

        $params = [
            'now' => date('Y-m-d H:i:s'),
            'ip'  => client_ip(),
            'id'  => $user['id'],
        ];

        if ($newHash) {
            $params['hash'] = $newHash;
        }

        $update->execute($params);

        // Prevent session fixation: regenerate the session ID on privilege change.
        session_regenerate_id(true);

        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['role_id']   = (int)$user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['full_name'] = $user['full_name'];

        self::loadPermissions($pdo, (int)$user['role_id']);

        AuditLog::record(
            'auth.login',
            'user',
            (int)$user['id'],
            'تسجيل دخول المستخدم'
        );

        return ['ok' => true, 'error' => null];
    }

    private static function registerFailedAttempt(
        PDO $pdo,
        int $userId,
        int $currentAttempts
    ): void {
        $attempts = $currentAttempts + 1;
        $lockedUntil = null;

        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = date(
                'Y-m-d H:i:s',
                time() + LOGIN_LOCKOUT_MINUTES * 60
            );
        }

        $stmt = $pdo->prepare(
            'UPDATE users
             SET failed_login_attempts = :a,
                 locked_until = :l
             WHERE id = :id'
        );

        $stmt->execute([
            'a'  => $attempts,
            'l'  => $lockedUntil,
            'id' => $userId,
        ]);
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditLog::record(
                'auth.logout',
                'user',
                self::id(),
                'تسجيل خروج المستخدم'
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function roleName(): ?string
    {
        return $_SESSION['role_name'] ?? null;
    }

    public static function fullName(): ?string
    {
        return $_SESSION['full_name'] ?? null;
    }

    /** Load and cache the permission key set for a role into the session. */
    public static function loadPermissions(PDO $pdo, int $roleId): void
    {
        $stmt = $pdo->prepare(
            'SELECT p.key
             FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :rid'
        );

        $stmt->execute([
            'rid' => $roleId
        ]);

        $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Check whether the current user has a given permission key. */
    public static function can(string $permissionKey): bool
    {
        if (!self::check()) {
            return false;
        }

        return in_array(
            $permissionKey,
            $_SESSION['permissions'] ?? [],
            true
        );
    }

    /** Require login; redirect to login page if not authenticated. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect(base_path('auth/login.php'));
        }

        // Defense-in-depth: re-validate the account is still active on every request.
        static $checked = false;

        if (!$checked) {
            $checked = true;

            $pdo = Database::get();

            $stmt = $pdo->prepare(
                'SELECT status FROM users WHERE id = :id'
            );

            $stmt->execute([
                'id' => self::id()
            ]);

            $row = $stmt->fetch();

            if (!$row || $row['status'] !== 'active') {
                self::logout();
                redirect(base_path('auth/login.php?deactivated=1'));
            }
        }
    }

    /**
     * Require a specific permission.
     * Enforced fully server-side — this is the real authorization gate,
     * independent of any hidden UI element.
     */
    public static function requirePermission(string $permissionKey): void
    {
        self::requireLogin();

        if (!self::can($permissionKey)) {
            http_response_code(403);

            AuditLog::record(
                'authz.denied',
                'permission',
                null,
                "تم رفض الوصول إلى {$permissionKey}"
            );

            include __DIR__ . '/../includes/403.php';
            exit;
        }
    }
}
