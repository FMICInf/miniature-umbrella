<?php
// Archivo: php/admin_dashboard.php
session_start();
require_once __DIR__ . '/config.php';
// Validar rol de admin
if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – Logística</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header-inner">
        <h1>Panel de Administrador</h1>
        <nav>
            <ul class="menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="admin/manage_users.php">Gestionar Usuarios</a></li>
                <li><a href="admin/manage_vehiculos.php">Gestionar Vehículos</a></li>
                <li><a href="admin/manage_rutas.php">Gestionar Rutas</a></li>
                <li><a href="admin/manage_solicitudes.php">Ver Solicitudes</a></li>
                <li><a href="admin/manage_asignaciones.php">Ver Asignaciones</a></li>
                <li><a href="logout.php">Cerrar sesión</a></li>
            </ul>
        </nav>
    </header>
    <main class="container">
        <div class="card">
            <h2>Bienvenido, <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?></h2>
            <p>Selecciona una opción del menú para comenzar.</p>
        </div>
    </main>
</body>
</html>
