<?php
// Archivo: php/user_dashboard.php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    header('Location: index.php');
    exit;
}
$userId = $_SESSION['user_id'];

// Métricas
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ?");
$totalStmt->execute([$userId]);
$totalSolicitudes = (int)$totalStmt->fetchColumn();

$pendStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'pendiente'");
$pendStmt->execute([$userId]);
$pendientes = (int)$pendStmt->fetchColumn();

$confStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'confirmada'");
$confStmt->execute([$userId]);
$confirmadas = (int)$confStmt->fetchColumn();

$canStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'cancelada'");
$canStmt->execute([$userId]);
$canceladas = (int)$canStmt->fetchColumn();

// Orígenes y destinos
$origenes = $pdo->query("SELECT DISTINCT origen FROM rutas ORDER BY origen")->fetchAll(PDO::FETCH_COLUMN);
$destinos = $pdo->query("SELECT DISTINCT destino FROM rutas ORDER BY destino")->fetchAll(PDO::FETCH_COLUMN);

// Solicitudes existentes
$solStmt = $pdo->prepare(
    "SELECT s.id, s.fecha_solicitada,
            r.origen, r.destino,
            s.horario_salida, s.hora_regreso,
            s.motivo, s.motivo_otro, s.adjunto,
            s.estado
     FROM solicitudes s
     JOIN rutas r ON s.ruta_id = r.id
     WHERE s.usuario_id = ?
     ORDER BY s.fecha_solicitada DESC"
);
$solStmt->execute([$userId]);
$solicitudes = $solStmt->fetchAll(PDO::FETCH_ASSOC);

