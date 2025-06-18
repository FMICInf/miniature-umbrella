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
        /* Estilos adicionales para centrar el login */
        .login-wrapper {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f5f5f5;
        }

        .login-box {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-box h2 {
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .login-box .form-group {
            margin-bottom: 1rem;
        }

        .login-box .alert-danger {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-box">
            <h2>Iniciar sesión</h2>

            <?php if ($error): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Correo:</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit">Ingresar</button>
            </form>
        </div>
    </div>

</body>
</html>
