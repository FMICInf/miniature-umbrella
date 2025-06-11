<?php
// Archivo: php/get_assigned_times.php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// Parámetros
$origen  = $_GET['origen']  ?? '';
$destino = $_GET['destino'] ?? '';
$fecha   = $_GET['fecha']   ?? '';
if (!$origen || !$destino || !$fecha) {
    echo json_encode(['success'=>false, 'message'=>'Parámetros incompletos']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT a.horario_salida, a.hora_regreso
         FROM asignaciones a
         JOIN rutas r ON a.ruta_id = r.id
         WHERE r.origen = ? AND r.destino = ? AND a.fecha = ?"
    );
    $stmt->execute([$origen, $destino, $fecha]);
    $times = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true, 'data'=>$times]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
