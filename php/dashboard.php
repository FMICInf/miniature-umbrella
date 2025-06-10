<?php
// Archivo: php/dashboard.php
session_start();
require_once __DIR__ . '/config.php';

// ===== 1. Métricas =====
$totalRutas      = $pdo->query('SELECT COUNT(*) FROM rutas')->fetchColumn();
$pendientes      = $pdo->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'")->fetchColumn();
$hoy             = date('Y-m-d');
$stmtHoy         = $pdo->prepare("SELECT COUNT(*) FROM asignaciones WHERE fecha = ?");
$stmtHoy->execute([$hoy]);
$asignacionesHoy = $stmtHoy->fetchColumn();
$mantenimiento   = $pdo->query("SELECT COUNT(*) FROM vehiculos WHERE estado = 'en_mantenimiento'")->fetchColumn();

// ===== 2. Eventos para calendario (fecha + hora + conductor) =====
$events = [];
$stmt = $pdo->query(
    "SELECT 
         a.fecha,
         r.origen,
         r.destino,
         r.horario_salida,
         u.nombre AS conductor
     FROM asignaciones a
     JOIN rutas    r ON a.ruta_id      = r.id
     JOIN usuarios u ON a.conductor_id = u.id
     ORDER BY a.fecha"
);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Combina fecha y hora para un evento con hora específica
    $events[] = [
        'title'        => "{$row['origen']} → {$row['destino']}",
        'start'        => $row['fecha'] . 'T' . $row['horario_salida'],
        'allDay'       => false,
        'extendedProps'=> [
            'hora'      => $row['horario_salida'],
            'conductor' => $row['conductor']
        ]
    ];
}

// ===== 3. Próximos viajes =====
$upcomingStmt = $pdo->prepare(
    "SELECT a.fecha, r.origen, r.destino, u.nombre AS conductor
     FROM asignaciones a
     JOIN rutas    r ON a.ruta_id      = r.id
     JOIN usuarios u ON a.conductor_id = u.id
     WHERE a.fecha >= CURDATE()
     ORDER BY a.fecha
     LIMIT 10"
);
$upcomingStmt->execute();
$upcoming = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard – Logística</title>
  <!-- CSS principal -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- FullCalendar CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css">
  <style>
    /* Hover sobre celdas de día en Month view */
#calendar .fc-daygrid-day-frame:hover {
  background-color: #eef6ff;  /* color suave, ajústalo a tu paleta */
  transition: background-color 0.2s ease;
}

/* Mantener puntero de enlace */
#calendar .fc-daygrid-day-frame:hover,
#calendar .fc-event {
  cursor: pointer;
}

/* Opcional: evento también destaque al pasar */
#calendar .fc-event:hover {
  opacity: 0.85;
}

    .metrics { display: grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:2rem; }
    .card    { background:#fff; padding:1rem; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); text-align:center; }
    .filter  { margin-bottom:1rem; }
    #calendar{ max-width:900px; margin:2rem auto; background:#fff; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); padding:1rem; }
    table    { width:100%; border-collapse:collapse; margin-top:2rem; }
    th,td    { padding:.75rem; border-bottom:1px solid #ddd; text-align:left; }
    th       { background:#004080; color:#fff; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1><?= htmlspecialchars($_SESSION['username'] ?? 'Invitado', ENT_QUOTES) ?></h1>
    <nav>
      <ul class="menu">
        <?php if (!empty($_SESSION['rol'])): ?>
          <?php if ($_SESSION['rol']==='admin'): ?>
            <li><a href="admin_dashboard.php">Panel Admin</a></li>
          <?php elseif ($_SESSION['rol']==='conductor'): ?>
            <li><a href="conductor_dashboard.php">Mis Asignaciones</a></li>
          <?php elseif ($_SESSION['rol']==='usuario'): ?>
            <li><a href="user_dashboard.php">Solicitar Transporte</a></li>
          <?php endif; ?>
          <li><a href="logout.php">Cerrar sesión</a></li>
        <?php else: ?>
          <li><a href="index.php">Iniciar sesión</a></li>
        <?php endif; ?>
      </ul>
    </nav>
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
    </section>

    <div id="calendar"></div>

    <section class="upcoming">
      <h2>Próximos viajes</h2>
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
    </section>
  </main>

  <!-- Inyecta eventos PHP en JS -->
  <script>
    window.calendarEvents = <?= json_encode($events, JSON_HEX_TAG) ?>;
  </script>
  <!-- FullCalendar UMD -->
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js"></script>
  <!-- Inicializador -->
  <script src="../assets/js/calendar.js"></script>
</body>
</html>
