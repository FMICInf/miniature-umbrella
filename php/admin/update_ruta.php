<?php
// Archivo: php/admin/update_ruta.php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])||$_SESSION['rol']!=='admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

$id             = filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
$origen         = trim($_POST['origen']         ?? '');
$destino        = trim($_POST['destino']        ?? '');
$horario_salida = trim($_POST['horario_salida'] ?? '');
$horario_llegada= trim($_POST['horario_llegada']?? '');
$latO           = filter_input(INPUT_POST, 'lat_origen',  FILTER_VALIDATE_FLOAT);
$lngO           = filter_input(INPUT_POST, 'lng_origen',  FILTER_VALIDATE_FLOAT);
$latD           = filter_input(INPUT_POST, 'lat_destino', FILTER_VALIDATE_FLOAT);
$lngD           = filter_input(INPUT_POST, 'lng_destino', FILTER_VALIDATE_FLOAT);

if (!$id||!$origen||!$destino||!$horario_salida) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos obligatorios']);
    exit;
}

try {
    $stmt = $pdo->prepare("
      UPDATE rutas
      SET origen=?, destino=?, horario_salida=?, horario_llegada=?,
          lat_origen=?, lng_origen=?, lat_destino=?, lng_destino=?
      WHERE id=?
    ");
    $stmt->execute([
      $origen, $destino, $horario_salida, $horario_llegada?:null,
      $latO, $lngO, $latD, $lngD,
      $id
    ]);
    echo json_encode(['success'=>true]);
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
