<?php
// Archivo: php/admin/export_solicitudes.php
session_start();
require_once __DIR__ . '/../config.php';

// Sólo admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    exit('No autorizado');
}

// Cabeceras para descarga Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=solicitudes.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Escritura de BOM para Excel en UTF-8
echo "\xEF\xBB\xBF";

// Consulta de todas las solicitudes
$stmt = $pdo->query("
  SELECT 
    s.id,
    u.nombre AS usuario,
    r.origen,
    r.destino,
    s.fecha_solicitada,
    s.horario_salida,
    s.hora_regreso,
    s.motivo,
    s.motivo_otro,
    s.adjunto,
    s.estado,
    s.creado_at
  FROM solicitudes s
  JOIN usuarios u ON s.usuario_id = u.id
  JOIN rutas   r ON s.ruta_id     = r.id
  ORDER BY s.creado_at DESC
");

// Encabezados de columna
$columns = [
  'ID','Usuario','Origen','Destino','Fecha','Hora Salida',
  'Hora Regreso','Motivo','Motivo Otro','Adjunto URL','Estado','Creada En'
];
echo implode("\t", $columns) . "\r\n";

// Recorre resultados
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Si motivo != Otro limpiamos el campo motivo_otro
    $motivoOt = ($row['motivo'] === 'Otro') 
                 ? $row['motivo_otro'] 
                 : '';
    // Monta fila
    $fields = [
      $row['id'],
      $row['usuario'],
      $row['origen'],
      $row['destino'],
      $row['fecha_solicitada'],
      $row['horario_salida'],
      $row['hora_regreso'] ?: '',
      $row['motivo'],
      $motivoOt,
      $row['adjunto'] ?: '',
      $row['estado'],
      $row['creado_at']
    ];
    echo implode("\t", $fields) . "\r\n";
}
exit;
