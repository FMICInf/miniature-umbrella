<?php
// Archivo: php/user_dashboard.php
session_start();
require_once __DIR__ . '/config.php';

// Validar sesión y rol de usuario
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    header('Location: index.php');
    exit;
}
$userId = $_SESSION['user_id'];

// ===== Métricas =====
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ?");
$stmtTotal->execute([$userId]);
$totalSolicitudes = (int)$stmtTotal->fetchColumn();

$stmtPend = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado='pendiente'");
$stmtPend->execute([$userId]);
$pendientes = (int)$stmtPend->fetchColumn();

$stmtConf = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado='confirmada'");
$stmtConf->execute([$userId]);
$confirmadas = (int)$stmtConf->fetchColumn();

$stmtCan = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado='cancelada'");
$stmtCan->execute([$userId]);
$canceladas = (int)$stmtCan->fetchColumn();

// ===== Opciones Origen/Destino =====
$origenStmt = $pdo->query("SELECT DISTINCT origen FROM rutas ORDER BY origen");
$origenes = $origenStmt->fetchAll(PDO::FETCH_COLUMN);
$destinoStmt = $pdo->query("SELECT DISTINCT destino FROM rutas ORDER BY destino");
$destinos = $destinoStmt->fetchAll(PDO::FETCH_COLUMN);

// ===== Solicitudes con asignaciones =====
$sql = 
    "SELECT s.id,
            s.fecha_solicitada,
            r.origen,
            r.destino,
            r.horario_salida,
            s.estado,
            a.conductor_id,
            u.nombre AS conductor
     FROM solicitudes s
     JOIN rutas r ON s.ruta_id = r.id
     LEFT JOIN asignaciones a
       ON a.ruta_id = s.ruta_id
      AND a.fecha    = s.fecha_solicitada
     LEFT JOIN usuarios u ON a.conductor_id = u.id
     WHERE s.usuario_id = ?
     ORDER BY s.fecha_solicitada DESC";
