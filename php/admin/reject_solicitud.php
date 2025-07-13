<?php
// Archivo: php/admin/reject_solicitud.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../assets/mail_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Validar rol admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit;
}

// Recoger y validar ID y motivo_rechazo
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$motivo = trim($_POST['motivo_rechazo'] ?? '');

if (!$id) {
    echo json_encode(['success'=>false,'message'=>'ID inválido']); exit;
}
if ($motivo === '') {
    echo json_encode(['success'=>false,'message'=>'Debe indicar motivo de rechazo']); exit;
}

try {
    $upd = $pdo->prepare("
        UPDATE solicitudes
           SET estado = 'cancelada',
               motivo_rechazo = ?
         WHERE id = ?
    ");
    $upd->execute([$motivo, $id]);

    // --- Notificación por correo con resumen ---
    $stmtUser = $pdo->prepare("
        SELECT u.email, u.nombre, s.fecha_solicitada, r.origen, r.destino, s.cantidad_pasajeros,
               s.motivo, s.motivo_otro, s.departamento, s.carrera, s.carrera_otro, s.horario_salida, s.hora_regreso
        FROM solicitudes s
        JOIN usuarios u ON s.usuario_id = u.id
        JOIN rutas r ON s.ruta_id = r.id
        WHERE s.id = ?
    ");
    $stmtUser->execute([$id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $mailTo = $user['email'];
        $nombre = $user['nombre'];
        $fecha_solicitada = $user['fecha_solicitada'];
        $origen = $user['origen'];
        $destino = $user['destino'];
        $cantidad_pasajeros = $user['cantidad_pasajeros'];
        $motivo_texto = $user['motivo'] === 'Otro' ? $user['motivo_otro'] : $user['motivo'];
        $departamento = $user['departamento'];
        $carrera = ($user['carrera'] === 'Otro' || empty($user['carrera'])) ? $user['carrera_otro'] : $user['carrera'];
        $horario_salida = $user['horario_salida'];
        $hora_regreso = $user['hora_regreso'] ?: '-';

        $asunto = "Solicitud de Transporte Rechazada";
        $cuerpo = "
        <p>Hola <b>$nombre</b>,<br>
        Tu solicitud <b>#$id</b> fue <b>rechazada</b>.<br>
        <b>Motivo:</b> <span style='color:#c00;'>".htmlspecialchars($motivo)."</span>
        <br><strong>Detalles de tu solicitud:</strong>
        <ul>
          <li><b>Departamento:</b> $departamento</li>
          <li><b>Carrera:</b> $carrera</li>
          <li><b>Fecha:</b> $fecha_solicitada</li>
          <li><b>Origen:</b> $origen</li>
          <li><b>Destino:</b> $destino</li>
          <li><b>Hora salida:</b> $horario_salida</li>
          <li><b>Hora regreso:</b> $hora_regreso</li>
          <li><b>Cantidad de pasajeros:</b> $cantidad_pasajeros</li>
          <li><b>Motivo original:</b> $motivo_texto</li>
        </ul>
        </p>
        ";
        enviarNotificacion($mailTo, $asunto, $cuerpo);
    }

    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Error BD: ' . $e->getMessage()]);
}
