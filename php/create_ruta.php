<?php
// Archivo: php/create_ruta.php
session_start();
header('Content-Type: application/json');
require_once __DIR__.'/config.php';

// autorización…
if (empty($_SESSION['user_id'])||$_SESSION['rol']!=='usuario') {
  echo json_encode(['success'=>false,'message'=>'No autorizado']); exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {
  echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
}

// recibo y sanitizo
$origen_label  = trim($_POST['origen_label']  ?? '');
$destino_label = trim($_POST['destino_label'] ?? '');
$horario       = trim($_POST['horario_salida'] ?? '');
$latO          = filter_input(INPUT_POST,'lat_origen', FILTER_VALIDATE_FLOAT);
$lngO          = filter_input(INPUT_POST,'lng_origen', FILTER_VALIDATE_FLOAT);
$latD          = filter_input(INPUT_POST,'lat_destino',FILTER_VALIDATE_FLOAT);
$lngD          = filter_input(INPUT_POST,'lng_destino',FILTER_VALIDATE_FLOAT);

if (!$origen_label||!$destino_label||!$horario
    || $latO===false||$lngO===false
    || $latD===false||$lngD===false) {
  echo json_encode(['success'=>false,'message'=>'Faltan datos para crear ruta']); exit;
}

try {
  // si ya existe ruta idéntica (por coords + horario), la devolvemos
  $chk = $pdo->prepare("
    SELECT id FROM rutas
     WHERE lat_origen=? AND lng_origen=? 
       AND lat_destino=? AND lng_destino=? 
       AND horario_salida=?
  ");
  $chk->execute([$latO,$lngO,$latD,$lngD,$horario]);
  if ($id = $chk->fetchColumn()) {
    echo json_encode(['success'=>true,'id'=>$id]); exit;
  }
  // insertamos
  $ins = $pdo->prepare("
    INSERT INTO rutas
      (origen, destino, horario_salida,
       lat_origen, lng_origen, lat_destino, lng_destino)
    VALUES (?,?,?,?,?,?,?)
  ");
  $ins->execute([
    $origen_label,
    $destino_label,
    $horario,
    $latO, $lngO,
    $latD, $lngD
  ]);
  echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
} catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