$solStmt = $pdo->prepare($sql);
$solStmt->execute([$userId]);
$solicitudes = $solStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de Usuario – Logística</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .metrics { display:flex; gap:1rem; margin-bottom:2rem; }
    .metric-card { flex:1; background:#fff; padding:1rem; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); text-align:center; }
    .metric-card h3 { margin:0; font-size:1rem; color:#004080; }
    .metric-card p { font-size:1.5rem; margin-top:.5rem; }
    .form-group { margin-bottom:1rem; }
    .hidden { display:none; }
    .badge { padding:.25em .5em; border-radius:4px; color:#fff; font-size:.85rem; }
    .badge-pendiente { background:#ffc107; }
    .badge-confirmada { background:#28a745; }
    .badge-cancelada { background:#dc3545; }
    table { width:100%; border-collapse:collapse; margin-top:1rem; }
    th, td { padding:.75rem; border-bottom:1px solid #ddd; }
    th { background:#004080; color:#fff; }
    .btn-cancel { padding:.25em .5em; background:#dc3545; color:#fff; border:none; border-radius:4px; cursor:pointer; }
    .btn-cancel:disabled { opacity:0.5; cursor:not-allowed; }
    #toast { position:fixed; top:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-bottom:.5rem; border-radius:4px; opacity:.9; }
    #addRouteBtn { margin-left:.5rem; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Bienvenido, <?=htmlspecialchars($_SESSION['username'],ENT_QUOTES)?></h1>
    <nav><ul class="menu"><li><a href="dashboard.php">Dashboard</a></li><li><a href="logout.php">Cerrar sesión</a></li></ul></nav>
  </header>
  <main class="container">
    <div class="metrics">
      <div class="metric-card"><h3>Total</h3><p id="met-total"><?=$totalSolicitudes?></p></div>
      <div class="metric-card"><h3>Pendientes</h3><p id="met-pend"><?=$pendientes?></p></div>
      <div class="metric-card"><h3>Confirmadas</h3><p id="met-conf"><?=$confirmadas?></p></div>
      <div class="metric-card"><h3>Canceladas</h3><p id="met-canc"><?=$canceladas?></p></div>
    </div>

    <section class="card">
      <h2>Solicitar Transporte</h2>
      <form id="solForm" class="form-inline">
        <div class="form-group">
          <label for="origen">Origen</label>
          <select name="origen" id="origen" required>
            <option value="">Seleccione origen</option>
            <?php foreach($origenes as $o):?>
              <option value="<?=htmlspecialchars($o)?>"><?=htmlspecialchars($o)?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="form-group">
          <label for="destino">Destino</label>
          <select name="destino" id="destino" required>
            <option value="">Seleccione destino</option>
            <?php foreach($destinos as $d):?>
              <option value="<?=htmlspecialchars($d)?>"><?=htmlspecialchars($d)?></option>
            <?php endforeach;?>
            <option value="otro">Otro...</option>
          </select>
          <input type="text" name="otro_destino" id="otro_destino" class="hidden" placeholder="Especifica destino" />
          <button type="button" id="addRouteBtn" class="hidden">Agregar Ruta</button>
        </div>
        <div class="form-group">
          <label for="fecha_solicitada">Fecha</label>
          <input type="date" name="fecha_solicitada" id="fecha_solicitada" required />
        </div>
        <div class="form-group">
          <label for="horario_salida">Hora salida</label>
          <input type="time" name="horario_salida" id="horario_salida" required />
        </div>
        <button type="submit">Enviar</button>
      </form>
    </section>

    <section class="card">
      <h2>Mis Solicitudes</h2>
      <?php if (empty($solicitudes)): ?>
        <p>No tienes solicitudes.</p>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Ruta</th>
            <th>Hora</th>
            <th>Estado</th>
            <th>Detalles</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($solicitudes as $s): ?>
          <tr data-id="<?=$s['id']?>">
            <td><?=htmlspecialchars($s['fecha_solicitada'])?></td>
            <td><?=htmlspecialchars("{$s['origen']} → {$s['destino']}")?></td>
            <td><?=htmlspecialchars($s['horario_salida'])?></td>
            <td><span class="badge badge-<?=$s['estado']?>"><?=ucfirst($s['estado'])?></span></td>
            <td>
              <?php if ($s['estado'] === 'pendiente'): ?>
                <button class="btn-cancel" data-id="<?=$s['id']?>">Cancelar</button>
              <?php elseif ($s['estado'] === 'confirmada' && $s['conductor_id']): ?>
                <span>Conductor: <?=htmlspecialchars($s['conductor'])?></span>
              <?php else: ?>
                <span>-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
  </main>

  <div id="toast"></div>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    var destino = document.getElementById('destino'), otro = document.getElementById('otro_destino'), btnAdd = document.getElementById('addRouteBtn');

    destino.addEventListener('change', function() {
      if (this.value === 'otro') { otro.classList.remove('hidden'); btnAdd.classList.remove('hidden'); otro.required = true; }
      else { otro.classList.add('hidden'); btnAdd.classList.add('hidden'); otro.required = false; }
    });

    btnAdd.addEventListener('click', function() {
      var o = document.getElementById('origen').value,
          d = otro.value,
          h = document.getElementById('horario_salida').value;
      if (!o) return showToast('Selecciona un origen');
      if (!d) return showToast('Escribe un destino');
      if (!h) return showToast('Selecciona hora de salida');

      fetch('/log/php/create_ruta.php', {
        method: 'POST', headers: { 'Content-Type':'application/x-www-form-urlencoded' },
        body: 'origen=' + encodeURIComponent(o) + '&destino=' + encodeURIComponent(d) + '&horario=' + encodeURIComponent(h)
      })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          var opt = document.createElement('option'); opt.value = json.id;
          opt.textContent = json.origen + ' → ' + json.destino + ' (' + json.horario + ')';
          destino.appendChild(opt); destino.value = json.id;
          showToast('Ruta agregada'); otro.value = ''; otro.classList.add('hidden'); btnAdd.classList.add('hidden');
        } else showToast(json.message);
      });
    });

    // Cancelar Solicitud
    document.querySelectorAll('.btn-cancel').forEach(function(btn) {
      btn.addEventListener('click', function() {
        if (!confirm('¿Cancelar esta solicitud?')) return;
        fetch('/log/php/cancel_solicitud.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id=' + encodeURIComponent(btn.dataset.id) })
        .then(res => res.json())
        .then(json => {
          if (json.success) {
            var tr = btn.closest('tr'); tr.querySelector('.badge').textContent = 'Cancelada'; btn.disabled = true; showToast('Solicitud cancelada');
          } else showToast('Error: ' + json.message);
        });
      });
    });

    // Crear Solicitud
    document.getElementById('solForm').addEventListener('submit', function(e) {
      e.preventDefault(); var f = new URLSearchParams(new FormData(this));
      fetch('/log/php/create_solicitud.php', { method:'POST', body:f })
      .then(res => res.json()).then(json => {
        if (json.success) {
          showToast('Solicitud enviada');
          // TODO: actualizar tabla y métricas
        } else showToast(json.message);
      });
    });

    function showToast(msg) {
      var c = document.getElementById('toast'), t = document.createElement('div');
      t.className = 'toast'; t.textContent = msg; c.appendChild(t);
      setTimeout(() => c.removeChild(t), 3000);
    }

    // Polling de estados cada 30s
    setInterval(function() {
      fetch('/log/php/get_solicitudes.php')
        .then(res => res.json())
        .then(json => {
          if (!json.success) return;
          json.solicitudes.forEach(function(s) {
            var tr = document.querySelector('tr[data-id="' + s.id + '"]');
            if (!tr) return;
            var badge = tr.querySelector('.badge');
            var btn   = tr.querySelector('.btn-cancel');
            var estadoCap = s.estado.charAt(0).toUpperCase() + s.estado.slice(1);
            badge.textContent = estadoCap;
            badge.className  = 'badge badge-' + s.estado;
            if (s.estado !== 'pendiente' && btn) btn.disabled = true;
          });
        });
    }, 30000);

  });
  </script>
</body>
</html>
