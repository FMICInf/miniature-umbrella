<?php
// Archivo: php/admin/create_ruta.php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// Solo admin puede
if (empty($_SESSION['user_id']) || $_SESSION['rol']!=='admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

// Recibir y sanear
$origen   = trim($_POST['origen']   ?? '');
$destino  = trim($_POST['destino']  ?? '');
$latO     = filter_input(INPUT_POST, 'lat_origen',  FILTER_VALIDATE_FLOAT);
$lngO     = filter_input(INPUT_POST, 'lng_origen',  FILTER_VALIDATE_FLOAT);
$latD     = filter_input(INPUT_POST, 'lat_destino', FILTER_VALIDATE_FLOAT);
$lngD     = filter_input(INPUT_POST, 'lng_destino', FILTER_VALIDATE_FLOAT);

if (!$origen || !$destino || $latO===false || $lngO===false || $latD===false || $lngD===false) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos obligatorios']);
    exit;
}

try {
    $stmt = $pdo->prepare("
      INSERT INTO rutas
        (origen, destino, lat_origen, lng_origen, lat_destino, lng_destino)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $origen, $destino,
      $latO,   $lngO,
      $latD,   $lngD
    ]);
    echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
