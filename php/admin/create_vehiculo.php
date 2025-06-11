<?php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// 1) Solo admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// 2) Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// 3) Recoger y sanitizar inputs
$patente        = trim(filter_input(INPUT_POST, 'patente', FILTER_SANITIZE_STRING));
$marca          = trim(filter_input(INPUT_POST, 'marca',   FILTER_SANITIZE_STRING));
$modelo         = trim(filter_input(INPUT_POST, 'modelo',  FILTER_SANITIZE_STRING));
$anio           = filter_input(INPUT_POST, 'anio',        FILTER_VALIDATE_INT);
$estado         = filter_input(INPUT_POST, 'estado',    FILTER_SANITIZE_STRING);
$disponibilidad = filter_input(INPUT_POST, 'disponibilidad', FILTER_SANITIZE_STRING);
$capacidad      = filter_input(INPUT_POST, 'capacidad',    FILTER_VALIDATE_INT);

// 4) Validar campos obligatorios
if (!$patente || !$marca || !$estado || !$disponibilidad || $capacidad === false) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

try {
    // 5) Insertar con capacidad
    $stmt = $pdo->prepare("
        INSERT INTO vehiculos
          (patente, marca, modelo, anio, estado, disponibilidad, capacidad)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $patente,
        $marca,
        $modelo,
        $anio,
        $estado,
        $disponibilidad,
        $capacidad
    ]);
    echo json_encode([
        'success' => true,
        'id'      => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error BD: ' . $e->getMessage()
    ]);
}
    