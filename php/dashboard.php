<?php
session_start();
require_once __DIR__ . '/config.php';

// ===== Métricas =====
// Contar solo rutas que tienen asignaciones con solicitudes confirmadas
$totalRutasStmt = $pdo->query("
    SELECT COUNT(DISTINCT r.id)
    FROM rutas r
    JOIN asignaciones a ON a.ruta_id = r.id
    JOIN solicitudes s ON s.ruta_id = r.id AND s.fecha_solicitada = a.fecha
    WHERE s.estado = 'confirmada'
");
$totalRutas = (int)$totalRutasStmt->fetchColumn();

// Solicitudes pendientes
$pendientes = $pdo->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'")->fetchColumn();

// Asignaciones hoy que corresponden a solicitudes confirmadas
$hoy = date('Y-m-d');
$stmtHoy = $pdo->prepare("
    SELECT COUNT(DISTINCT a.id)
    FROM asignaciones a
    JOIN solicitudes s ON a.ruta_id = s.ruta_id AND a.fecha = s.fecha_solicitada
    WHERE s.estado = 'confirmada' AND a.fecha = ?
");
$stmtHoy->execute([$hoy]);
$asignacionesHoy = (int)$stmtHoy->fetchColumn();

// Vehículos en mantenimiento
$mantenimiento = $pdo->query("SELECT COUNT(*) FROM vehiculos WHERE estado = 'en_mantenimiento'")->fetchColumn();

// ===== Eventos para calendario =====
$events = [];
$stmt = $pdo->query(
    "SELECT 
        s.fecha_solicitada AS fecha,
        r.origen,
        r.destino,
        s.horario_salida,
        s.hora_regreso,
        v.patente AS vehiculo
     FROM solicitudes s
     JOIN rutas r ON s.ruta_id = r.id
     JOIN asignaciones a ON a.ruta_id = r.id AND a.fecha = s.fecha_solicitada
     JOIN vehiculos v ON a.vehiculo_id = v.id
     WHERE s.estado = 'confirmada'
     ORDER BY s.fecha_solicitada"
);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $start = $row['fecha'] . 'T' . $row['horario_salida'];
    $end = $row['hora_regreso'] 
        ? $row['fecha'] . 'T' . $row['hora_regreso'] 
        : null;

    $event = [
        'title'        => "{$row['origen']} → {$row['destino']}",
        'start'        => $start,
        'allDay'       => false,
        'extendedProps'=> [
            'vehiculo' => $row['vehiculo']
        ],
        'classNames'   => ['vehiculo-' . preg_replace('/[^a-z0-9]/i', '', strtolower($row['vehiculo']))]
    ];
    if ($end) {
        $event['end'] = $end;
    }
    $events[] = $event;
}

// ===== Próximos viajes con paginación =====
$pageUpcoming = isset($_GET['page_upcoming']) ? max(1, (int)$_GET['page_upcoming']) : 1;
$perPageUpcoming = 5;
$offsetUpcoming = ($pageUpcoming - 1) * $perPageUpcoming;

// Contar total próximos viajes para paginación
$totalUpcomingStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM asignaciones a
    JOIN rutas r ON a.ruta_id = r.id
    WHERE a.fecha >= CURDATE()
");
$totalUpcomingStmt->execute();
$totalUpcoming = (int)$totalUpcomingStmt->fetchColumn();
$totalPagesUpcoming = max(1, ceil($totalUpcoming / $perPageUpcoming));

// Obtener próximos viajes con LIMIT y OFFSET para paginación
$upcomingStmt = $pdo->prepare(
    "SELECT a.fecha, r.origen, r.destino, u.nombre AS conductor
     FROM asignaciones a
     JOIN rutas r ON a.ruta_id = r.id
     JOIN usuarios u ON a.conductor_id = u.id
     WHERE a.fecha >= CURDATE()
     ORDER BY a.fecha
     LIMIT ? OFFSET ?"
);
$upcomingStmt->bindValue(1, $perPageUpcoming, PDO::PARAM_INT);
$upcomingStmt->bindValue(2, $offsetUpcoming, PDO::PARAM_INT);
$upcomingStmt->execute();
$upcoming = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard – Logística</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" />
  <style>
    body {
      background: #f5f9fc;
      margin: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
      color: #183046;
    }
    .logo-header {
      width: 70px;
      margin: 2.5rem auto 1rem auto;
      display: block;
    }
    .header-inner {
      background: #fff;
      box-shadow: 0 2px 12px rgba(0,0,0,0.03);
      padding: 0.2rem 0 0.5rem 0;
      margin-bottom: 1.5rem;
      border-radius: 0 0 18px 18px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .header-inner h1 {
      color: #004080;
      font-weight: 700;
      margin: 0 0 0.4rem 0;
      font-size: 2.1rem;
    }
    .menu {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      gap: 1.1rem;
      font-size: 1.06rem;
    }
    .menu a {
      text-decoration: none;
      color: #004080;
      font-weight: 600;
      padding: 2px 9px;
      border-radius: 5px;
      transition: background 0.15s, color 0.2s;
    }
    .menu a:hover {
      background: #e5eef9;
      color: #0361b2;
    }

    main.container {
      max-width: 1150px;
      margin: 0 auto 3.5rem auto;
      padding: 0 1.5rem;
    }
    .metrics {
      display: grid; 
      grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); 
      gap:2.3rem; 
      margin-bottom:2.8rem;
    }
    .card {
      background:#fff;
      padding:1.4rem 0.7rem;
      border-radius:12px;
      box-shadow:0 3px 18px rgba(0,32,80,0.07);
      text-align:center;
      border-bottom: 4px solid #32cdcf20;
    }
    .card h3 {
      margin: 0 0 .6rem 0;
      color: #0073bb;
      font-weight: 600;
      font-size: 1.12rem;
      letter-spacing: 0.01em;
    }
    .card p {
      font-size: 2.5rem;
      color: #004080;
      margin: 0;
      font-weight: 700;
    }

    .filter {
      margin-bottom:1.4rem;
      background: #fff;
      border-radius: 10px;
      box-shadow:0 2px 10px rgba(0,32,80,0.04);
      padding: 1.2rem 1rem 0.8rem 1rem;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 1.2rem;
    }
    .filter label {
      font-weight: 500;
      color: #175072;
      margin-right: 0.4rem;
    }
    .filter input[type="date"] {
      padding: 0.45rem 0.7rem;
      border-radius: 5px;
      border: 1px solid #b2cbe4;
      margin-right: 0.7rem;
      font-size: 1.04rem;
    }
    .filter button {
      padding: 0.5rem 1.25rem;
      border-radius: 5px;
      background: #004080;
      color: #fff;
      font-weight: 600;
      border: none;
      margin-right: 0.7rem;
      font-size: 1.05rem;
      cursor: pointer;
      transition: background 0.18s;
    }
    .filter button:hover {
      background: #0098d6;
    }
    .help-icon {
      display: inline-block;
      width: 28px; height: 28px;
      border-radius: 50%;
      background: #004080;
      color: #fff;
      text-align: center;
      line-height: 28px;
      font-size: 20px;
      font-weight: bold;
      cursor: pointer;
      margin-left: 8px;
      vertical-align: middle;
      user-select: none;
      transition: background 0.2s;
    }
    .help-icon:hover { background: #0072d1; }
    #calendar .fc-daygrid-day,
    #calendar .fc-daygrid-day-frame,
    #calendar .fc-daygrid-day-top,
    #calendar .fc-daygrid-day-number,
    #calendar .fc-event {
      cursor: pointer !important;
      transition: background 0.25s;
    }
    #calendar .fc-daygrid-day:hover,
    #calendar .fc-daygrid-day-frame:hover {
      background: #e0f0fb !important; /* Celeste suave */
    }
    table { width:100%; border-collapse:collapse; margin-top:2rem; }
    th, td { padding:.75rem; border-bottom:1px solid #e0e5ef; text-align:left; }
    th { background:#004080; color:#fff; font-weight: 600;}
    .upcoming h2 {margin-bottom: .5rem;}
    /* Animación de modales ayuda */
    .modal-ayuda .slide-ayuda { min-height: 260px; }
    .modal-ayuda button:disabled { background:#cccccc; cursor:not-allowed; }
    .modal-ayuda {
      animation: fadeIn .23s;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    /* Vehículo colores demo */
.vehiculo-jgsj35   { background: #007bff !important; color: #fff !important; } /* Montero Sport 2013 */
.vehiculo-lyht39   { background: #ff9800 !important; color: #fff !important; } /* Hilux 2016 */
.vehiculo-jgsj75   { background: #43a047 !important; color: #fff !important; } /* Sprinter 2019 */
.vehiculo-trht11   { background: #d32f2f !important; color: #fff !important; } /* Montero Sport 2025 */

    .pagination-upcoming {
      margin-top: 1rem;
      text-align: center;
    }
    .pagination-upcoming a {
      padding: 6px 12px;
      background: #004080;
      color: white;
      border-radius: 5px;
      margin: 0 6px;
      text-decoration: none;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }
    .pagination-upcoming a:hover {
      background: #0066cc;
    }
  </style>
</head>
<body>
<header class="header-bar">
  <div class="header-content">
    <div class="header-left">
      <img src="../assets/img/logo-uaysen_patagonia_sin_fondo.png" alt="Logo Uaysén" class="logo-header">
      <span class="bienvenido">Bienvenido, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Invitado', ENT_QUOTES) ?></strong></span>
    </div>
    <div class="header-right">
      <?php if (!empty($_SESSION['rol'])): ?>
        <?php if ($_SESSION['rol'] === 'admin'): ?>
          <a href="admin_dashboard.php" class="nav-btn">Panel Admin</a>
        <?php elseif ($_SESSION['rol'] === 'conductor'): ?>
          <a href="conductor_dashboard.php" class="nav-btn">Mis Asignaciones</a>
        <?php elseif ($_SESSION['rol'] === 'usuario'): ?>
          <a href="user_dashboard.php" class="nav-btn">Solicitar Transporte</a>
          <a href="mis_solicitudes.php" class="nav-btn">Mis solicitudes</a>
        <?php endif; ?>
        <a href="logout.php" class="nav-btn logout">Cerrar sesión</a>
      <?php else: ?>
        <a href="index.php" class="nav-btn">Iniciar sesión</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="container">
  <section class="metrics">
    <div class="card"><h3>Total Rutas</h3><p><?= $totalRutas ?></p></div>
    <div class="card"><h3>Solicitudes Pendientes</h3><p><?= $pendientes ?></p></div>
    <div class="card"><h3>Asignaciones Hoy</h3><p><?= $asignacionesHoy ?></p></div>
    <div class="card"><h3>En Mantenimiento</h3><p><?= $mantenimiento ?></p></div>
  </section>

  <section class="filter">
    <label>Desde: <input type="date" id="fromDate"></label>
    <label>Hasta: <input type="date" id="toDate"></label>
    <button type="button" id="applyFilter">Filtrar Calendario</button>
    <button type="button" id="resetFilter">Restablecer Calendario</button>
    <span class="help-icon" onclick="document.getElementById('ayudaModal-calendario').style.display='flex'; showAyudaSlide(0);" title="¿Cómo funciona el calendario?">?</span>
  </section>

  <div id="calendar"></div>

  <section class="upcoming">
    <h2 style="display:inline-block;vertical-align:middle;">Próximos viajes</h2>
    <span class="help-icon" onclick="document.getElementById('ayudaModal-viajes').style.display='flex'" title="¿Cómo funciona la tabla de próximos viajes?">?</span>
    <table id="upcomingTable">
      <thead>
        <tr><th>Fecha</th><th>Ruta</th><th>Conductor</th></tr>
      </thead>
      <tbody>
        <?php foreach ($upcoming as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['fecha']) ?></td>
            <td><?= htmlspecialchars("{$row['origen']} → {$row['destino']}") ?></td>
            <td><?= htmlspecialchars($row['conductor']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <nav class="pagination-upcoming">
      <?php if ($pageUpcoming > 1): ?>
        <a href="?page_upcoming=<?= $pageUpcoming - 1 ?>">&laquo; Anterior</a>
      <?php endif; ?>
      Página <?= $pageUpcoming ?> de <?= $totalPagesUpcoming ?>
      <?php if ($pageUpcoming < $totalPagesUpcoming): ?>
        <a href="?page_upcoming=<?= $pageUpcoming + 1 ?>">Siguiente &raquo;</a>
      <?php endif; ?>
    </nav>
  </section>
</main>

<!-- Modal de ayuda Próximos viajes -->
<div id="ayudaModal-viajes" class="modal-ayuda" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
  <div style="background:#fff; padding:20px; border-radius:8px; max-width:480px; width:90%; position:relative; text-align:center; box-shadow:0 4px 24px rgba(0,0,0,0.10);">
    <span style="position:absolute; top:8px; right:16px; cursor:pointer; font-size:28px; font-weight:bold; color:#333;" onclick="document.getElementById('ayudaModal-viajes').style.display='none'">&times;</span>
    <h3 style="margin-top:0;">¿Cómo funciona la tabla de próximos viajes?</h3>
    <img src="../assets/gifs/Proximos_Viajes.gif" alt="Ayuda Próximos viajes" style="width:100%; border-radius:6px; margin-bottom:10px;">
    <p style="font-size:1rem;">
      Aquí verás los viajes próximos, con fecha, ruta y conductor asignado. Usa esto para planificar o revisar tus asignaciones.
    </p>
  </div>
</div>

<!-- Modal de ayuda Calendario (Carrusel con 4 slides) -->
<div id="ayudaModal-calendario" class="modal-ayuda" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
  <div style="background:#fff; padding:20px; border-radius:8px; max-width:500px; width:95%; position:relative; text-align:center; box-shadow:0 4px 24px rgba(0,0,0,0.10); min-height:420px;">
    <span style="position:absolute; top:8px; right:16px; cursor:pointer; font-size:28px; font-weight:bold; color:#333;"
          onclick="document.getElementById('ayudaModal-calendario').style.display='none'">&times;</span>
    
    <!-- Slide 0: Métricas principales -->
    <div class="slide-ayuda" id="slide-ayuda-0">
      <h3 style="margin-top:0;">¿Qué significan las métricas?</h3>
      <img src="../assets/gifs/Ver_info.png" alt="Información de métricas" style="width:100%; border-radius:6px; margin-bottom:10px;">
      <p style="font-size:1rem;">
        Estas tarjetas muestran información general del sistema:<br>
        <b>Total Rutas</b>: número de rutas disponibles.<br>
        <b>Solicitudes Pendientes</b>: solicitudes que esperan aprobación.<br>
        <b>Asignaciones Hoy</b>: viajes programados para hoy.<br>
        <b>En Mantenimiento</b>: vehículos fuera de servicio.<br>
        Puedes usar estos datos para conocer el estado global del sistema rápidamente.
      </p>
    </div>

    <!-- Slide 1: Calendario -->
    <div class="slide-ayuda" id="slide-ayuda-1" style="display:none;">
      <h3 style="margin-top:0;">¿Cómo usar el calendario?</h3>
      <img src="../assets/gifs/Mostrar_calendario.gif" alt="Ayuda Calendario" style="width:100%; border-radius:6px; margin-bottom:10px;">
      <p style="font-size:1rem;">
        Puedes filtrar el calendario seleccionando un <b>rango de fechas</b>.<br>
        Usa los campos <b>Desde</b> y <b>Hasta</b>, y luego haz clic en <b>Filtrar Calendario</b>.<br>
        Así verás solo los eventos del período que tú elijas.
      </p>
    </div>
    <!-- Slide 2: Navegación por día -->
    <div class="slide-ayuda" id="slide-ayuda-2" style="display:none;">
      <h3 style="margin-top:0;">Navega por los días</h3>
      <img src="../assets/gifs/Nav_Dia.gif" alt="Navegación por día" style="width:100%; border-radius:6px; margin-bottom:10px;">
      <p style="font-size:1rem;">
        Haz click en los días dentro del calendario.<br>
        Así puedes ver <b>qué viajes hay asignados en una fecha específica</b> y todos sus detalles.
      </p>
    </div>
    <!-- Slide 3: Filtro por fechas -->
    <div class="slide-ayuda" id="slide-ayuda-3" style="display:none;">
      <h3 style="margin-top:0;">Filtra por fechas</h3>
      <img src="../assets/gifs/Filtro.png" alt="Filtro por fechas" style="width:100%; border-radius:6px; margin-bottom:10px;">
      <p style="font-size:1rem;">
        Puedes filtrar por el rango que quieras, usando las cajas <b>Desde</b> y <b>Hasta</b> arriba del calendario.<br>
        Haz clic en <b>Filtrar Calendario</b> para ver solo esos días.
      </p>
    </div>
    
    <!-- Navegación -->
    <div style="margin-top:12px;">
      <button id="ayudaPrev" style="padding:6px 18px; font-size:1.3em; border-radius:30px; border:none; background:#004080; color:#fff; margin-right:18px; cursor:pointer;">&#8592;</button>
      <span id="ayudaIndicador" style="font-size:1.1em;">1 / 4</span>
      <button id="ayudaNext" style="padding:6px 18px; font-size:1.3em; border-radius:30px; border:none; background:#004080; color:#fff; margin-left:18px; cursor:pointer;">&#8594;</button>
    </div>
  </div>
</div>

<script>
  // Inyectar eventos PHP en JS
  window.calendarEvents = <?= json_encode($events, JSON_HEX_TAG) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js"></script>
<script src="../assets/js/calendar.js"></script>
<script>
  // Carrusel de ayuda
  let ayudaIndex = 0;
  const totalSlides = 4;
  function showAyudaSlide(idx) {
    for(let i=0; i<totalSlides; i++) {
      document.getElementById('slide-ayuda-'+i).style.display = (i === idx) ? '' : 'none';
    }
    document.getElementById('ayudaIndicador').textContent = (idx+1) + ' / ' + totalSlides;
    document.getElementById('ayudaPrev').disabled = (idx === 0);
    document.getElementById('ayudaNext').disabled = (idx === totalSlides-1);
    ayudaIndex = idx;
  }
  document.getElementById('ayudaPrev').onclick = () => showAyudaSlide(ayudaIndex-1);
  document.getElementById('ayudaNext').onclick = () => showAyudaSlide(ayudaIndex+1);
  // Siempre parte en el primer slide si abres el modal
  document.querySelectorAll('.help-icon').forEach(el=>{
    el.addEventListener('click', function(){
      if(this.getAttribute('onclick')?.includes('ayudaModal-calendario')) showAyudaSlide(0);
    });
  });
</script>

</body>
</html>
