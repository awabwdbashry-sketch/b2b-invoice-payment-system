<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::logout();
redirect(base_path('auth/login.php'));
