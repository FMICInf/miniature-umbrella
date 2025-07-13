<?php
// Archivo: php/index.php

session_start();
require_once __DIR__ . '/config.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password, rol, nombre, must_reset_password FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['username'] = $user['nombre'];

        if ($user['must_reset_password'] == 1) {
            header('Location: reset_password.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    } else {
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: #f5f9fc;
        }
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: #f5f9fc;
        }
        .logo-uaysen {
            margin-bottom: 1.5rem;
            max-width: 230px;
            width: 80%;
        }
        .login-box {
            background: white;
            padding: 2.2rem 2.5rem;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(0, 64, 128, 0.12);
            width: 100%;
            max-width: 400px;
            margin-top: 0;
        }
        .login-box h2 {
            margin-bottom: 1.5rem;
            text-align: center;
            color: #004080;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        label {
            font-weight: 600;
            color: #004080;
            margin-bottom: 0.35rem;
            display: block;
        }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 0.7rem;
            border-radius: 5px;
            border: 1px solid #b2cbe4;
            font-size: 1.03rem;
        }
        button[type="submit"] {
            width: 100%;
            padding: 0.8rem;
            background: #004080;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.08rem;
            margin-top: 0.8rem;
            cursor: pointer;
            transition: background .2s;
        }
        button[type="submit"]:hover {
            background: #0062bf;
        }
        .alert-danger {
            background: #f8d7da;
            color: #842029;
            padding: .75rem;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 1.1rem;
        }
        .forgot-link {
            display: block;
            margin-top: 15px;
            text-align: center;
            font-size: .98rem;
        }
        .forgot-link a {
            color: #004080;
            text-decoration: underline;
            transition: color .15s;
        }
        .forgot-link a:hover {
            color: #0099cc;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <img class="logo-uaysen" src="../assets/img/logo-uaysen_patagonia_sin_fondo.png" alt="Universidad de Aysén">
    <div class="login-box">
        <h2>Iniciar sesión</h2>

        <?php if ($error): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Correo institucional:</label>
                <input type="email" name="email" id="email" placeholder="nombre@uaysen.cl" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" name="password" id="password" required>
            </div>

            <button type="submit">Ingresar</button>
            <div class="forgot-link">
                <a href="forgot_password.php">¿Olvidaste tu contraseña?</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
