<?php
// Archivo: php/admin/manage_users.php
session_start();
require_once __DIR__ . '/../config.php';
// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// --- PAGINACIÓN ---
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// Total de usuarios (para paginar)
$totalStmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
$totalUsers = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($totalUsers / $perPage));

// Usuarios de la página actual
$stmt = $pdo->prepare("
    SELECT id, nombre, email, rol, creado_at
    FROM usuarios
    ORDER BY creado_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin – Gestionar Usuarios</title>
  <link rel="stylesheet" href="../../assets/css/style.css" />
  <style>
    /* Estilos generales y modal */
    .modal {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.5); display: none;
      align-items: center; justify-content: center;
      z-index: 3000;
    }
    .modal.active { display: flex; }
    .modal-content {
      background: #fff; padding: 2rem; border-radius: 8px;
      width: 90%; max-width: 480px; position: relative;
      box-shadow: 0 8px 44px rgba(28,42,90,0.18);
      animation: modalPop .2s;
      text-align: left;
    }
    @keyframes modalPop {
      from { transform: scale(0.93); opacity: 0.3; }
      to { transform: scale(1); opacity: 1; }
    }
    .modal-close {
      position: absolute; top: .5rem; right: .5rem; background: none; border: none;
      font-size: 1.5rem; cursor: pointer; color: #254A80;
      transition: color .18s;
    }
    .modal-close:hover { color: #e23333; }
    .modal-content label { display: block; margin-bottom: 1rem; }
    .modal-content input, .modal-content select {
      width: 100%; padding: .5rem; margin-top: .25rem;
    }
    .modal-actions { text-align: right; margin-top: 1rem; }
    #toast-container {
      position: fixed; bottom: 1rem; right: 1rem; z-index: 1000;
    }
    .toast {
      background: #333; color: #fff; padding: 1rem; margin-top: .5rem; border-radius: 4px;
    }
    .pagination {
      display: flex; list-style: none; padding: 0; margin: 18px 0 0 0; justify-content: center;
    }
    .pagination li { margin: 0 2px; }
    .pagination a, .pagination span {
      padding: .33rem .67rem; border: 1px solid #bbc; border-radius: 5px;
      text-decoration: none; color: #0a398f; background: #fff;
      font-weight: 500; font-size: 1.07rem; transition: .14s;
    }
    .pagination .current {
      background: #004080; color: #fff; border-color: #004080;
    }
    .pagination a:hover:not(.current) { background: #f2f2f2; }

    /* Ajuste para el contenedor del botón + Agregar Usuario y el icono ayuda */
    .top-controls {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 1rem;
    }
    #btn-add-user {
      padding: 0.5em 1em;
      font-size: 1rem;
      cursor: pointer;
    }
