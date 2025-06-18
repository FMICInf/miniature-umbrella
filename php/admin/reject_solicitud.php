<?php
// Archivo: php/admin/reject_solicitud.php
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

// Recoger y validar ID y motivo_rechazo
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$motivo = trim($_POST['motivo_rechazo'] ?? '');

if (!$id) {
    echo json_encode(['success'=>false,'message'=>'ID inválido']); exit;
}
if ($motivo === '') {
    echo json_encode(['success'=>false,'message'=>'Debe indicar motivo de rechazo']); exit;
}

try {
    // Opcional: podrías verificar que la solicitud siga en estado 'pendiente' antes de cambiar, etc.
    $upd = $pdo->prepare("
        UPDATE solicitudes
           SET estado = 'cancelada',
               motivo_rechazo = ?
         WHERE id = ?
    ");
    $upd->execute([$motivo, $id]);
    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: ' . $e->getMessage()]);
}
