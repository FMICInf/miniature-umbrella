<?php
// Archivo: php/admin/delete_user.php

session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])||$_SESSION['rol']!=='admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']); exit;
}
$id = filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['success'=>false,'message'=>'ID inválido']); exit;
}

try {
    $stmt = $pdo->prepare("
      SELECT id, origen, destino, horario_salida, horario_llegada
      FROM rutas WHERE id = ?
    ");
    $stmt->execute([$id]);
    $ruta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ruta) echo json_encode(['success'=>false,'message'=>'No encontrado']);
    else echo json_encode(['success'=>true,'data'=>$ruta]);
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}

session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// Solo admin + POST
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['success'=>false,'message'=>'ID inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
