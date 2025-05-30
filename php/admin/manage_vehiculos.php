<?php
// Archivo: php/admin/manage_vehiculos.php
session_start();
require_once __DIR__ . '/../config.php';
// Validar sesión y rol de admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
// Obtener lista de vehículos
try {
    $stmt = $pdo->query(
        "SELECT id, patente, marca, modelo, anio, estado, disponibilidad
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin – Gestionar Vehículos</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    /* Modal Styles */
    .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%;
             background: rgba(0, 0, 0, 0.5); display: none;
             align-items: center; justify-content: center; }
    .modal-content { position: relative; background: #fff; padding: 2rem; border-radius: 8px;
                     width: 90%; max-width: 500px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .modal-content label { display: block; margin-bottom: 1rem; }
    .modal-content input, .modal-content select { width: 100%; padding: .5rem; margin-top: .25rem; }
    .modal-actions { text-align: right; margin-top: 1rem; }
    .modal-close { position: absolute; top: .5rem; right: .5rem; background: none; border: none;
                   font-size: 1.5rem; cursor: pointer; }
    /* Toast */
    .toast { position: fixed; bottom: 1rem; right: 1rem;
             background: #333; color: #fff; padding: 1rem; border-radius: 4px;
             opacity: 0.9; }



  table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed; /* Forzar ancho fijo */
  }
  th, td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: center;
    word-wrap: break-word;
  }
  th {
    background-color: #f2f2f2;
  }
  .btn {
    padding: 6px 12px;
    font-size: 14px;
  }
  .btn-edit, .btn-delete {
    margin: 2px 0;
    width: 80px;
  }
  /* Ajustes de columna (opcional) */
  th:nth-child(1), td:nth-child(1) { width: 50px; } /* ID */
  th:nth-child(2), td:nth-child(2) { width: 100px; } /* Patente */
  th:nth-child(3), td:nth-child(3) { width: 120px; } /* Marca */
  th:nth-child(4), td:nth-child(4) { width: 120px; } /* Modelo */
  th:nth-child(5), td:nth-child(5) { width: 80px; } /* Año */
  th:nth-child(6), td:nth-child(6) { width: 140px; } /* Estado */
  th:nth-child(7), td:nth-child(7) { width: 140px; } /* Disponibilidad */
  th:nth-child(8), td:nth-child(8) { width: 150px; } /* Acciones */
</style>

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
  <div id="modal" class="modal">
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
      <input type="hidden" name="id">
      <div class="modal-actions">
        <button type="submit" class="btn">Guardar</button>
        <button type="button" id="btn-cancel" class="btn btn-cancel">Cancelar</button>
      </div>
    </form>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('modal');
  const form = document.getElementById('formVeh');
  const title = document.getElementById('modalTitle');
  const closeBtn = document.querySelector('.modal-close');

  const showModal = (t, data = {}) => {
    title.textContent = t;
    Object.keys(data).forEach(k => { if (form[k]) form[k].value = data[k]; });
    modal.style.display = 'flex';
  };
  const hideModal = () => { modal.style.display = 'none'; form.reset(); };
  const showToast = msg => {
    const t = document.createElement('div');
    t.className = 'toast'; t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  };

  // Agregar
  document.getElementById('btn-add').addEventListener('click', () => showModal('Agregar Vehículo'));
  // Cerrar
  closeBtn.addEventListener('click', hideModal);
  document.getElementById('btn-cancel').addEventListener('click', hideModal);

  // Editar
  document.querySelectorAll('.btn-edit').forEach(btn => btn.addEventListener('click', () => {
    fetch(`get_vehiculo.php?id=${btn.dataset.id}`)
      .then(r => r.json()).then(json => {
        if (json.success) showModal('Editar Vehículo', json.data);
        else showToast(json.message);
      });
  }));

  // Eliminar
  document.querySelectorAll('.btn-delete').forEach(btn => btn.addEventListener('click', () => {
    if (!confirm('¿Eliminar vehículo?')) return;
    fetch('delete_vehiculo.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `id=${btn.dataset.id}`
    })
    .then(r => r.json()).then(json => {
      if (json.success) location.reload(); else showToast(json.message);
    });
  }));

  // Guardar
  form.addEventListener('submit', e => {
    e.preventDefault();
    const id = form.id.value;
    const url = id ? 'update_vehiculo.php' : 'create_vehiculo.php';
    fetch(url, { method: 'POST', body: new URLSearchParams(new FormData(form)) })
      .then(r => r.json()).then(json => {
        if (json.success) { hideModal(); location.reload(); }
        else showToast(json.message);
      });
  });
});

  </script>
</body>
</html>
