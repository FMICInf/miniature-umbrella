<?php
session_start();
if (empty($_SESSION['recovery_email']) || empty($_SESSION['recovery_valid'])) {
    header('Location: forgot_password.php');
    exit;
}
require_once __DIR__ . '/config.php';
$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['new_password'] ?? '';
    $pass2 = $_POST['confirm_password'] ?? '';
    if ($pass !== $pass2) {
        $error = "Las contraseñas no coinciden.";
    } elseif (
        strlen($pass) < 8 ||
        !preg_match('/[A-Z]/', $pass) ||
        !preg_match('/[a-z]/', $pass) ||
        !preg_match('/[0-9]/', $pass)
    ) {
        $error = "La contraseña debe tener mínimo 8 caracteres, mayúsculas, minúsculas y números.";
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password=?, reset_code=NULL, reset_code_expira=NULL, must_reset_password=0 WHERE email=?");
        $stmt->execute([$hashed, $_SESSION['recovery_email']]);
        session_unset();
        $success = "Contraseña actualizada correctamente. <a href='index.php'>Iniciar sesión</a>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer contraseña</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .form-box { max-width:390px; margin:70px auto; background:#fff; border-radius:12px; box-shadow:0 4px 18px #0001; padding:2.5rem 2rem; }
    .form-box h2 { text-align:center; margin-bottom:1.7rem;}
    .form-group { margin-bottom:1.2rem; }
    .form-box input[type="password"] { width:100%; padding:.7rem; border:1px solid #bbb; border-radius:5px; }
    .form-box button { background:#004080; color:#fff; width:100%; border:none; padding:.7rem; border-radius:5px; font-size:1.09em; }
    .alert-danger { background:#f8d7da; color:#842029; padding:.7rem; border-radius:4px; margin-bottom:1rem; text-align:center; }
    .alert-success { background:#d1e7dd; color:#175c3c; padding:.7rem; border-radius:4px; margin-bottom:1rem; text-align:center; }
    ul.pw-rules { margin: 0 0 1rem 0; padding:0; list-style:none; font-size:.97em;}
    ul.pw-rules li { margin:.2em 0; color:#888; }
    ul.pw-rules li.valid { color:#28a745; }
    </style>
</head>
<body>
<div class="form-box">
    <h2>Restablecer contraseña</h2>
    <?php if ($error): ?><div class="alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?= $success ?></div><?php else: ?>
    <form method="POST" autocomplete="off">
        <div class="form-group">
            <label for="new_password">Nueva contraseña:</label>
            <input type="password" name="new_password" id="new_password" required autocomplete="new-password" oninput="checkPasswordRules()">
            <ul class="pw-rules" id="pw-rules-list">
                <li id="pw-len">Al menos 8 caracteres</li>
                <li id="pw-upp">Una letra mayúscula</li>
                <li id="pw-low">Una letra minúscula</li>
                <li id="pw-num">Un número</li>
            </ul>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirmar contraseña:</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit">Guardar nueva contraseña</button>
    </form>
    <?php endif; ?>
</div>
<script>
function checkPasswordRules() {
    const val = document.getElementById('new_password').value;
    document.getElementById('pw-len').className = val.length >= 8 ? 'valid' : '';
    document.getElementById('pw-upp').className = /[A-Z]/.test(val) ? 'valid' : '';
    document.getElementById('pw-low').className = /[a-z]/.test(val) ? 'valid' : '';
    document.getElementById('pw-num').className = /\d/.test(val) ? 'valid' : '';
}
</script>
</body>
</html>
