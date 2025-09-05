<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM admin_users WHERE username = ?", [$username]);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Ungültige Anmeldedaten';
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        /* Reset & Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--clr-surface-a0);
            color: var(--clr-light-a0);
            line-height: 1.6;
        }

        /* Login Container */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--clr-surface-a0) 0%, var(--clr-surface-tonal-a0) 100%);
        }

        /* Login Card */
        .login-card {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: var(--radius-lg);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        /* Login Header */
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-title {
            color: var(--clr-primary-a20);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .login-logo-image {
            width: 45px;
            height: 45px;
            object-fit: contain;
            filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.3));
        }


        /* Alert Messages */
        .alert {
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background-color: rgba(248, 113, 113, 0.1);
            border: 1px solid #f87171;
            color: #fca5a5;
        }

        .alert-success {
            background-color: rgba(74, 222, 128, 0.1);
            border: 1px solid #4ade80;
            color: #86efac;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            color: var(--clr-surface-a50);
            font-weight: 500;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            background-color: var(--clr-surface-a05);
            border: 1px solid var(--clr-surface-a20);
            border-radius: var(--radius-sm);
            color: var(--clr-light-a0);
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--clr-primary-a0);
            box-shadow: 0 0 0 2px rgba(230, 163, 9, 0.2);
            background: var(--clr-surface-a10);
        }

        .form-input::placeholder {
            color: var(--clr-surface-a40);
        }

        /* Login Button */
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn:hover {
            background-color: var(--clr-primary-a10);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background-color: var(--clr-surface-a20);
            color: var(--clr-light-a0);
        }

        .btn-secondary:hover {
            background-color: var(--clr-surface-a30);
        }

        .btn-icon {
            padding: 6px 8px;
            font-size: 12px;
            min-width: auto;
        }

        .btn-full {
            width: 100%;
            margin-top: 10px;
            justify-content: center;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }

        /* Demo Info Box */
        .demo-info {
            background: var(--clr-surface-tonal-a10);
            border: 1px solid var(--clr-primary-a20);
            border-radius: var(--radius-sm);
            padding: 12px;
            margin-top: 20px;
            font-size: 13px;
        }

        .demo-info-title {
            color: var(--clr-primary-a20);
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .demo-credentials {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .demo-credential {
            text-align: center;
        }

        .demo-credential strong {
            color: var(--clr-primary-a20);
            display: block;
            margin-bottom: 2px;
        }

        .demo-credential span {
            color: var(--clr-surface-a50);
            font-size: 12px;
        }

        /* Form Footer */
        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-surface-a20);
        }

        .form-footer p {
            color: var(--clr-surface-a50);
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-card {
                margin: 20px;
                padding: 30px;
            }

            .login-logo-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }

            .login-title {
                font-size: 1.3rem;
            }

            .demo-credentials {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 25px;
            }

            .login-title {
                font-size: 1.2rem;
            }

            .btn {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="../assets/images/logo.png" alt="Meine Firma Logo" class="login-logo-image">
                    <h1 class="login-title">Meine Firma Termine</h1>
                </div>
                <p class="login-subtitle">Dein persönlicher Termine-Manager</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="username">
                        Benutzername
                    </label>
                    <input type="text" id="username" name="username" class="form-input" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        Passwort
                    </label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>

                <button type="submit" class="btn btn-full">
                    Anmelden
                </button>
            </form>

            <!-- Demo Credentials -->
            <div class="demo-info">
                <div class="demo-info-title">
                    <i class="fas fa-info-circle"></i> Demo-Zugangsdaten
                </div>
                <div class="demo-credentials">
                    <div class="demo-credential">
                        <strong>Admin</strong>
                        <span>admin / admin123</span>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <?php
                $start_year = 2024;
                $current_year = date('Y');
                ?>
                <p>© <?= $start_year == $current_year ? $current_year : $start_year . ' - ' . $current_year ?> · Flammang Yves</p>
            </div>
        </div>
    </div>
</body>

</html>