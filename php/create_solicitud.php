<?php
// Archivo: php/create_solicitud.php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}

// Recibir datos
$origen        = trim($_POST['origen']        ?? '');
$destino       = trim($_POST['destino']       ?? '');
if ($destino === 'otro') {
    $destino = trim($_POST['otro_destino'] ?? '');
}
$fecha         = $_POST['fecha_solicitada'] ?? '';
$salida        = $_POST['horario_salida']   ?? '';
$regreso       = $_POST['hora_regreso']     ?? '';
$motivo        = $_POST['motivo']           ?? '';
$motivo_otro   = trim($_POST['motivo_otro'] ?? '');

// Validar básicos
if (!$origen || !$destino || !$fecha || !$salida || !$motivo) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos']);
    exit;
}

// Manejar adjunto
$adjuntoPath = null;
if (!empty($_FILES['adjunto']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = uniqid() . '_' . basename($_FILES['adjunto']['name']);
    if (!move_uploaded_file($_FILES['adjunto']['tmp_name'], $uploadDir.$filename)) {
        echo json_encode(['success'=>false,'message'=>'Error subiendo adjunto']);
        exit;
    }
    $adjuntoPath = 'uploads/' . $filename;
}

try {
    // Lookup / crear ruta (igual que antes)…
    $chk = $pdo->prepare("SELECT id FROM rutas WHERE origen=? AND destino=? AND horario_salida=?");
    $chk->execute([$origen,$destino,$salida]);
    $rutaId = $chk->fetchColumn();
    if (!$rutaId) {
        $insR = $pdo->prepare("INSERT INTO rutas (origen,destino,horario_salida) VALUES (?,?,?)");
        $insR->execute([$origen,$destino,$salida]);
        $rutaId = $pdo->lastInsertId();
    }

    // Insertar solicitud con nuevos campos
    $ins = $pdo->prepare("
      INSERT INTO solicitudes
        (usuario_id, ruta_id, fecha_solicitada,
         horario_salida, hora_regreso,
         motivo, motivo_otro, adjunto, estado)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    $ins->execute([
      $_SESSION['user_id'],
      $rutaId,
      $fecha,
      $salida,
      $regreso ?: null,
      $motivo,
      $motivo === 'Otro' ? $motivo_otro : null,
      $adjuntoPath
    ]);

    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
