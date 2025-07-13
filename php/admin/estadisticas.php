<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config.php';

$filtroEstado = $_GET['estado'] ?? '';

// Totales por estado con filtro
$sqlTotales = "SELECT estado, COUNT(*) AS total FROM solicitudes";
$paramsTot = [];
if ($filtroEstado) {
    $sqlTotales .= " WHERE estado = :estado";
    $paramsTot['estado'] = $filtroEstado;
}
$sqlTotales .= " GROUP BY estado";
$stmt = $pdo->prepare($sqlTotales);
$stmt->execute($paramsTot);
$totales = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalSolicitudes = array_sum($totales);
$pendientes       = $totales['pendiente']   ?? 0;
$confirmadas      = $totales['confirmada']  ?? 0;
$canceladas       = $totales['cancelada']   ?? 0;

// Top carreras
$topCarreras = $pdo->query("
  SELECT
    IF(carrera='Otro' OR carrera = '' OR carrera IS NULL, carrera_otro, carrera) AS carrera,
    COUNT(*) AS total
  FROM solicitudes
  GROUP BY IF(carrera='Otro' OR carrera = '' OR carrera IS NULL, carrera_otro, carrera)
  ORDER BY total DESC
  LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Top destinos
$topDestinos = $pdo->query("
  SELECT r.destino, COUNT(*) AS total
  FROM solicitudes s
  JOIN rutas r ON s.ruta_id = r.id
  GROUP BY r.destino
  ORDER BY total DESC
  LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// --- Top rutas por KM recorridos (robusto, sin problemas de GROUP BY) ---
$topRutasKm = $pdo->query("
  SELECT ruta, SUM(km_estimados) AS km_totales_estimados FROM (
      SELECT 
        CONCAT(r.origen, ' → ', r.destino) AS ruta,
        IF(
          r.lat_origen IS NOT NULL AND r.lng_origen IS NOT NULL AND 
          r.lat_destino IS NOT NULL AND r.lng_destino IS NOT NULL,
          (111.32 * SQRT(POW(r.lat_destino - r.lat_origen, 2) + POW((r.lng_destino - r.lng_origen)*COS(RADIANS((r.lat_origen+r.lat_destino)/2)), 2))),
          0
        ) AS km_estimados
      FROM solicitudes s
      JOIN rutas r ON s.ruta_id = r.id
      WHERE s.estado = 'confirmada'
    ) t
    GROUP BY ruta
    ORDER BY km_totales_estimados DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$labelsRutasKm = array_column($topRutasKm, 'ruta');
$dataRutasKm   = array_map('floatval', array_column($topRutasKm, 'km_totales_estimados'));

// Últimas solicitudes
$sqlUlt = "
  SELECT s.id, u.nombre AS usuario, s.departamento,
         IF(s.carrera='Otro', s.carrera_otro, s.carrera) AS carrera,
         r.origen, r.destino,
         s.fecha_solicitada, s.horario_salida,
         s.cantidad_pasajeros, s.estado
  FROM solicitudes s
  JOIN usuarios u ON s.usuario_id = u.id
  JOIN rutas   r ON s.ruta_id     = r.id
";
$paramsUlt = [];
if ($filtroEstado) {
    $sqlUlt .= " WHERE s.estado = :estado";
    $paramsUlt['estado'] = $filtroEstado;
}
$sqlUlt .= " ORDER BY s.creado_at DESC LIMIT 8";
$stmt2 = $pdo->prepare($sqlUlt);
$stmt2->execute($paramsUlt);
$ultSolicitudes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Usuarios recientes
$ultUsuarios = $pdo->query("
  SELECT id, nombre, email, rol, creado_at
  FROM usuarios
  ORDER BY creado_at DESC
  LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Vehículos recientes
$ultVehiculos = $pdo->query("
  SELECT id, patente, marca, modelo, anio, capacidad, estado, creado_at
  FROM vehiculos
  ORDER BY creado_at DESC
  LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard de Estadísticas</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background:#f6f8fa; font-family:'Segoe UI',Arial,sans-serif; margin:0; }
    .main-header { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:1.3rem 2rem; border-radius:0 0 14px 14px; box-shadow:0 3px 16px #20408014; margin-bottom:1.8rem; }
    .main-header h1 { font-size:1.7rem; font-weight:800; color:#143468; margin:0; }
    .header-btn { background:#1852a0; color:#fff; font-weight:600; padding:0.5em 1em; border:none; border-radius:7px; text-decoration:none; transition:background .2s; }
    .header-btn:hover { background:#123463; }
    .container { max-width:1220px; margin:0 auto; background:#fff; border-radius:0 0 14px 14px; padding:2rem; box-shadow:0 4px 32px #0001; }
    .metrics { display:flex; gap:1rem; margin-bottom:2rem; }
    .metric-card { flex:1; background:#eaf2fb; border-radius:10px; padding:1rem; text-align:center; box-shadow:0 2px 8px #abc1e81a; }
    .metric-card span { display:block; font-size:2rem; font-weight:800; color:#204080; }
    .metric-card small { font-size:1rem; color:#305084; }
    .filters { margin-bottom:1rem; }
    .filters label { font-weight:600; margin-right:0.5em; }
    .filters select { padding:0.3em; }
    .row { display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:2rem; }
    .panel { flex:1 1 300px; background:#f8fbfd; padding:1rem; border-radius:10px; box-shadow:0 2px 12px #abc1e81a; }
    .panel-title { font-weight:600; margin-bottom:0.5em; color:#2140aa; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:0.5em; border:1px solid #e3e7ee; text-align:left; }
    th { background:#204080; color:#fff; }
    tbody tr:nth-child(even) { background:#f2f6fa; }
    .status-badge { padding:0.3em 0.6em; border-radius:5px; font-size:0.9em; font-weight:600; }
    .pendiente { background:#ffc10722; color:#946200; }
    .confirmada { background:#28a74522; color:#216c2a; }
    .cancelada { background:#dc354522; color:#942f2f; }
    @media (max-width: 900px) {
      .row { flex-direction: column; }
    }
    /* Ayuda */
    #btn-ayuda-estadisticas {
      margin-left: 1rem; background: #004080; color: #fff;
      border: none; border-radius: 50%; width: 38px; height: 38px;
      font-size: 1.3rem; cursor: pointer; display: inline-flex;
      align-items: center; justify-content: center;
      transition: background 0.2s;
    }
    #btn-ayuda-estadisticas:hover { background: #0069d9; }
    #modal-ayuda-estadisticas {
      display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.5);
    }
    #modal-ayuda-estadisticas .modal-content {
      background: #fff; border-radius: 12px; max-width: 540px; margin: 70px auto; position: relative; box-shadow: 0 8px 32px #0004;
    }
    #modal-ayuda-estadisticas .cerrar-modal {
      position: absolute; right: 10px; top: 7px; border: none; background: none; font-size: 2rem; color: #888; cursor: pointer;
    }
    @media (max-width: 600px){
      #modal-ayuda-estadisticas .modal-content{width:95vw;}
    }
  </style>
</head>
<body>
  <div class="main-header">
    <div style="display:flex;align-items:center;">
      <h1>Dashboard de Estadísticas</h1>
      <button id="btn-ayuda-estadisticas" title="Ayuda">?</button>
    </div>
    <a href="../admin_dashboard.php" class="header-btn">Volver</a>
  </div>
  <div class="container">
    <div class="metrics">
      <div class="metric-card"><span><?= $totalSolicitudes ?></span><small>Total Solicitudes</small></div>
      <div class="metric-card"><span><?= $pendientes ?></span><small>Pendientes</small></div>
      <div class="metric-card"><span><?= $confirmadas ?></span><small>Confirmadas</small></div>
      <div class="metric-card"><span><?= $canceladas ?></span><small>Canceladas</small></div>
    </div>
    <form method="get" class="filters">
      <label for="estado_filtro">Filtrar por estado:</label>
      <select id="estado_filtro" name="estado" onchange="this.form.submit()">
        <option value="" <?= $filtroEstado===''?'selected':'' ?>>Todos</option>
        <option value="pendiente" <?= $filtroEstado==='pendiente'?'selected':'' ?>>Pendiente</option>
        <option value="confirmada" <?= $filtroEstado==='confirmada'?'selected':'' ?>>Confirmada</option>
        <option value="cancelada" <?= $filtroEstado==='cancelada'?'selected':'' ?>>Cancelada</option>
      </select>
    </form>
    <div class="row">
      <div class="panel"><div class="panel-title">Distribución de Estados</div><canvas id="chartEstados" height="150"></canvas></div>
      <div class="panel"><div class="panel-title">Top Carreras</div><canvas id="chartCarreras" height="150"></canvas></div>
    </div>
    <div class="row">
      <div class="panel"><div class="panel-title">Top Destinos</div><canvas id="chartDestinos" height="150"></canvas></div>
    </div>
    <div class="row">
      <div class="panel">
        <div class="panel-title">Rutas con más KM recorridos (estimado)</div>
        <canvas id="chartRutasKm" height="200"></canvas>
      </div>
    </div>
    <div class="row">
      <div class="panel" style="flex:2;">
        <div class="panel-title">Últimas Solicitudes<?= $filtroEstado? " ({$filtroEstado})": '' ?></div>
        <table>
          <thead><tr><th>ID</th><th>Usuario</th><th>Depto</th><th>Carrera</th><th>Origen</th><th>Destino</th><th>Fecha</th><th>Hora</th><th>Pasajeros</th><th>Estado</th></tr></thead>
          <tbody>
            <?php foreach($ultSolicitudes as $s): ?>
            <tr>
              <td><?= $s['id'] ?></td>
              <td><?= htmlspecialchars($s['usuario']) ?></td>
              <td><?= htmlspecialchars($s['departamento']) ?></td>
              <td><?= htmlspecialchars($s['carrera']) ?></td>
              <td><?= htmlspecialchars($s['origen']) ?></td>
              <td><?= htmlspecialchars($s['destino']) ?></td>
              <td><?= htmlspecialchars($s['fecha_solicitada']) ?></td>
              <td><?= htmlspecialchars($s['horario_salida']) ?></td>
              <td><?= htmlspecialchars($s['cantidad_pasajeros']) ?></td>
              <td><span class="status-badge <?= $s['estado'] ?>"><?= ucfirst($s['estado']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="row">
      <div class="panel">
        <div class="panel-title">Usuarios Recientes</div>
        <table>
          <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Registro</th></tr></thead>
          <tbody>
            <?php foreach($ultUsuarios as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td><?= htmlspecialchars($u['nombre']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= ucfirst($u['rol']) ?></td>
              <td><?= htmlspecialchars($u['creado_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="panel">
        <div class="panel-title">Vehículos Recientes</div>
        <table>
          <thead><tr><th>ID</th><th>Patente</th><th>Marca</th><th>Modelo</th><th>Año</th><th>Capacidad</th><th>Estado</th><th>Registro</th></tr></thead>
          <tbody>
            <?php foreach($ultVehiculos as $v): ?>
            <tr>
              <td><?= $v['id'] ?></td>
              <td><?= htmlspecialchars($v['patente']) ?></td>
              <td><?= htmlspecialchars($v['marca']) ?></td>
              <td><?= htmlspecialchars($v['modelo']) ?></td>
              <td><?= htmlspecialchars($v['anio']) ?></td>
              <td><?= htmlspecialchars($v['capacidad']) ?></td>
              <td><?= ucfirst($v['estado']) ?></td>
              <td><?= htmlspecialchars($v['creado_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<!-- MODAL DE AYUDA ESTADISTICAS -->
<div id="modal-ayuda-estadisticas" style="display:none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5);">
  <div class="modal-content" style="background: #fff; border-radius: 12px; max-width: 540px; margin: 70px auto; position: relative; box-shadow: 0 8px 32px #0004;">
    <button onclick="cerrarAyudaEstadisticas()" class="cerrar-modal" style="position: absolute; right: 10px; top: 7px; border: none; background: none; font-size: 2rem; color: #888; cursor: pointer;">&times;</button>
    <div style="width: 500px; max-width: 90vw; padding: 30px 30px 20px 30px; display: flex; flex-direction: column; align-items: center;">
      <!-- Slide 1 -->
      <div id="slide-estadisticas-0" class="slide-estadisticas" style="display:block;">
        <p style="margin:0 0 9px 0; font-size: 1.1rem; font-weight: bold;">
          ¿Cómo usar el panel de estadísticas?
        </p>
        <div style="margin-bottom:12px; color:#222;">
          Este panel te permite visualizar las principales métricas y estadísticas del sistema de solicitudes. Puedes filtrar los datos según el estado de la solicitud, explorar gráficos y consultar los últimos movimientos registrados.
        </div>
      </div>
      <!-- Slide 2 -->
      <div id="slide-estadisticas-1" class="slide-estadisticas" style="display:none;">
        <p style="margin:0 0 9px 0; font-size: 1.1rem; font-weight: bold;">
          Métricas principales (arriba)
        </p>
        <div style="margin-bottom:12px; color:#222;">
          Aquí ves el resumen total de solicitudes, junto al número de pendientes, confirmadas y canceladas. Estos datos se actualizan automáticamente al cambiar el filtro de estado.
        </div>
      </div>
      <!-- Slide 3 -->
      <div id="slide-estadisticas-2" class="slide-estadisticas" style="display:none;">
        <p style="margin:0 0 9px 0; font-size: 1.1rem; font-weight: bold;">
          Gráficos: Distribución y Ranking
        </p>
        <div style="margin-bottom:12px; color:#222;">
          <b>Distribución de Estados:</b> Visualiza la proporción de solicitudes pendientes, confirmadas y canceladas en un gráfico tipo pastel.<br><br>
          <b>Top Carreras:</b> Ranking de las carreras o departamentos con mayor cantidad de solicitudes.<br>
          <b>Top Destinos:</b> Muestra los destinos más solicitados por los usuarios.<br>
          <b>Rutas con más KM recorridos:</b> Indica qué rutas han significado más kilometraje para la flota, útil para análisis logístico.
        </div>
      </div>
      <!-- Slide 4 -->
      <div id="slide-estadisticas-3" class="slide-estadisticas" style="display:none;">
        <p style="margin:0 0 9px 0; font-size: 1.1rem; font-weight: bold;">
          Últimos registros
        </p>
        <div style="margin-bottom:12px; color:#222;">
          En la parte inferior puedes ver tablas con las últimas solicitudes, los usuarios y vehículos registrados recientemente. Así mantienes el control de los movimientos más recientes del sistema.
        </div>
      </div>
      <!-- Slide 5 -->
      <div id="slide-estadisticas-4" class="slide-estadisticas" style="display:none;">
        <p style="margin:0 0 9px 0; font-size: 1.1rem; font-weight: bold;">
          Consejo rápido
        </p>
        <div style="margin-bottom:12px; color:#222;">
          Puedes hacer clic en el filtro de estado para analizar datos solo de solicitudes pendientes, confirmadas o canceladas. Los gráficos y las tablas se actualizan automáticamente según tu selección.
        </div>
      </div>
      <!-- Carrusel navegación -->
      <div style="margin-top:10px;">
        <button onclick="cambiarSlideEstadisticas(-1)" style="background:#eee; border:none; padding:4px 14px; margin-right:8px; border-radius:5px; font-size:1.1rem; cursor:pointer;">&#8592;</button>
        <button onclick="cambiarSlideEstadisticas(1)" style="background:#eee; border:none; padding:4px 14px; border-radius:5px; font-size:1.1rem; cursor:pointer;">&#8594;</button>
      </div>
    </div>
  </div>
</div>
<script>
  // Modal ayuda
  let idxEstadisticas = 0;
  function mostrarAyudaEstadisticas() {
    document.getElementById('modal-ayuda-estadisticas').style.display = 'block';
    mostrarSlideEstadisticas(idxEstadisticas);
  }
  function cerrarAyudaEstadisticas() {
    document.getElementById('modal-ayuda-estadisticas').style.display = 'none';
  }
  function cambiarSlideEstadisticas(dir) {
    const total = 5; // cantidad de slides
    idxEstadisticas = (idxEstadisticas + dir + total) % total;
    mostrarSlideEstadisticas(idxEstadisticas);
  }
  function mostrarSlideEstadisticas(idx) {
    for (let i = 0; i < 5; i++)
      document.getElementById('slide-estadisticas-' + i).style.display = (i === idx) ? 'block' : 'none';
  }
  // Asocia el botón
  window.onload = function(){
    document.getElementById('btn-ayuda-estadisticas').onclick = mostrarAyudaEstadisticas;
  }
</script>
<script>
  const chartEstadosData = {
    labels: ['Pendiente','Confirmada','Cancelada'],
    datasets: [{
      data: [<?= $pendientes ?>, <?= $confirmadas ?>, <?= $canceladas ?>],
      backgroundColor: ['#ffc107','#28a745','#dc3545']
    }]
  };

  const chartCarrerasData = {
    labels: <?= json_encode(array_column($topCarreras,'carrera')) ?>,
    datasets: [{
      label: 'Solicitudes',
      data: <?= json_encode(array_column($topCarreras,'total')) ?>,
      backgroundColor: '#3a81f8'
    }]
  };

  const chartDestinosData = {
    labels: <?= json_encode(array_column($topDestinos,'destino')) ?>,
    datasets: [{
      label: 'Solicitudes',
      data: <?= json_encode(array_column($topDestinos,'total')) ?>,
      backgroundColor: '#2ad3c9'
    }]
  };

  const chartRutasKmData = {
    labels: <?= json_encode($labelsRutasKm) ?>,
    datasets: [{
      label: 'KM Recorridos',
      data: <?= json_encode($dataRutasKm) ?>,
      backgroundColor: '#fdc43b'
    }]
  };

  window.addEventListener('DOMContentLoaded', () => {
    new Chart(
      document.getElementById('chartEstados').getContext('2d'),
      {
        type: 'pie',
        data: chartEstadosData,
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } }
        }
      }
    );

    new Chart(
      document.getElementById('chartCarreras').getContext('2d'),
      {
        type: 'bar',
        data: chartCarrerasData,
        options: {
          indexAxis: 'y',
          responsive: true,
          scales: { x: { beginAtZero: true } }
        }
      }
    );

    new Chart(
      document.getElementById('chartDestinos').getContext('2d'),
      {
        type: 'bar',
        data: chartDestinosData,
        options: {
          indexAxis: 'y',
          responsive: true,
          scales: { x: { beginAtZero: true } }
        }
      }
    );

    new Chart(
      document.getElementById('chartRutasKm').getContext('2d'),
      {
        type: 'bar',
        data: chartRutasKmData,
        options: {
          indexAxis: 'y',
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.raw+' km' } }
          },
          scales: {
            x: { beginAtZero: true, title: { display: true, text: 'KM Recorridos' } }
          }
        }
      }
    );
  });
</script>
</body>
</html>
