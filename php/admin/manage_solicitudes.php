<?php
session_start();
require_once __DIR__ . '/../config.php';
// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php'); exit;
}

try {
    // Obtener solicitudes pendientes
    $stmt = $pdo->query(
        "SELECT s.id, u.nombre AS usuario, r.id AS ruta_id, r.origen, r.destino,
                s.fecha_solicitada, r.horario_salida
         FROM solicitudes s
         JOIN usuarios u ON s.usuario_id = u.id
         JOIN rutas    r ON s.ruta_id     = r.id
         WHERE s.estado = 'pendiente'
         ORDER BY s.fecha_solicitada DESC"
    );
    $solicitudes = $stmt->fetchAll();
    // Obtener lista de conductores
    $condStmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol = 'conductor' ORDER BY nombre");
    $conductores = $condStmt->fetchAll();
    // Obtener lista de vehículos disponibles
    $vehStmt = $pdo->query("SELECT id, patente FROM vehiculos WHERE disponibilidad = 'disponible'");
    $vehiculos = $vehStmt->fetchAll();
} catch (PDOException $e) {
    die('Error BD: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin – Asignar Conductor y Vehículo</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <header class="header-inner">
    <h1>Asignar Conductor y Vehículo</h1>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_solicitudes.php" class="active">Solicitudes</a></li>
        <li><a href="manage_asignaciones.php">Asignaciones</a></li>
        <li><a href="../logout.php">Cerrar sesión</a></li>
      </ul>
    </nav>
  </header>
  <main class="container">
    <section class="card">
      <?php if (empty($solicitudes)): ?>
        <p>No hay solicitudes pendientes.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Usuario</th><th>Ruta</th><th>Fecha</th><th>Horario</th>
              <th>Conductor</th><th>Vehículo</th><th>Acciones</th>
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
                  <option value="">-- Conductor --</option>
                  <?php foreach ($conductores as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select class="select-vehicle" data-id="<?= $s['id'] ?>">
                  <option value="">-- Vehículo --</option>
                  <?php foreach ($vehiculos as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['patente']) ?></option>
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
    </section>
  </main>
  <div id="toast" class="toast-container"></div>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const showToast = msg => {
        const c = document.getElementById('toast');
        const t = document.createElement('div'); t.className = 'toast'; t.textContent = msg;
        c.appendChild(t); setTimeout(() => c.removeChild(t), 3000);
      };
      document.querySelectorAll('.btn-assign').forEach(btn => btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const driverId = document.querySelector(`.select-driver[data-id="${id}"]`).value;
        const vehId    = document.querySelector(`.select-vehicle[data-id="${id}"]`).value;
        if (!driverId) return showToast('Selecciona un conductor');
        if (!vehId)    return showToast('Selecciona un vehículo');
        if (!confirm(`Asignar solicitud ${id}?`)) return;
        fetch('approve_solicitud.php', {
          method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: `id=${encodeURIComponent(id)}&conductor_id=${driverId}&vehiculo_id=${vehId}`
        })
        .then(r => r.json()).then(json => {
          if (json.success) {
            document.querySelector(`tr[data-id="${id}"]`).remove();
            showToast('Asignación creada');
          } else showToast('Error: '+json.message);
        });
      }));
      document.querySelectorAll('.btn-reject').forEach(btn => btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        if (!confirm(`Rechazar solicitud ${id}?`)) return;
        fetch('reject_solicitud.php', {
          method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:`id=${encodeURIComponent(id)}`
        })
        .then(r => r.json()).then(json => {
          if (json.success) {
            document.querySelector(`tr[data-id="${id}"]`).remove();
            showToast('Solicitud rechazada');
          } else showToast('Error: '+json.message);
        });
      }));
    });
  </script>
</body>
</html>