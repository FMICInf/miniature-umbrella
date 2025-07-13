<?php
// Archivo: php/admin/create_user.php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user_id'])||$_SESSION['rol']!=='admin'){
  echo json_encode(['success'=>false,'message'=>'No autorizado']); exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){
  echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
}
$nombre = trim($_POST['nombre']??'');
$email  = trim($_POST['email']??'');
$rol    = $_POST['rol']??'usuario';
$pass   = $_POST['password']??'';
if(!$nombre||!$email||!$rol||!$pass){
  echo json_encode(['success'=>false,'message'=>'Faltan datos']);
  exit;
}
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
  echo json_encode(['success'=>false,'message'=>'Email inválido']); exit;
}
try {
  $hash = password_hash($pass, PASSWORD_BCRYPT);
  $stmt = $pdo->prepare("INSERT INTO usuarios (nombre,email,password,rol) VALUES (?,?,?,?)");
  $stmt->execute([$nombre, $email, $hash, $rol]);
  echo json_encode(['success'=>true]);
} catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
