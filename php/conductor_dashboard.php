<?php
// Archivo: php/conductor_dashboard.php
session_start();
require_once __DIR__ . '/config.php';

// Validar sesión y rol de conductor
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'conductor') {
    header('Location: index.php');
    exit;
}
$conductorId = $_SESSION['user_id'];

// Parámetros de filtro por fecha
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';

// Construir parte WHERE dinámica
$where = 'WHERE a.conductor_id = :cond';
$params = ['cond' => $conductorId];

if ($from) {
    $where .= ' AND a.fecha >= :from';
    $params['from'] = $from;
}
if ($to) {
    $where .= ' AND a.fecha <= :to';
    $params['to'] = $to;
}

try {
    // Traer asignaciones con hora de salida y vehículo
    $sql = "
      SELECT
        a.id,
        a.fecha,
        r.origen,
        r.destino,
        r.horario_salida,
        v.patente     AS vehiculo,
        a.creado_at
      FROM asignaciones a
      JOIN rutas     r ON a.ruta_id     = r.id
      JOIN vehiculos v ON a.vehiculo_id = v.id
      $where
      ORDER BY a.fecha DESC, a.creado_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error BD: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bitácora de Conductor</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .container { max-width: 900px; margin:2rem auto; padding:0 1rem; }
    .filter { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
    .filter label { display:flex; flex-direction:column; font-weight:500; }
    .filter input { padding:.5rem; border:1px solid #ccc; border-radius:4px; }
    .filter button { align-self:flex-end; padding:.5rem 1rem; background:#004080; color:#fff; border:none; border-radius:4px; cursor:pointer; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:.75rem; border:1px solid #ddd; text-align:left; word-wrap:break-word; }
    th { background:#004080; color:#fff; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Mi Bitácora de Viajes</h1>
    <nav>
      <ul class="menu">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="logout.php">Cerrar sesión</a></li>
      </ul>
    </nav>
  </header>
  <div class="container">
    <!-- Filtro de fechas -->
    <form class="filter" method="get">
      <label>
        Desde
        <input type="date" name="from" value="<?=htmlspecialchars($from)?>">
      </label>
      <label>
        Hasta
        <input type="date" name="to" value="<?=htmlspecialchars($to)?>">
      </label>
      <button type="submit">Filtrar</button>
      <button type="button" onclick="window.location='conductor_dashboard.php';">Restablecer</button>
    </form>

    <?php if (empty($asignaciones)): ?>
      <p>No tienes asignaciones en el periodo seleccionado.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Ruta</th>
            <th>Hora Salida</th>
            <th>Vehículo</th>
            <th>Registrado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asignaciones as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['id']) ?></td>
            <td><?= htmlspecialchars($a['fecha']) ?></td>
            <td><?= htmlspecialchars("{$a['origen']} → {$a['destino']}") ?></td>
            <td><?= htmlspecialchars($a['horario_salida']) ?></td>
            <td><?= htmlspecialchars($a['vehiculo']) ?></td>
            <td><?= htmlspecialchars($a['creado_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
