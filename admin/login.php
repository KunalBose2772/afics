<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$settings = get_settings($pdo);


$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Check if user has CMS access (website_manager or super_admin)
        if ($user['role'] === 'website_manager' || $user['role'] === 'super_admin') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard');
            exit;
        } else {
            $error = 'Access Denied: Only Website Manager or Super Admin can access this panel.';
        }
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Documantraa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            min-height: 100dvh; /* Better mobile support */
            background: #000;
            overflow-x: hidden;
            overflow-y: auto; /* Allow scrolling on small screens/keyboard open */
            padding: 1rem;
            margin: 0;
        }
        
        /* Animated Background */
        body::before {
            content: '';
            position: fixed; /* Fixed so it doesn't scroll */
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 210, 255, 0.1) 0%, rgba(0, 0, 0, 0) 70%);
            animation: rotateBG 20s linear infinite;
            z-index: -1;
        }

        @keyframes rotateBG {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .login-card {
            width: 100%;
            max-width: 350px;
            padding: 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border-radius: 20px;
            overflow: hidden;
            margin: auto; /* Helps with centering in flex container */
        }

        .logo-container {
            background: #fff;
            padding: 0;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .login-body {
            padding: 2rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 0.7rem 1rem;
            font-size: 0.9rem;
            border-radius: 8px;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(0, 210, 255, 0.1);
            color: #fff;
        }

        .form-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.4rem;
        }

        .btn-login {
            background: var(--accent-gradient);
            border: none;
            padding: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            width: 100%;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 210, 255, 0.3);
        }

        /* Mobile Optimization */
        @media (max-width: 576px) {
            body {
                align-items: flex-start;
                padding-top: 30px;
            }
            .login-card {
                max-width: 100%;
                border-radius: 16px;
                margin: 0;
            }
            .logo-container {
                height: 100px;
            }
            .login-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-container">
            <img src="../assets/images/Documantraa.gif" alt="Documantra">
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 text-danger d-flex align-items-center py-2 px-3 mb-3 small" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" style="border-color: rgba(255,255,255,0.1); color: #fff;">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login rounded-pill">
                        Login
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye icon
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>
