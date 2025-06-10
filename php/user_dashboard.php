<?php
// Archivo: php/user_dashboard.php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    header('Location: index.php');
    exit;
}
$userId = $_SESSION['user_id'];

// Métricas (igual que antes)…

// Orígenes y destinos
$origenes = $pdo->query("SELECT DISTINCT origen FROM rutas ORDER BY origen")
                  ->fetchAll(PDO::FETCH_COLUMN);
$destinos = $pdo->query("SELECT DISTINCT destino FROM rutas ORDER BY destino")
                  ->fetchAll(PDO::FETCH_COLUMN);

// Solicitudes existentes, ahora con nuevas columnas
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel de Usuario – Logística</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .hidden { display: none; }
    table { width:100%; border-collapse:collapse; margin-top:1rem; }
    th,td { padding:.75rem; border-bottom:1px solid #ddd; }
    th { background:#004080; color:#fff; }
    .badge { padding:.25em .5em; border-radius:4px; color:#fff; }
    .badge-pendiente  { background:#ffc107; }
    .badge-confirmada { background:#28a745; }
    .badge-cancelada  { background:#dc3545; }
    #toast { position:fixed; top:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-bottom:.5rem; border-radius:4px; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Bienvenido, <?=htmlspecialchars($_SESSION['username'],ENT_QUOTES)?></h1>
    <nav><!-- menú --></nav>
  </header>
  <main class="container">
    <!-- Métricas aquí… -->

    <!-- Solicitar transporte -->
    <section class="card">
      <h2>Solicitar Transporte</h2>
      <form id="solForm" enctype="multipart/form-data" class="form-inline">
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
        </div>
        <div class="form-group">
          <label for="fecha_solicitada">Fecha</label>
          <input type="date" name="fecha_solicitada" id="fecha_solicitada" required>
        </div>
        <div class="form-group">
          <label for="horario_salida">Hora salida</label>
          <input type="time" name="horario_salida" id="horario_salida" required>
        </div>
        <div class="form-group">
          <label><input type="checkbox" id="round_trip" /> Viaje de vuelta?</label>
        </div>
        <div class="form-group hidden" id="returnTimeGroup">
          <label for="hora_regreso">Hora de regreso</label>
          <input type="time" name="hora_regreso" id="hora_regreso">
        </div>
        <div class="form-group">
          <label for="motivo">Motivo</label>
          <select name="motivo" id="motivo">
            <option value="Salida A Terreno">Salida A Terreno</option>
            <option value="Otro">Otro...</option>
          </select>
        </div>
        <div class="form-group hidden" id="motivoOtroGroup">
          <label for="motivo_otro">Especificar motivo</label>
          <input type="text" name="motivo_otro" id="motivo_otro">
        </div>
        <div class="form-group">
          <label for="adjunto">Adjuntar documento</label>
          <input type="file" name="adjunto" id="adjunto" accept=".pdf,.doc,.docx">
        </div>
        <button type="submit">Enviar Solicitud</button>
      </form>
    </section>

    <!-- Mis Solicitudes -->
    <section class="card">
      <h2>Mis Solicitudes</h2>
      <?php if(empty($solicitudes)): ?>
        <p>No tienes solicitudes.</p>
      <?php else: ?>
        <table id="solTable">
          <thead>
            <tr>
              <th>Fecha</th><th>Ruta</th><th>Salida</th><th>Regreso</th>
              <th>Motivo</th><th>Adjunto</th><th>Estado</th><th>Acción</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($solicitudes as $sol): ?>
            <tr data-id="<?=$sol['id']?>">
              <td><?=$sol['fecha_solicitada']?></td>
              <td><?=htmlspecialchars("{$sol['origen']} → {$sol['destino']}")?></td>
              <td><?=$sol['horario_salida']?></td>
              <td><?=$sol['hora_regreso'] ?: '-'?></td>
              <td>
                <?php if($sol['motivo']==='Otro'): ?>
                  <?=htmlspecialchars($sol['motivo_otro'])?>
                <?php else: ?>
                  <?=$sol['motivo']?>
                <?php endif; ?>
              </td>
              <td>
                <?php if($sol['adjunto']): ?>
                  <a href="../<?=$sol['adjunto']?>" target="_blank">Ver</a>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?=$sol['estado']?>"><?=ucfirst($sol['estado'])?></span></td>
              <td>
                <?php if($sol['estado']==='pendiente'): ?>
                  <button class="btn-cancel" data-id="<?=$sol['id']?>">Cancelar</button>
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
  <script src="../assets/js/user_dashboard.js"></script>
</body>
</html>
