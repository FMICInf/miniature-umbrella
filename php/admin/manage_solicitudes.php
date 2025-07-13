<?php
// Archivo: php/admin/manage_solicitudes.php
session_start();
require_once __DIR__ . '/../config.php';
// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Parámetros para filtro y paginación
$estadoFiltro = isset($_GET['estado']) ? $_GET['estado'] : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    // Si se selecciona un estado específico, filtra por él.
    if ($estadoFiltro && in_array($estadoFiltro, ['pendiente', 'confirmada', 'cancelada', 'rechazada'])) {
        $whereFiltro = "WHERE s.estado = :estado";
        $params = [':estado' => $estadoFiltro];
    } else {
        // Si no, muestra solo pendientes y confirmadas sin asignar (lógica original)
        $whereFiltro = "WHERE (
            s.estado = 'pendiente'
            OR 
            (s.estado = 'confirmada' AND NOT EXISTS (
                SELECT 1 FROM asignaciones a WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada
            ))
        )";
        $params = [];
    }

    // Contar total para paginación
    $countSql = "SELECT COUNT(*) FROM solicitudes s $whereFiltro";
    $countStmt = $pdo->prepare($countSql);
    if (isset($params[':estado'])) {
        $countStmt->bindValue(':estado', $params[':estado'], PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalSolicitudes = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalSolicitudes / $perPage));

    // Obtener solicitudes con límite y offset
    $sql = "
      SELECT 
        s.id,
        u.nombre AS usuario,
        u.email AS usuario_email,
        s.departamento,
        s.carrera,
        s.carrera_otro,
        s.fecha_solicitada,
        s.horario_salida,
        s.hora_regreso,
        s.cantidad_pasajeros,
        s.motivo,
        s.motivo_otro,
        s.adjunto,
        s.estado,
        s.motivo_rechazo,
        r.origen,
        r.destino,
        (SELECT v.patente FROM asignaciones a JOIN vehiculos v ON v.id=a.vehiculo_id WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada LIMIT 1) AS vehiculo_patente,
        (SELECT v.marca FROM asignaciones a JOIN vehiculos v ON v.id=a.vehiculo_id WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada LIMIT 1) AS vehiculo_marca,
        (SELECT v.modelo FROM asignaciones a JOIN vehiculos v ON v.id=a.vehiculo_id WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada LIMIT 1) AS vehiculo_modelo,
        (SELECT v.anio FROM asignaciones a JOIN vehiculos v ON v.id=a.vehiculo_id WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada LIMIT 1) AS vehiculo_anio,
        (SELECT v.id FROM asignaciones a JOIN vehiculos v ON v.id=a.vehiculo_id WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada LIMIT 1) AS vehiculo_id,
        (SELECT c.id FROM asignaciones a JOIN conductores c ON c.usuario_id=a.conductor_id WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada LIMIT 1) AS conductor_id,
        (SELECT cu.nombre FROM asignaciones a JOIN usuarios cu ON cu.id=a.conductor_id WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada LIMIT 1) AS chofer_nombre,
        (SELECT cu.email FROM asignaciones a JOIN usuarios cu ON cu.id=a.conductor_id WHERE a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada LIMIT 1) AS chofer_email
      FROM solicitudes s
      JOIN usuarios u ON s.usuario_id = u.id
      JOIN rutas r ON s.ruta_id = r.id
      $whereFiltro
      ORDER BY s.creado_at DESC
      LIMIT :perPage OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    if (isset($params[':estado'])) {
        $stmt->bindValue(':estado', $params[':estado'], PDO::PARAM_STR);
    }
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conductores
    $condStmt = $pdo->query("
        SELECT id, nombre, email
        FROM usuarios
        WHERE rol = 'conductor'
        ORDER BY nombre
    ");
    $conductores = $condStmt->fetchAll(PDO::FETCH_ASSOC);

    // Vehículos activos
    $vehStmt = $pdo->query("
        SELECT id, patente, marca, modelo, anio
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin – Gestionar Solicitudes</title>
  <link rel="stylesheet" href="../../assets/css/style.css" />
  <style>
    body {background: #f5f5f5;margin: 0;font-family: sans-serif;}
    .container {max-width: 1200px;margin: 2rem auto;padding: 0 1rem;}
    .card {background: #fff;border-radius: 8px;box-shadow: 0 2px 8px rgba(0,0,0,0.05);padding: 1.5rem;margin-bottom: 2rem;width: 100%;box-sizing: border-box;border: 1px solid #e0e0e0;}
    .btn-export {background: #0069d9;color: #fff;padding: .5rem 1rem;border: none;border-radius: 4px;cursor: pointer;text-decoration: none;display: inline-block;font-size: 0.9rem;float: right;}
    .table-wrapper {width: 100%;overflow-x: auto;margin-top: 0.5rem;}
    table {width: 100%;border-collapse: collapse;min-width: 1000px;}
    thead th {padding: .75rem;border: 1px solid #ddd;background: #004080;color: #fff;text-align: left;white-space: nowrap;}
    tbody td {padding: .75rem;border: 1px solid #ddd;text-align: left;vertical-align: middle;white-space: nowrap;}
    tbody tr:nth-child(even) {background-color: #f9f9f9;}
    tbody tr:hover {background-color: #eef5fb;}
    .btn {padding: .5rem 1rem;border: none;border-radius: 4px;cursor: pointer;font-size: 0.9rem;}
    .btn-assign {background: #28a745;color: #fff;}
    .btn-reject {background: #dc3545;color: #fff;}
    #toast-container {position: fixed;bottom: 1rem;right: 1rem;z-index: 1000;}
    .toast {background: #333;color: #fff;padding: 1rem;margin-top: .5rem;border-radius: 4px;font-size: 0.9rem;}
    /* Paginación */
    .pagination {
      margin-top: 1rem;
      text-align: center;
    }
    .pagination a, .pagination span {
      display: inline-block;
      margin: 0 6px;
      padding: 6px 12px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: 600;
      color: #004080;
      border: 1px solid #004080;
      cursor: pointer;
      transition: background 0.2s;
    }
    .pagination a:hover {
      background: #004080;
      color: white;
    }
    .pagination .current {
      background: #004080;
      color: white;
      cursor: default;
      border-color: #004080;
    }
    /* Botón ayuda */
    #btn-ayuda {
      margin-left: 1rem; background: #004080; color: #fff;
      border: none; border-radius: 50%; width: 38px; height: 38px;
      font-size: 1.3rem; cursor: pointer; display: inline-flex;
      align-items: center; justify-content: center;
      transition: background 0.2s;
    }
    #btn-ayuda:hover { background: #0069d9; }
    /* Modal ayuda */
    #modal-ayuda {
      display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.5);
    }
    #modal-ayuda .modal-content {
      background: #fff; border-radius: 12px; max-width: 530px; margin: 70px auto; position: relative; padding: 0; box-shadow: 0 8px 32px #0004;
    }
    #modal-ayuda .cerrar-modal {
      position: absolute; right: 10px; top: 7px; border: none; background: none; font-size: 2rem; color: #888; cursor: pointer;
    }
    #carrusel-ayuda {
      width: 500px; max-width: 90vw; padding: 30px 30px 10px 30px; display: flex; flex-direction: column; align-items: center;
    }
    .slide-ayuda img { max-width: 420px; border-radius:10px; }
    @media (max-width: 600px){
      #carrusel-ayuda { width:100vw;padding:15px 3vw;}
      .slide-ayuda img{max-width:85vw;}
    }
  </style>
</head>
<body>
  <header class="header-inner" style="display:flex;align-items:center;justify-content:space-between;">
    <div style="display: flex; align-items: center;">
      <h1 style="margin-right: 8px;">Gestionar Solicitudes</h1>
      <button id="btn-ayuda" title="Ayuda">?</button>
    </div>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Volver</a></li>
      </ul>
    </nav>
  </header>

  <div class="card">
    <!-- Filtro por estado -->
    <form method="GET" style="margin-bottom: 1rem;">
      <label for="estadoFiltro">Filtrar por estado: </label>
      <select name="estado" id="estadoFiltro" onchange="this.form.submit()">
        <option value="" <?= $estadoFiltro === '' ? 'selected' : '' ?>>Todos</option>
        <option value="pendiente" <?= $estadoFiltro === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
        <option value="confirmada" <?= $estadoFiltro === 'confirmada' ? 'selected' : '' ?>>Confirmada</option>
        <option value="cancelada" <?= $estadoFiltro === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
      </select>
    </form>

    <!-- Botón Exportar -->
    <a href="export_solicitudes.php" class="btn btn-export"> Exportar a csv</a>

    <?php if (empty($solicitudes)): ?>
      <p>No hay solicitudes registradas.</p>
    <?php else: ?>
      <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Correo Usuario</th>
            <th>Departamento</th>
            <th>Carrera</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Fecha</th>
            <th>Salida</th>
            <th>Regreso</th>
            <th>Cant. Pasajeros</th>
            <th>Vehículo</th>
            <th>Chofer</th>
            <th>Motivo</th>
            <th>Adjunto</th>
            <th>Estado</th>
            <th>Motivo Rechazo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($solicitudes as $s): ?>
          <tr data-id="<?= $s['id'] ?>">
            <td><?= $s['id'] ?></td>
            <td><?= htmlspecialchars($s['usuario']) ?></td>
            <td><?= htmlspecialchars($s['usuario_email']) ?></td>
            <td><?= htmlspecialchars($s['departamento']) ?></td>
            <td>
              <?php
                if (isset($s['carrera']) && $s['carrera'] === 'Otro' && !empty($s['carrera_otro'])) {
                    echo htmlspecialchars($s['carrera_otro']);
                } elseif (empty($s['carrera']) && !empty($s['carrera_otro'])) {
                    echo htmlspecialchars($s['carrera_otro']);
                } else {
                    echo htmlspecialchars($s['carrera']);
                }
              ?>
            </td>
            <td><?= htmlspecialchars($s['origen']) ?></td>
            <td><?= htmlspecialchars($s['destino']) ?></td>
            <td><?= htmlspecialchars($s['fecha_solicitada']) ?></td>
            <td><?= htmlspecialchars($s['horario_salida']) ?></td>
            <td><?= $s['hora_regreso'] ?: '-' ?></td>
            <td><?= htmlspecialchars($s['cantidad_pasajeros']) ?></td>
            <td>
              <?php if($s['vehiculo_patente']): ?>
                <?= htmlspecialchars($s['vehiculo_patente']) ?><br>
                <small><?= htmlspecialchars($s['vehiculo_marca']) ?>, <?= htmlspecialchars($s['vehiculo_modelo']) ?>, <?= htmlspecialchars($s['vehiculo_anio']) ?></small>
              <?php else: ?>
                <!-- Selector si NO asignado -->
                <select class="select-vehicle" data-id="<?= $s['id'] ?>">
                  <option value="">-- Seleccionar --</option>
                  <?php foreach ($vehiculos as $v): ?>
                    <option value="<?= $v['id'] ?>">
                      <?= htmlspecialchars($v['patente']) ?> (<?= htmlspecialchars($v['marca']) ?> <?= htmlspecialchars($v['modelo']) ?> <?= htmlspecialchars($v['anio']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </td>
            <td>
              <?php if($s['chofer_nombre']): ?>
                <?= htmlspecialchars($s['chofer_nombre']) ?><br>
                <small><?= htmlspecialchars($s['chofer_email']) ?></small>
              <?php else: ?>
                <select class="select-driver" data-id="<?= $s['id'] ?>">
                  <option value="">-- Seleccionar --</option>
                  <?php foreach ($conductores as $c): ?>
                    <option value="<?= $c['id'] ?>">
                      <?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['email']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </td>
            <td>
              <?= $s['motivo']==='Otro'
                 ? htmlspecialchars($s['motivo_otro'])
                 : htmlspecialchars($s['motivo']) ?>
            </td>
            <td>
              <?php if($s['adjunto']):?>
                <a href="../<?=htmlspecialchars($s['adjunto'])?>" target="_blank">Ver</a>
              <?php else:?>-<?php endif;?>
            </td>
            <td><span class="badge badge-<?=$s['estado']?>"><?=ucfirst($s['estado'])?></span></td>
            <td><?= htmlspecialchars($s['motivo_rechazo']) ?: '-' ?></td>
            <td>
              <?php if($s['estado'] === 'pendiente'): ?>
                <?php if(!$s['vehiculo_patente'] && !$s['chofer_nombre']): ?>
                  <button class="btn btn-assign" data-id="<?= $s['id'] ?>">Asignar</button>
                <?php endif; ?>
                <button class="btn btn-reject" data-id="<?= $s['id'] ?>">Rechazar</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <!-- Paginación -->
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?estado=<?= urlencode($estadoFiltro) ?>&page=<?= $page - 1 ?>">&laquo; Anterior</a>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php if ($p == $page): ?>
            <span class="current"><?= $p ?></span>
          <?php else: ?>
            <a href="?estado=<?= urlencode($estadoFiltro) ?>&page=<?= $p ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?estado=<?= urlencode($estadoFiltro) ?>&page=<?= $page + 1 ?>">Siguiente &raquo;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

<!-- MODAL DE AYUDA -->
<div id="modal-ayuda">
  <div class="modal-content">
    <button onclick="cerrarAyuda()" class="cerrar-modal">&times;</button>
    <div id="carrusel-ayuda">
      <!-- Slide 1: Gestión de Solicitudes -->
      <div id="slide-ayuda-0" class="slide-ayuda" style="display:block;">
        <img src="../../assets/gifs/ADMIN_GESTIONAR_SOLICITUD.gif" alt="Gestión de solicitudes GIF">
        <p style="margin: 15px 0 3px 0; font-size: 1rem; font-weight: bold;">
          Pantalla principal: Gestión de Solicitudes
        </p>
        <div style="margin-bottom:8px; color:#222;">
          En esta pantalla puedes ver el listado completo de solicitudes de transporte. Puedes filtrar por estado, revisar los detalles de cada solicitud, asignar vehículos y conductores, y exportar la información a un archivo <b>.csv</b> para llevar un registro externo.
        </div>
      </div>
      <!-- Slide 2: Rechazar Solicitud -->
      <div id="slide-ayuda-1" class="slide-ayuda" style="display:none;">
        <img src="../../assets/gifs/ADMIN_RECHAZAR_SOLICITUD.gif" alt="Rechazar solicitud GIF">
        <p style="margin: 15px 0 3px 0; font-size: 1rem; font-weight: bold;">
          Cómo rechazar una solicitud
        </p>
        <div style="margin-bottom:8px; color:#222;">
          Si alguna solicitud no cumple con los criterios, puedes rechazarla presionando el botón <b>Rechazar</b>. El sistema te pedirá ingresar el motivo del rechazo, el cual será notificado al usuario solicitante.
        </div>
      </div>
      <!-- Slide 3: Notificación de Solicitud -->
      <div id="slide-ayuda-2" class="slide-ayuda" style="display:none;">
        <img src="../../assets/gifs/NOTIFICACIÓN_SOLICITUD.png" alt="Notificación solicitud">
        <p style="margin: 15px 0 3px 0; font-size: 1rem; font-weight: bold;">
          Notificación de nueva solicitud
        </p>
        <div style="margin-bottom:8px; color:#222;">
          Cuando se genera una nueva solicitud, verás una notificación en el panel de “Ver Solicitudes”. Así puedes estar al tanto de nuevas solicitudes pendientes por revisar o gestionar.
        </div>
      </div>
      <div style="margin-top:12px; margin-bottom: 2px;">
        <button onclick="cambiarSlideAyuda(-1)" style="background:#eee; border:none; padding:4px 14px; margin-right:6px; border-radius:5px; font-size:1.1rem; cursor:pointer;">&#8592;</button>
        <button onclick="cambiarSlideAyuda(1)" style="background:#eee; border:none; padding:4px 14px; border-radius:5px; font-size:1.1rem; cursor:pointer;">&#8594;</button>
      </div>
      <div style="color:#0069d9; margin: 8px 0 10px 0; font-size: 1rem; font-weight: 500; border-top: 1px solid #eee; padding-top: 10px;">
        Nota: Se pueden exportar todos los datos que se muestran en la tabla en formato <b>.csv</b>
      </div>
    </div>
  </div>
</div>

  <div id="toast-container"></div>

  <script>
  // Modal/carrusel ayuda
  let idxAyuda = 0;
  function mostrarAyuda() {
    document.getElementById('modal-ayuda').style.display = 'block';
    mostrarSlideAyuda(idxAyuda);
  }
  function cerrarAyuda() {
    document.getElementById('modal-ayuda').style.display = 'none';
  }
  function cambiarSlideAyuda(dir) {
    idxAyuda = (idxAyuda + dir + 3) % 3;
    mostrarSlideAyuda(idxAyuda);
  }
  function mostrarSlideAyuda(idx) {
    for (let i = 0; i < 3; i++)
      document.getElementById('slide-ayuda-' + i).style.display = (i === idx) ? 'block' : 'none';
  }
  document.getElementById('btn-ayuda').onclick = mostrarAyuda;

  // Toasts y funcionalidad solicitudes
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
        const driverId = document.querySelector(`.select-driver[data-id="${id}"]`)?.value;
        const vehId    = document.querySelector(`.select-vehicle[data-id="${id}"]`)?.value;
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
        let motivo = prompt('Indica el motivo de rechazo:');
        if (motivo === null) return;
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
