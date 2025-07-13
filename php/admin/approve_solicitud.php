<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Archivo: php/admin/approve_solicitud.php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../assets/mail_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Validar rol admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

// Parámetros
$id           = intval($_POST['id'] ?? 0);
$conductor_id = intval($_POST['conductor_id'] ?? 0);
$vehiculo_id  = intval($_POST['vehiculo_id'] ?? 0);

if (!$id || !$conductor_id || !$vehiculo_id) {
    echo json_encode(['success'=>false,'message'=>'Faltan datos']);
    exit;
}

try {
    // Obtener ruta y fecha de la solicitud
    $stmt = $pdo->prepare("
        SELECT ruta_id, fecha_solicitada
        FROM solicitudes
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $sol = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sol) {
        echo json_encode(['success'=>false,'message'=>'Solicitud no encontrada']);
        exit;
    }

    $ruta_id = $sol['ruta_id'];
    $fecha   = $sol['fecha_solicitada'];

    // Iniciar transacción
    $pdo->beginTransaction();

    // 1. Actualizar solicitud
    $updSol = $pdo->prepare("
        UPDATE solicitudes
        SET estado = 'confirmada'
        WHERE id = ?
    ");
    $updSol->execute([$id]);

    // 2. Insertar en asignaciones
    $insAsig = $pdo->prepare("
        INSERT INTO asignaciones (vehiculo_id, conductor_id, ruta_id, fecha)
        VALUES (?, ?, ?, ?)
    ");
    $insAsig->execute([$vehiculo_id, $conductor_id, $ruta_id, $fecha]);

    // 3. Marcar vehículo como ocupado
    $updVeh = $pdo->prepare("
        UPDATE vehiculos
        SET disponibilidad = 'ocupado'
        WHERE id = ?
    ");
    $updVeh->execute([$vehiculo_id]);

    $pdo->commit();

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

        $asunto = "Solicitud de Transporte Aprobada";
        $cuerpo = "
        <p>Hola <b>$nombre</b>,<br>
        Tu solicitud <b>#$id</b> ha sido <b>aprobada</b> y fue asignada.<br>
        <strong>Detalles de tu solicitud:</strong>
        <ul>
          <li><b>Departamento:</b> $departamento</li>
          <li><b>Carrera:</b> $carrera</li>
          <li><b>Fecha:</b> $fecha_solicitada</li>
          <li><b>Origen:</b> $origen</li>
          <li><b>Destino:</b> $destino</li>
          <li><b>Hora salida:</b> $horario_salida</li>
          <li><b>Hora regreso:</b> $hora_regreso</li>
          <li><b>Cantidad de pasajeros:</b> $cantidad_pasajeros</li>
          <li><b>Motivo:</b> $motivo_texto</li>
        </ul>
        Revisa tu panel de usuario para más detalles.<br>
        </p>
        ";
        enviarNotificacion($mailTo, $asunto, $cuerpo);
    }

    echo json_encode(['success'=>true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
