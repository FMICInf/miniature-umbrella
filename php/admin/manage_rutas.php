<?php
// Archivo: php/admin/manage_rutas.php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol']!=='admin') {
  header('Location: ../index.php');
  exit;
}
try {
  $stmt = $pdo->query("
    SELECT
      r.id, r.origen, r.destino,
      r.horario_salida, r.horario_llegada,
      r.lat_origen, r.lng_origen,
      r.lat_destino, r.lng_destino,
      r.creado_at,
      MAX(s.fecha_solicitada) AS ultima_solicitud
    FROM rutas r
    LEFT JOIN solicitudes s ON s.ruta_id = r.id
    GROUP BY r.id
    ORDER BY r.creado_at DESC
  ");
  $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  die('Error BD: '.$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin – Gestionar Rutas</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <style>
    .modal { position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center; }
    .modal.active { display:flex; }
    .modal-content { background:#fff;padding:1.5rem;border-radius:8px;width:90%;max-width:700px;position:relative; }
    .modal-close { position:absolute;top:.5rem;right:.5rem;background:none;border:none;font-size:1.5rem;cursor:pointer; }
    .form-group { margin-bottom:1rem; }
    .form-group label { display:block;font-weight:500;margin-bottom:.25rem; }
    .form-grid { display:grid;grid-template-columns:1fr 1fr;gap:1rem; }
#map {
  width: 100%;
  height: 300px;      /* altura fija */
  margin-top: 1rem;
  border: 1px solid #ccc;
}
    .route-info { margin-top:.5rem; font-weight:500; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Gestionar Rutas</h1>
    <nav><ul class="menu">
      <li><a href="../admin_dashboard.php">Dashboard</a></li>
      <li><a href="manage_solicitudes.php">Solicitudes</a></li>
      <li><a href="manage_asignaciones.php">Asignaciones</a></li>
      <li><a href="manage_vehiculos.php">Vehículos</a></li>
      <li><a href="manage_rutas.php" class="active">Rutas</a></li>
      <li><a href="manage_users.php">Usuarios</a></li>
      <li><a href="../logout.php">Cerrar sesión</a></li>
    </ul></nav>
  </header>

  <main class="container">
    <section class="card">
      <button id="btn-add-route" class="btn">+ Agregar Ruta</button>
      <table>
        <thead><tr>
          <th>ID</th><th>Origen</th><th>Destino</th><th>Salida</th><th>Llegada</th><th>Últ. Solicitud</th><th>Acciones</th>
        </tr></thead>
        <tbody>
        <?php foreach($rutas as $r): ?>
          <tr data-id="<?= $r['id'] ?>">
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['origen']) ?></td>
            <td><?= htmlspecialchars($r['destino']) ?></td>
            <td><?= $r['horario_salida'] ?></td>
            <td><?= $r['horario_llegada'] ?></td>
            <td><?= $r['ultima_solicitud']?:'-' ?></td>
            <td>
              <button class="btn btn-edit-route" data-id="<?= $r['id'] ?>">Editar</button>
              <button class="btn btn-delete-route" data-id="<?= $r['id'] ?>">Eliminar</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Modal -->
  <div id="routeModal" class="modal">
    <form id="routeForm" class="modal-content">
      <button type="button" class="modal-close">&times;</button>
      <h2 id="routeModalTitle">Agregar Ruta</h2>
      <div class="form-grid">
        <div class="form-group">
          <label>Origen</label>
          <input name="origen" id="origen" type="text" required>
        </div>
        <div class="form-group">
          <label>Destino</label>
          <input name="destino" id="destino" type="text" required>
        </div>
        <div class="form-group">
          <label>Hora Salida</label>
          <input name="horario_salida" id="horario_salida" type="time" required>
        </div>
        <div class="form-group">
          <label>Hora Llegada</label>
          <input name="horario_llegada" id="horario_llegada" type="time">
        </div>
        <div class="form-group">
          <label>Lat Origen</label>
          <input name="lat_origen" id="lat_origen" type="number" step="0.000001">
        </div>
        <div class="form-group">
          <label>Lng Origen</label>
          <input name="lng_origen" id="lng_origen" type="number" step="0.000001">
        </div>
        <div class="form-group">
          <label>Lat Destino</label>
          <input name="lat_destino" id="lat_destino" type="number" step="0.000001">
        </div>
        <div class="form-group">
          <label>Lng Destino</label>
          <input name="lng_destino" id="lng_destino" type="number" step="0.000001">
        </div>
      </div>

      <div id="map"></div>
      <div class="route-info">
        Distancia: <span id="route-distance">–</span>,
        Duración: <span id="route-duration">–</span>
      </div>

      <input type="hidden" name="id" id="route-id">
      <div class="modal-actions">
        <button type="submit" class="btn">Guardar</button>
        <button type="button" id="routeCancel" class="btn btn-cancel">Cancelar</button>
      </div>
    </form>
  </div>

  <div id="toast-container"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal       = document.getElementById('routeModal');
  const form        = document.getElementById('routeForm');
  const closeBtn    = form.querySelector('.modal-close');
  const cancelBtn   = document.getElementById('routeCancel');
  const distanceEl  = document.getElementById('route-distance');
  const durationEl  = document.getElementById('route-duration');
  let map, routeLayer;

  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  function initMap() {
    if (map) {
      map.remove();
    }
    map = L.map('map').setView([-45.5, -72.0667], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18, attribution: '© OpenStreetMap'
    }).addTo(map);
    routeLayer = L.geoJSON().addTo(map);
  }

  function showRoute() {
    const latO = parseFloat(form.lat_origen.value),
          lngO = parseFloat(form.lng_origen.value),
          latD = parseFloat(form.lat_destino.value),
          lngD = parseFloat(form.lng_destino.value);
    if (isNaN(latO)||isNaN(lngO)||isNaN(latD)||isNaN(lngD)) return;

    const url = `https://router.project-osrm.org/route/v1/driving/${lngO},${latO};${lngD},${latD}?overview=full&geometries=geojson`;
    fetch(url)
      .then(r => r.json())
      .then(data => {
        if (!data.routes || !data.routes.length) return;
        const route = data.routes[0];
        routeLayer.clearLayers();
        routeLayer.addData(route.geometry);
        map.fitBounds(routeLayer.getBounds(), { padding: [20,20] });
        distanceEl.textContent = (route.distance/1000).toFixed(1) + ' km';
        let mins = Math.round(route.duration/60);
        durationEl.textContent = `${Math.floor(mins/60)}h ${mins%60}m`;
      });
  }

  function showModal(mode, data = {}) {
    form.reset();
    form.querySelector('#route-id').value = data.id || '';
    ['origen','destino','horario_salida','horario_llegada',
     'lat_origen','lng_origen','lat_destino','lng_destino']
      .forEach(id => form[id].value = data[id] || '');

    document.getElementById('routeModalTitle').textContent = mode + ' Ruta';
    initMap();
    modal.classList.add('active');

    // Necesario para que Leaflet ajuste el tamaño correctamente
    setTimeout(() => {
      map.invalidateSize();
      if (data.lat_origen && data.lat_destino) {
        showRoute();
      }
    }, 200);

    // Solo después de abrir, escuchamos clicks de coordenadas...
    map.on('click', onMapClick);
  }

  function hideModal() {
    modal.classList.remove('active');
    map.off('click', onMapClick);
  }

  let clickCount = 0;
  function onMapClick(e) {
    clickCount++;
    if (clickCount === 1) {
      L.marker(e.latlng).addTo(map).bindPopup('Origen').openPopup();
      form.lat_origen.value = e.latlng.lat.toFixed(6);
      form.lng_origen.value = e.latlng.lng.toFixed(6);
      showToast('Origen seleccionado, ahora haz click en destino.');
    } else if (clickCount === 2) {
      L.marker(e.latlng).addTo(map).bindPopup('Destino').openPopup();
      form.lat_destino.value = e.latlng.lat.toFixed(6);
      form.lng_destino.value = e.latlng.lng.toFixed(6);
      showRoute();
      map.off('click', onMapClick);
      clickCount = 0;
    }
  }

  closeBtn.addEventListener('click', hideModal);
  cancelBtn.addEventListener('click', hideModal);
  document.getElementById('btn-add-route').addEventListener('click', () => showModal('Agregar'));
  document.querySelectorAll('.btn-edit-route').forEach(btn =>
    btn.addEventListener('click', () => {
      fetch(`get_ruta.php?id=${btn.dataset.id}`)
        .then(r => r.json())
        .then(json => {
          if (json.success) showModal('Editar', json.data);
          else showToast(json.message);
        });
    })
  );

  form.addEventListener('submit', e => {
    e.preventDefault();
    const id = form.id.value;
    const url = id ? 'update_ruta.php' : 'create_ruta.php';
    fetch(url, {
      method: 'POST',
      body: new URLSearchParams(new FormData(form))
    })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        hideModal();
        setTimeout(() => location.reload(), 300);
      } else {
        showToast(json.message);
      }
    });
  });
});
</script>
