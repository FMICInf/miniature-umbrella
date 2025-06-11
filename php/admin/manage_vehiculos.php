<?php
// Archivo: php/admin/manage_vehiculos.php
session_start();
require_once __DIR__ . '/../config.php';
// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
try {
    // Obtener lista de vehículos con capacidad
    $stmt = $pdo->query(
        "SELECT id, patente, marca, modelo, anio, estado, disponibilidad, capacidad
         FROM vehiculos
         ORDER BY creado_at DESC"
    );
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error BD: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin – Gestionar Vehículos</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    /* Modal */
    .modal { position: fixed; top:0; left:0; width:100%; height:100%;
             background:rgba(0,0,0,0.5); display:none;
             align-items:center; justify-content:center; }
    .modal.active { display:flex; }
    .modal-content { background:#fff; padding:2rem; border-radius:8px;
                     width:90%; max-width:500px; position:relative; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
    .modal-close { position:absolute; top:.5rem; right:.5rem; background:none; border:none;
                   font-size:1.5rem; cursor:pointer; }
    .modal-content label { display:block; margin-bottom:1rem; }
    .modal-content input, .modal-content select { width:100%; padding:.5rem; margin-top:.25rem; }
    .modal-actions { text-align:right; margin-top:1rem; }
    /* Toast */
    #toast-container { position:fixed; bottom:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-top:.5rem; border-radius:4px; }

    /* Tabla */
    table { width:100%; border-collapse:collapse; table-layout:fixed; }
    th, td { border:1px solid #ddd; padding:.75rem; word-wrap:break-word; text-align:center; }
    th { background:#f2f2f2; }

    /* Botones */
    .btn { padding:6px 12px; font-size:14px; }
    .btn-edit, .btn-delete { margin:2px 0; width:80px; }

    /* Column widths */
    th:nth-child(1), td:nth-child(1) { width:50px; }
    th:nth-child(2), td:nth-child(2) { width:100px; }
    th:nth-child(3), td:nth-child(3) { width:120px; }
    th:nth-child(4), td:nth-child(4) { width:120px; }
    th:nth-child(5), td:nth-child(5) { width:80px; }
    th:nth-child(6), td:nth-child(6) { width:140px; }
    th:nth-child(7), td:nth-child(7) { width:140px; }
    th:nth-child(8), td:nth-child(8) { width:100px; }
    th:nth-child(9), td:nth-child(9) { width:160px; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Gestionar Vehículos</h1>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_solicitudes.php">Solicitudes</a></li>
        <li><a href="manage_asignaciones.php">Asignaciones</a></li>
        <li><a href="manage_vehiculos.php" class="active">Vehículos</a></li>
        <li><a href="manage_rutas.php">Rutas</a></li>
        <li><a href="manage_users.php">Usuarios</a></li>
        <li><a href="../logout.php">Cerrar sesión</a></li>
      </ul>
    </nav>
  </header>

  <main class="container">
    <section class="card">
      <button id="btn-add" class="btn">+ Agregar Vehículo</button>
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

    // Agregar
    btnAdd.addEventListener('click', () => showModal('Agregar'));

    // Cerrar
    btnClose.addEventListener('click', hideModal);
    btnCancel.addEventListener('click', hideModal);

    // Editar
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

    // Eliminar
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

    // Guardar
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
  });
  </script>
</body>
</html>
