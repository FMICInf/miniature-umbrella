<?php
// Archivo: php/admin/manage_solicitudes.php
session_start();
require_once __DIR__ . '/../config.php';
// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

try {
    // 1) Solicitudes pendientes
    $stmt = $pdo->query("
        SELECT
            s.id,
            u.nombre AS usuario,
            r.origen,
            r.destino,
            s.fecha_solicitada,
            r.horario_salida
        FROM solicitudes s
        JOIN usuarios u ON s.usuario_id = u.id
        JOIN rutas    r ON s.ruta_id     = r.id
        WHERE s.estado = 'pendiente'
        ORDER BY s.fecha_solicitada DESC
    ");
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Conductores
    $condStmt = $pdo->query("
        SELECT id, nombre
        FROM usuarios
        WHERE rol = 'conductor'
        ORDER BY nombre
    ");
    $conductores = $condStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3) Vehículos activos
    $vehStmt = $pdo->query("
        SELECT id, patente
        FROM vehiculos
        WHERE estado = 'activo'
        ORDER BY patente
    ");
    $vehiculos = $vehStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Error BD: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin – Gestionar Solicitudes</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    .card { background:#fff; padding:1.5rem; border-radius:8px; margin:2rem auto; max-width:960px; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    table { width:100%; border-collapse:collapse; margin-top:1rem; }
    th, td { padding:.75rem; border:1px solid #ddd; text-align:left; }
    th { background:#004080; color:#fff; }
    .btn { padding:.5rem 1rem; border:none; border-radius:4px; cursor:pointer; }
    .btn-assign { background:#28a745; color:#fff; }
    .btn-reject { background:#dc3545; color:#fff; }
    .btn-export { background:#0069d9; color:#fff; float:right; }
    .form-group { margin:0; }
    .form-group select { width:100%; }
    #toast-container { position:fixed; bottom:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-top:.5rem; border-radius:4px; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Solicitudes Pendientes</h1>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_solicitudes.php" class="active">Solicitudes</a></li>
        <li><a href="manage_asignaciones.php">Asignaciones</a></li>
        <li><a href="manage_vehiculos.php">Vehículos</a></li>
        <li><a href="manage_rutas.php">Rutas</a></li>
        <li><a href="manage_users.php">Usuarios</a></li>
        <li><a href="../logout.php">Cerrar sesión</a></li>
      </ul>
    </nav>
  </header>

  <div class="card">
    <!-- Botón Exportar -->
    <a href="export_solicitudes.php" class="btn btn-export">📥 Exportar a Excel</a>

    <?php if (empty($solicitudes)): ?>
      <p>No hay solicitudes pendientes.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Ruta</th>
            <th>Fecha</th>
            <th>Horario</th>
            <th>Conductor</th>
            <th>Vehículo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($solicitudes as $s): ?>
          <tr data-id="<?= $s['id'] ?>">
            <td><?= $s['id'] ?></td>
            <td><?= htmlspecialchars($s['usuario']) ?></td>
            <td><?= htmlspecialchars("{$s['origen']} → {$s['destino']}") ?></td>
            <td><?= $s['fecha_solicitada'] ?></td>
            <td><?= $s['horario_salida'] ?></td>
            <td>
              <select class="select-driver" data-id="<?= $s['id'] ?>">
                <option value="">-- Seleccionar --</option>
                <?php foreach ($conductores as $c): ?>
                  <option value="<?= $c['id'] ?>">
                    <?= htmlspecialchars($c['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select class="select-vehicle" data-id="<?= $s['id'] ?>">
                <option value="">-- Seleccionar --</option>
                <?php foreach ($vehiculos as $v): ?>
                  <option value="<?= $v['id'] ?>">
                    <?= htmlspecialchars($v['patente']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <button class="btn btn-assign" data-id="<?= $s['id'] ?>">Asignar</button>
              <button class="btn btn-reject" data-id="<?= $s['id'] ?>">Rechazar</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div id="toast-container"></div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const showToast = msg => {
      const c = document.getElementById('toast-container');
      const t = document.createElement('div');
      t.className = 'toast';
      t.textContent = msg;
      c.appendChild(t);
      setTimeout(() => t.remove(), 3000);
    };

    // Asignar
    document.querySelectorAll('.btn-assign').forEach(btn => {
      btn.addEventListener('click', () => {
        const id       = btn.dataset.id;
        const driverId = document.querySelector(`.select-driver[data-id="${id}"]`).value;
        const vehId    = document.querySelector(`.select-vehicle[data-id="${id}"]`).value;
        if (!driverId) return showToast('Selecciona un conductor');
        if (!vehId)    return showToast('Selecciona un vehículo');
        if (!confirm(`¿Confirmar asignación de solicitud #${id}?`)) return;

        fetch('approve_solicitud.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: `id=${encodeURIComponent(id)}&conductor_id=${encodeURIComponent(driverId)}&vehiculo_id=${encodeURIComponent(vehId)}`
        })
        .then(r => r.json())
        .then(json => {
          if (json.success) {
            document.querySelector(`tr[data-id="${id}"]`).remove();
            showToast('Solicitud asignada');
          } else {
            showToast('Error: ' + json.message);
          }
        });
      });
    });

    // Rechazar
    document.querySelectorAll('.btn-reject').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        if (!confirm(`¿Rechazar solicitud #${id}?`)) return;
        fetch('reject_solicitud.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`id=${encodeURIComponent(id)}`
        })
        .then(r => r.json())
        .then(json => {
          if (json.success) {
            document.querySelector(`tr[data-id="${id}"]`).remove();
            showToast('Solicitud rechazada');
          } else {
            showToast('Error: ' + json.message);
          }
        });
      });
    });
  });
  </script>
</body>
</html>
