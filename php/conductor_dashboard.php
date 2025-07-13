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

// Paginación
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

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

// Obtener total de asignaciones para paginación
try {
    $sqlTotal = "
      SELECT COUNT(*) FROM asignaciones a
      JOIN rutas r ON a.ruta_id = r.id
      JOIN vehiculos v ON a.vehiculo_id = v.id
      $where
    ";
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute($params);
    $totalAsignaciones = (int)$stmtTotal->fetchColumn();
    $totalPages = max(1, ceil($totalAsignaciones / $perPage));
} catch (PDOException $e) {
    die('Error BD: ' . $e->getMessage());
}

// Obtener asignaciones paginadas
try {
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
      LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(is_int($k) ? $k+1 : ':'.$k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
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
    .info-banner {
      background: #f9fbfc;
      border: 1.5px solid #e3edf7;
      border-radius: 12px;
      margin-bottom: 25px;
      box-shadow: 0 2px 10px rgba(32,50,80,0.06);
      padding: 18px 12px 14px 12px;
      color: #133366;
      font-size: 1.06rem;
    }
    .filter { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
    .filter label { display:flex; flex-direction:column; font-weight:500; }
    .filter input { padding:.5rem; border:1px solid #ccc; border-radius:4px; }
    .filter button { align-self:flex-end; padding:.5rem 1rem; background:#004080; color:#fff; border:none; border-radius:4px; cursor:pointer; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:.75rem; border:1px solid #ddd; text-align:left; word-wrap:break-word; }
    th { background:#004080; color:#fff; }
    .pagination {
      margin: 20px 0 10px 0;
      text-align: center;
    }
    .pagination a, .pagination span {
      display: inline-block;
      margin: 0 5px;
      padding: 6px 13px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: 600;
      color: #004080;
      border: 1px solid #004080;
      cursor: pointer;
      background: #fff;
      transition: background 0.2s;
    }
    .pagination .current {
      background: #004080;
      color: #fff;
      cursor: default;
      border-color: #004080;
    }
    .pagination a:hover:not(.current) {
      background: #e5eef9;
    }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Mi Bitácora de Viajes</h1>
    <nav>
      <ul class="menu">
        <li><a href="dashboard.php">Volver</a></li>
      </ul>
    </nav>
  </header>

  <div class="container">
    <!-- NOTA INFORMATIVA -->
    <div class="info-banner">
      <b>¿Cómo funciona este panel?</b><br>
      Este panel muestra la <b>bitácora de viajes</b> asignados al conductor.<br>
      Aquí puedes consultar todos los viajes que te han sido asignados, con su fecha, ruta, hora de salida, vehículo y la fecha en que se registró la asignación.<br>
      Utiliza los filtros de fecha para buscar solo los viajes de un período específico y así llevar un registro personal de tus traslados.<br>
      Si necesitas registrar alguna observación, puedes contactar al administrador del sistema.
    </div>
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
      <!-- PAGINACIÓN -->
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">&laquo; Anterior</a>
        <?php endif; ?>
        <?php for ($p=1; $p<=$totalPages; $p++): ?>
          <?php if ($p == $page): ?>
            <span class="current"><?= $p ?></span>
          <?php else: ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">Siguiente &raquo;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
