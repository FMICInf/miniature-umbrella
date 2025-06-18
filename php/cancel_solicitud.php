<?php
// Archivo: php/cancel_solicitud.php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}
$id = filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['success'=>false,'message'=>'ID inválido']);
    exit;
}

try {
    // Cancelar: estado 'cancelada', y limpiamos cualquier motivo_rechazo
    $stmt = $pdo->prepare("
        UPDATE solicitudes
           SET estado = 'cancelada',
               motivo_rechazo = NULL
         WHERE id = ? AND usuario_id = ?
    ");
    $stmt->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
