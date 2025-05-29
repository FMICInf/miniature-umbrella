<?php
// Archivo: php/get_solicitudes.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Solo usuarios
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    echo json_encode(['success' => false]);
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, estado FROM solicitudes WHERE usuario_id = ?");
$stmt->execute([$userId]);
$sols = $stmt->fetchAll();

echo json_encode([
    'success'      => true,
    'solicitudes'  => $sols
]);
