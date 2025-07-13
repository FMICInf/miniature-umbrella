<?php
session_start();
$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config.php';
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $error = "Debes ingresar tu correo electrónico.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $pdo->prepare("UPDATE usuarios SET reset_code=?, reset_code_expira=DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id=?");
            $stmt->execute([$code, $user['id']]);
            require_once __DIR__ . '/assets/mail_helper.php';
            $asunto = "Código de recuperación de contraseña";
            $cuerpo = "<p>Tu código de recuperación es: <b>$code</b></p><p>Ingresa este código en el formulario para continuar.</p>";
            enviarNotificacion($email, $asunto, $cuerpo);
            $_SESSION['recovery_email'] = $email;
            header('Location: validate_code.php');
            exit;
        } else {
            $error = "Correo no encontrado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .form-box { max-width:370px; margin:70px auto; background:#fff; border-radius:12px; box-shadow:0 4px 18px #0001; padding:2.5rem 2rem; }
    .form-box h2 { text-align:center; margin-bottom:1.7rem;}
    .form-group { margin-bottom:1.2rem; }
    .form-box input[type="email"] { width:100%; padding:.7rem; border:1px solid #bbb; border-radius:5px; }
    .form-box button { background:#004080; color:#fff; width:100%; border:none; padding:.7rem; border-radius:5px; font-size:1.09em; }
    .alert-danger { background:#f8d7da; color:#842029; padding:.7rem; border-radius:4px; margin-bottom:1rem; text-align:center; }
    </style>
</head>
<body>
    <div class="form-box">
        <h2>¿Olvidaste tu contraseña?</h2>
        <?php if ($error): ?><div class="alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="email">Correo electrónico:</label>
                <input type="email" name="email" required autofocus>
            </div>
            <button type="submit">Enviar código</button>
        </form>
    </div>
</body>
</html>
