<?php
// Archivo: php/create_solicitud.php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// 1) Autorización
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// 2) Datos de ruta / manual
$rutaId  = $_POST['ruta_id'] ?: null;
$origen  = trim($_POST['origen']  ?? '');
$destino = trim($_POST['destino'] ?? '');
if ($destino === 'Otro') {
    $destino = trim($_POST['otro_destino'] ?? '');
}

// 3) Departamento / Carrera
$departamento     = trim($_POST['departamento'] ?? '');
$carrera_raw      = trim($_POST['carrera']      ?? '');
$carrera_otro_raw = trim($_POST['carrera_otro'] ?? '');

// Si el depto es “Otro” guardamos directamente el texto libre en carrera
if ($departamento === 'Otro') {
    $carrera      = $carrera_otro_raw;
    $carrera_otro = null;
}
// Si eligió “Otro” dentro del select de carreras, lo guardamos en carrera_otro
elseif ($carrera_raw === 'Otro') {
    $carrera      = null;
    $carrera_otro = $carrera_otro_raw;
}
// En cualquier otro caso usamos la carrera seleccionada
else {
    $carrera      = $carrera_raw;
    $carrera_otro = null;
}

// 4) Resto de campos
$fecha       = $_POST['fecha_solicitada'] ?? '';
$salida      = $_POST['horario_salida']   ?? '';
$regreso     = $_POST['hora_regreso']     ?? null;

$motivo      = trim($_POST['motivo']      ?? '');
$motivo_otro = ($motivo === 'Otro')
              ? trim($_POST['motivo_otro'] ?? '')
              : null;

// 5) Validar obligatorios
if (
    // ruta o bien manual origen+destino
    (!$rutaId && (!$origen || !$destino)) ||
    // departamento + carrera
    !$departamento || !$carrera ||
    // fecha, hora y motivo
    !$fecha || !$salida || !$motivo
) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

// 6) Manejar adjunto
$adjunto = null;
if (!empty($_FILES['adjunto']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
    $uploaddir = __DIR__ . '/../uploads/';
    if (!is_dir($uploaddir)) mkdir($uploaddir, 0755, true);
    $file = uniqid() . '_' . basename($_FILES['adjunto']['name']);
    if (move_uploaded_file($_FILES['adjunto']['tmp_name'], "$uploaddir$file")) {
        $adjunto = 'uploads/' . $file;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error subiendo adjunto']);
        exit;
    }
}

// 7) Si no llegó ruta_id, buscamos o creamos una ruta manual
if (!$rutaId) {
    try {
        $chk = $pdo->prepare("
            SELECT id FROM rutas
             WHERE origen = ? AND destino = ? AND horario_salida = ?
        ");
        $chk->execute([$origen, $destino, $salida]);
        $rutaId = $chk->fetchColumn();
        if (!$rutaId) {
            $insR = $pdo->prepare("
                INSERT INTO rutas (origen, destino, horario_salida)
                VALUES (?, ?, ?)
            ");
            $insR->execute([$origen, $destino, $salida]);
            $rutaId = $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Error al crear ruta: '.$e->getMessage()]);
        exit;
    }
}

// 8) Insertar solicitud
try {
    $ins = $pdo->prepare("
      INSERT INTO solicitudes
        (usuario_id, ruta_id,
         departamento, carrera, carrera_otro,
         fecha_solicitada, horario_salida, hora_regreso,
         motivo, motivo_otro, adjunto, estado)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    $ins->execute([
      $_SESSION['user_id'],
      $rutaId,
      $departamento,
      $carrera,
      $carrera_otro,
      $fecha,
      $salida,
      $regreso ?: null,
      $motivo,
      $motivo_otro,
      $adjunto
    ]);
    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
