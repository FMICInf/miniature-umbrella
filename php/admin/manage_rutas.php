<?php
// Archivo: php/admin/manage_rutas.php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

try {
    // Traer la última fecha de solicitud por ruta
    $stmt = $pdo->query("
        SELECT
            r.id,
            r.origen,
            r.destino,
            r.horario_salida,
            r.horario_llegada,
            r.creado_at,
            MAX(s.fecha_solicitada) AS ultima_solicitud
        FROM rutas r
        LEFT JOIN solicitudes s ON s.ruta_id = r.id
        GROUP BY r.id
        ORDER BY r.creado_at DESC
    ");
    $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error BD: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin – Gestionar Rutas</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    /* Modal base */
    .modal { position:fixed; top:0; left:0; width:100%; height:100%;
             background:rgba(0,0,0,0.5); display:none;
             align-items:center; justify-content:center; }
    .modal.active { display:flex; }
    .modal-content { background:#fff; padding:2rem; border-radius:8px;
                     width:90%; max-width:500px; position:relative; }
    .modal-close { position:absolute; top:.5rem; right:.5rem;
                   background:none; border:none; font-size:1.5rem;
                   cursor:pointer; }
    .modal-content label { display:block; margin-bottom:1rem; }
    .modal-content input[type=time] { width:100%; padding:.5rem; }
    .modal-actions { text-align:right; margin-top:1rem; }
    /* Toast container */
    #toast-container { position:fixed; bottom:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-top:.5rem; border-radius:4px; }

    /* Quitar scroll horizontal */
    body, .container {
      overflow-x: hidden !important;
    }
    /* Ajustar tabla al ancho del contenedor */
    .container table {
      width: 100%;
      table-layout: auto;
      word-break: break-word;
    }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Gestionar Rutas</h1>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_solicitudes.php">Solicitudes</a></li>
        <li><a href="manage_asignaciones.php">Asignaciones</a></li>
        <li><a href="manage_vehiculos.php">Vehículos</a></li>
        <li><a href="manage_rutas.php" class="active">Rutas</a></li>
        <li><a href="manage_users.php">Usuarios</a></li>
        <li><a href="../logout.php">Cerrar sesión</a></li>
      </ul>
    </nav>
  </header>

  <main class="container">
    <section class="card">
      <button id="btn-add-route" class="btn">+ Agregar Ruta</button>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Salida</th>
            <th>Llegada</th>
            <th>Creado En</th>
            <th>Última Solicitud</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rutas as $r): ?>
          <tr data-id="<?= $r['id'] ?>">
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['origen']) ?></td>
            <td><?= htmlspecialchars($r['destino']) ?></td>
            <td><?= $r['horario_salida'] ?></td>
            <td><?= $r['horario_llegada'] ?></td>
            <td><?= $r['creado_at'] ?></td>
            <td><?= $r['ultima_solicitud'] ?: '-' ?></td>
            <td>
              <button class="btn btn-edit-route" data-id="<?= $r['id'] ?>">Editar</button>
              <button class="btn btn-delete-route" data-id="<?= $r['id'] ?>">Eliminar</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Modal para agregar/editar ruta -->
  <div id="routeModal" class="modal hidden">
    <form id="routeForm" class="modal-content">
      <button type="button" class="modal-close">&times;</button>
      <h2 id="routeModalTitle">Agregar Ruta</h2>
      <label>Origen:<input name="origen" required></label>
      <label>Destino:<input name="destino" required></label>
      <label>Salida:<input name="horario_salida" type="time" required></label>
      <label>Llegada:<input name="horario_llegada" type="time"></label>
      <input type="hidden" name="id">
      <div class="modal-actions">
        <button type="submit" class="btn">Guardar</button>
        <button type="button" id="routeCancel" class="btn btn-cancel">Cancelar</button>
      </div>
    </form>
  </div>

  <div id="toast-container"></div>
  <script src="../../assets/js/manage_rutas.js"></script>
</body>
</html>
