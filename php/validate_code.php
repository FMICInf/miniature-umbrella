<?php
session_start();
if (!isset($_SESSION['recovery_email'])) {
    header('Location: forgot_password.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config.php';
    $email = $_SESSION['recovery_email'];
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email=? AND reset_code=? AND reset_code_expira > NOW()");
    $stmt->execute([$email, $code]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['recovery_valid'] = true;
        header('Location: reset_password_code.php');
        exit;
    } else {
        $error = "Código inválido o expirado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Validar código</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .form-box { max-width:370px; margin:70px auto; background:#fff; border-radius:12px; box-shadow:0 4px 18px #0001; padding:2.5rem 2rem; }
    .form-box h2 { text-align:center; margin-bottom:1.7rem;}
    .form-group { margin-bottom:1.2rem; }
    .form-box input[type="text"] { width:100%; padding:.7rem; border:1px solid #bbb; border-radius:5px; }
    .form-box button { background:#004080; color:#fff; width:100%; border:none; padding:.7rem; border-radius:5px; font-size:1.09em; }
    .alert-danger { background:#f8d7da; color:#842029; padding:.7rem; border-radius:4px; margin-bottom:1rem; text-align:center; }
    </style>
</head>
<body>
    <div class="form-box">
        <h2>Introduce el código recibido</h2>
        <?php if ($error): ?><div class="alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="code">Código:</label>
                <input type="text" name="code" maxlength="6" required autofocus>
            </div>
            <button type="submit">Validar código</button>
        </form>
    </div>
</body>
</html>
