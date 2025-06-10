<?php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])||$_SESSION['rol']!=='admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']); exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
}

$id             = filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
$origen         = trim($_POST['origen']         ?? '');
$destino        = trim($_POST['destino']        ?? '');
$horario_salida = trim($_POST['horario_salida'] ?? '');
$horario_llegada= trim($_POST['horario_llegada']?? '');

if (!$id||!$origen||!$destino||!$horario_salida) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos obligatorios']); exit;
}

try {
    $stmt = $pdo->prepare("
      UPDATE rutas
      SET origen=?, destino=?, horario_salida=?, horario_llegada=?
      WHERE id=?
    ");
    $stmt->execute([$origen,$destino,$horario_salida,$horario_llegada?:null,$id]);
    echo json_encode(['success'=>true]);
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
