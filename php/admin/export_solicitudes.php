<?php
// Archivo: php/admin/export_solicitudes.php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    exit('No autorizado');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=solicitudes.csv');

$out = fopen('php://output', 'w');
// Nuevos títulos de columnas:
$headers = [
    'ID',
    'Usuario',
    'Departamento',
    'Carrera',
    'Ruta',
    'Fecha Solicitada',
    'Horario Salida',
    'Cantidad Pasajeros',
    'Vehículo',
    'Marca',
    'Modelo',
    'Año',
    'Chofer',
    'Email Chofer',
    'Estado',
    'Motivo Rechazo'
];
fputcsv($out, $headers);

// Consulta con JOIN a asignaciones, vehiculos y usuarios (chofer)
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
      s.cantidad_pasajeros,
      v.patente AS vehiculo_patente,
      v.marca AS vehiculo_marca,
      v.modelo AS vehiculo_modelo,
      v.anio AS vehiculo_anio,
      chofer.nombre AS chofer_nombre,
      chofer.email AS chofer_email,
      s.estado,
      s.motivo_rechazo
    FROM solicitudes s
    JOIN usuarios u ON s.usuario_id = u.id
    JOIN rutas r ON s.ruta_id = r.id
    LEFT JOIN asignaciones a ON a.ruta_id = r.id AND a.fecha = s.fecha_solicitada
    LEFT JOIN vehiculos v ON a.vehiculo_id = v.id
    LEFT JOIN usuarios chofer ON a.conductor_id = chofer.id
    ORDER BY s.creado_at ASC
";
$stmt = $pdo->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Determinar valor de "Carrera"
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
        $row['cantidad_pasajeros'] ?? '',
        $row['vehiculo_patente'] ?? '',
        $row['vehiculo_marca'] ?? '',
        $row['vehiculo_modelo'] ?? '',
        $row['vehiculo_anio'] ?? '',
        $row['chofer_nombre'] ?? '',
        $row['chofer_email'] ?? '',
        $row['estado'],
        $row['motivo_rechazo'] ?? ''
    ];
    fputcsv($out, $line);
}
fclose($out);
exit;
