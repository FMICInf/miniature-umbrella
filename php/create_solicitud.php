<?php
// Archivo: php/create_solicitud.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// 1) Validar sesión y rol de usuario
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
$userId = $_SESSION['user_id'];

// 2) Recibir y validar datos
$origen       = trim($_POST['origen']  ?? '');
$destino      = trim($_POST['destino'] ?? '');
if ($destino === 'otro') {
    $destino = trim($_POST['otro_destino'] ?? '');
}
$fechaSolicitada = $_POST['fecha_solicitada'] ?? '';
$horaSalida      = $_POST['horario_salida']  ?? '';

if (!$origen || !$destino || !$fechaSolicitada || !$horaSalida) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

try {
    // 3) Lookup / Insert ruta
    $stmtChk = $pdo->prepare(
        "SELECT id FROM rutas WHERE origen = ? AND destino = ? AND horario_salida = ?"
    );
    $stmtChk->execute([$origen, $destino, $horaSalida]);
    $rutaId = $stmtChk->fetchColumn();
    if (!$rutaId) {
        $stmtInsRuta = $pdo->prepare(
            "INSERT INTO rutas (origen, destino, horario_salida) VALUES (?, ?, ?)"
        );
        $stmtInsRuta->execute([$origen, $destino, $horaSalida]);
        $rutaId = $pdo->lastInsertId();
    }

    // 4) Insertar solicitud (sin columna horario_salida en solicitudes)
    $stmtInsSol = $pdo->prepare(
        "INSERT INTO solicitudes (usuario_id, ruta_id, fecha_solicitada, estado)
         VALUES (?, ?, ?, 'pendiente')"
    );
    $stmtInsSol->execute([$userId, $rutaId, $fechaSolicitada]);
    $solId = $pdo->lastInsertId();

    // 5) Devolver JSON con datos para el cliente
    echo json_encode([
        'success' => true,
        'id'      => $solId,
        'fecha'   => $fechaSolicitada,
        'origen'  => $origen,
        'destino' => $destino,
        'horario' => $horaSalida
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
