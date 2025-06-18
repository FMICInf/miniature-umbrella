<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT must_reset_password FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$must_reset = $stmt->fetchColumn();

if ($must_reset == 0) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, must_reset_password = 0 WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['user_id']]);
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar contraseña</title>
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Asegúrate de que exista -->
    <style>
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

        .form-group {
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #842029;
            padding: .75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h2>Cambiar contraseña</h2>

            <?php if ($error): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="new_password">Nueva contraseña:</label>
                    <input type="password" name="new_password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar nueva contraseña:</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <button type="submit">Guardar nueva contraseña</button>
            </form>
        </div>
    </div>
</body>
</html>
