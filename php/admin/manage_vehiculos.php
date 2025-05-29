<?php
session_start();
require_once __DIR__ . '/../config.php';
// Validar rol admin
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php'); exit;
}

// CRUD vehículos: obtener lista
try {
    $stmt = $pdo->query(
        "SELECT id, patente, marca, modelo, anio, estado, disponibilidad
         FROM vehiculos
         ORDER BY creado_at DESC"
    );
    $vehiculos = $stmt->fetchAll();
} catch(PDOException $e) {
    die('Error BD: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin – Gestionar Vehículos</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <header class="header-inner">
    <h1>Gestionar Vehículos</h1>
    <nav><ul class="menu">
      <li><a href="../admin_dashboard.php">Dashboard</a></li>
      <li><a href="manage_solicitudes.php">Solicitudes</a></li>
      <li><a href="manage_asignaciones.php">Asignaciones</a></li>
      <li><a href="manage_vehiculos.php" class="active">Vehículos</a></li>
      <li><a href="manage_rutas.php">Rutas</a></li>
      <li><a href="../logout.php">Cerrar sesión</a></li>
    </ul></nav>
  </header>
  <main class="container">
    <section class="card">
      <button id="btn-add" class="btn">+ Agregar Vehículo</button>
      <table>
        <thead><tr>
          <th>ID</th><th>Patente</th><th>Marca</th><th>Modelo</th><th>Año</th><th>Estado</th><th>Disponibilidad</th><th>Acciones</th>
        </tr></thead>
        <tbody>
          <?php foreach($vehiculos as $v): ?>
          <tr data-id="<?= $v['id'] ?>">
            <td><?= $v['id'] ?></td>
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

  <!-- Modal/Formulario Agregar/Editar -->
  <div id="vehicleModal" class="modal hidden">
    <form id="vehForm" class="modal-content">
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
        <button type="submit" class="btn btn-save">Guardar</button>
        <button type="button" id="btn-cancel" class="btn btn-cancel">Cancelar</button>
      </div>
    </form>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('vehicleModal');
    const form = document.getElementById('vehForm');
    const table = document.querySelector('tbody');
    const showModal = (title, data={}) => {
      document.getElementById('modalTitle').textContent = title;
      for (let [k,v] of Object.entries(data)) if(form[k]) form[k].value = v;
      modal.classList.remove('hidden');
    };
    const hideModal = () => { modal.classList.add('hidden'); form.reset(); };

    // Agregar
    document.getElementById('btn-add').addEventListener('click', () => showModal('Agregar Vehículo'));
    // Cancelar modal
    document.getElementById('btn-cancel').addEventListener('click', hideModal);

    // Editar
    document.querySelectorAll('.btn-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        fetch(`get_vehiculo.php?id=${id}`)
          .then(res => res.json()).then(json => {
            if(json.success) showModal('Editar Vehículo', json.data);
            else alert(json.message);
          });
      });
    });

    // Eliminar
    document.querySelectorAll('.btn-delete').forEach(btn => btn.addEventListener('click', () => {
      if(!confirm('¿Eliminar vehículo?')) return;
      fetch('delete_vehiculo.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${btn.dataset.id}`})
      .then(r=>r.json()).then(j=>{ if(j.success) location.reload(); else alert(j.message); });
    }));

    // Guardar (create/update)
    form.addEventListener('submit', e => {
      e.preventDefault();
      const url = form.id.value ? 'update_vehiculo.php' : 'create_vehiculo.php';
      const body = new URLSearchParams(new FormData(form));
      fetch(url, {method:'POST',body})
        .then(res=>res.json()).then(j=>{
          if(j.success) location.reload(); else alert(j.message);
        });
    });
  });
  </script>