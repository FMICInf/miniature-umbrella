<?php
// Archivo: php/mis_solicitudes.php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    header('Location: index.php');
    exit;
}
$userId = $_SESSION['user_id'];
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;
$estadoFiltro = $_GET['estado'] ?? '';

// --- CORREGIDO: El conteo respeta el filtro de estado ---
$whereTotal = "usuario_id = ?";
$paramsTotal = [$userId];
if ($estadoFiltro) {
    $whereTotal .= " AND estado = ?";
    $paramsTotal[] = $estadoFiltro;
}
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE $whereTotal");
$totalStmt->execute($paramsTotal);
$totalSolicitudes = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalSolicitudes / $perPage));

// --- Consulta de solicitudes (paginada y filtrada) ---
$where = "s.usuario_id = ?";
$params = [$userId];
if ($estadoFiltro) {
    $where .= " AND s.estado = ?";
    $params[] = $estadoFiltro;
}
$solStmt = $pdo->prepare("
  SELECT s.id, s.fecha_solicitada, r.origen, r.destino,
         s.horario_salida, s.hora_regreso,
         s.departamento, s.carrera, s.carrera_otro,
         s.cantidad_pasajeros,
         s.motivo, s.motivo_otro, s.adjunto, s.estado,
         s.motivo_rechazo
  FROM solicitudes s
  JOIN rutas r ON s.ruta_id = r.id
  WHERE $where
  ORDER BY FIELD(s.estado, 'pendiente', 'confirmada', 'cancelada', 'rechazada'),
           s.creado_at DESC, s.id DESC
  LIMIT ? OFFSET ?
");
foreach ($params as $k => $v) {
    $solStmt->bindValue($k+1, $v, is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
}
$solStmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$solStmt->bindValue(count($params)+2, $offset, PDO::PARAM_INT);
$solStmt->execute();
$solicitudes = $solStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Solicitudes – Logística</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    body {
      background: #f5f5f5;
      font-family: sans-serif;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 1000px;
      margin: 2rem auto;
      padding: 0 1rem;
    }
    h2 {
      color: #004080;
      display: inline-block;
      margin-right: 12px;
      font-size: 1.55rem;
      font-weight: 700;
    }
.help-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #1976d2;
  color: #fff;
  font-size: 22px;
  font-weight: bold;
  cursor: pointer;
  vertical-align: middle;
  margin-left: 10px;
  box-shadow: 0 2px 7px rgba(25,118,210,0.12);
  border: none;
  outline: none;
  transition: background 0.18s;
  position: relative;
}.help-icon::before {
  content: "";
  font-size: 21px;
  font-weight: bold;
  display: block;
  line-height: 1;
}
.help-icon:hover, .help-icon:focus { background: #135cb3; }    .help-icon:hover, .help-icon:focus {
      background: #0a6cd4;
      transform: scale(1.10);
    }
    /* Modal styles mejorados */
    #ayudaModal {
      display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100vw; height: 100vh;
      background: rgba(10,24,40,0.30);
      justify-content: center; align-items: center;
    }
    #ayudaModal .modal-content {
      background: #fff;
      border-radius: 16px;
      padding: 34px 38px 23px 38px;
      max-width: 540px;
      width: 98vw;
      box-shadow: 0 8px 44px rgba(28,42,90,0.18);
      text-align: left;
      position: relative;
      margin: 0 auto;
      animation: modalPop .20s;
    }
    @keyframes modalPop {
      from { transform: scale(0.93); opacity: 0.3;}
      to { transform: scale(1); opacity: 1;}
    }
    #ayudaModal .close {
      position: absolute;
      top: 14px; right: 20px;
      color: #254A80;
      font-size: 2.0rem;
      font-weight: bold;
      cursor: pointer;
      border: none;
      background: none;
      line-height: 1;
      transition: color .18s;
    }
    #ayudaModal .close:hover { color: #e23333;}
    #ayudaModal h3 {
      color: #194185; margin-top: 0;
      font-size: 1.17rem;
      margin-bottom: 0.88rem;
    }
    #ayudaModal ul {
      margin:0 0 0 18px; padding:0 0 0 14px; font-size:1.07rem;
    }
    #ayudaModal li { margin-bottom: 4px;}
    #ayudaModal img {
      max-width: 100%;
      width: 420px;
      border-radius: 10px;
      box-shadow: 0 3px 16px rgba(28,42,90,0.10);
      margin-top: 7px;
      margin-bottom: 10px;
    }
    #ayudaModal .ejemplo {
      font-size:1.07rem; color:#223452; margin-bottom: 7px;
    }
    .card {
      background: #fff;
      padding: 1.5rem;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      margin-bottom: 2rem;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    th, td {
      padding: .75rem;
      border: 1px solid #ddd;
      text-align: left;
      word-wrap: break-word;
    }
    th {
      background: #004080;
      color: #fff;
    }
    .badge {
      padding: .25em .5em;
      border-radius: 4px;
      color: #fff;
    }
    .badge-pendiente { background: #ffc107; }
    .badge-confirmada { background: #28a745; }
    .badge-cancelada { background: #dc3545; }
    .btn-cancel {
      background: #e74c3c;
      color: #fff;
      border: none;
      padding: .4rem .8rem;
      border-radius: 4px;
      cursor: pointer;
    }
    .btn-cancel:hover {
      background: #c0392b;
    }
    .pagination {
      display: flex;
      list-style: none;
      padding: 0;
      margin: 1rem 0;
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
    }
    .pagination .current {
      background: #004080;
      color: #fff;
      border-color: #004080;
    }
    form select {
      padding: 0.4rem;
      border-radius: 4px;
      border: 1px solid #ccc;
    }
    #toast {
      position: fixed;
      top: 1.2rem;
      right: 1.2rem;
      z-index: 9999;
    }
    .toast {
      background: #333;
      color: #fff;
      padding: .7rem 1.2rem;
      margin-bottom: .6rem;
      border-radius: 6px;
      font-size: 1.04rem;
      box-shadow: 0 2px 8px rgba(30,40,60,0.13);
    }
    @media (max-width:700px){
      #ayudaModal .modal-content { max-width: 99vw; padding: 0.8rem 0.5rem 1.2rem 0.5rem; }
      #ayudaModal img { width: 96vw;}
    }
  </style>
</head>
<body>
  <div class="container">

    <!-- Caja de botón volver -->
    <div style="
      background: #fff;
      padding: 1rem;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      margin-bottom: 2rem;
      display: flex;
      justify-content: flex-start;
    ">
      <a href="dashboard.php" style="
        background: #004080;
        color: #fff;
        padding: 0.55rem 1.2rem;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
        transition: background 0.2s;
      ">← Volver</a>
    </div>

<section class="card">
  <div style="display:flex;align-items:center;gap:15px;margin-bottom:10px;">
    <h2>Mis Solicitudes</h2>
    <button class="help-icon" id="ayudaBtn" title="¿Cómo funciona este panel?" tabindex="0">?</button>
  </div>

  <!-- Modal de ayuda con gif y explicación -->
  <div id="ayudaModal">
    <div class="modal-content">
      <button class="close" onclick="document.getElementById('ayudaModal').style.display='none'">&times;</button>
      <h3>¿Cómo funciona el panel "Mis Solicitudes"?</h3>
      <div style="margin-bottom:0.9rem;">
        <ul>
          <li><b>Filtrar tus solicitudes:</b> selecciona el estado que te interesa para ver solo las solicitudes pendientes, confirmadas o canceladas.</li>
          <li><b>Navegar por páginas:</b> si tienes muchas solicitudes, puedes avanzar o retroceder entre páginas usando la paginación inferior.</li>
          <li><b>Cancelar solicitudes:</b> si tu solicitud está pendiente, puedes cancelarla directamente desde el panel.</li>
        </ul>
      </div>
      <div style="text-align:center; margin:18px 0;">
        <img src="../assets/gifs/MIS_SOLICITUDES_PANEL.gif" alt="Panel Mis Solicitudes">
      </div>
      <div class="ejemplo">
        <b>¿Qué muestra este ejemplo?</b><br>
        Aquí se muestra cómo puedes utilizar el filtro para ver solicitudes según su estado, cómo navegar entre diferentes páginas de tu historial y cómo visualizar los detalles de cada viaje solicitado. Todo esto facilita el seguimiento de tus solicitudes y el control de tu información personal de transporte.
      </div>
    </div>
  </div>

  <!-- Filtro por estado -->
  <form method="get" style="margin-bottom:1rem;">
    <label for="estado_filtro">Filtrar por estado:</label>
    <select name="estado" id="estado_filtro" onchange="this.form.submit()">
      <option value="">-- Todos --</option>
      <option value="pendiente"   <?= $estadoFiltro==='pendiente'   ? 'selected' : '' ?>>Pendiente</option>
      <option value="confirmada"  <?= $estadoFiltro==='confirmada'  ? 'selected' : '' ?>>Confirmada</option>
      <option value="cancelada"   <?= $estadoFiltro==='cancelada'   ? 'selected' : '' ?>>Cancelada</option>
    </select>
  </form>

  <!-- Tabla -->
  <?php if(empty($solicitudes)): ?>
    <p>No tienes solicitudes.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Fecha</th><th>Depto.</th><th>Carrera</th><th>Ruta</th>
        <th>Salida</th><th>Regreso</th><th>Motivo</th><th>Adjunto</th>
        <th>Cant. Pasajeros</th><th>Estado</th><th>Motivo Rechazo</th><th>Acción</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($solicitudes as $s): ?>
      <tr data-id="<?=$s['id']?>">
        <td><?=htmlspecialchars($s['fecha_solicitada'])?></td>
        <td><?=htmlspecialchars($s['departamento'])?></td>
        <td><?= $s['carrera'] !== 'Otro' ? htmlspecialchars($s['carrera']) : htmlspecialchars($s['carrera_otro']) ?></td>
        <td><?=htmlspecialchars("{$s['origen']} → {$s['destino']}")?></td>
        <td><?=htmlspecialchars($s['horario_salida'])?></td>
        <td><?=$s['hora_regreso']?:'-'?></td>
        <td><?= $s['motivo']==='Otro' ? htmlspecialchars($s['motivo_otro']) : htmlspecialchars($s['motivo']) ?></td>
        <td>
          <?php if($s['adjunto']):?>
            <a href="../<?=htmlspecialchars($s['adjunto'])?>" target="_blank">Ver</a>
          <?php else:?>-<?php endif;?>
        </td>
        <td><?= htmlspecialchars($s['cantidad_pasajeros']) ?></td>
        <td><span class="badge badge-<?=$s['estado']?>"><?=ucfirst($s['estado'])?></span></td>
        <td><?= ($s['estado'] === 'cancelada' && !empty($s['motivo_rechazo'])) ? htmlspecialchars($s['motivo_rechazo']) : '-' ?></td>
        <td>
          <?php if($s['estado']==='pendiente'):?>
            <button class="btn-cancel" data-id="<?=$s['id']?>">Cancelar</button>
          <?php endif;?>
        </td>
      </tr>
    <?php endforeach;?>
    </tbody>
  </table>

  <!-- Paginación -->
  <ul class="pagination">
    <?php if($page>1): ?>
      <li><a href="?page=<?=$page-1?><?= $estadoFiltro ? '&estado='.urlencode($estadoFiltro) : '' ?>">&laquo; Anterior</a></li>
    <?php endif; ?>
    <?php for($p=1;$p<=$totalPages;$p++): ?>
      <?php if($p===$page): ?>
        <li><span class="current"><?=$p?></span></li>
      <?php else: ?>
        <li><a href="?page=<?=$p?><?= $estadoFiltro ? '&estado='.urlencode($estadoFiltro) : '' ?>"><?=$p?></a></li>
      <?php endif;?>
    <?php endfor;?>
    <?php if($page<$totalPages): ?>
      <li><a href="?page=<?=$page+1?><?= $estadoFiltro ? '&estado='.urlencode($estadoFiltro) : '' ?>">Siguiente &raquo;</a></li>
    <?php endif;?>
  </ul>
  <?php endif; ?>
</section>
  </div>

  <div id="toast"></div>
  <script>
    // Modal de ayuda
    document.getElementById('ayudaBtn').onclick = function(){
      document.getElementById('ayudaModal').style.display='flex';
    };
    // Cierra ayuda si se hace click fuera del modal
    window.onclick = function(event) {
      const modal = document.getElementById('ayudaModal');
      if (event.target == modal) modal.style.display = 'none';
    };

    // Cancelar solicitud por AJAX (sin recargar)
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.btn-cancel').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          if (!confirm('¿Cancelar esta solicitud?')) return;
          const id = this.dataset.id;
          fetch('cancel_solicitud.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'id=' + encodeURIComponent(id)
          })
          .then(r=>r.json())
          .then(j=>{
            if(j.success){
              // Cambia el estado en la fila y deshabilita botón
              const row = btn.closest('tr');
              row.querySelector('.badge').textContent = 'Cancelada';
              row.querySelector('.badge').className = 'badge badge-cancelada';
              btn.disabled = true;
              btn.style.opacity = 0.55;
              btn.textContent = 'Cancelado';
              showToast('Solicitud cancelada con éxito.');
            }else{
              showToast(j.message || 'Error al cancelar solicitud');
            }
          })
          .catch(()=>showToast('Error de red'));
        });
      });

      // Toast (mensaje)
      function showToast(msg){
        let t = document.createElement('div');
        t.className = 'toast'; t.textContent = msg;
        document.getElementById('toast').appendChild(t);
        setTimeout(()=>t.remove(),3200);
      }
    });
  </script>
</body>
</html>
