<?php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// Validar rol admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

// Parámetros
$id           = intval($_POST['id'] ?? 0);
$conductor_id = intval($_POST['conductor_id'] ?? 0);
$vehiculo_id  = intval($_POST['vehiculo_id'] ?? 0);

if (!$id || !$conductor_id || !$vehiculo_id) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos']);
    exit;
}

try {
    // Obtener ruta y fecha de la solicitud
    $stmt = $pdo->prepare("
        SELECT ruta_id, fecha_solicitada
        FROM solicitudes
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $sol = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sol) {
        echo json_encode(['success'=>false,'message'=>'Solicitud no encontrada']);
        exit;
    }

    $ruta_id = $sol['ruta_id'];
    $fecha   = $sol['fecha_solicitada'];

    // Iniciar transacción
    $pdo->beginTransaction();

    // 1. Actualizar solicitud
    $updSol = $pdo->prepare("
        UPDATE solicitudes
        SET estado = 'confirmada'
        WHERE id = ?
    ");
    $updSol->execute([$id]);

    // 2. Insertar en asignaciones
    $insAsig = $pdo->prepare("
        INSERT INTO asignaciones (vehiculo_id, conductor_id, ruta_id, fecha)
        VALUES (?, ?, ?, ?)
    ");
    $insAsig->execute([$vehiculo_id, $conductor_id, $ruta_id, $fecha]);

    // 3. Marcar vehículo como ocupado
    $updVeh = $pdo->prepare("
        UPDATE vehiculos
        SET disponibilidad = 'ocupado'
        WHERE id = ?
    ");
    $updVeh->execute([$vehiculo_id]);

    $pdo->commit();

    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
