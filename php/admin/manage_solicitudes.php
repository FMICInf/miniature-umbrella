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
    // 1) Solicitudes pendientes, incluyendo departamento y carrera/carrera_otro
    $stmt = $pdo->query("
        SELECT
            s.id,
            u.nombre AS usuario,
            s.departamento,
            s.carrera,
            s.carrera_otro,
            r.origen,
            r.destino,
            s.fecha_solicitada,
            s.horario_salida
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
  /* Fondo de página */
  body {
    background: #f5f5f5;
    margin: 0;
    font-family: sans-serif;
  }
  /* Contenedor principal centrado */
  .container {
    max-width: 1200px;  /* aumentar si la tabla es muy ancha */
    margin: 2rem auto;
    padding: 0 1rem;
  }
  /* Tarjeta que envuelve contenido */
  .card {
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    padding: 1.5rem;
    margin-bottom: 2rem;
    /* Relajar el max-width o eliminar si quieres que sea más ancha */
    /* max-width: 960px; */
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e0e0e0;
  }
  /* Contenedor para el botón Exportar, alineado a la derecha dentro de .card */
  .export-container {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.75rem;
  }
  .btn-export {
    background: #0069d9;
    color: #fff;
    padding: .5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    /* ya no float */
    text-decoration: none;
    display: inline-block;
    font-size: 0.9rem;
  }
  /* Wrapper para la tabla: permite scroll horizontal en pantallas estrechas o tablas anchas */
  .table-wrapper {
    width: 100%;
    overflow-x: auto;
    /* margen entre export y tabla */
    margin-top: 0.5rem;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px; /* si tu tabla tiene muchas columnas, ajusta este mínimo */
  }
  thead th {
    padding: .75rem;
    border: 1px solid #ddd;
    background: #004080;
    color: #fff;
    text-align: left;
    white-space: nowrap;
  }
  tbody td {
    padding: .75rem;
    border: 1px solid #ddd;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
  }
  tbody tr:nth-child(even) {
    background-color: #f9f9f9;
  }
  tbody tr:hover {
    background-color: #eef5fb;
  }
  /* Botones dentro de la tabla */
  .btn {
    padding: .5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
  }
  .btn-assign {
    background: #28a745;
    color: #fff;
  }
  .btn-reject {
    background: #dc3545;
    color: #fff;
  }
  /* Paginación centrada */
  .pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 1rem 0 0 0;
    justify-content: center;
  }
  .pagination li {
    margin: 0 .25rem;
  }
  .pagination a,
  .pagination span {
    padding: .25rem .5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    text-decoration: none;
    color: #004080;
    font-size: 0.9rem;
  }
  .pagination .current {
    background: #004080;
    color: #fff;
    border-color: #004080;
  }
  /* Toasts */
  #toast-container {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    z-index: 1000;
  }
  .toast {
    background: #333;
    color: #fff;
    padding: 1rem;
    margin-top: .5rem;
    border-radius: 4px;
    font-size: 0.9rem;
  }
</style>

</head>
<body>
  <header class="header-inner">
    <h1>Solicitudes Pendientes</h1>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Volver</a></li>

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
            <th>Departamento</th>
            <th>Carrera</th>
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
            <!-- Mostrar Departamento -->
            <td><?= htmlspecialchars($s['departamento']) ?></td>
            <!-- Mostrar Carrera o carrera_otro si corresponde -->
            <td>
              <?php
                // Ajusta la lógica según cómo guardes en BD:
                // Si guardas cadena 'Otro' en s['carrera']:
                if (isset($s['carrera']) && $s['carrera'] === 'Otro' && !empty($s['carrera_otro'])) {
                    echo htmlspecialchars($s['carrera_otro']);
                }
                // Si guardas NULL o vacío en s['carrera'] cuando es otro, usa:
                elseif (empty($s['carrera']) && !empty($s['carrera_otro'])) {
                    echo htmlspecialchars($s['carrera_otro']);
                }
                else {
                    echo htmlspecialchars($s['carrera']);
                }
              ?>
            </td>
            <td><?= htmlspecialchars("{$s['origen']} → {$s['destino']}") ?></td>
            <td><?= htmlspecialchars($s['fecha_solicitada']) ?></td>
            <td><?= htmlspecialchars($s['horario_salida']) ?></td>
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
        // Aquí podrías abrir un prompt/modal para que el admin escriba "motivo_rechazo"
        // Por simplicidad, asumimos que reject_solicitud.php pedirá un motivo fijo o un campo adicional.
        // Por ejemplo: prompt para motivo:
        let motivo = prompt('Indica el motivo de rechazo:');
        if (motivo === null) {
          // cancelado por admin
          return;
        }
        motivo = motivo.trim();
        if (motivo === '') {
          alert('El motivo no puede estar vacío.');
          return;
        }
        fetch('reject_solicitud.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: `id=${encodeURIComponent(id)}&motivo_rechazo=${encodeURIComponent(motivo)}`
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
