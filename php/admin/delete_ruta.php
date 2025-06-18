<?php
// Archivo: php/admin/delete_ruta.php

session_start();
require_once __DIR__.'/../config.php';
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user_id'])||$_SESSION['rol']!=='admin'){
  echo json_encode(['success'=>false,'message'=>'No autorizado']); exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){
  echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
}
$id = intval($_POST['id'] ?? 0);
if(!$id){
  echo json_encode(['success'=>false,'message'=>'ID inválido']); exit;
}
try {
  $del = $pdo->prepare("DELETE FROM rutas WHERE id = ?");
  $del->execute([$id]);
  echo json_encode(['success'=>true]);
} catch(PDOException $e){
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
