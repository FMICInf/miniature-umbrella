<?php
// Archivo: php/admin_dashboard.php
session_start();
require_once __DIR__ . '/config.php';
// Validar rol de admin
if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: index.php');
    exit;
}
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES);

// Obtener conteo de solicitudes pendientes para notificación
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'");
    $pendingCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    // En caso de error, consideramos 0
    $pendingCount = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel de Administrador – Logística</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    body { background:#f5f5f5; margin:0; font-family:sans-serif; }
    .header-inner { background:#004080; color:#fff; padding:1rem; display:flex; justify-content: space-between; align-items: center; }
    .header-inner h1 { margin:0;font-size:1.5rem; }
    .container { max-width:960px; margin:2rem auto; padding:0 1rem; }
    .cards-grid {
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
      gap:1.5rem;
    }
    .card-option {
      position: relative; /* para posicionar badge dentro */
      background:#fff;
      border-radius:8px;
      box-shadow:0 2px 6px rgba(0,0,0,0.1);
      text-align:center;
      padding:2rem 1rem;
      transition:transform .15s ease,box-shadow .15s ease;
    }
    .card-option:hover {
      transform:translateY(-4px);
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
    }
    .card-option a {
      text-decoration:none;
      color:#004080;
      display:block;
      margin-top:1rem;
      font-weight:600;
    }
    .card-option .icon {
      font-size:2.5rem;
      margin-bottom:.5rem;
      color:#004080;
    }
    /* Badge de notificación */
    .badge-notif {
      position: absolute;
      top: 0.75rem;
      right: 0.75rem;
      background: #dc3545;
      color: #fff;
      border-radius: 50%;
      padding: 0.25rem 0.5rem;
      font-size: 0.8rem;
      line-height: 1;
    }
  </style>
  <!-- Iconos Font Awesome (si ya lo usas) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <header class="header-inner">
    <h1>Panel de Administrador</h1>
    <p>Bienvenido, <?= $username ?></p>

    <nav>
      <ul class="menu">
        <li><a href="dashboard.php">Volver</a></li>
      </ul>
    </nav>
  </header>

  <main class="container">
    <div class="cards-grid">

      <div class="card-option">
        <div class="icon"><i class="fas fa-users"></i></div>
        <h3>Gestionar Usuarios</h3>
        <a href="admin/manage_users.php">Ir a Usuarios →</a>
      </div>

      <div class="card-option">
        <div class="icon"><i class="fas fa-truck-moving"></i></div>
        <h3>Gestionar Vehículos</h3>
        <a href="admin/manage_vehiculos.php">Ir a Vehículos →</a>
      </div>

      <div class="card-option">
        <div class="icon"><i class="fas fa-route"></i></div>
        <h3>Gestionar Rutas</h3>
        <a href="admin/manage_rutas.php">Ir a Rutas →</a>
      </div>

      <div class="card-option">
        <div class="icon"><i class="fas fa-file-alt"></i></div>
        <h3>Ver Solicitudes</h3>
        <?php if ($pendingCount > 0): ?>
          <span class="badge-notif"><?= $pendingCount ?></span>
        <?php endif; ?>
        <a href="admin/manage_solicitudes.php">Ir a Solicitudes →</a>
      </div>

      <div class="card-option">
        <div class="icon"><i class="fas fa-calendar-check"></i></div>
        <h3>Ver Asignaciones</h3>
        <a href="admin/manage_asignaciones.php">Ir a Asignaciones →</a>
      </div>

      <div class="card-option">
        <div class="icon"><i class="fas fa-sign-out-alt"></i></div>
        <h3>Cerrar Sesión</h3>
        <a href="logout.php">Cerrar sesión →</a>
      </div>

    </div>
  </main>
</body>
</html>
