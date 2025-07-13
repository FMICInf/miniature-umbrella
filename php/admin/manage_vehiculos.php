<?php
// Archivo: php/admin/manage_vehiculos.php
session_start();
require_once __DIR__ . '/../config.php';
// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// --- PAGINACIÓN ---
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Total de vehículos para la paginación
$totalStmt = $pdo->query("SELECT COUNT(*) FROM vehiculos");
$totalVehiculos = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($totalVehiculos / $perPage));

// Solo vehículos de la página actual
$stmt = $pdo->prepare(
    "SELECT id, patente, marca, modelo, anio, estado, disponibilidad, capacidad
     FROM vehiculos
     ORDER BY creado_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin – Gestionar Vehículos</title>
  <link rel="stylesheet" href="../../assets/css/style.css" />
  <style>
    /* Modal */
    .modal { position: fixed; top:0; left:0; width:100%; height:100%;
             background:rgba(0,0,0,0.5); display:none;
             align-items:center; justify-content:center;
             z-index: 3000;
           }
    .modal.active { display:flex; }
    .modal-content {
      background:#fff; padding:2rem; border-radius:8px;
      width:90%; max-width:500px; position:relative; box-shadow:0 2px 10px rgba(0,0,0,0.1);
      animation: modalPop .2s;
      text-align: left;
    }
    @keyframes modalPop {
      from { transform: scale(0.93); opacity: 0.3; }
      to { transform: scale(1); opacity: 1; }
    }
    .modal-close {
      position:absolute; top:.5rem; right:.5rem; background:none; border:none;
      font-size:1.5rem; cursor:pointer;
    }
    .modal-content label { display:block; margin-bottom:1rem; }
    .modal-content input, .modal-content select { width:100%; padding:.5rem; margin-top:.25rem; }
    .modal-actions { text-align:right; margin-top:1rem; }
    #toast-container { position:fixed; bottom:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-top:.5rem; border-radius:4px; }
    table { width:100%; border-collapse:collapse; table-layout:fixed; }
    th, td { border:1px solid #ddd; padding:.75rem; word-wrap:break-word; text-align:center; }
    th { background:#f2f2f2; }
    .btn { padding:6px 12px; font-size:14px; }
    .btn-edit, .btn-delete { margin:2px 0; width:80px; }
    th:nth-child(1), td:nth-child(1) { width:50px; }
    th:nth-child(2), td:nth-child(2) { width:100px; }
    th:nth-child(3), td:nth-child(3) { width:120px; }
    th:nth-child(4), td:nth-child(4) { width:120px; }
    th:nth-child(5), td:nth-child(5) { width:80px; }
    th:nth-child(6), td:nth-child(6) { width:140px; }
    th:nth-child(7), td:nth-child(7) { width:140px; }
    th:nth-child(8), td:nth-child(8) { width:100px; }
    th:nth-child(9), td:nth-child(9) { width:160px; }
    /* Paginación */
    .pagination { display:flex; list-style:none; padding:0; margin:18px 0 0 0; justify-content:center;}
    .pagination li { margin: 0 2px; }
    .pagination a, .pagination span {
      padding:.33rem .67rem; border:1px solid #bbc; border-radius:5px;
      text-decoration:none; color:#0a398f; background:#fff;
      font-weight:500; font-size:1.07rem; transition:.14s;
    }
    .pagination .current { background:#004080; color:#fff; border-color:#004080; }
    .pagination a:hover:not(.current) { background:#f2f2f2; }
    
    /* Contenedor para botón + Agregar y ayuda */
    .top-controls {
      position: relative;
      margin-bottom: 1rem;
    }
    #btn-add {
      display: inline-block;
    }
    #helpBtn {
      position: absolute;
      top: 50%;
      right: 0;
      transform: translateY(-50%);
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #004080;
      color: #fff;
      font-weight: bold;
      font-size: 20px;
      line-height: 28px;
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

    }
    #helpBtn:hover, #helpBtn:focus {
      background: #0a6cd4;
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
    <h1>Gestionar Vehículos</h1>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Volver</a></li>
      </ul>
    </nav>
  </header>

  <main class="container">
    <section class="card">
      <div class="top-controls">
        <button id="btn-add" class="btn">+ Agregar Vehículo</button>
        <button id="helpBtn" title="Ayuda">?</button>
      </div>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Patente</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Año</th>
            <th>Estado</th>
            <th>Disponibilidad</th>
            <th>Capacidad</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vehiculos as $v): ?>
          <tr data-id="<?= $v['id'] ?>">
            <td><?= htmlspecialchars($v['id']) ?></td>
            <td><?= htmlspecialchars($v['patente']) ?></td>
            <td><?= htmlspecialchars($v['marca']) ?></td>
            <td><?= htmlspecialchars($v['modelo']) ?></td>
            <td><?= htmlspecialchars($v['anio']) ?></td>
            <td><?= htmlspecialchars($v['estado']) ?></td>
            <td><?= htmlspecialchars($v['disponibilidad']) ?></td>
            <td><?= htmlspecialchars($v['capacidad']) ?></td>
            <td>
              <button class="btn btn-edit" data-id="<?= $v['id'] ?>">Editar</button>
              <button class="btn btn-delete" data-id="<?= $v['id'] ?>">Eliminar</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- PAGINACIÓN -->
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

  <!-- Modal agregar/editar vehículo -->
  <div id="modal" class="modal hidden">
    <form id="formVeh" class="modal-content">
      <button type="button" class="modal-close" aria-label="Cerrar">&times;</button>
      <h2 id="modalTitle">Agregar Vehículo</h2>
      <label>Patente:<input name="patente" required></label>
      <label>Marca:<input name="marca" required></label>
      <label>Modelo:<input name="modelo"></label>
      <label>Año:<input name="anio" type="number" min="1900" max="2100"></label>
      <label>Estado:
        <select name="estado">
          <option value="activo">Activo</option>
          <option value="inactivo">Inactivo</option>
          <option value="en_mantenimiento">En mantenimiento</option>
          <option value="ocupado">Ocupado</option>
        </select>
      </label>
      <label>Disponibilidad:
        <select name="disponibilidad">
          <option value="disponible">Disponible</option>
          <option value="reservado">Reservado</option>
          <option value="ocupado">Ocupado</option>
        </select>
      </label>
      <label>Capacidad pasajeros:<input name="capacidad" type="number" min="1" required></label>
      <input type="hidden" name="id">
      <div class="modal-actions">
        <button type="submit" class="btn">Guardar</button>
        <button type="button" id="btn-cancel" class="btn btn-cancel">Cancelar</button>
      </div>
    </form>
  </div>

  <!-- Modal ayuda con paginación -->
  <div id="helpModal" class="modal">
    <div class="modal-content">
      <button class="close" id="helpClose" aria-label="Cerrar">&times;</button>
      <h3>¿Cómo usar el panel de gestión de vehículos?</h3>

      <!-- Contenedor para páginas -->
      <div id="helpPages" style="min-height: 320px;">

        <!-- Página 1 -->
        <div class="help-page" data-page="1" style="display: block;">
          <p>Este panel te permite agregar, editar y eliminar vehículos del sistema.</p>
          <ul>
            <li><strong>Agregar Vehículo:</strong> Usa el botón "+ Agregar Vehículo" para abrir el formulario y crear un nuevo vehículo.</li>
            <li><strong>Editar Vehículo:</strong> Haz clic en "Editar" para modificar los datos de un vehículo existente.</li>
            <li><strong>Eliminar Vehículo:</strong> Elimina un vehículo con el botón "Eliminar".</li>
          </ul>
        </div>

        <!-- Página 2 -->
        <div class="help-page" data-page="2" style="display: none;">
          <p>Para crear un nuevo vehículo, llena el formulario con patente, marca, modelo, año, estado, disponibilidad y capacidad, luego guarda los datos.</p>
          <img src="../assets/gifs/ADMIN_AGREGAR_VEHICULO.gif" alt="Agregar Vehículo" style="max-width:100%; border-radius:8px; box-shadow:0 3px 16px rgba(28,42,90,0.1);" />
        </div>

        <!-- Página 3 -->
        <div class="help-page" data-page="3" style="display: none;">
          <p>Para editar un vehículo, haz clic en "Editar", modifica los datos en el formulario y guarda los cambios.</p>
          <img src="../assets/gifs/ADMIN_EDITAR_VEHICULO.gif" alt="Editar Vehículo" style="max-width:100%; border-radius:8px; box-shadow:0 3px 16px rgba(28,42,90,0.1);" />
        </div>

        <!-- Página 4 -->
        <div class="help-page" data-page="4" style="display: none;">
          <p>Para eliminar un vehículo, haz clic en "Eliminar" y confirma la acción en la ventana emergente.</p>
          <img src="../assets/gifs/ADMIN_ELIMINAR_VEHICULO.gif" alt="Eliminar Vehículo" style="max-width:100%; border-radius:8px; box-shadow:0 3px 16px rgba(28,42,90,0.1);" />
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

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const modal      = document.getElementById('modal');
    const form       = document.getElementById('formVeh');
    const titleEl    = document.getElementById('modalTitle');
    const btnAdd     = document.getElementById('btn-add');
    const btnClose   = modal.querySelector('.modal-close');
    const btnCancel  = document.getElementById('btn-cancel');
    const toastCont  = document.getElementById('toast-container');

    function showToast(msg) {
      const t = document.createElement('div');
      t.className = 'toast';
      t.textContent = msg;
      toastCont.appendChild(t);
      setTimeout(() => t.remove(), 3000);
    }

    function showModal(mode, data = {}) {
      titleEl.textContent = mode + ' Vehículo';
      ['id','patente','marca','modelo','anio','estado','disponibilidad','capacidad']
        .forEach(name => {
          if (form[name] !== undefined) form[name].value = data[name] ?? '';
        });
      modal.classList.add('active');
      modal.classList.remove('hidden');
    }

    function hideModal() {
      modal.classList.remove('active');
      modal.classList.add('hidden');
      form.reset();
    }

    btnAdd.addEventListener('click', () => showModal('Agregar'));
    btnClose.addEventListener('click', hideModal);
    btnCancel.addEventListener('click', hideModal);

    document.querySelectorAll('.btn-edit').forEach(btn =>
      btn.addEventListener('click', () => {
        fetch(`get_vehiculo.php?id=${btn.dataset.id}`)
          .then(res => res.json())
          .then(json => {
            if (!json.success) return showToast(json.message);
            showModal('Editar', json.data);
          })
          .catch(() => showToast('Error de red al cargar vehículo'));
      })
    );

    document.querySelectorAll('.btn-delete').forEach(btn =>
      btn.addEventListener('click', () => {
        if (!confirm('¿Eliminar este vehículo?')) return;
        fetch('delete_vehiculo.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: `id=${encodeURIComponent(btn.dataset.id)}`
        })
        .then(res => res.json())
        .then(json => {
          if (json.success) location.reload();
          else showToast(json.message);
        })
        .catch(() => showToast('Error de red al eliminar'));
      })
    );

    form.addEventListener('submit', e => {
      e.preventDefault();
      const id  = form.id.value;
      const url = id
        ? 'update_vehiculo.php'
        : 'create_vehiculo.php';
      const data = new URLSearchParams(new FormData(form));
      fetch(url, { method: 'POST', body: data })
        .then(res => res.json())
        .then(json => {
          if (!json.success) showToast(json.message);
          else {
            hideModal();
            setTimeout(() => location.reload(), 500);
          }
        })
        .catch(() => showToast('Error de red al guardar'));
    });

    // Modal ayuda
    const helpModal = document.getElementById('helpModal');
    const helpPages = [...document.querySelectorAll('.help-page')];
    const prevHelpBtn = document.getElementById('prevHelpPage');
    const nextHelpBtn = document.getElementById('nextHelpPage');
    const helpPageIndicator = document.getElementById('helpPageIndicator');
    let currentHelpPage = 1;

    function updateHelpPages() {
      helpPages.forEach(page => {
        page.style.display = parseInt(page.dataset.page) === currentHelpPage ? 'block' : 'none';
      });
      helpPageIndicator.textContent = `Página ${currentHelpPage} de ${helpPages.length}`;
      prevHelpBtn.disabled = currentHelpPage === 1;
      nextHelpBtn.disabled = currentHelpPage === helpPages.length;
    }

    document.getElementById('helpBtn').addEventListener('click', () => {
      helpModal.classList.add('active');
      currentHelpPage = 1;
      updateHelpPages();
    });

    document.getElementById('helpClose').addEventListener('click', () => {
      helpModal.classList.remove('active');
    });

    window.addEventListener('click', (e) => {
      if (e.target === helpModal) helpModal.classList.remove('active');
    });

    prevHelpBtn.addEventListener('click', () => {
      if (currentHelpPage > 1) {
        currentHelpPage--;
        updateHelpPages();
      }
    });

    nextHelpBtn.addEventListener('click', () => {
      if (currentHelpPage < helpPages.length) {
        currentHelpPage++;
        updateHelpPages();
      }
    });
  });
  </script>
</body>
</html>
