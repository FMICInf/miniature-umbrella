<?php
// Archivo: php/admin/export_solicitudes.php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    // opcional: redirigir o retornar error
    exit('No autorizado');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=solicitudes.csv');

$out = fopen('php://output', 'w');
// Títulos de columnas: ajusta según lo que desees exportar
$headers = [
    'ID',
    'Usuario',
    'Departamento',
    'Carrera',
    'Ruta',
    'Fecha Solicitada',
    'Horario Salida',
    'Estado',
    'Motivo Rechazo'
];
fputcsv($out, $headers);

// Obtener datos: por ejemplo todas (pendientes, confirmadas, rechazadas, etc.)
// Ajusta la consulta según lo que quieras exportar (p. ej. solo pendientes o todas)
$sql = "
    SELECT 
      s.id,
      u.nombre AS usuario,
      s.departamento,
      s.carrera,
      s.carrera_otro,
      r.origen,
      r.destino,
      s.fecha_solicitada,
      s.horario_salida,
      s.estado,
      s.motivo_rechazo
    FROM solicitudes s
    JOIN usuarios u ON s.usuario_id = u.id
    JOIN rutas r ON s.ruta_id = r.id
    ORDER BY s.creado_at ASC
";
$stmt = $pdo->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Determinar valor de "Carrera" para exportar:
    if (!empty($row['carrera']) && $row['carrera'] !== 'Otro') {
        $carreraText = $row['carrera'];
    } elseif (!empty($row['carrera_otro'])) {
        $carreraText = $row['carrera_otro'];
    } else {
        $carreraText = '';
    }
    // Ruta texto:
    $rutaText = $row['origen'] . ' → ' . $row['destino'];

    $line = [
        $row['id'],
        $row['usuario'],
        $row['departamento'],
        $carreraText,
        $rutaText,
        $row['fecha_solicitada'],
        $row['horario_salida'],
        $row['estado'],
        $row['motivo_rechazo'] ?? ''
    ];
    fputcsv($out, $line);
}
fclose($out);
exit;
