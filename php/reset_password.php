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

    // Validación de contraseña fuerte
    $errores = [];
    if (strlen($new_password) < 8)           $errores[] = "Al menos 8 caracteres";
    if (!preg_match('/[A-Z]/', $new_password)) $errores[] = "Al menos 1 mayúscula";
    if (!preg_match('/[a-z]/', $new_password)) $errores[] = "Al menos 1 minúscula";
    if (!preg_match('/\d/', $new_password))    $errores[] = "Al menos 1 número";
    if (!preg_match('/[!@#$%^&*()_\-+=\[\]{};:,.<>¿¡?\/\\|`~"\']/', $new_password)) $errores[] = "Al menos 1 símbolo";

    if ($errores) {
        $error = "La nueva contraseña debe tener:<br>- " . implode("<br>- ", $errores);
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
    <link rel="stylesheet" href="../assets/css/style.css">
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
        #password-checklist {
            list-style:none;
            margin:10px 0 0 0;
            padding:0;
            font-size:.98em;
        }
        #password-checklist li {
            transition: color 0.2s;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h2>Cambiar contraseña</h2>

            <?php if ($error): ?>
                <div class="alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="new_password">Nueva contraseña:</label>
                    <input type="password" name="new_password" id="new_password" required autocomplete="new-password">
                    <ul id="password-checklist">
                        <li id="chk-length">&#10007; Al menos 8 caracteres</li>
                        <li id="chk-mayus">&#10007; Al menos 1 mayúscula</li>
                        <li id="chk-minus">&#10007; Al menos 1 minúscula</li>
                        <li id="chk-num">&#10007; Al menos 1 número</li>
                        <li id="chk-symbol">&#10007; Al menos 1 símbolo</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar nueva contraseña:</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <button type="submit">Guardar nueva contraseña</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const passInput = document.getElementById('new_password');
        const chkLength = document.getElementById('chk-length');
        const chkMayus  = document.getElementById('chk-mayus');
        const chkMinus  = document.getElementById('chk-minus');
        const chkNum    = document.getElementById('chk-num');
        const chkSym    = document.getElementById('chk-symbol');

        passInput.addEventListener('input', function() {
            const val = passInput.value;

            // RegEx checks
            const hasLength = val.length >= 8;
            const hasMayus  = /[A-Z]/.test(val);
            const hasMinus  = /[a-z]/.test(val);
            const hasNum    = /\d/.test(val);
            const hasSym    = /[!@#$%^&*()_\-+=\[\]{};:,.<>¿¡?\/\\|`~"'°]/.test(val);

            function check(item, ok) {
                item.style.color = ok ? '#090' : '#c00';
                item.innerHTML = (ok ? '&#10003;' : '&#10007;') + ' ' + item.textContent.replace(/^(\u2713|\u2717) /, '');
            }

            check(chkLength, hasLength);
            check(chkMayus,  hasMayus);
            check(chkMinus,  hasMinus);
            check(chkNum,    hasNum);
            check(chkSym,    hasSym);
        });
    });
    </script>
</body>
</html>
