<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}
$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success'=>false,'message'=>'ID faltante']);
    exit;
}

try {
    $upd = $pdo->prepare("UPDATE solicitudes SET estado='cancelada' WHERE id = ?");
    $upd->execute([$id]);
    echo json_encode(['success'=>true]);
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