// Genera options de 00:00 a 23:30 cada 30 min
function generarHoras() {
    $opts = '';
    for ($h = 0; $h < 24; $h++) {
        foreach ([0,30] as $m) {
            $val = sprintf('%02d:%02d', $h, $m);
            $opts .= "<option value=\"$val\">$val</option>\n";
        }
    }
    return $opts;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel de Usuario – Logística</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    body { background:#f5f5f5; margin:0; font-family:sans-serif }
    .container { max-width:960px; margin:2rem auto; padding:0 1rem; }
    .metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:2rem; }
    .metric-card { background:#fff; padding:1rem; border-radius:8px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    .metric-card h3 { color:#004080; margin:0; font-size:1rem; }
    .metric-card p { margin:.5rem 0 0; font-size:1.5rem; }
    .card { background:#fff; padding:1.5rem; border-radius:8px; margin-bottom:2rem; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    .card h2 { color:#004080; margin-top:0; }
    .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; }
    .form-group { display:flex; flex-direction:column; }
    .form-group label { font-weight:500; margin-bottom:.25rem; }
    .form-group input,
    .form-group select { padding:.5rem; border:1px solid #ccc; border-radius:4px; }
    button.btn { background:#004080; color:#fff; border:none; padding:.75rem 1.5rem; border-radius:4px; cursor:pointer; grid-column:1/-1; }
    table { width:100%; border-collapse:collapse; margin-top:1rem; }
    th, td { padding:.75rem; border:1px solid #ddd; text-align:left; word-wrap:break-word; }
    th { background:#004080; color:#fff; }
    .badge { padding:.25em .5em; border-radius:4px; color:#fff; }
    .badge-pendiente  { background:#ffc107; }
    .badge-confirmada { background:#28a745; }
    .badge-cancelada  { background:#dc3545; }
    #toast { position:fixed; top:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:.75rem 1rem; margin-bottom:.5rem; border-radius:4px; }
    .hidden { display:none; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Bienvenido, <?=htmlspecialchars($_SESSION['username'],ENT_QUOTES)?></h1>
    <nav><ul class="menu"><li><a href="dashboard.php">Dashboard</a></li><li><a href="logout.php">Cerrar sesión</a></li></ul></nav>
  </header>

  <div class="container">
    <!-- Métricas -->
    <section class="metrics">
      <div class="metric-card"><h3>Total</h3><p><?= $totalSolicitudes ?></p></div>
      <div class="metric-card"><h3>Pendientes</h3><p><?= $pendientes ?></p></div>
      <div class="metric-card"><h3>Confirmadas</h3><p><?= $confirmadas ?></p></div>
      <div class="metric-card"><h3>Canceladas</h3><p><?= $canceladas ?></p></div>
    </section>

    <!-- Formulario de solicitud -->
    <section class="card">
      <h2>Solicitar Transporte</h2>
      <form id="solForm" enctype="multipart/form-data">
        <div class="form-grid">
          <div class="form-group">
            <label for="origen">Origen</label>
            <select id="origen" name="origen" required>
              <option value="">Seleccione origen</option>
              <?php foreach ($origenes as $o): ?>
                <option><?=htmlspecialchars($o)?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="destino">Destino</label>
            <select id="destino" name="destino" required>
              <option value="">Seleccione destino</option>
              <?php foreach ($destinos as $d): ?>
                <option><?=htmlspecialchars($d)?></option>
              <?php endforeach; ?>
              <option value="Otro">Otro...</option>
            </select>
          </div>
          <div class="form-group hidden" id="otroDestinoGroup">
            <label for="otro_destino">Especifica destino</label>
            <input id="otro_destino" name="otro_destino" placeholder="Otro destino">
          </div>

          <div class="form-group">
            <label for="fecha_solicitada">Fecha</label>
            <input id="fecha_solicitada" name="fecha_solicitada" type="date" required>
          </div>

          <div class="form-group">
            <label for="horario_salida">Hora de salida</label>
            <select id="horario_salida" name="horario_salida" required>
              <option value="">--:--</option>
              <?= generarHoras() ?>
            </select>
          </div>

          <div class="form-group">
            <label><input id="round_trip" type="checkbox"> Viaje de vuelta?</label>
          </div>
          <div class="form-group hidden" id="returnTimeGroup">
            <label for="hora_regreso">Hora de regreso</label>
            <select id="hora_regreso" name="hora_regreso">
              <option value="">--:--</option>
              <?= generarHoras() ?>
            </select>
          </div>

          <div class="form-group">
            <label for="motivo">Motivo</label>
            <select id="motivo" name="motivo">
              <option value="Salida A Terreno">Salida A Terreno</option>
              <option value="Otro">Otro...</option>
            </select>
          </div>
          <div class="form-group hidden" id="motivoOtroGroup">
            <label for="motivo_otro">Especificar motivo</label>
            <input id="motivo_otro" name="motivo_otro" placeholder="Detalle motivo">
          </div>

          <div class="form-group">
            <label for="adjunto">Adjuntar documento</label>
            <input id="adjunto" name="adjunto" type="file" accept=".pdf,.doc,.docx">
          </div>
        </div>

        <button type="submit" class="btn">Enviar Solicitud</button>
      </form>
    </section>

    <!-- Mis Solicitudes -->
    <section class="card">
      <h2>Mis Solicitudes</h2>
      <?php if (empty($solicitudes)): ?>
        <p>No tienes solicitudes.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Fecha</th><th>Ruta</th><th>Salida</th><th>Regreso</th>
              <th>Motivo</th><th>Adjunto</th><th>Estado</th><th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($solicitudes as $s): ?>
            <tr data-id="<?= $s['id'] ?>">
              <td><?=htmlspecialchars($s['fecha_solicitada'])?></td>
              <td><?=htmlspecialchars("{$s['origen']} → {$s['destino']}")?></td>
              <td><?=htmlspecialchars($s['horario_salida'])?></td>
              <td><?= $s['hora_regreso'] ?: '-' ?></td>
              <td>
                <?= $s['motivo']==='Otro'
                     ? htmlspecialchars($s['motivo_otro'])
                     : htmlspecialchars($s['motivo']) ?>
              </td>
              <td>
                <?php if ($s['adjunto']): ?>
                  <a href="../<?=htmlspecialchars($s['adjunto'])?>" target="_blank">Ver</a>
                <?php else: echo '-'; endif; ?>
              </td>
              <td><span class="badge badge-<?= $s['estado'] ?>">
                <?= ucfirst($s['estado']) ?></span></td>
              <td>
                <?php if ($s['estado']==='pendiente'): ?>
                  <button class="btn btn-cancel" data-id="<?= $s['id'] ?>">
                    Cancelar
                  </button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>

  <div id="toast"></div>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const destinoSel  = document.getElementById('destino');
    const otroGrp     = document.getElementById('otroDestinoGroup');
    const otroInput   = document.getElementById('otro_destino');
    const roundChk    = document.getElementById('round_trip');
    const returnGrp   = document.getElementById('returnTimeGroup');
    const returnSel   = document.getElementById('hora_regreso');
    const motivoSel   = document.getElementById('motivo');
    const motivoGrp   = document.getElementById('motivoOtroGroup');
    const motivoIn    = document.getElementById('motivo_otro');
    const form        = document.getElementById('solForm');
    const toast       = document.getElementById('toast');

    function showToast(msg) {
      const t = document.createElement('div');
      t.className = 'toast';
      t.textContent = msg;
      toast.appendChild(t);
      setTimeout(() => t.remove(), 3000);
    }

    destinoSel.addEventListener('change', () => {
      if (destinoSel.value === 'Otro') {
        otroGrp.classList.remove('hidden');
        otroInput.required = true;
      } else {
        otroGrp.classList.add('hidden');
        otroInput.required = false;
        otroInput.value = '';
      }
    });

    roundChk.addEventListener('change', () => {
      if (roundChk.checked) {
        returnGrp.classList.remove('hidden');
        returnSel.required = true;
      } else {
        returnGrp.classList.add('hidden');
        returnSel.required = false;
        returnSel.value = '';
      }
    });

    motivoSel.addEventListener('change', () => {
      if (motivoSel.value === 'Otro') {
        motivoGrp.classList.remove('hidden');
        motivoIn.required = true;
      } else {
        motivoGrp.classList.add('hidden');
        motivoIn.required = false;
        motivoIn.value = '';
      }
    });

    form.addEventListener('submit', e => {
      e.preventDefault();
      const data = new FormData(form);
      fetch('/log/php/create_solicitud.php', {
        method: 'POST', body: data
      })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          showToast('Solicitud enviada');
          form.reset();
        } else {
          showToast('Error: ' + json.message);
        }
      })
      .catch(() => showToast('Error de red'));
    });

    document.querySelectorAll('.btn-cancel').forEach(btn =>
      btn.addEventListener('click', () => {
        if (!confirm('¿Cancelar esta solicitud?')) return;
        fetch('/log/php/cancel_solicitud.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'id='+encodeURIComponent(btn.dataset.id)
        })
        .then(r=>r.json())
        .then(json=>{
          if (json.success) {
            btn.disabled=true;
            btn.closest('tr').querySelector('.badge').textContent='Cancelada';
            showToast('Solicitud cancelada');
          } else showToast('Error: '+json.message);
        })
        .catch(()=>showToast('Error de red'));
      })
    );
  });

  // Genera opciones de 30 min
  function generarHoras() {
    let html = '';
    for (let h = 0; h < 24; h++) {
      ['00','30'].forEach(m => {
        const val = `${String(h).padStart(2,'0')}:${m}`;
        html += `<option value="${val}">${val}</option>`;
      });
    }
    return html;
  }

  // Inyecta opciones en los selects al cargar
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('horario_salida').innerHTML += generarHoras();
    document.getElementById('hora_regreso').innerHTML += generarHoras();
  });
  </script>
</body>
</html>
