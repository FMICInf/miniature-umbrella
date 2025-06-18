<?php
// Archivo: php/admin/manage_users.php
session_start();
require_once __DIR__ . '/../config.php';
// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

try {
    // Obtener todos los usuarios
    $stmt = $pdo->query("
        SELECT id, nombre, email, rol, creado_at
        FROM usuarios
        ORDER BY creado_at DESC
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error BD: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin – Gestionar Usuarios</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    /* Modal y Toast base */
    .modal { position:fixed; top:0; left:0; width:100%; height:100%;
             background:rgba(0,0,0,0.5); display:none;
             align-items:center; justify-content:center; }
    .modal.active { display:flex; }
    .modal-content { background:#fff; padding:2rem; border-radius:8px;
                     width:90%; max-width:400px; position:relative; }
    .modal-close { position:absolute; top:.5rem; right:.5rem; background:none; border:none;
                   font-size:1.5rem; cursor:pointer; }
    .modal-content label { display:block; margin-bottom:1rem; }
    .modal-content input, .modal-content select { width:100%; padding:.5rem; margin-top:.25rem; }
    .modal-actions { text-align:right; margin-top:1rem; }
    #toast-container { position:fixed; bottom:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-top:.5rem; border-radius:4px; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Gestionar Usuarios</h1>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Volver</a></li>

      </ul>
    </nav>
  </header>

  <main class="container">
    <section class="card">
      <button id="btn-add-user" class="btn">+ Agregar Usuario</button>
      <table>
        <thead>
          <tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Creado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach($usuarios as $u): ?>
          <tr data-id="<?= $u['id'] ?>">
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['nombre']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['rol']) ?></td>
            <td><?= htmlspecialchars($u['creado_at']) ?></td>
            <td>
              <button class="btn btn-edit-user" data-id="<?= $u['id'] ?>">Editar</button>
              <button class="btn btn-delete-user" data-id="<?= $u['id'] ?>">Eliminar</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Modal Agregar/Editar Usuario -->
  <div id="userModal" class="modal">
    <form id="userForm" class="modal-content">
      <button type="button" class="modal-close">&times;</button>
      <h2 id="userModalTitle">Agregar Usuario</h2>
      <label>Nombre:
        <input name="nombre" required>
      </label>
      <label>Email:
        <input name="email" type="email" required>
      </label>
      <label>Contraseña:
        <input name="password" type="password" placeholder="Sólo al crear">
      </label>
      <label>Rol:
        <select name="rol">
          <option value="admin">Admin</option>
          <option value="conductor">Conductor</option>
          <option value="usuario" selected>Usuario</option>
        </select>
      </label>
      <input type="hidden" name="id">
      <div class="modal-actions">
        <button type="submit" class="btn">Guardar</button>
        <button type="button" id="userCancel" class="btn btn-cancel">Cancelar</button>
      </div>
    </form>
  </div>

  <div id="toast-container"></div>
  <script src="../../assets/js/manage_users.js"></script>
</body>
</html>