#helpBtn {
  position: absolute;
  top: 18%;
  right: 320px;  /* a la derecha del botón, ajusta este valor si quieres más o menos espacio */
  transform: translateY(-50%);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #004080;
  color: #fff;
  font-weight: bold;
  font-size: 20px;
  line-height: 32px;
  text-align: center;
  border: none;
  cursor: pointer;
  transition: background 0.18s;
  outline: none;
  user-select: none;

  /* Centramos el contenido */
  display: flex;
  justify-content: center;
  align-items: center;
  line-height: normal;  /* para que no afecte */
  padding: 0;          /* para quitar espacio extra */
}    #helpBtn:hover, #helpBtn:focus {
      background: #0a6cd4;
    }

    th.actions-header {
      width: 110px;
      vertical-align: middle;
    }

    .gif-description {
      font-size: 0.9rem;
      color: #444;
      margin-top: 0.4rem;
      margin-bottom: 1.2rem;
      text-align: center;
    }
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
      <div class="top-controls">
        <button id="btn-add-user" class="btn">+ Agregar Usuario</button>
        <button id="helpBtn" title="Ayuda">?</button>
      </div>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Creado</th>
            <th class="actions-header">Acciones</th>
          </tr>
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

      <!-- Paginación -->
      <ul class="pagination">
        <?php if ($page > 1): ?>
          <li><a href="?page=<?= $page-1 ?>">&laquo; Anterior</a></li>
        <?php endif; ?>
        <?php for ($p=1; $p<=$totalPages; $p++): ?>
          <?php if ($p == $page): ?>
            <li><span class="current"><?= $p ?></span></li>
          <?php else: ?>
            <li><a href="?page=<?= $p ?>"><?= $p ?></a></li>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <li><a href="?page=<?= $page+1 ?>">Siguiente &raquo;</a></li>
        <?php endif; ?>
      </ul>
    </section>
  </main>

  <!-- Modal Agregar/Editar Usuario -->
  <div id="userModal" class="modal">
    <form id="userForm" class="modal-content">
      <button type="button" class="modal-close">&times;</button>
      <h2 id="userModalTitle">Agregar Usuario</h2>
      <label>Nombre:
        <input name="nombre" required />
      </label>
      <label>Email:
        <input name="email" type="email" required />
      </label>
      <label>Contraseña:
        <input name="password" type="password" placeholder="Sólo al crear" />
      </label>
      <label>Rol:
        <select name="rol">
          <option value="admin">Admin</option>
          <option value="conductor">Conductor</option>
          <option value="usuario" selected>Usuario</option>
        </select>
      </label>
      <input type="hidden" name="id" />
      <div class="modal-actions">
        <button type="submit" class="btn">Guardar</button>
        <button type="button" id="userCancel" class="btn btn-cancel">Cancelar</button>
      </div>
    </form>
  </div>

  <!-- Modal ayuda con paginación -->
  <div id="helpModal" class="modal">
    <div class="modal-content">
      <button class="close" id="helpClose">&times;</button>
      <h3>¿Cómo usar el panel de gestión de usuarios?</h3>

      <!-- Contenedor para páginas -->
      <div id="helpPages" style="min-height: 320px;">

        <!-- Página 1 -->
        <div class="help-page" data-page="1" style="display: block;">
          <p>Este panel te permite agregar, editar y eliminar usuarios del sistema.</p>
          <ul>
            <li><strong>Agregar Usuario:</strong> Usa el botón "+ Agregar Usuario" para abrir el formulario y crear un nuevo usuario.</li>
            <li><strong>Editar Usuario:</strong> Haz clic en "Editar" para modificar los datos de un usuario existente.</li>
            <li><strong>Eliminar Usuario:</strong> Elimina un usuario con el botón "Eliminar".</li>
          </ul>
        </div>

        <!-- Página 2 -->
        <div class="help-page" data-page="2" style="display: none;">
          <p>Para crear un nuevo usuario, llena el formulario con el nombre, email, contraseña y rol deseado, luego guarda los datos.</p>
          <img src="../assets/gifs/ADMIN_CREAR_USUARIO.gif" alt="Crear Usuario" style="max-width:100%; border-radius:8px; box-shadow:0 3px 16px rgba(28,42,90,0.1);" />
        </div>

        <!-- Página 3 -->
        <div class="help-page" data-page="3" style="display: none;">
          <p>Para editar un usuario, haz clic en "Editar", modifica los datos en el formulario y guarda los cambios.</p>
          <img src="../assets/gifs/ADMIN_EDITAR_USUARIO.gif" alt="Editar Usuario" style="max-width:100%; border-radius:8px; box-shadow:0 3px 16px rgba(28,42,90,0.1);" />
        </div>

        <!-- Página 4 -->
        <div class="help-page" data-page="4" style="display: none;">
          <p>Para eliminar un usuario, haz clic en "Eliminar" y confirma la acción en la ventana emergente.</p>
          <img src="../assets/gifs/ADMIN_ELIMINAR_USUARIO.gif" alt="Eliminar Usuario" style="max-width:100%; border-radius:8px; box-shadow:0 3px 16px rgba(28,42,90,0.1);" />
        </div>
      </div>

      <!-- Controles paginación -->
      <div style="text-align: center; margin-top: 1rem;">
        <button id="prevHelpPage" class="btn btn-cancel" disabled>Anterior</button>
        <span id="helpPageIndicator" style="margin: 0 1rem; font-weight: 600;">Página 1 de 4</span>
        <button id="nextHelpPage" class="btn">Siguiente</button>
      </div>
    </div>
  </div>

  <div id="toast-container"></div>
  <script src="../../assets/js/manage_users.js"></script>
  <script>
    // Abrir modal de ayuda
    document.getElementById('helpBtn').addEventListener('click', () => {
      document.getElementById('helpModal').classList.add('active');
      currentPage = 1;
      updateHelpPages();
    });
    // Cerrar modal de ayuda
    document.getElementById('helpClose').addEventListener('click', () => {
      document.getElementById('helpModal').classList.remove('active');
    });
    // Cerrar modal de ayuda si clic afuera
    window.addEventListener('click', (e) => {
      const modal = document.getElementById('helpModal');
      if (e.target === modal) modal.classList.remove('active');
    });

    const pages = [...document.querySelectorAll('.help-page')];
    const prevBtn = document.getElementById('prevHelpPage');
    const nextBtn = document.getElementById('nextHelpPage');
    const pageIndicator = document.getElementById('helpPageIndicator');
    let currentPage = 1;
    const totalPages = pages.length;

    function updateHelpPages() {
      pages.forEach((pageDiv) => {
        pageDiv.style.display = (parseInt(pageDiv.dataset.page) === currentPage) ? 'block' : 'none';
      });
      pageIndicator.textContent = `Página ${currentPage} de ${totalPages}`;
      prevBtn.disabled = currentPage === 1;
      nextBtn.disabled = currentPage === totalPages;
    }

    prevBtn.addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        updateHelpPages();
      }
    });

    nextBtn.addEventListener('click', () => {
      if (currentPage < totalPages) {
        currentPage++;
        updateHelpPages();
      }
    });
  </script>
</body>
</html>
