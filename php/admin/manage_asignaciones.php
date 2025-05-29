<?php
// Archivo: php/admin/manage_asignaciones.php
session_start();
require_once __DIR__ . '/../config.php';

// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

try {
    // Obtener asignaciones con detalles de conductor, vehículo y ruta
    $stmt = $pdo->query(
        "SELECT a.id,
                u.nombre AS conductor,
                v.patente AS vehiculo,
                r.origen,
                r.destino,
                a.fecha,
                a.creado_at
         FROM asignaciones a
         JOIN usuarios u ON a.conductor_id = u.id
         JOIN vehiculos v ON a.vehiculo_id  = v.id
         JOIN rutas    r ON a.ruta_id      = r.id
         ORDER BY a.fecha DESC, a.creado_at DESC"
    );
    $asignaciones = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Error al obtener asignaciones: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Asignaciones</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <header class="header-inner">
        <h1>Asignaciones</h1>
        <nav>
            <ul class="menu">
                <li><a href="../admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_solicitudes.php">Solicitudes</a></li>
                <li><a href="manage_asignaciones.php" class="active">Asignaciones</a></li>
                <li><a href="../logout.php">Cerrar sesión</a></li>
            </ul>
        </nav>
    </header>
    <main class="container">
        <section class="card">
            <?php if (empty($asignaciones)): ?>
                <p>No hay asignaciones registradas.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Vehículo</th>
                            <th>Ruta</th>
                            <th>Creado en</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asignaciones as $a): ?>
                        <tr>
                            <td><?= $a['id'] ?></td>
                            <td><?= htmlspecialchars($a['fecha']) ?></td>
                            <td><?= htmlspecialchars($a['conductor']) ?></td>
                            <td><?= htmlspecialchars($a['vehiculo']) ?></td>
                            <td><?= htmlspecialchars($a['origen'] . ' → ' . $a['destino']) ?></td>
                            <td><?= htmlspecialchars($a['creado_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
