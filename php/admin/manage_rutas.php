<?php
// Archivo: php/admin/manage_rutas.php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// --- PAGINACIÓN ---
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Total de rutas para la paginación
$totalStmt = $pdo->query("SELECT COUNT(*) FROM rutas");
$totalRutas = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($totalRutas / $perPage));

// Consulta paginada de rutas con última solicitud
$stmt = $pdo->prepare("
    SELECT r.id, r.origen, r.destino, r.lat_origen, r.lng_origen, r.lat_destino, r.lng_destino, r.creado_at, 
           MAX(s.fecha_solicitada) AS ultima_solicitud 
      FROM rutas r 
 LEFT JOIN solicitudes s ON s.ruta_id = r.id 
  GROUP BY r.id 
  ORDER BY r.creado_at DESC
  LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    /* Estilos para modal, tabla, paginación, botones, etc. */
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
    .search-row { display:flex; gap:6px; margin-bottom:.8rem; }
    .search-row input { flex:1 1 0; padding:.5rem; border-radius:4px; border:1px solid #b8c6d9; }
    .search-row button { padding:0.5rem 1.1rem; border:none; background:#194185; color:#fff; border-radius:4px; font-size:1rem; cursor:pointer;}
    .btn-delete-route { background: #dc3545; color: #fff; margin-left: 0.5rem; }
    .card { background:#fff; padding:1.5rem; border-radius:8px; margin:2rem auto; max-width:1100px; box-shadow:0 1px 3px rgba(0,0,0,0.10);}
    .table-wrapper { width:100%; overflow-x:auto; }
    table { width:100%; border-collapse:collapse; min-width:800px; margin-top:1rem; }
    th, td { padding:.77rem; border:1px solid #dde; text-align:left; font-size:1.04rem; }
    th { background:#004080; color:#fff; letter-spacing:.5px; }
    tr:nth-child(even) { background: #f7fafd; }
    .btn { padding:.47rem 1.05rem; border:none; border-radius:4px; cursor:pointer; font-size:1rem; }
    .btn-assign { background:#28a745; color:#fff; }
    .btn-edit-route { background:#0b6bf5; color:#fff; }
    .btn-delete-route { background:#dc3545; color:#fff; }
    .form-group select { width:100%; }
    #toast-container { position:fixed; bottom:1rem; right:1rem; z-index:1000; }
    .toast { background:#333; color:#fff; padding:1rem; margin-top:.5rem; border-radius:4px; }
    .pagination { display:flex; list-style:none; padding:0; margin:18px 0 0 0; justify-content:center;}
    .pagination li { margin: 0 2px; }
    .pagination a, .pagination span {
      padding:.33rem .67rem; border:1px solid #bbc; border-radius:5px;
      text-decoration:none; color:#0a398f; background:#fff;
      font-weight:500; font-size:1.07rem; transition:.14s;
    }
    .pagination .current { background:#004080; color:#fff; border-color:#004080; }
    .pagination a:hover:not(.current) { background:#f2f2f2; }
    @media (max-width:900px){ table {min-width:720px;} }
    @media (max-width:600px){ table {min-width:540px;} .modal-content{padding:.7rem;} }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Gestionar Rutas</h1>
    <nav>
      <ul class="menu">
        <li><a href="../admin_dashboard.php">Volver</a></li>
      </ul>
    </nav>
  </header>

  <main class="container">
    <section class="card">
      <button id="btn-add-route" class="btn" style="margin-bottom:10px;">+ Agregar Ruta</button>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Origen</th>
              <th>Destino</th>
              <th>Coordenadas Origen</th>
              <th>Coordenadas Destino</th>
              <th>Creado En</th>
              <th>Últ. Solicitud</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rutas as $r): ?>
            <tr data-id="<?= $r['id'] ?>">
              <td><?= htmlspecialchars($r['id']) ?></td>
              <td><?= htmlspecialchars($r['origen']) ?></td>
              <td><?= htmlspecialchars($r['destino']) ?></td>
              <td>
                <?= htmlspecialchars($r['lat_origen'] . ', ' . $r['lng_origen']) ?>
              </td>
              <td>
                <?= htmlspecialchars($r['lat_destino'] . ', ' . $r['lng_destino']) ?>
              </td>
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

  <!-- Modal Crear/Editar Ruta (con búsqueda estilo usuario)-->
  <div id="routeModal" class="modal">
    <form id="routeForm" class="modal-content" autocomplete="off">
      <button type="button" class="modal-close">&times;</button>
      <h2 id="routeModalTitle">Agregar Ruta</h2>
      <!-- Buscadores manuales -->
      <div class="form-group">
        <label>Buscar Origen</label>
        <div class="search-row">
          <input type="text" id="originSearch" placeholder="Buscar dirección, lugar...">
          <button type="button" id="btnOriginSearch">&#128269;</button>
        </div>
      </div>
      <div class="form-group">
        <label>Buscar Destino</label>
        <div class="search-row">
          <input type="text" id="destinationSearch" placeholder="Buscar dirección, lugar...">
          <button type="button" id="btnDestSearch">&#128269;</button>
        </div>
      </div>
      <div class="form-group">
        <label>Origen seleccionado</label>
        <input name="origen" id="origen" type="text" readonly required>
      </div>
      <div class="form-group">
        <label>Destino seleccionado</label>
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
          durEl     = document.getElementById('route-duration'),
          originSearch = document.getElementById('originSearch'),
          destSearch = document.getElementById('destinationSearch'),
          btnOriginSearch = document.getElementById('btnOriginSearch'),
          btnDestSearch = document.getElementById('btnDestSearch'),
          origenIn = document.getElementById('origen'),
          destinoIn = document.getElementById('destino'),
          latO = document.getElementById('lat_origen'),
          lngO = document.getElementById('lng_origen'),
          latD = document.getElementById('lat_destino'),
          lngD = document.getElementById('lng_destino');

    let map, layer, markerO = null, markerD = null, originName = '', destName = '';
    let mapInitialized = false;

    function showToast(msg) {
      const t = document.createElement('div');
      t.className = 'toast'; t.textContent = msg;
      document.getElementById('toast-container').appendChild(t);
      setTimeout(()=>t.remove(),3000);
    }

    function initMap() {
      if (map) map.remove();
      map = L.map('map').setView([-45.5, -72.0667], 10);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 18}).addTo(map);
      layer = L.geoJSON().addTo(map);
      markerO = markerD = null;
      latO.value = lngO.value = latD.value = lngD.value = '';
      origenIn.value = destinoIn.value = '';
      originSearch.value = destSearch.value = '';
      originName = destName = '';
      distEl.textContent = durEl.textContent = '–';
    }

    function drawRoute(){
      const o = `${lngO.value},${latO.value}`,
            d = `${lngD.value},${latD.value}`;
      fetch(`https://router.project-osrm.org/route/v1/driving/${o};${d}?overview=full&geometries=geojson`)
        .then(res=>res.json()).then(js=>{
          const rt = js.routes[0];
          layer.clearLayers().addData(rt.geometry);
          map.fitBounds(layer.getBounds(),{padding:[20,20]});
          distEl.textContent=(rt.distance/1000).toFixed(1)+' km';
          const m=Math.round(rt.duration/60);
          durEl.textContent=`${Math.floor(m/60)}h ${m%60}m`;
        })
        .catch(()=>showToast('Error calculando ruta'));
    }

    function buscarLugar(input, callback) {
      const q = input.value.trim();
      if(!q) return showToast('Campo vacío');
      fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`)
        .then(r=>r.json())
        .then(results => {
          if(!results.length) return showToast('Lugar no encontrado');
          const {lat, lon, display_name} = results[0];
          callback(parseFloat(lat), parseFloat(lon), display_name);
        })
        .catch(()=>showToast('Error buscando lugar'));
    }

    btnOriginSearch.onclick = function() {
      buscarLugar(originSearch, (lat, lng, name) => {
        latO.value = lat.toFixed(6);
        lngO.value = lng.toFixed(6);
        originName = name;
        origenIn.value = name;
        if(markerO) map.removeLayer(markerO);
        markerO = L.marker([lat, lng]).addTo(map).bindPopup('Origen: '+name).openPopup();
        map.setView([lat, lng], 14);
        if(latO.value && lngO.value && latD.value && lngD.value) drawRoute();
      });
    };

    btnDestSearch.onclick = function() {
      buscarLugar(destSearch, (lat, lng, name) => {
        latD.value = lat.toFixed(6);
        lngD.value = lng.toFixed(6);
        destName = name;
        destinoIn.value = name;
        if(markerD) map.removeLayer(markerD);
        markerD = L.marker([lat, lng]).addTo(map).bindPopup('Destino: '+name).openPopup();
        map.setView([lat, lng], 14);
        if(latO.value && lngO.value && latD.value && lngD.value) drawRoute();
      });
    };

    // Permitir Enter para buscar
    originSearch.addEventListener('keydown', e=>{
      if(e.key==='Enter'){ e.preventDefault(); btnOriginSearch.click(); }
    });
    destSearch.addEventListener('keydown', e=>{
      if(e.key==='Enter'){ e.preventDefault(); btnDestSearch.click(); }
    });

    function showModal(mode, data={}) {
      form.reset(); initMap();
      if (data && data.id) {
        // Si es edición, rellenar datos y mostrar marcadores
        origenIn.value = data.origen||'';
        destinoIn.value = data.destino||'';
        latO.value = data.lat_origen||'';
        lngO.value = data.lng_origen||'';
        latD.value = data.lat_destino||'';
        lngD.value = data.lng_destino||'';
        form.elements['id'].value = data.id;
        if(latO.value && lngO.value){
          markerO = L.marker([latO.value, lngO.value]).addTo(map).bindPopup('Origen: '+origenIn.value).openPopup();
          map.setView([latO.value, lngO.value], 12);
        }
        if(latD.value && lngD.value){
          markerD = L.marker([latD.value, lngD.value]).addTo(map).bindPopup('Destino: '+destinoIn.value).openPopup();
          map.setView([latD.value, lngD.value], 12);
        }
        if(latO.value && lngO.value && latD.value && lngD.value) drawRoute();
      }
      document.getElementById('routeModalTitle').textContent = mode+' Ruta';
      modal.classList.add('active');
      setTimeout(()=>map.invalidateSize(),200);
    }

    function hideModal() {
      modal.classList.remove('active');
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

    document.querySelectorAll('.btn-delete-route').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        if (!confirm(`¿Eliminar ruta #${id}? Esta acción es irreversible.`)) {
          return;
        }
        fetch('delete_ruta.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `id=${encodeURIComponent(id)}`
        })
        .then(r => r.json())
        .then(json => {
          if (json.success) {
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
    // Inicialización
    initMap();
  });
  </script>
  
  <!-- Gifs explicativos -->
  <section class="card" style="max-width: 1100px; margin: 1rem auto;">
    <h3>Guías rápidas para gestionar rutas</h3>
    <div style="display:flex; gap: 20px; flex-wrap: wrap; justify-content: center; margin-top: 15px;">
      <div style="text-align:center; max-width: 320px;">
        <img src="../assets/gifs/ADMIN_AGREGAR_RUTA.gif" alt="Agregar Ruta" style="width: 100%; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <p style="margin-top: 8px; font-weight: 600; color: #004080;">Agregar nueva ruta: Cómo buscar origen y destino y confirmar.</p>
      </div>
      <div style="text-align:center; max-width: 320px;">
        <img src="../assets/gifs/ADMIN_EDITAR_RUTA.gif" alt="Editar Ruta" style="width: 100%; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <p style="margin-top: 8px; font-weight: 600; color: #004080;">Editar ruta existente: Modificar ubicaciones y actualizar.</p>
      </div>
      <div style="text-align:center; max-width: 320px;">
        <img src="../assets/gifs/ADMIN_ELIMINAR_RUTA.gif" alt="Eliminar Ruta" style="width: 100%; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <p style="margin-top: 8px; font-weight: 600; color: #004080;">Eliminar ruta: Confirmación y eliminación definitiva.</p>
      </div>
    </div>
  </section>
  
</body>
</html>
