<?php
// Archivo: php/user_dashboard.php
session_start();
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol'] !== 'usuario') {
    header('Location: index.php');
    exit;
}
$userId = $_SESSION['user_id'];

// --- Parámetros de paginación ---
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Métricas
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ?");
$totalStmt->execute([$userId]);
$totalSolicitudes = (int)$totalStmt->fetchColumn();

$pendStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'pendiente'");
$pendStmt->execute([$userId]);
$pendientes = (int)$pendStmt->fetchColumn();

$confStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'confirmada'");
$confStmt->execute([$userId]);
$confirmadas = (int)$confStmt->fetchColumn();

$canStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'cancelada'");
$canStmt->execute([$userId]);
$canceladas = (int)$canStmt->fetchColumn();

$totalPages = (int)ceil($totalSolicitudes / $perPage);

// Orígenes y destinos manuales
$origenes = $pdo->query("SELECT DISTINCT origen FROM rutas ORDER BY origen")->fetchAll(PDO::FETCH_COLUMN);
$destinos = $pdo->query("SELECT DISTINCT destino FROM rutas ORDER BY destino")->fetchAll(PDO::FETCH_COLUMN);

// Solicitudes existentes (página actual), incluyendo motivo_rechazo
$solStmt = $pdo->prepare("
    SELECT s.id, s.fecha_solicitada, r.origen, r.destino,
           s.horario_salida, s.hora_regreso,
           s.departamento, s.carrera, s.carrera_otro,
           s.motivo, s.motivo_otro, s.adjunto, s.estado,
           s.motivo_rechazo
    FROM solicitudes s
    JOIN rutas r ON s.ruta_id = r.id
    WHERE s.usuario_id = ?
    ORDER BY s.fecha_solicitada DESC
    LIMIT ? OFFSET ?
");
$solStmt->bindValue(1, $userId,  PDO::PARAM_INT);
$solStmt->bindValue(2, $perPage, PDO::PARAM_INT);
$solStmt->bindValue(3, $offset,  PDO::PARAM_INT);
$solStmt->execute();
$solicitudes = $solStmt->fetchAll(PDO::FETCH_ASSOC);

// Generador de opciones de hora cada 30 min
function generarHoras(){
    $opts = '';
    for($h = 0; $h < 24; $h++){
        foreach([0,30] as $m){
            $v = sprintf('%02d:%02d',$h,$m);
            $opts .= "<option value=\"$v\">$v</option>\n";
        }
    }
    return $opts;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel de Usuario – Logística</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <style>
    body{background:#f5f5f5;margin:0;font-family:sans-serif}
    .container{max-width:960px;margin:2rem auto;padding:0 1rem;}
    .metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:2rem;}
    .metric-card{background:#fff;padding:1rem;border-radius:8px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
    .metric-card h3{color:#004080;margin:0;font-size:1rem;}
    .metric-card p{margin:.5rem 0 0;font-size:1.5rem;}
    .card{background:#fff;padding:1.5rem;border-radius:8px;margin-bottom:2rem;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
    .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;}
    .form-group{display:flex;flex-direction:column;}
    .form-group label{font-weight:500;margin-bottom:.5rem;}
    .form-group input,.form-group select{padding:.5rem;border:1px solid #ccc;border-radius:4px;}
    button.btn{background:#004080;color:#fff;border:none;padding:.75rem 1.5rem;border-radius:4px;cursor:pointer;grid-column:1/-1;}
    #map{width:100%;height:300px;margin-top:1rem;display:none;}
    .route-info{margin-top:.5rem;font-weight:500;}
    .hidden{display:none;}
    table{width:100%;border-collapse:collapse;margin-top:1rem;}
    th,td{padding:.75rem;border:1px solid #ddd;text-align:left;word-wrap:break-word;}
    th{background:#004080;color:#fff;}
    .badge{padding:.25em .5em;border-radius:4px;color:#fff;}
    .badge-pendiente{background:#ffc107;}
    .badge-confirmada{background:#28a745;}
    .badge-cancelada{background:#dc3545;}
    #toast{position:fixed;top:1rem;right:1rem;z-index:1000;}
    .toast{background:#333;color:#fff;padding:.75rem 1rem;margin-bottom:.5rem;border-radius:4px;}
    .pagination{display:flex;list-style:none;padding:0;margin:1rem 0;}
    .pagination li{margin:0 .25rem;}
    .pagination a, .pagination span{
      padding:.25rem .5rem;border:1px solid #ccc;border-radius:4px;
      text-decoration:none;color:#004080;
    }
    .pagination .current{background:#004080;color:#fff;border-color:#004080;}
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Bienvenido, <?=htmlspecialchars($_SESSION['username'],ENT_QUOTES)?></h1>
    <nav>
      <ul class="menu">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="logout.php">Cerrar sesión</a></li>
      </ul>
    </nav>
  </header>

  <div class="container">

    <!-- MÉTRICAS -->
    <section class="metrics">
      <div class="metric-card"><h3>Total</h3><p><?=$totalSolicitudes?></p></div>
      <div class="metric-card"><h3>Pendientes</h3><p><?=$pendientes?></p></div>
      <div class="metric-card"><h3>Confirmadas</h3><p><?=$confirmadas?></p></div>
      <div class="metric-card"><h3>Canceladas</h3><p><?=$canceladas?></p></div>
    </section>

    <!-- FORMULARIO -->
    <section class="card">
      <h2>Solicitar Transporte</h2>
      <form id="solForm" enctype="multipart/form-data">
        <div class="form-grid">

          <!-- DEPARTAMENTO -->
          <div class="form-group">
            <label for="departamento">Departamento</label>
            <select id="departamento" required>
              <option value="">Seleccione departamento</option>
              <option>Ciencias de la Salud</option>
              <option>Ciencias Naturales y Tecnología</option>
              <option>Ciencias Sociales y Humanidades</option>
              <option>Otro</option>
            </select>
          </div>

          <!-- CARRERA (select o libre) -->
          <div class="form-group" id="carreraGroup">
            <label for="carrera">Carrera</label>
            <select id="carrera" >
              <option value="">– primero elija departamento –</option>
            </select>
          </div>
          <div class="form-group hidden" id="carreraOtroGroup">
            <label for="carrera_otro">Especificar carrera</label>
            <input id="carrera_otro" placeholder="Otra carrera">
          </div>

          <!-- Campos ocultos que se enviarán: departamento, carrera/carrera_otro -->
          <input type="hidden" name="departamento" id="hdDepartamento">
          <input type="hidden" name="carrera"      id="hdCarrera">
          <input type="hidden" name="carrera_otro" id="hdCarreraOtro">

          <!-- TOGGLE MAPA/MANUAL -->
          <div class="form-group" style="grid-column:1/-1">
            <label><input type="checkbox" id="useMap"> Elegir origen/destino con mapa</label>
          </div>

          <!-- ORIGEN / DESTINO MANUAL -->
          <div id="manualFields">
            <div class="form-group">
              <label for="origen">Origen</label>
              <select id="origen" name="origen">
                <option value="">Seleccione origen</option>
                <?php foreach($origenes as $o):?>
                  <option><?=htmlspecialchars($o)?></option>
                <?php endforeach;?>
              </select>
            </div>
            <div class="form-group">
              <label for="destino">Destino</label>
              <select id="destino" name="destino">
                <option value="">Seleccione destino</option>
                <?php foreach($destinos as $d):?>
                  <option><?=htmlspecialchars($d)?></option>
                <?php endforeach;?>
                <option>Otro</option>
              </select>
            </div>
            <div id="otroDestinoGroup" class="form-group hidden">
              <label for="otro_destino">Especificar destino</label>
              <input id="otro_destino" name="otro_destino" placeholder="Otro destino">
            </div>
          </div>

          <!-- MAPA + OSRM -->
          <div id="mapContainer" class="form-group hidden" style="grid-column:1/-1">
            <div id="map"></div>
            <div class="route-info">
              Distancia: <span id="route-distance">–</span>,
              Duración: <span id="route-duration">–</span>
            </div>
            <button type="button" id="confirmRoute" class="btn hidden">Confirmar Ruta</button>
          </div>

          <!-- Campos ocultos de ruta -->
          <input type="hidden" name="ruta_id"      id="ruta_id">
          <input type="hidden" name="lat_origen"   id="lat_origen">
          <input type="hidden" name="lng_origen"   id="lng_origen">
          <input type="hidden" name="lat_destino"  id="lat_destino">
          <input type="hidden" name="lng_destino"  id="lng_destino">

          <!-- RESTO DE CAMPOS -->
          <div class="form-group">
            <label for="fecha_solicitada">Fecha</label>
            <input id="fecha_solicitada" name="fecha_solicitada" type="date" required>
          </div>
          <div class="form-group">
            <label for="horario_salida">Hora de salida</label>
            <select id="horario_salida" name="horario_salida" required>
              <option value="">--:--</option>
              <?=generarHoras()?>
            </select>
          </div>
          <div class="form-group">
            <label><input id="round_trip" type="checkbox"> Viaje de vuelta?</label>
          </div>
          <div id="returnTimeGroup" class="form-group hidden">
            <label for="hora_regreso">Hora de regreso</label>
            <select id="hora_regreso" name="hora_regreso">
              <option value="">--:--</option>
              <?=generarHoras()?>
            </select>
          </div>
          <div class="form-group">
            <label for="motivo">Motivo</label>
            <select id="motivo" name="motivo">
              <option>Salida A Terreno</option>
              <option>Otro</option>
            </select>
          </div>
          <div id="motivoOtroGroup" class="form-group hidden">
            <label for="motivo_otro">Especificar motivo</label>
            <input id="motivo_otro" name="motivo_otro" placeholder="Detalle motivo">
          </div>
          <div class="form-group">
            <label for="adjunto">Adjuntar documento</label>
            <input id="adjunto" name="adjunto" type="file" accept=".pdf,.doc,.docx">
          </div>
        </div>

        <button type="submit" class="btn">Enviar Solicitud</button>
      </form>
    </section>

    <!-- MIS SOLICITUDES -->
    <section class="card">
      <h2>Mis Solicitudes</h2>
      <?php if(empty($solicitudes)): ?>
        <p>No tienes solicitudes.</p>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Fecha</th><th>Depto.</th><th>Carrera</th><th>Ruta</th>
            <th>Salida</th><th>Regreso</th><th>Motivo</th><th>Adjunto</th>
            <th>Estado</th><th>Motivo Rechazo</th><th>Acción</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($solicitudes as $s): ?>
          <tr data-id="<?=$s['id']?>">
            <td><?=htmlspecialchars($s['fecha_solicitada'])?></td>
            <td><?=htmlspecialchars($s['departamento'])?></td>
            <td>
              <?= $s['carrera'] !== 'Otro'
                 ? htmlspecialchars($s['carrera'])
                 : htmlspecialchars($s['carrera_otro']) ?>
            </td>
            <td><?=htmlspecialchars("{$s['origen']} → {$s['destino']}")?></td>
            <td><?=htmlspecialchars($s['horario_salida'])?></td>
            <td><?=$s['hora_regreso']?:'-'?></td>
            <td>
              <?= $s['motivo']==='Otro'
                 ? htmlspecialchars($s['motivo_otro'])
                 : htmlspecialchars($s['motivo']) ?>
            </td>
            <td>
              <?php if($s['adjunto']):?>
                <a href="../<?=htmlspecialchars($s['adjunto'])?>" target="_blank">Ver</a>
              <?php else:?>-<?php endif;?>
            </td>
            <td><span class="badge badge-<?=$s['estado']?>"><?=ucfirst($s['estado'])?></span></td>
            <td>
              <?php
                if ($s['estado'] === 'cancelada' && !empty($s['motivo_rechazo'])) {
                  echo htmlspecialchars($s['motivo_rechazo']);
                } else {
                  echo '-';
                }
              ?>
            </td>
            <td>
              <?php if($s['estado']==='pendiente'):?>
                <button class="btn btn-cancel" data-id="<?=$s['id']?>">Cancelar</button>
              <?php endif;?>
            </td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>

      <!-- PAGINACIÓN -->
      <ul class="pagination">
        <?php if($page>1): ?>
          <li><a href="?page=<?=$page-1?>">&laquo; Anterior</a></li>
        <?php endif; ?>
        <?php for($p=1;$p<=$totalPages;$p++): ?>
          <?php if($p===$page): ?>
            <li><span class="current"><?=$p?></span></li>
          <?php else: ?>
            <li><a href="?page=<?=$p?>"><?=$p?></a></li>
          <?php endif;?>
        <?php endfor;?>
        <?php if($page<$totalPages): ?>
          <li><a href="?page=<?=$page+1?>">Siguiente &raquo;</a></li>
        <?php endif;?>
      </ul>
      <?php endif;?>
    </section>
  </div>

  <div id="toast"></div>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', ()=> {
    // 1) Configuración Departamento/Carrera
    const deptCarreras = {
      'Ciencias de la Salud': ['Obstetricia','Enfermería','Terapia Ocupacional'],
      'Ciencias Naturales y Tecnología': ['Agronomía','Ingeniería Forestal','Ingeniería Civil Industrial','Ingeniería Civil Informática'],
      'Ciencias Sociales y Humanidades': ['Trabajo Social','Psicología','Ingeniería Comercial']
    };
    const departamento   = document.getElementById('departamento'),
          carrera        = document.getElementById('carrera'),
          carreraGrp     = document.getElementById('carreraGroup'),
          carreraOtroGrp = document.getElementById('carreraOtroGroup'),
          carreraOtroIn  = document.getElementById('carrera_otro'),
          hdDept         = document.getElementById('hdDepartamento'),
          hdCarr         = document.getElementById('hdCarrera'),
          hdCarrOtro     = document.getElementById('hdCarreraOtro');

    departamento.addEventListener('change', e=>{
      const dep = e.target.value;
      // Rellenar hidden
      hdDept.value = dep;
      // Reset
      carrera.innerHTML = '';
      carrera.required = false;
      carreraGrp.querySelector('label').textContent = 'Carrera';
      carreraGrp.classList.remove('hidden');
      carreraOtroGrp.classList.add('hidden');
      carreraOtroIn.required = false;
      carreraOtroIn.value = '';

      if(dep==='Otro'){
        // ocultar select, mostrar input libre
        carreraGrp.classList.add('hidden');
        carreraOtroGrp.classList.remove('hidden');
        carreraOtroIn.required = true;
      } else {
        // poblar select
        const arr = deptCarreras[dep] || [];
        let html = '<option value="">Seleccione carrera</option>';
        arr.forEach(c=> html += `<option value="${c}">${c}</option>`);
        html += '<option value="Otro">Otro</option>';
        carrera.innerHTML = html;
        carrera.required = true;
      }
    });
    carrera.addEventListener('change', e=>{
      hdCarr.value = e.target.value;
      if(e.target.value==='Otro'){
        carreraGrp.classList.add('hidden');
        carreraOtroGrp.classList.remove('hidden');
        carreraOtroIn.required = true;
      } else {
        carreraOtroGrp.classList.add('hidden');
        carreraOtroIn.required = false;
        hdCarr.value = e.target.value;
        carreraOtroIn.value = '';
      }
    });
    carreraOtroIn.addEventListener('input', e=> {
      hdCarrOtro.value = e.target.value;
    });

    // 2) Validaciones destino, vuelta, motivo
    const destinoSel = document.getElementById('destino'),
          otroDestGrp = document.getElementById('otroDestinoGroup'),
          otroDestIn  = document.getElementById('otro_destino'),
          roundTrip   = document.getElementById('round_trip'),
          returnGrp   = document.getElementById('returnTimeGroup'),
          returnSel   = document.getElementById('hora_regreso'),
          motivoSel   = document.getElementById('motivo'),
          motivoGrp   = document.getElementById('motivoOtroGroup'),
          motivoIn    = document.getElementById('motivo_otro');

    destinoSel.addEventListener('change', ()=>{
      if(destinoSel.value==='Otro'){
        otroDestGrp.classList.remove('hidden');
        otroDestIn.required = true;
      } else {
        otroDestGrp.classList.add('hidden');
        otroDestIn.required = false;
        otroDestIn.value = '';
      }
    });
    roundTrip.addEventListener('change', ()=>{
      if(roundTrip.checked){
        returnGrp.classList.remove('hidden');
        returnSel.required = true;
      } else {
        returnGrp.classList.add('hidden');
        returnSel.required = false;
        returnSel.value = '';
      }
    });
    motivoSel.addEventListener('change', ()=>{
      if(motivoSel.value==='Otro'){
        motivoGrp.classList.remove('hidden');
        motivoIn.required = true;
      } else {
        motivoGrp.classList.add('hidden');
        motivoIn.required = false;
        motivoIn.value = '';
      }
    });

    // 3) Toast helper
    const toast = document.getElementById('toast');
    function showToast(msg){
      const t = document.createElement('div');
      t.className='toast'; t.textContent=msg;
      toast.appendChild(t);
      setTimeout(()=>t.remove(),3000);
    }

    // 4) Mapa + OSRM + Confirmar Ruta
    const useMap     = document.getElementById('useMap'),
          manual     = document.getElementById('manualFields'),
          mapCont    = document.getElementById('mapContainer'),
          mapEl      = document.getElementById('map'),
          distEl     = document.getElementById('route-distance'),
          durEl      = document.getElementById('route-duration'),
          btnConfirm = document.getElementById('confirmRoute'),
          rutaId     = document.getElementById('ruta_id'),
          latO       = document.getElementById('lat_origen'),
          lngO       = document.getElementById('lng_origen'),
          latD       = document.getElementById('lat_destino'),
          lngD       = document.getElementById('lng_destino'),
          hsSel      = document.getElementById('horario_salida');

    let map, layer, clickCnt, originName, destName;

    useMap.addEventListener('change', ()=>{
      if(useMap.checked){
        manual.classList.add('hidden');
        mapCont.classList.remove('hidden');
        initMap();
      } else {
        manual.classList.remove('hidden');
        mapCont.classList.add('hidden');
        if(map) map.remove();
        rutaId.value = '';
        btnConfirm.classList.add('hidden');
      }
    });

    function initMap(){
      if(map) map.remove();
      map = L.map(mapEl).setView([-45.5,-72.0667],10);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18}).addTo(map);
      layer = L.geoJSON().addTo(map);
      mapEl.style.display='block';
      clickCnt=0; rutaId.value=''; btnConfirm.classList.add('hidden');
      distEl.textContent=durEl.textContent='–';
      map.on('click', onMapClick);
    }
    function onMapClick(e){
      clickCnt++;
      const lat = e.latlng.lat.toFixed(6),
            lng = e.latlng.lng.toFixed(6);
      if(clickCnt===1){
        originName = prompt('Nombre para Origen:');
        if(!originName){ clickCnt=0; return showToast('Debes nombrar el origen'); }
        latO.value = lat; lngO.value = lng;
        L.marker(e.latlng).addTo(map).bindPopup(originName).openPopup();
        showToast('Origen guardado. Ahora selecciona destino.');
      } else {
        destName = prompt('Nombre para Destino:');
        if(!destName){ clickCnt=1; return showToast('Debes nombrar el destino'); }
        latD.value = lat; lngD.value = lng;
        L.marker(e.latlng).addTo(map).bindPopup(destName).openPopup();
        map.off('click', onMapClick);
        drawRoute();
      }
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
          btnConfirm.classList.remove('hidden');
        })
        .catch(()=>showToast('Error calculando ruta'));
    }
    btnConfirm.addEventListener('click', ()=>{
      const hs = hsSel.value;
      fetch('/log/php/create_ruta.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          origen_label:   originName,
          destino_label:  destName,
          horario_salida: hs,
          lat_origen:     latO.value,
          lng_origen:     lngO.value,
          lat_destino:    latD.value,
          lng_destino:    lngD.value
        })
      })
      .then(r=>r.json())
      .then(j=>{
        if(j.success){
          rutaId.value = j.id;
          showToast('Ruta confirmada ✔️');
          btnConfirm.classList.add('hidden');
        } else {
          showToast('Error al crear ruta: '+j.message);
        }
      })
      .catch(()=>showToast('Error creando ruta'));
    });

    // 5) Enviar solicitud
    const solForm = document.getElementById('solForm');
    solForm.addEventListener('submit', e=>{
      e.preventDefault();
      // Validar ocultos
      if(useMap.checked && !rutaId.value){
        return showToast('Selecciona la ruta en el mapa antes de enviar');
      }
      // Rellenar campos ocultos antes de enviar
      hdDept.value     = departamento.value;
      hdCarr.value     = (carrera.value!=='Otro')? carrera.value : carreraOtroIn.value;
      hdCarrOtro.value = carreraOtroIn.value;

      const fd = new FormData(solForm);
      fetch('/log/php/create_solicitud.php', { method:'POST', body:fd })
        .then(r=>r.json())
        .then(j=>{
          if(j.success){
            showToast('Solicitud enviada');
            solForm.reset();
            if(map) map.remove();
            setTimeout(()=>location.reload(),500);
          } else {
            showToast('Error: '+j.message);
          }
        })
        .catch(()=>showToast('Error de red'));
    });

    // 6) Cancelar solicitud
    document.querySelectorAll('.btn-cancel').forEach(btn=>
      btn.addEventListener('click', ()=>{
        if(!confirm('¿Cancelar esta solicitud?')) return;
        fetch('/log/php/cancel_solicitud.php',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'id='+encodeURIComponent(btn.dataset.id)
        })
        .then(r=>r.json())
        .then(j=>{
          if(j.success){
            btn.disabled=true;
            const bd=btn.closest('tr').querySelector('.badge');
            bd.textContent='Cancelada';
            bd.className='badge badge-cancelada';
            showToast('Solicitud cancelada');
          } else showToast('Error: '+j.message);
        })
        .catch(()=>showToast('Error de red'));
      })
    );
  });
  </script>
</body>
</html>
