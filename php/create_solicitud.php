<?php
// Archivo: php/create_solicitud.php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// 1) Solo usuarios autenticados
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// 2) Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// 3) Recoger y sanitizar inputs
$origen        = trim($_POST['origen']        ?? '');
$destino       = trim($_POST['destino']       ?? '');
if ($destino === 'otro') {
    $destino = trim($_POST['otro_destino'] ?? '');
}
$fecha         = $_POST['fecha_solicitada']  ?? '';
$salida        = $_POST['horario_salida']    ?? '';
$regreso       = $_POST['hora_regreso']      ?? '';
$motivo        = $_POST['motivo']            ?? '';
$motivo_otro   = trim($_POST['motivo_otro']  ?? '');

// 4) Validar campos obligatorios
if (!$origen || !$destino || !$fecha || !$salida || !$motivo) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

// 5) Validar lead time de al menos 2 horas
date_default_timezone_set('America/Santiago');
$requestedDT = DateTime::createFromFormat('Y-m-d H:i', "$fecha $salida");
if (!$requestedDT) {
    echo json_encode(['success' => false, 'message' => 'Fecha u hora inválida']);
    exit;
}
$minLead = (new DateTime('now'))->modify('+2 hours');
if ($requestedDT < $minLead) {
    echo json_encode(['success' => false, 'message' => 'Debe solicitar con al menos 2 horas de anticipación']);
    exit;
}

// 6) Manejar adjunto (opcional)
$adjuntoPath = null;
if (!empty($_FILES['adjunto']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $fname = uniqid() . '_' . basename($_FILES['adjunto']['name']);
    if (move_uploaded_file($_FILES['adjunto']['tmp_name'], $uploadDir . $fname)) {
        $adjuntoPath = 'uploads/' . $fname;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al subir adjunto']);
        exit;
    }
}

// 7) Buscar o crear ruta
try {
    $chk = $pdo->prepare("SELECT id FROM rutas WHERE origen = ? AND destino = ? AND horario_salida = ?");
    $chk->execute([$origen, $destino, $salida]);
    $rutaId = $chk->fetchColumn();
    if (!$rutaId) {
        $insR = $pdo->prepare("INSERT INTO rutas (origen,destino,horario_salida) VALUES (?,?,?)");
        $insR->execute([$origen, $destino, $salida]);
        $rutaId = $pdo->lastInsertId();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al buscar/crear ruta']);
    exit;
}

// 8) Verificar conflicto de horario (solo ruta y fecha)
try {
    $conf = $pdo->prepare("
        SELECT COUNT(*) 
        FROM asignaciones 
        WHERE ruta_id = ? AND fecha = ?
    ");
    $conf->execute([$rutaId, $fecha]);
    if ($conf->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Horario no disponible para esa ruta y fecha']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al verificar disponibilidad']);
    exit;
}

// 9) Insertar solicitud
try {
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
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar solicitud: ' . $e->getMessage()]);
}
