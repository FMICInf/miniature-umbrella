<?php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// Validar rol admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
}
// Validar ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['success'=>false,'message'=>'ID inválido']); exit;
}
try {
    $pdo->prepare("UPDATE solicitudes SET estado='cancelada' WHERE id=?")
        ->execute([$id]);
    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
