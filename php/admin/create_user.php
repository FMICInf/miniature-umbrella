<?php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

// Solo admin + POST
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

// Recoger datos
$nombre   = trim($_POST['nombre']   ?? '');
$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
$rol      = trim($_POST['rol']      ?? '');

if (!$nombre || !$email || !$password || !$rol) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos requeridos']);
    exit;
}

try {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("
      INSERT INTO usuarios (nombre, email, password, rol)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$nombre, $email, $hash, $rol]);
    echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
