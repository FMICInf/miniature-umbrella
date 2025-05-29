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

// ===== 2. Eventos para calendario =====
$events = [];
$stmt = $pdo->query(
    "SELECT a.fecha, r.origen, r.destino
     FROM asignaciones a
     JOIN rutas r ON a.ruta_id = r.id
     ORDER BY a.fecha"
);
while ($row = $stmt->fetch()) {
    $events[] = [
        'title' => $row['origen'] . ' → ' . $row['destino'],
        'start' => $row['fecha'],
    ];
}

// ===== 3. Próximos viajes =====
$upcomingStmt = $pdo->prepare(
    "SELECT a.fecha, r.origen, r.destino, u.nombre AS conductor
     FROM asignaciones a
     JOIN rutas r ON a.ruta_id = r.id
     JOIN usuarios u ON a.conductor_id = u.id
     WHERE a.fecha >= CURDATE()
     ORDER BY a.fecha
     LIMIT 10"
);
$upcomingStmt->execute();
$upcoming = $upcomingStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard – Logística</title>
  <!-- CSS principal -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- FullCalendar CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css">
  <style>
    .metrics { display: grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap: 1rem; margin-bottom:2rem; }
    .card { background:#fff; padding:1rem; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); text-align:center; }
    .card h3 { margin:0 0 .5rem; font-size:1rem; color:#004080; }
    .card p { font-size:1.5rem; margin:0; }
    .filter { margin-bottom:1rem; }
    .filter label { margin-right:1rem; }
    /* Eliminado height fijo para permitir altura automática */
    #calendar { max-width:900px; margin:2rem auto; background:#fff; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); padding:1rem; }
    table { width:100%; border-collapse:collapse; margin-top:2rem; }
    th, td { padding:.75rem; text-align:left; border-bottom:1px solid #ddd; }
    th { background:#004080; color:#fff; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1><?= htmlspecialchars($_SESSION['username'] ?? 'Invitado', ENT_QUOTES, 'UTF-8') ?></h1>
    <nav>
      <ul class="menu">
        <?php if (!empty($_SESSION['rol'])): ?>
          <?php if ($_SESSION['rol'] === 'admin'): ?>
            <li><a href="admin_dashboard.php">Panel Admin</a></li>
          <?php elseif ($_SESSION['rol'] === 'conductor'): ?>
            <li><a href="conductor_dashboard.php">Mis Asignaciones</a></li>
          <?php elseif ($_SESSION['rol'] === 'usuario'): ?>
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
        <thead><tr><th>Fecha</th><th>Ruta</th><th>Conductor</th></tr></thead>
        <tbody>
          <?php foreach ($upcoming as $row): ?>
            <tr data-fecha="<?= $row['fecha'] ?>">
              <td><?= $row['fecha'] ?></td>
              <td><?= htmlspecialchars("{$row['origen']} → {$row['destino']}") ?></td>
              <td><?= htmlspecialchars($row['conductor']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- FullCalendar UMD JS -->
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var eventsData = <?= json_encode($events) ?>;
      var calendarEl = document.getElementById('calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
        height: 'auto',
        contentHeight: 'auto',
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: eventsData
      });
      calendar.render();

      document.getElementById('applyFilter').addEventListener('click', function() {
        var from = document.getElementById('fromDate').value;
        var to   = document.getElementById('toDate').value;
        var filtered = eventsData.filter(function(ev) {
          return (!from || ev.start >= from) && (!to || ev.start <= to);
        });
        calendar.removeAllEvents();
        calendar.addEventSource(filtered);
      });

      document.getElementById('resetFilter').addEventListener('click', function() {
        document.getElementById('fromDate').value = '';
        document.getElementById('toDate').value = '';
        calendar.removeAllEvents();
        calendar.addEventSource(eventsData);
      });
    });
  </script>
</body>
</html>
