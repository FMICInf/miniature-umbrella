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
            r.id, r.origen, r.destino, r.creado_at,
            MAX(s.fecha_solicitada) AS ultima_solicitud
        FROM rutas r
        LEFT JOIN solicitudes s ON s.ruta_id = r.id
        GROUP BY r.id
        ORDER BY r.creado_at DESC
    ");
    $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error BD: ' . htmlspecialchars($e->getMessage()));
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
    /* Mantengo tu estilo original y solo agrego estilo para el botón eliminar si es necesario */
    .modal { position:fixed; top:0; left:0; width:100%; height:100%;
             background:rgba(0,0,0,0.5); display:none;
             align-items:center; justify-content:center; }
    .modal.active { display:flex; }
    .modal-content { background:#fff; padding:1.5rem; border-radius:8px;
                     width:90%; max-width:700px; position:relative; }
    .modal-close { position:absolute; top:.5rem; right:.5rem;
                   background:none; border:none; font-size:1.5rem; cursor:pointer; }
    .form-group { margin-bottom:1rem; }
    .form-group label { display:block; font-weight:500; margin-bottom:.25rem; }
    #map { width:100%; height:300px; margin-top:1rem; border:1px solid #ccc; }
    .route-info { margin-top:.5rem; font-weight:500; }
    /* Botón eliminar estilo si quieres diferenciar */
    .btn-delete-route {
      background: #dc3545;
      color: #fff;
      margin-left: 0.5rem;
    }
    /* Asegurarse de que .card y tabla se ajusten bien en el contenedor */
    .card { background:#fff; padding:1.5rem; border-radius:8px; margin:2rem auto; max-width:960px; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    table { width:100%; border-collapse:collapse; margin-top:1rem; }
    th, td { padding:.75rem; border:1px solid #ddd; text-align:left; }
    th { background:#004080; color:#fff; }
    /* Hacer la tabla scrollable en pantallas pequeñas */
    .table-wrapper { width:100%; overflow-x:auto; }
    .btn { padding:.5rem 1rem; border:none; border-radius:4px; cursor:pointer; }
    .btn-assign { background:#28a745; color:#fff; }
    .btn-edit-route { background:#0069d9; color:#fff; }
    .btn-delete-route { background:#dc3545; color:#fff; }
    .form-group select { width:100%; }
    #toast-container { position:fixed; bottom:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-top:.5rem; border-radius:4px; }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Gestionar Rutas</h1>
    <nav>
      <ul class="menu">
        <li><a href="../dashboard.php">Volver</a></li>

      </ul>
    </nav>
  </header>

  <main class="container">
    <section class="card">
      <button id="btn-add-route" class="btn">+ Agregar Ruta</button>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Origen</th><th>Destino</th>
              <th>Creado En</th><th>Últ. Solicitud</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rutas as $r): ?>
            <tr data-id="<?= $r['id'] ?>">
              <td><?= htmlspecialchars($r['id']) ?></td>
              <td><?= htmlspecialchars($r['origen']) ?></td>
              <td><?= htmlspecialchars($r['destino']) ?></td>
              <td><?= htmlspecialchars($r['creado_at']) ?></td>
              <td><?= $r['ultima_solicitud']?htmlspecialchars($r['ultima_solicitud']):'-' ?></td>
              <td>
                <button class="btn btn-edit-route" data-id="<?= $r['id'] ?>">Editar</button>
                <button class="btn btn-delete-route" data-id="<?= $r['id'] ?>">Eliminar</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <!-- Modal Crear/Editar Ruta -->
  <div id="routeModal" class="modal">
    <form id="routeForm" class="modal-content">
      <button type="button" class="modal-close">&times;</button>
      <h2 id="routeModalTitle">Agregar Ruta</h2>

      <div class="form-group">
        <label>Origen</label>
        <input name="origen" id="origen" type="text" readonly required>
      </div>
      <div class="form-group">
        <label>Destino</label>
        <input name="destino" id="destino" type="text" readonly required>
      </div>

      <!-- Coordenadas ocultas -->
      <input type="hidden" name="lat_origen" id="lat_origen">
      <input type="hidden" name="lng_origen" id="lng_origen">
      <input type="hidden" name="lat_destino" id="lat_destino">
      <input type="hidden" name="lng_destino" id="lng_destino">
      <input type="hidden" name="id" id="route-id">

      <!-- Mapa interactivo -->
      <div id="map" style="width:100%;height:300px;"></div>
      <div class="route-info">
        Distancia: <span id="route-distance">–</span>,
        Duración: <span id="route-duration">–</span>
      </div>

      <div class="modal-actions" style="margin-top:1rem;">
        <button type="submit" class="btn">Guardar</button>
        <button type="button" id="routeCancel" class="btn btn-cancel">Cancelar</button>
      </div>
    </form>
  </div>

  <div id="toast-container"></div>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const modal     = document.getElementById('routeModal'),
          form      = document.getElementById('routeForm'),
          closeBtn  = form.querySelector('.modal-close'),
          cancelBtn = document.getElementById('routeCancel'),
          distEl    = document.getElementById('route-distance'),
          durEl     = document.getElementById('route-duration');
    let map, layer, clickCount = 0;

    function showToast(msg) {
      const t = document.createElement('div');
      t.className = 'toast'; t.textContent = msg;
      document.getElementById('toast-container').appendChild(t);
      setTimeout(()=>t.remove(),3000);
    }

    function initMap() {
      if (map) map.remove();
      map = L.map('map').setView([-45.5, -72.0667], 10);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:18 }).addTo(map);
      layer = L.geoJSON().addTo(map);
      clickCount = 0;
      map.on('click', onMapClick);
      distEl.textContent = '–'; durEl.textContent = '–';
    }

    function onMapClick(e) {
      clickCount++;
      const lat = e.latlng.lat.toFixed(6),
            lng = e.latlng.lng.toFixed(6);

      if (clickCount === 1) {
        const nameO = prompt('Nombre para el origen:');
        if (!nameO) { clickCount = 0; return showToast('Debes nombrar el origen'); }
        form.origen.value     = nameO;
        form.lat_origen.value = lat;
        form.lng_origen.value = lng;
        L.marker(e.latlng).addTo(map).bindPopup(nameO).openPopup();
        showToast('Origen seleccionado. Ahora haz click en destino.');
      }
      else if (clickCount === 2) {
        const nameD = prompt('Nombre para el destino:');
        if (!nameD) { clickCount = 1; return showToast('Debes nombrar el destino'); }
        form.destino.value      = nameD;
        form.lat_destino.value  = lat;
        form.lng_destino.value  = lng;
        L.marker(e.latlng).addTo(map).bindPopup(nameD).openPopup();
        map.off('click', onMapClick);
        drawRoute();
      }
    }

    function drawRoute() {
      const o = form.lng_origen.value+','+form.lat_origen.value,
            d = form.lng_destino.value+','+form.lat_destino.value;
      fetch(`https://router.project-osrm.org/route/v1/driving/${o};${d}?overview=full&geometries=geojson`)
        .then(r=>r.json()).then(js=>{
          const route = js.routes[0];
          layer.clearLayers().addData(route.geometry);
          map.fitBounds(layer.getBounds(),{padding:[20,20]});
          distEl.textContent = (route.distance/1000).toFixed(1)+' km';
          const mins = Math.round(route.duration/60);
          durEl.textContent = Math.floor(mins/60)+'h '+(mins%60)+'m';
        })
        .catch(()=>showToast('Error calculando ruta'));
    }

    function showModal(mode, data={}) {
      form.reset(); initMap();
      ['origen','destino','id','lat_origen','lng_origen','lat_destino','lng_destino']
        .forEach(k => {
          const el = form.elements[k];
          if (el) el.value = data[k] || '';
        });
      document.getElementById('routeModalTitle').textContent = mode+' Ruta';
      modal.classList.add('active');
      setTimeout(()=>map.invalidateSize(),200);
    }

    function hideModal() {
      modal.classList.remove('active');
      if (map) map.off('click', onMapClick);
    }

    closeBtn.addEventListener('click', hideModal);
    cancelBtn.addEventListener('click', hideModal);
    document.getElementById('btn-add-route').addEventListener('click', ()=>showModal('Agregar'));
    document.querySelectorAll('.btn-edit-route').forEach(btn=>
      btn.addEventListener('click', ()=>{
        fetch(`get_ruta.php?id=${btn.dataset.id}`)
          .then(r=>r.json()).then(j=>{
            if (j.success && j.data) {
              showModal('Editar', j.data);
            } else {
              showToast('Error al cargar datos de la ruta');
            }
          }).catch(()=>showToast('Error de red'));
      })
    );
    form.addEventListener('submit', e=>{
      e.preventDefault();
      const url = form.elements['id'].value ? 'update_ruta.php' : 'create_ruta.php';
      fetch(url, { method:'POST', body:new URLSearchParams(new FormData(form)) })
        .then(r=>r.json()).then(j=>{
          if (j.success) {
            hideModal();
            setTimeout(()=>location.reload(),300);
          } else {
            showToast(j.message || 'Error en guardar');
          }
        }).catch(()=>showToast('Error de red al guardar'));
    });

    // === Lógica para ELIMINAR ruta ===
    document.querySelectorAll('.btn-delete-route').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        if (!confirm(`¿Eliminar ruta #${id}? Esta acción es irreversible.`)) {
          return;
        }
        // Petición AJAX a delete_ruta.php
        fetch('delete_ruta.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `id=${encodeURIComponent(id)}`
        })
        .then(r => r.json())
        .then(json => {
          if (json.success) {
            // Remover fila de la tabla
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.remove();
            showToast('Ruta eliminada correctamente');
          } else {
            showToast('Error al eliminar: ' + (json.message||''));
          }
        })
        .catch(() => showToast('Error de red al eliminar'));
      });
    });

    // Inicialización de mapa vacío
    initMap();
  });
  </script>
</body>
</html>
