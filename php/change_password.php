<?php
// Archivo: php/change_password.php

session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword !== $confirmPassword) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, must_reset_password = 0 WHERE id = ?");
        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);

        $success = "Contraseña actualizada correctamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Cambiar contraseña</h2>
        <?php if (!empty($error)): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif (!empty($success)): ?>
            <div class="alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="new_password">Nueva contraseña</label>
                <input type="password" name="new_password" id="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar contraseña</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
            </div>
            <button type="submit">Guardar</button>
        </form>
    </div>
</body>
</html>
