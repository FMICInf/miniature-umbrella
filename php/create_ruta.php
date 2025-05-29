<?php
// Archivo: php/create_ruta.php
session_start();
// DEBUG: retornar datos POST recibidos
if (!empty($_GET['debug'])) {
    header('Content-Type: application/json');
    echo json_encode(['debug' => true, 'post' => $_POST]);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Solo usuarios autenticados pueden agregar rutas
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Recibir datos
$origen  = trim($_POST['origen']  ?? '');
$destino = trim($_POST['destino'] ?? '');
$horario = trim($_POST['horario'] ?? '');

if (!$origen || !$destino || !$horario) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos para crear ruta']);
    exit;
}

try {
    // Verificar si ya existe
    $stmtCheck = $pdo->prepare(
        "SELECT id FROM rutas WHERE origen = ? AND destino = ? AND horario_salida = ?"
    );
    $stmtCheck->execute([$origen, $destino, $horario]);
    $existing = $stmtCheck->fetchColumn();
    if ($existing) {
        echo json_encode([
            'success' => true,
            'id'      => $existing,
            'origen'  => $origen,
            'destino' => $destino,
            'horario' => $horario
        ]);
        exit;
    }

    // Insertar nueva ruta
    $stmt = $pdo->prepare(
        "INSERT INTO rutas (origen, destino, horario_salida) VALUES (?, ?, ?)"
    );
    $stmt->execute([$origen, $destino, $horario]);
    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id'      => $id,
        'origen'  => $origen,
        'destino' => $destino,
        'horario' => $horario
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear ruta: ' . $e->getMessage()
    ]);
}
