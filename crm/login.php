<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$settings = get_settings($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_type = sanitize_input($_POST['login_type'] ?? 'employee');

    // Validation
    $req_fields = ['email' => 'Email Address', 'password' => 'Password'];
    $errors = validate_required($req_fields, $_POST);
    
    if (empty($errors) && !validate_email($email)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Deny Website Manager from CRM (unless they are super admin)
            if ($user['email'] === 'websitemanager@documantraa.in' && $user['role'] !== 'super_admin') {
                $errors[] = 'Access Denied: Website Managers cannot access the Investigator Portal.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investigator Login - <?= htmlspecialchars($settings['site_name'] ?? 'Documantraa') ?></title>

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d2b4a">

    <!-- Google Fonts: Montserrat for Professional Look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        :root {
            --primary-color: #0d2b4a;
            /* Deep Navy Blue for Trust/Security */
            --accent-color: #c9a25d;
            /* Gold/Bronze for Authority/Premium */
            --bg-color: #f4f6f9;
            /* Light Slate Gray for Professional Background */
            --text-dark: #1a1a1a;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #ffffff;
            background-image: none;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 900px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            display: flex;
            flex-direction: row;
            /* Split layout */
        }

        /* Left Side: Brand/Image */
        .login-brand-side {
            background-color: var(--primary-color);
            width: 45%;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-brand-side::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('../assets/images/hero-bg.jpg') center/cover;
            /* Fallback or specific login bg */
            opacity: 0.15;
            mix-blend-mode: overlay;
        }

        .brand-logo {
            max-width: 250px;
            /* Increased size for visibility */
            width: 100%;
            height: auto;
            position: relative;
            z-index: 2;
            border-radius: 15px;
            /* Added border radius as requested */
            /* filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); */
            /* Removed drop shadow for GIF clarity */
        }

        /* Right Side: Form */
        .login-form-side {
            width: 55%;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .form-header h2 {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
            font-size: 1.75rem;
            text-transform: uppercase;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control,
        .form-select {
            padding: 12px 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #fbfbfb;
            font-family: 'Montserrat', sans-serif;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 43, 74, 0.1);
            background-color: #fff;
            outline: none;
        }

        .btn-login {
            background-color: var(--primary-color);
            color: white;
            padding: 14px;
            border-radius: 6px;
            font-weight: 700;
            letter-spacing: 1px;
            width: 100%;
            border: none;
            transition: all 0.3s;
            margin-top: 10px;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .btn-login:hover {
            background-color: #0a2138;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(13, 43, 74, 0.2);
        }

        .input-group-text {
            background-color: #fbfbfb;
            border: 1px solid #dee2e6;
            border-left: none;
            cursor: pointer;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
                margin: 0 auto;
            }

            .login-brand-side {
                width: 100%;
                padding: 30px 20px;
            }

            .login-form-side {
                width: 100%;
                padding: 30px 25px;
            }

            .brand-logo {
                max-width: 150px;
            }
        }

        /* Alert styling */
        .alert-custom {
            font-size: 0.85rem;
            border-radius: 6px;
            padding: 12px 15px;
            font-weight: 500;
        }
    </style>
</head>

<body>

    <div class="login-container">
        <!-- Brand Side -->
        <div class="login-brand-side">
            <!-- Explicitly using the requested GIF logo and removing text -->
            <img src="../assets/images/Documantraa.gif" alt="Documantraa" class="brand-logo">
        </div>

        <!-- Form Side -->
        <div class="login-form-side">
            <div class="form-header">
                <h2>Investigator Login</h2>
                <p>Secure Access Portal</p>
            </div>
            <div class="login-body">
            <?= render_form_errors($errors ?? []) ?>

            <form method="POST" action="" class="needs-validation" novalidate>
                <!-- Role Selector -->
                <div class="form-group">
                    <label class="form-label" for="login_type">Type of Login</label>
                    <select name="login_type" id="login_type" class="form-select" required>
                        <option value="admin">Admin Login</option>
                        <option value="hr">HR Login</option>
                        <option value="office_staff">Office Staff Login</option>
                        <option value="employee">Employee Login</option>
                        <option value="freelancer">Freelancer Login</option>
                        <option value="doctor">CI Login (Claim Incharge)</option>
                        <option value="incharge">Incharge Login</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 bg-white text-muted"><i
                                class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control border-start-0 ps-0"
                            placeholder="name@company.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0 bg-white text-muted"><i
                                class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password"
                            class="form-control border-start-0 ps-0 border-end-0" placeholder="••••••••" required>
                        <span class="input-group-text bg-white" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    Secure Sign In <i class="bi bi-arrow-right-short ms-1"></i>
                </button>

                <div class="text-center mt-4">
                    <a href="forgot-password" class="text-decoration-none small text-muted hover-primary"
                        style="font-weight: 500;">Forgot your password?</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/validation.js"></script>
    <script>
        // Password Visibility Toggle
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // Toggle Icon
            const icon = this.querySelector('i');
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });

        // Optional: Pre-select role based on URL param if needed ?role=hr
        const urlParams = new URLSearchParams(window.location.search);
        const role = urlParams.get('role');
        if (role) {
            const select = document.getElementById('login_type');
            if (select.querySelector(`option[value="${role}"]`)) {
                select.value = role;
            }
        }

        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js').then(function(registration) {
                    console.log('ServiceWorker registration successful scope: ', registration.scope);
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>

</html>