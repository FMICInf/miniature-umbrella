<?php
// Archivo: php/admin/update_user.php

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
$id       = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$nombre   = trim($_POST['nombre'] ?? '');
$email    = trim($_POST['email']  ?? '');
$password = trim($_POST['password'] ?? ''); // opcional
$rol      = trim($_POST['rol']    ?? '');

if (!$id || !$nombre || !$email || !$rol) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos requeridos']);
    exit;
}

try {
    // Armar SQL dinámico para incluir password si se envía
    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
          UPDATE usuarios
          SET nombre = ?, email = ?, password = ?, rol = ?
          WHERE id = ?
        ");
        $stmt->execute([$nombre, $email, $hash, $rol, $id]);
    } else {
        $stmt = $pdo->prepare("
          UPDATE usuarios
          SET nombre = ?, email = ?, rol = ?
          WHERE id = ?
        ");
        $stmt->execute([$nombre, $email, $rol, $id]);
    }
    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
