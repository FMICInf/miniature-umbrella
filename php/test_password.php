<?php
// Contraseña escrita por el usuario
$ingresada = '123456';

// Hash copiado desde la base de datos
$hash = '$2y$10$9VZPjUOB.Ei3WXTcSbz7Leu3WpQkHX1LoZ9XoJFlBDmvK0lIj5/1S';

if (password_verify($ingresada, $hash)) {
    echo "✅ Contraseña válida";
} else {
    echo "❌ Contraseña inválida";
}
