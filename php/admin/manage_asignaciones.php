<?php
// Archivo: php/admin/manage_asignaciones.php
session_start();
require_once __DIR__ . '/../config.php';

// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// --- PARÁMETROS DE FILTRO ---
$filtrarConductor = $_GET['conductor'] ?? '';
$filtrarVehiculo  = $_GET['vehiculo']  ?? '';

// --- PAGINACIÓN ---
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// --- LISTA FILTROS (para selects) ---
$conductores = $pdo->query("SELECT DISTINCT u.nombre FROM usuarios u INNER JOIN asignaciones a ON a.conductor_id=u.id ORDER BY u.nombre")->fetchAll(PDO::FETCH_COLUMN);
$vehiculos   = $pdo->query("SELECT DISTINCT v.patente FROM vehiculos v INNER JOIN asignaciones a ON a.vehiculo_id=v.id ORDER BY v.patente")->fetchAll(PDO::FETCH_COLUMN);

// --- CONDICIÓN SQL FILTRO ---
$where = "1=1";
$params = [];
if ($filtrarConductor) {
    $where .= " AND u.nombre = ?";
    $params[] = $filtrarConductor;
}
if ($filtrarVehiculo) {
    $where .= " AND v.patente = ?";
    $params[] = $filtrarVehiculo;
}

// --- TOTAL PARA PAGINACIÓN ---
$totalStmt = $pdo->prepare("
    SELECT COUNT(*) FROM asignaciones a
    JOIN usuarios u ON a.conductor_id = u.id
    JOIN vehiculos v ON a.vehiculo_id  = v.id
    JOIN rutas    r ON a.ruta_id      = r.id
    WHERE $where
");
$totalStmt->execute($params);
$totalAsignaciones = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($totalAsignaciones / $perPage));

// --- CONSULTA PAGINADA ---
$sql = "
    SELECT a.id,
           u.nombre AS conductor,
           v.patente AS vehiculo,
           r.origen,
           r.destino,
           a.fecha,
           a.creado_at
    FROM asignaciones a
    JOIN usuarios u ON a.conductor_id = u.id
    JOIN vehiculos v ON a.vehiculo_id  = v.id
    JOIN rutas    r ON a.ruta_id      = r.id
    WHERE $where
    ORDER BY a.fecha DESC, a.creado_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$asignaciones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Asignaciones</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: center;
        word-wrap: break-word;
        font-size: 0.98rem;
    }
    th {
        background-color: #f2f2f2;
        font-weight: 600;
    }
    th:nth-child(1), td:nth-child(1) { width: 50px; }
    th:nth-child(2), td:nth-child(2) { width: 110px; }
    th:nth-child(3), td:nth-child(3) { width: 135px; }
    th:nth-child(4), td:nth-child(4) { width: 90px; }
    th:nth-child(5), td:nth-child(5) { width: 170px; }
    th:nth-child(6), td:nth-child(6) { width: 135px; }
    .card { background:#fff; padding:1.5rem; border-radius:10px; margin-bottom:2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05);}
    .filter-bar { display:flex; gap:15px; margin-bottom: 1.2rem; align-items: flex-end;}
    .filter-bar select, .filter-bar button {
        padding:.4rem .7rem;
        border-radius:5px;
        border:1px solid #bbb;
        font-size:1rem;
    }
    .pagination { display:flex; list-style:none; padding:0; margin:20px 0 0 0; justify-content:center;}
    .pagination li { margin: 0 2px; }
    .pagination a, .pagination span {
      padding:.33rem .67rem; border:1px solid #bbc; border-radius:5px;
      text-decoration:none; color:#0a398f; background:#fff;
      font-weight:500; font-size:1.07rem; transition:.14s;
    }
    .pagination .current { background:#004080; color:#fff; border-color:#004080; }
    .pagination a:hover:not(.current) { background:#f2f2f2; }
    /* Botón ayuda */
    #btn-ayuda-asignaciones {
      margin-left: 1rem; background: #004080; color: #fff;
      border: none; border-radius: 50%; width: 38px; height: 38px;
      font-size: 1.3rem; cursor: pointer; display: inline-flex;
      align-items: center; justify-content: center;
      transition: background 0.2s;
    }
    #btn-ayuda-asignaciones:hover { background: #0069d9; }
    /* Modal ayuda */
    #modal-ayuda-asignaciones {
      display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.5);
    }
    #modal-ayuda-asignaciones .modal-content {
      background: #fff; border-radius: 12px; max-width: 530px; margin: 70px auto; position: relative; box-shadow: 0 8px 32px #0004;
    }
    #modal-ayuda-asignaciones .cerrar-modal {
      position: absolute; right: 10px; top: 7px; border: none; background: none; font-size: 2rem; color: #888; cursor: pointer;
    }
    @media (max-width: 600px){
      #modal-ayuda-asignaciones .modal-content{width:95vw;}
      #modal-ayuda-asignaciones img{max-width:85vw;}
    }
    </style>
