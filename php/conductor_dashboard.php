<?php
// Archivo: php/conductor_dashboard.php
session_start();
require_once __DIR__ . '/config.php';

// Verificar sesión y rol de conductor
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'conductor') {
    header('Location: index.php');
    exit;
}
$conductorId = $_SESSION['user_id'];

// ===== 1. Métricas de conductor =====
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM asignaciones WHERE conductor_id = ?");
$stmtTotal->execute([$conductorId]);
$totalAsignaciones = $stmtTotal->fetchColumn();

$stmtProx = $pdo->prepare("SELECT COUNT(*) FROM asignaciones WHERE conductor_id = ? AND fecha >= CURDATE()");
$stmtProx->execute([$conductorId]);
$asignacionesProx = $stmtProx->fetchColumn();

// ===== 2. Eventos para calendario =====
$events = [];
$stmtEvents = $pdo->prepare(
    "SELECT a.fecha, r.origen, r.destino
     FROM asignaciones a
     JOIN rutas r ON a.ruta_id = r.id
     WHERE a.conductor_id = ?"
);
$stmtEvents->execute([$conductorId]);
while ($row = $stmtEvents->fetch()) {
    // Colorear eventos: verde si futuro, gris si pasado
    $color = ($row['fecha'] >= date('Y-m-d')) ? '#28a745' : '#6c757d';
    $events[] = [
        'title' => $row['origen'] . ' → ' . $row['destino'],
        'start' => $row['fecha'],
        'color' => $color
    ];
}

// ===== 3. Obtener asignaciones detalladas =====
$assignStmt = $pdo->prepare(
    "SELECT a.id, a.fecha, r.origen, r.destino, r.horario_salida
     FROM asignaciones a
     JOIN rutas r ON a.ruta_id = r.id
     WHERE a.conductor_id = ?
     ORDER BY a.fecha DESC"
);
$assignStmt->execute([$conductorId]);
$asignaciones = $assignStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de Conductor – Logística</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css">
  <style>
    .metrics { display: flex; gap:1rem; margin-bottom:2rem; }
    .metric-card { flex:1; background:#fff; padding:1rem; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); text-align:center; }
    .metric-card h3 { margin:0; font-size:1rem; color:#004080; }
    .metric-card p { font-size:1.5rem; margin-top:.5rem; }
    #calendar { max-width:900px; margin:2rem auto; padding:1rem; background:#fff; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
    .filter-search { margin-bottom:1rem; }
    table { width:100%; border-collapse:collapse; margin-top:1rem; }
    th, td { padding:.75rem; border-bottom:1px solid #ddd; }
    th { background:#004080; color:#fff; }
    tr.clickable { cursor:pointer; }
    /* Modal */
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
    .modal-content { background:#fff; padding:1.5rem; border-radius:8px; max-width:500px; width:90%; }
    .modal-close { float:right; cursor:pointer; font-size:1.2rem; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Bienvenido, <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES) ?></h1>
    <nav>
      <ul class="menu">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="logout.php">Cerrar sesión</a></li>
      </ul>
    </nav>
  </header>
  <main class="container">
    <!-- Métricas -->
    <div class="metrics">
      <div class="metric-card"><h3>Total Asignaciones</h3><p><?= $totalAsignaciones ?></p></div>
      <div class="metric-card"><h3>Próximas</h3><p><?= $asignacionesProx ?></p></div>
    </div>

    <!-- Calendario -->
    <div id="calendar"></div>

    <!-- Buscador de tabla -->
    <div class="filter-search">
      <input type="text" id="search" placeholder="Buscar ruta...">
    </div>

    <!-- Tabla de asignaciones -->
    <section class="card">
      <h2>Mis Asignaciones</h2>
      <?php if (empty($asignaciones)): ?>
        <p>No tienes asignaciones.</p>
      <?php else: ?>
        <table id="assignTable">
          <thead><tr><th>Fecha</th><th>Ruta</th><th>Horario</th></tr></thead>
          <tbody>
            <?php foreach ($asignaciones as $a): ?>
              <tr class="clickable" data-fecha="<?= $a['fecha'] ?>" data-origen="<?= htmlspecialchars($a['origen']) ?>" data-destino="<?= htmlspecialchars($a['destino']) ?>" data-horario="<?= $a['horario_salida'] ?>">
                <td><?= $a['fecha'] ?></td>
                <td><?= htmlspecialchars($a['origen'] . ' → ' . $a['destino']) ?></td>
                <td><?= $a['horario_salida'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>

  <!-- Modal detalle -->
  <div id="modal" class="modal">
    <div class="modal-content">
      <span id="closeModal" class="modal-close">&times;</span>
      <h3>Detalle de Asignación</h3>
      <p><strong>Fecha:</strong> <span id="modalFecha"></span></p>
      <p><strong>Ruta:</strong> <span id="modalRuta"></span></p>
      <p><strong>Horario:</strong> <span id="modalHorario"></span></p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Init calendar
      var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth', locale: 'es', height: 'auto',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        events: <?= json_encode($events) ?>
      });
      calendar.render();

      // Search filter
      document.getElementById('search').addEventListener('input', function() {
        var term = this.value.toLowerCase();
        document.querySelectorAll('#assignTable tbody tr').forEach(function(tr) {
          var text = tr.textContent.toLowerCase();
          tr.style.display = text.includes(term) ? '' : 'none';
        });
      });

      // Modal interactions
      var modal = document.getElementById('modal');
      var closeBtn = document.getElementById('closeModal');
      document.querySelectorAll('tr.clickable').forEach(function(tr) {
        tr.addEventListener('click', function() {
          document.getElementById('modalFecha').textContent = this.dataset.fecha;
          document.getElementById('modalRuta').textContent = this.dataset.origen + ' → ' + this.dataset.destino;
          document.getElementById('modalHorario').textContent = this.dataset.horario;
          modal.style.display = 'flex';
        });
      });
      closeBtn.addEventListener('click', function() { modal.style.display = 'none'; });
      window.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
    });
  </script>
</body>
</html>
