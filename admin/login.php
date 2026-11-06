<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$error = '';
$success = isset($_GET['setup']) ? 'Admin account created successfully. Please log in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $users = readJson(USERS_FILE);
        $matchedUser = null;

        foreach ($users as $user) {
            if (
                is_array($user)
                && isset($user['username'], $user['password'])
                && is_string($user['username'])
                && is_string($user['password'])
                && hash_equals($user['username'], $username)
                && password_verify($password, $user['password'])
            ) {
                $matchedUser = $user;
                break;
            }
        }

        if ($matchedUser !== null) {
            session_regenerate_id(true);

            $_SESSION['admin_id'] = (string) ($matchedUser['id'] ?? '');
            $_SESSION['admin_username'] = (string) $matchedUser['username'];
            $_SESSION['admin_role'] = (string) ($matchedUser['role'] ?? 'editor');

            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Deepshitech</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f4f5f7;
            color: #222;
            font-family: Arial, sans-serif;
        }
        .card {
            width: 100%;
            max-width: 420px;
            padding: 32px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }
        h1 { margin-top: 0; font-size: 26px; }
        p { color: #666; line-height: 1.5; }
        label { display: block; margin: 18px 0 7px; font-weight: 600; }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }
        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            border: 0;
            border-radius: 6px;
            background: #c99424;
            color: #111;
            font-weight: 700;
            cursor: pointer;
        }
        .error { color: #b42318; }
        .success { color: #187044; }
    </style>
</head>
<body>
<main class="card">
    <h1>Deepshitech Blog Admin</h1>
    <p>Sign in to manage your blog posts.</p>

    <?php if ($success !== ''): ?>
        <p class="success"><?= e($success) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <label for="username">Username</label>
        <input id="username" name="username" required
               autocomplete="username"
               value="<?= e((string) ($_POST['username'] ?? '')) ?>">

        <label for="password">Password</label>
        <input id="password" name="password" type="password"
               required autocomplete="current-password">

        <button type="submit">Log In</button>
    </form>
</main>
</body>
</html>