</head>
<body>
    <header class="header-inner" style="display:flex;align-items:center;justify-content:space-between;">
        <div style="display: flex; align-items: center;">
            <h1 style="margin-right: 8px;">Asignaciones</h1>
            <!-- Botón de ayuda -->
            <button id="btn-ayuda-asignaciones" title="Ayuda">?</button>
        </div>
        <nav>
            <ul class="menu">
                <li><a href="../admin_dashboard.php">Volver</a></li>
            </ul>
        </nav>
    </header>
    <main class="container">
        <section class="card">
            <!-- FILTRO BARRA -->
            <form class="filter-bar" method="get" style="margin-bottom:1.4rem;">
                <label>
                  Conductor:
                  <select name="conductor" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($conductores as $c): ?>
                      <option value="<?= htmlspecialchars($c) ?>" <?= $c === $filtrarConductor ? 'selected':''?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>
                  Vehículo:
                  <select name="vehiculo" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($vehiculos as $v): ?>
                      <option value="<?= htmlspecialchars($v) ?>" <?= $v === $filtrarVehiculo ? 'selected':''?>><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <?php if($filtrarConductor||$filtrarVehiculo): ?>
                  <button type="button" onclick="location.href='manage_asignaciones.php'">Limpiar filtros</button>
                <?php endif;?>
            </form>

            <?php if (empty($asignaciones)): ?>
                <p>No hay asignaciones registradas para este filtro.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Vehículo</th>
                            <th>Ruta</th>
                            <th>Creado en</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asignaciones as $a): ?>
                        <tr>
                            <td><?= $a['id'] ?></td>
                            <td><?= htmlspecialchars($a['fecha']) ?></td>
                            <td><?= htmlspecialchars($a['conductor']) ?></td>
                            <td><?= htmlspecialchars($a['vehiculo']) ?></td>
                            <td><?= htmlspecialchars($a['origen'] . ' → ' . $a['destino']) ?></td>
                            <td><?= htmlspecialchars($a['creado_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- PAGINACIÓN -->
                <ul class="pagination">
                <?php if ($page > 1): ?>
                  <li>
                    <a href="?<?=http_build_query(array_merge($_GET,['page'=>$page-1]))?>">&laquo; Anterior</a>
                  </li>
                <?php endif; ?>
                <?php for ($p=1; $p<=$totalPages; $p++): ?>
                  <?php if ($p == $page): ?>
                    <li><span class="current"><?= $p ?></span></li>
                  <?php else: ?>
                    <li>
                      <a href="?<?=http_build_query(array_merge($_GET,['page'=>$p]))?>"><?= $p ?></a>
                    </li>
                  <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                  <li>
                    <a href="?<?=http_build_query(array_merge($_GET,['page'=>$page+1]))?>">Siguiente &raquo;</a>
                  </li>
                <?php endif; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
    <!-- MODAL DE AYUDA ASIGNACIONES -->
    <div id="modal-ayuda-asignaciones" style="display:none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5);">
      <div class="modal-content" style="background: #fff; border-radius: 12px; max-width: 530px; margin: 70px auto; position: relative; box-shadow: 0 8px 32px #0004;">
        <button onclick="cerrarAyudaAsignaciones()" class="cerrar-modal" style="position: absolute; right: 10px; top: 7px; border: none; background: none; font-size: 2rem; color: #888; cursor: pointer;">&times;</button>
        <div style="width: 500px; max-width: 90vw; padding: 30px 30px 20px 30px; display: flex; flex-direction: column; align-items: center;">
          <img src="../../assets/gifs/ADMIN_VIENDO_ASIGNACIONES%20DE%20LOS%20CONDUCTORES.gif" style="max-width:420px; border-radius:10px;" alt="Demostración ver asignaciones">
          <p style="margin: 18px 0 6px 0; font-size: 1.07rem; font-weight: bold;">¿Cómo funciona el panel de Asignaciones?</p>
          <div style="margin-bottom:10px; color:#222;">
            En este panel puedes <b>visualizar todas las asignaciones de viajes</b> realizadas en el sistema, filtrando rápidamente por <b>conductor</b> o <b>vehículo</b> desde las listas desplegables superiores.<br><br>
            En la tabla, se muestra para cada asignación: la fecha, el conductor, el vehículo, la ruta y la fecha de creación. También puedes navegar entre páginas si hay muchas asignaciones.<br><br>
            Aquí también se muestra cómo usar los filtros para consultar las asignaciones por conductor y vehículo, y cómo limpiar los filtros para ver todas las asignaciones nuevamente.
          </div>
        </div>
      </div>
    </div>
    <script>
      function mostrarAyudaAsignaciones() {
        document.getElementById('modal-ayuda-asignaciones').style.display = 'block';
      }
      function cerrarAyudaAsignaciones() {
        document.getElementById('modal-ayuda-asignaciones').style.display = 'none';
      }
      document.getElementById('btn-ayuda-asignaciones').onclick = mostrarAyudaAsignaciones;
    </script>
</body>
</html>
