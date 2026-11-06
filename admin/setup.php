<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (is_file(USERS_FILE) && readJson(USERS_FILE) !== []) {
    exit('Admin setup is already complete. Delete or disable this setup file.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
            $error = 'Username must be 3–30 characters and contain only letters, numbers, dots, underscores, or hyphens.';
        } elseif (strlen($password) < 12) {
            $error = 'Password must be at least 12 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $users = readJson(USERS_FILE);

            if ($users !== []) {
                $error = 'Admin setup has already been completed.';
            } else {
                $users[] = [
                    'id' => bin2hex(random_bytes(16)),
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'admin',
                    'created_at' => date('c'),
                ];

                if (writeJson(USERS_FILE, $users)) {
                    header('Location: ' . BASE_URL . '/admin/login.php?setup=success');
                    exit;
                }

                $error = 'Unable to save the account. Check write permissions for the data folder.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initial Admin Setup | Deepshitech</title>
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
            max-width: 440px;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }
        h1 { margin-top: 0; font-size: 25px; }
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
    </style>
</head>
<body>
<main class="card">
    <h1>Create your admin account</h1>
    <p>Set up the first administrator for the Deepshitech Blog CMS.</p>

    <?php if ($error !== ''): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <label for="username">Username</label>
        <input id="username" name="username" required minlength="3"
               maxlength="30" autocomplete="username"
               value="<?= e((string) ($_POST['username'] ?? '')) ?>">

        <label for="password">Password</label>
        <input id="password" name="password" type="password"
               required minlength="12" autocomplete="new-password">

        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password"
               type="password" required minlength="12"
               autocomplete="new-password">

        <button type="submit">Create Admin Account</button>
    </form>
</main>
</body>
</html>
