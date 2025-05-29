<?php
session_start(); require_once __DIR__ . '/../config.php'; header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id']) || $_SESSION['rol']!=='admin') { echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit; }
$id = filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
$driverId = filter_input(INPUT_POST,'conductor_id',FILTER_VALIDATE_INT);
$vehId    = filter_input(INPUT_POST,'vehiculo_id',FILTER_VALIDATE_INT);
if (!$id||!$driverId||!$vehId) { echo json_encode(['success'=>false,'message'=>'Datos faltantes']); exit; }
try {
    // Obtener datos de solicitud
    $stmt0 = $pdo->prepare("SELECT ruta_id, fecha_solicitada FROM solicitudes WHERE id = ?"); $stmt0->execute([$id]);
    $info = $stmt0->fetch(PDO::FETCH_ASSOC);
    if (!$info) { echo json_encode(['success'=>false,'message'=>'Solicitud no encontrada']); exit; }
    // Actualizar solicitud
    $pdo->prepare("UPDATE solicitudes SET estado='confirmada' WHERE id=?")->execute([$id]);
    // Crear asignación
    $pdo->prepare(
      "INSERT INTO asignaciones (vehiculo_id, conductor_id, ruta_id, fecha)
       VALUES (?, ?, ?, ?)"
    )->execute([$vehId, $driverId, $info['ruta_id'], $info['fecha_solicitada']]);
    echo json_encode(['success'=>true]);
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
