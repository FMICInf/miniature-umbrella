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

// --- Filtro por estado ---
$estadoFiltro = isset($_GET['estado']) ? $_GET['estado'] : '';

// Métricas: totalSolicitudes sólo con estado 'confirmada' para coherencia con el calendario
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'confirmada'");
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

// Orígenes recientes usados por el usuario (máximo 4 distintos, más usados primero)
$origenesStmt = $pdo->prepare("
    SELECT r.origen, COUNT(*) as total_uso
    FROM solicitudes s
    JOIN rutas r ON s.ruta_id = r.id
    WHERE s.usuario_id = ?
    GROUP BY r.origen
    ORDER BY total_uso DESC, r.origen ASC
    LIMIT 4
");
$origenesStmt->execute([$userId]);
$origenes = $origenesStmt->fetchAll(PDO::FETCH_COLUMN);

// Destinos recientes usados por el usuario (máximo 4 distintos, más usados primero)
$destinosStmt = $pdo->prepare("
    SELECT r.destino, COUNT(*) as total_uso
    FROM solicitudes s
    JOIN rutas r ON s.ruta_id = r.id
    WHERE s.usuario_id = ?
    GROUP BY r.destino
    ORDER BY total_uso DESC, r.destino ASC
    LIMIT 4
");
$destinosStmt->execute([$userId]);
$destinos = $destinosStmt->fetchAll(PDO::FETCH_COLUMN);

// WHERE y parámetros para solicitudes
$where = "s.usuario_id = ?";
$params = [$userId];
if ($estadoFiltro) {
    $where .= " AND s.estado = ?";
    $params[] = $estadoFiltro;
} else {
    // Por defecto mostrar sólo solicitudes confirmadas para coherencia con las métricas y calendario
    $where .= " AND s.estado = 'confirmada'";
}

// Solicitudes existentes (página actual)
$solStmt = $pdo->prepare("
    SELECT s.id, s.fecha_solicitada, r.origen, r.destino,
           s.horario_salida, s.hora_regreso,
           s.departamento, s.carrera, s.carrera_otro,
           s.cantidad_pasajeros,
           s.motivo, s.motivo_otro, s.adjunto, s.estado,
           s.motivo_rechazo
    FROM solicitudes s
    JOIN rutas r ON s.ruta_id = r.id
    WHERE $where
    ORDER BY FIELD(s.estado, 'pendiente', 'confirmada', 'cancelada', 'rechazada'),
             s.creado_at DESC, s.id DESC
    LIMIT ? OFFSET ?
");

// Bindeo de parámetros
$solStmt->bindValue(1, $params[0], PDO::PARAM_INT);
$bindIdx = 2;
if ($estadoFiltro) {
    $solStmt->bindValue($bindIdx++, $params[1], PDO::PARAM_STR);
}
$solStmt->bindValue($bindIdx++, $perPage, PDO::PARAM_INT);
$solStmt->bindValue($bindIdx, $offset, PDO::PARAM_INT);
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
    #map{width:100%;height:300px;min-height:250px;background:#e0e0e0;}
    .route-info{margin-top:.5rem;font-weight:500;}
    .hidden{display:none;}
    .info-banner {
      background: #fff;
      border: 1.5px solid #e3edf7;
      border-radius: 12px;
      margin-bottom: 25px;
      box-shadow: 0 3px 12px rgba(32,50,80,0.05);
      padding: 20px 12px 14px 12px;
      max-width: 850px;
      margin-left: auto;
      margin-right: auto;
    }
    .help-icon {
      display: inline-block;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #004080;
      color: #fff;
      text-align: center;
      line-height: 28px;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      margin-left: 7px;
      vertical-align: middle;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
      transition: background 0.18s;
    }
    .help-icon:hover, .help-icon:focus { background: #0a6cd4; outline: none; }
    /* Modal ayuda */
    #modalAyuda { display:none; position:fixed; top:0; left:0; width:100vw; height:100vh;
      background:rgba(0,0,0,0.34); z-index:10000; align-items:center; justify-content:center;}
    #modalAyuda .modal-content {
      background:#fff; padding:22px 18px 18px 18px; border-radius:10px;
      max-width:470px; width:95vw; position:relative; text-align:center;
      box-shadow:0 4px 24px rgba(0,0,0,0.11); min-height:320px;
      animation: fadeIn .15s;
    }
    .ayuda-slide {display:none;}
    .ayuda-slide.active {display:block;}
    .ayuda-nav-btn {
      padding:6px 18px; font-size:1.2em; border-radius:30px; border:none;
      background:#004080; color:#fff; margin:0 12px; cursor:pointer;
    }
    .ayuda-nav-btn:disabled { background:#ccc; cursor:not-allowed; }
    .close {
      position:absolute; top:12px; right:18px; cursor:pointer; font-size:28px; font-weight:bold; color:#222;
      transition: color .12s;
    }
    .close:hover { color:#0057c2; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @media (max-width:600px) {
      .info-banner { padding: 14px 4px 8px 4px; }
      .help-icon { width:24px; height:24px; font-size:16px; line-height:24px; }
      #modalAyuda .modal-content { padding: 12px 5vw 12px 5vw; }
    }
  </style>
</head>
<body>
  <header class="header-inner">
    <h1>Bienvenido, <?=htmlspecialchars($_SESSION['username'],ENT_QUOTES)?></h1>
    <nav>
      <ul class="menu">
        <li><a href="dashboard.php">Volver</a></li>
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

    <!-- BANNER EXPLICATIVO + AYUDA -->
    <div class="info-banner">
      <div style="display:flex; align-items:center; justify-content:center;">
        <div style="flex:1;">
          <div style="font-weight:600; font-size:1.19rem; color:#194185; text-align:center;">
            ¿Cómo funciona la Solicitud de Transporte?
            <span id="ayudaTrigger" class="help-icon" tabindex="0" title="Ver ayuda sobre el proceso de solicitud">?</span>
          </div>
          <div style="margin-top:0.35rem; color:#222; text-align:center; font-size:1.01rem;">
            Usa este formulario para pedir un viaje oficial, llenando todos los campos requeridos.<br>
            Si usas la opción de mapa para definir tu origen y destino, puedes buscar direcciones, nombres de lugares y códigos postales (Como recomendación, usar google maps para tener una mejor geolocalización)<br>
            Para buscar un punto exacto, escribe y presiona <b>ENTER</b> en cada campo (origen/destino).
          </div>
        </div>
      </div>
    </div>

    <!-- FORMULARIO -->
    <section class="card">
      <div style="display:flex; align-items:center; justify-content:center; margin-bottom:10px;">
        <span style="font-weight:600; font-size:1.27rem; color:#222; margin-right:8px;">
          Solicitar Transporte
        </span>
        <span id="ayudaTrigger2" class="help-icon" tabindex="0" title="Ayuda rápida sobre cómo solicitar transporte">?</span>
      </div>
      <form id="solForm" enctype="multipart/form-data" method="POST" action="create_solicitud.php">
        <div class="form-grid">
          <!-- Departamento -->
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
          <!-- Carrera -->
          <div class="form-group" id="carreraGroup">
            <label for="carrera">Carrera</label>
            <select id="carrera">
              <option value="">– primero elija departamento –</option>
            </select>
          </div>
          <div class="form-group hidden" id="carreraOtroGroup">
            <label for="carrera_otro">Especificar carrera</label>
            <input id="carrera_otro" placeholder="Otra carrera">
          </div>
          <input type="hidden" name="departamento" id="hdDepartamento">
          <input type="hidden" name="carrera" id="hdCarrera">
          <input type="hidden" name="carrera_otro" id="hdCarreraOtro">
          <!-- Toggle Mapa/Manual -->
          <div class="form-group" style="grid-column:1/-1">
            <label><input type="checkbox" id="useMap"> Elegir origen/destino con mapa</label>
          </div>
          <!-- Búsqueda en Mapa -->
          <div id="mapContainer" class="form-group hidden" style="grid-column:1/-1">
            <input type="text" id="originSearch" placeholder="Buscar origen..." style="width:100%;padding:.5rem;margin-bottom:.5rem;">
            <input type="text" id="destinationSearch" placeholder="Buscar destino..." style="width:100%;padding:.5rem;margin-bottom:1rem;">
            <div id="map" class="hidden"></div>
            <div class="route-info">
              Distancia: <span id="route-distance">–</span>,
              Duración: <span id="route-duration">–</span>
            </div>
            <button type="button" id="confirmRoute" class="btn hidden">Confirmar Ruta</button>
          </div>
          <!-- Manual Fields -->
          <div id="manualFields">
            <div class="form-group">
              <label for="origen">Origen</label>
              <select id="origen" name="origen">
                <option value="">Seleccione origen</option>
                <?php foreach($origenes as $o): ?>
                  <option><?=htmlspecialchars($o)?></option>
                <?php endforeach; ?>
                <option value="Otro">Otro</option>
              </select>
            </div>
            <div id="otroOrigenGroup" class="form-group hidden">
              <label for="otro_origen">Especificar origen</label>
              <input id="otro_origen" name="otro_origen" placeholder="Otro origen">
            </div>
            <div class="form-group">
              <label for="destino">Destino</label>
              <select id="destino" name="destino">
                <option value="">Seleccione destino</option>
                <?php foreach($destinos as $d): ?>
                  <option><?=htmlspecialchars($d)?></option>
                <?php endforeach; ?>
                <option>Otro</option>
              </select>
            </div>
            <div id="otroDestinoGroup" class="form-group hidden">
              <label for="otro_destino">Especificar destino</label>
              <input id="otro_destino" name="otro_destino" placeholder="Otro destino">
            </div>
          </div>
          <!-- Campos ocultos de ruta -->
          <input type="hidden" name="ruta_id" id="ruta_id">
          <input type="hidden" name="lat_origen" id="lat_origen">
          <input type="hidden" name="lng_origen" id="lng_origen">
          <input type="hidden" name="lat_destino" id="lat_destino">
          <input type="hidden" name="lng_destino" id="lng_destino">
          <!-- Resto campos -->
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
          <div class="form-group">
            <label for="cantidad_pasajeros">Cantidad de pasajeros</label>
            <input type="number" id="cantidad_pasajeros" name="cantidad_pasajeros" min="1" required class="form-control">
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
  </div>

  <div id="toast"></div>

<!-- MODAL DE AYUDA EXPLICATIVA -->
<div id="modalAyuda">
  <div class="modal-content">
    <span class="close" onclick="closeAyuda()">&times;</span>

    <div class="ayuda-slide" id="slide0">
      <h3>1. Solicitud manual de transporte</h3>
      <img src="../assets/gifs/Solicitar_transporte_manualmente.gif" alt="Solicitud manual" style="max-width:95%;border-radius:7px;">
      <div style="margin-top:6px;text-align:left;">
        <b>¿Qué muestra este ejemplo?</b><br>
        Aquí ves cómo realizar una solicitud llenando todos los campos manualmente: debes seleccionar tu departamento, carrera, origen y destino (eligiendo desde las opciones predefinidas o escribiendo otra si no aparece en la lista), la fecha y hora del viaje, motivo, cantidad de pasajeros y puedes adjuntar documentos si es necesario. 
        <br><br>
        <b>¿Para qué sirve?</b><br>
        Esta opción es útil cuando ya conoces la ruta y solo quieres ingresar los datos rápidamente. Así tu solicitud llegará al área de transporte y podrás hacer seguimiento a su estado.
      </div>
    </div>

    <div class="ayuda-slide" id="slide1">
      <h3>2. Solicitud usando el mapa</h3>
      <img src="../assets/gifs/HACIENDO_SOLICITUD_RELLENANDO_TODOS_LOS_CAMPOS_USANDO_EL_MAPA.gif" alt="Solicitud por mapa" style="max-width:95%;border-radius:7px;">
      <div style="margin-top:6px;text-align:left;">
        <b>¿Qué muestra este ejemplo?</b><br>
        Aquí puedes ver cómo elegir un origen y destino directamente desde el mapa. El usuario utiliza los campos de búsqueda para ingresar direcciones, lugares, códigos postales, o simplemente copiar un punto desde Google Maps. Después de escribir la dirección, presiona <b>ENTER</b> y el sistema ubicará el punto automáticamente. Puedes ver que el mapa permite mayor precisión y ayuda a evitar errores de ubicación.
        <br><br>
        <b>¿Para qué sirve?</b><br>
        Esta opción es ideal cuando no sabes el nombre exacto del lugar, pero tienes una referencia visual (mapa) o necesitas especificar una ubicación poco habitual. El sistema calculará automáticamente la distancia y la duración estimada del viaje.
      </div>
    </div>

    <div class="ayuda-slide" id="slide2">
      <h3>3. Opciones avanzadas: viaje de vuelta y adjuntos</h3>
      <img src="../assets/gifs/Opción_solicitar_viaje_de_vuelta.gif" alt="Viaje de vuelta" style="max-width:95%;border-radius:7px;">
      <div style="margin-top:6px;text-align:left;">
        <b>¿Qué muestra este ejemplo?</b><br>
        En este paso, el usuario activa la opción de <b>viaje de vuelta</b> para definir también la hora de regreso, y se ve cómo puede agregar detalles extras como motivo específico y adjuntar archivos importantes (por ejemplo, autorizaciones, permisos o cartas).
        <br><br>
        <b>¿Para qué sirve?</b><br>
        Activar el viaje de vuelta permite reservar transporte tanto para la ida como para el regreso, en una sola solicitud, facilitando la organización y el seguimiento. Adjuntar documentos es obligatorio si tu unidad lo requiere.
      </div>
    </div>

    <div class="ayuda-slide" id="slide3">
      <h3>4. Uso avanzado del campo Mapa</h3>
      <img src="../assets/gifs/explicación_usomapa1.gif" alt="Explicación uso mapa" style="max-width:95%;border-radius:7px;">
      <div style="margin-top:6px;text-align:left;">
        <b>¿Qué muestra este ejemplo?</b><br>
        Aquí se muestra cómo aprovechar al máximo los campos del mapa para ingresar ubicaciones. Muestra cómo puedes buscar tanto direcciones exactas, nombres de lugares, códigos postales o incluso referencias tomadas desde Google Maps. Además, se observa cómo cada vez que escribes un lugar y presionas <b>ENTER</b>, el sistema lo ubica y lo marca en el mapa.
        <br><br>
        <b>¿Para qué sirve?</b><br>
        Así puedes asegurarte que el punto de origen o destino está correctamente seleccionado. Esto es muy útil si tienes dudas sobre la dirección, ya que la referencia visual ayuda a evitar errores y mejora la comunicación con el equipo de transporte. Nota: si se hace uso del mapa, se deben completar todos los datos, luego confirmar la ruta y por último envíar la solicitud.

      </div>
    </div>

    <div style="margin-top:18px;">
      <button id="prevAyuda" class="ayuda-nav-btn" onclick="navAyuda(-1)">&#8592;</button>
      <span id="ayudaIndicador">1 / 4</span>
      <button id="nextAyuda" class="ayuda-nav-btn" onclick="navAyuda(1)">&#8594;</button>
    </div>
  </div>
</div>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script>
    // Modal ayuda funcional para ambos iconos
    let currentAyuda = 0;
    function showAyuda(idx=0) {
      document.getElementById('modalAyuda').style.display = 'flex';
      navAyuda(0, idx);
    }
    function closeAyuda() {
      document.getElementById('modalAyuda').style.display = 'none';
    }
    function navAyuda(offset, setIndex=null) {
      const slides = [0,1,2,3];
      if(setIndex!==null) currentAyuda = setIndex;
      else currentAyuda += offset;
      if(currentAyuda<0) currentAyuda=0;
      if(currentAyuda>3) currentAyuda=3;
      slides.forEach(i=>{
        document.getElementById('slide'+i).className = 'ayuda-slide'+(i===currentAyuda?' active':'');
      });
      document.getElementById('ayudaIndicador').textContent = (currentAyuda+1) + ' / 4';
      document.getElementById('prevAyuda').disabled = currentAyuda === 0;
      document.getElementById('nextAyuda').disabled = currentAyuda === slides.length-1;
    }
    // Cierra ayuda si se hace click fuera del modal
    window.onclick = function(event) {
      const modal = document.getElementById('modalAyuda');
      if (event.target == modal) closeAyuda();
    };
    // Iconos de ayuda (arriba y en formulario)
    document.addEventListener('DOMContentLoaded',function(){
      document.getElementById('ayudaTrigger').onclick = ()=>showAyuda(0);
      document.getElementById('ayudaTrigger2').onclick = ()=>showAyuda(0);
    });
  </script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // VARIABLES
  const departamento   = document.getElementById('departamento'),
        carrera        = document.getElementById('carrera'),
        carreraGrp     = document.getElementById('carreraGroup'),
        carreraOtroGrp = document.getElementById('carreraOtroGroup'),
        carreraOtroIn  = document.getElementById('carrera_otro'),
        hdDept         = document.getElementById('hdDepartamento'),
        hdCarr         = document.getElementById('hdCarrera'),
        hdCarrOtro     = document.getElementById('hdCarreraOtro'),
        useMap         = document.getElementById('useMap'),
        manual         = document.getElementById('manualFields'),
        mapCont        = document.getElementById('mapContainer'),
        mapEl          = document.getElementById('map'),
        originSearch   = document.getElementById('originSearch'),
        destinationSearch = document.getElementById('destinationSearch'),
        distEl         = document.getElementById('route-distance'),
        durEl          = document.getElementById('route-duration'),
        btnConfirm     = document.getElementById('confirmRoute'),
        rutaId         = document.getElementById('ruta_id'),
        latO           = document.getElementById('lat_origen'),
        lngO           = document.getElementById('lng_origen'),
        latD           = document.getElementById('lat_destino'),
        lngD           = document.getElementById('lng_destino'),
        hsSel          = document.getElementById('horario_salida'),
        solForm        = document.getElementById('solForm'),
        toast          = document.getElementById('toast'),
        origenSel      = document.getElementById('origen'),
        otroOrigenGrp  = document.getElementById('otroOrigenGroup'),
        otroOrigenIn   = document.getElementById('otro_origen'),
        destinoSel     = document.getElementById('destino'),
        otroDestGrp    = document.getElementById('otroDestinoGroup'),
        otroDestIn     = document.getElementById('otro_destino'),
        roundTrip      = document.getElementById('round_trip'),
        returnGrp      = document.getElementById('returnTimeGroup'),
        returnSel      = document.getElementById('hora_regreso'),
        motivoSel      = document.getElementById('motivo'),
        motivoGrp      = document.getElementById('motivoOtroGroup'),
        motivoIn       = document.getElementById('motivo_otro');

  let map, layer, markerO = null, markerD = null, originName = '', destName = '';
  let mapInitialized = false;

  function showToast(msg){
    const t = document.createElement('div');
    t.className='toast'; t.textContent=msg;
    toast.appendChild(t);
    setTimeout(()=>t.remove(),3000);
  }

  // Departamento/Carrera
  const deptCarreras = {
    'Ciencias de la Salud': ['Obstetricia','Enfermería','Terapia Ocupacional'],
    'Ciencias Naturales y Tecnología': ['Agronomía','Ingeniería Forestal','Ingeniería Civil Industrial','Ingeniería Civil Informática'],
    'Ciencias Sociales y Humanidades': ['Trabajo Social','Psicología','Ingeniería Comercial']
  };
  departamento.addEventListener('change', e => {
    const dep = e.target.value;
    hdDept.value = dep;
    carrera.innerHTML = '';
    carrera.required = false;
    carreraGrp.classList.remove('hidden');
    carreraOtroGrp.classList.add('hidden');
    carreraOtroIn.required = false;
    carreraOtroIn.value = '';
    if (dep === 'Otro') {
      carreraGrp.classList.add('hidden');
      carreraOtroGrp.classList.remove('hidden');
      carreraOtroIn.required = true;
    } else {
      const arr = deptCarreras[dep] || [];
      let html = '<option value="">Seleccione carrera</option>';
      arr.forEach(c => html += `<option value="${c}">${c}</option>`);
      html += '<option value="Otro">Otro</option>';
      carrera.innerHTML = html;
      carrera.required = true;
    }
  });
  carrera.addEventListener('change', e => {
    hdCarr.value = e.target.value;
    if (e.target.value === 'Otro') {
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
  carreraOtroIn.addEventListener('input', e => { hdCarrOtro.value = e.target.value; });

  // Origen/Destino Manual - Otros
  if (origenSel) {
    origenSel.addEventListener('change', () => {
      if (origenSel.value === 'Otro') {
        otroOrigenGrp.classList.remove('hidden');
        otroOrigenIn.required = true;
      } else {
        otroOrigenGrp.classList.add('hidden');
        otroOrigenIn.required = false;
        otroOrigenIn.value = '';
      }
    });
  }
  destinoSel.addEventListener('change', () => {
    if (destinoSel.value === 'Otro') {
      otroDestGrp.classList.remove('hidden');
      otroDestIn.required = true;
    } else {
      otroDestGrp.classList.add('hidden');
      otroDestIn.required = false;
      otroDestIn.value = '';
    }
  });
  roundTrip.addEventListener('change', () => {
    if (roundTrip.checked) {
      returnGrp.classList.remove('hidden');
      returnSel.required = true;
    } else {
      returnGrp.classList.add('hidden');
      returnSel.required = false;
      returnSel.value = '';
    }
  });
  motivoSel.addEventListener('change', () => {
    if (motivoSel.value === 'Otro') {
      motivoGrp.classList.remove('hidden');
      motivoIn.required = true;
    } else {
      motivoGrp.classList.add('hidden');
      motivoIn.required = false;
      motivoIn.value = '';
    }
  });

  // Mostrar/ocultar mapa
  useMap.addEventListener('change', () => {
    if (useMap.checked) {
      manual.classList.add('hidden');
      mapCont.classList.remove('hidden');
      mapEl.classList.remove('hidden');
      if (!mapInitialized) {
        initMap();
        mapInitialized = true;
      } else {
        setTimeout(() => { map.invalidateSize(); }, 250);
      }
    } else {
      manual.classList.remove('hidden');
      mapCont.classList.add('hidden');
      mapEl.classList.add('hidden');
      // Limpia los campos SOLO al ocultar el mapa
      if (markerO) { map.removeLayer(markerO); markerO = null; }
      if (markerD) { map.removeLayer(markerD); markerD = null; }
      originSearch.value = '';
      destinationSearch.value = '';
      latO.value = lngO.value = latD.value = lngD.value = '';
      distEl.textContent = durEl.textContent = '–';
      btnConfirm.classList.add('hidden');
    }
  });

  function initMap() {
    mapEl.classList.remove('hidden');
    map = L.map(mapEl).setView([-45.5, -72.0667], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 18}).addTo(map);
    layer = L.geoJSON().addTo(map);
    markerO = markerD = null;
    latO.value = lngO.value = latD.value = lngD.value = '';
    distEl.textContent = durEl.textContent = '–';
    btnConfirm.classList.add('hidden');

    // Búsqueda de origen
    originSearch.onkeydown = function(e){
      if(e.key === 'Enter'){
        e.preventDefault();
        const q = originSearch.value.trim();
        if(!q) return;
        fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`)
          .then(r => r.json())
          .then(results => {
            if(!results.length) return showToast('Origen no encontrado');
            const {lat, lon, display_name} = results[0];
            latO.value = parseFloat(lat).toFixed(6);
            lngO.value = parseFloat(lon).toFixed(6);
            originName = display_name;
            if(markerO) map.removeLayer(markerO);
            markerO = L.marker([lat, lon]).addTo(map).bindPopup('Origen: '+display_name).openPopup();
            map.setView([lat, lon], 14);
            if(latO.value && lngO.value && latD.value && lngD.value) drawRoute();
          })
          .catch(()=>showToast('Error buscando origen'));
      }
    };

    // Búsqueda de destino
    destinationSearch.onkeydown = function(e){
      if(e.key === 'Enter'){
        e.preventDefault();
        const q = destinationSearch.value.trim();
        if(!q) return;
        fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`)
          .then(r => r.json())
          .then(results => {
            if(!results.length) return showToast('Destino no encontrado');
            const {lat, lon, display_name} = results[0];
            latD.value = parseFloat(lat).toFixed(6);
            lngD.value = parseFloat(lon).toFixed(6);
            destName = display_name;
            if(markerD) map.removeLayer(markerD);
            markerD = L.marker([lat, lon]).addTo(map).bindPopup('Destino: '+display_name).openPopup();
            map.setView([lat, lon], 14);
            if(latO.value && lngO.value && latD.value && lngD.value) drawRoute();
          })
          .catch(()=>showToast('Error buscando destino'));
      }
    };
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
    fetch('create_ruta.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        origen_label: originName,
        destino_label: destName,
        horario_salida: hsSel.value,
        lat_origen: latO.value,
        lng_origen: lngO.value,
        lat_destino: latD.value,
        lng_destino: lngD.value
      })
    }).then(r=>r.json()).then(j=>{
      if(j.success){
        rutaId.value = j.id;
        showToast('Ruta confirmada');
        btnConfirm.classList.add('hidden');
      }else{
        showToast('Error: '+j.message);
      }
    }).catch(()=>showToast('Error creando ruta'));
  });

  // Enviar solicitud (validación de mapa y pasajeros)
  solForm.addEventListener('submit', e => {
    e.preventDefault();
    if (useMap.checked && !rutaId.value) return showToast('Selecciona la ruta antes de enviar');
    const cantidad = parseInt(document.getElementById('cantidad_pasajeros').value,10);
    if (isNaN(cantidad) || cantidad < 1) return showToast('Cantidad inválida');
    hdDept.value     = departamento.value;
    hdCarr.value     = carrera.value !== 'Otro' ? carrera.value : carreraOtroIn.value;
    hdCarrOtro.value = carreraOtroIn.value;
    const fd = new FormData(solForm);
    fetch('create_solicitud.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(j=>{
        if (j.success) {
          showToast('Solicitud enviada');
          solForm.reset();
          if (map) map.remove();
          setTimeout(()=>location.reload(),500);
        } else showToast('Error: '+j.message);
      }).catch(()=>showToast('Error de red'));
  });

  // Cancelar solicitud (si tienes botones de cancelar en tu tabla de solicitudes)
  document.querySelectorAll('.btn-cancel').forEach(btn=>
    btn.addEventListener('click', ()=>{
      if(!confirm('¿Cancelar esta solicitud?')) return;
      fetch('cancel_solicitud.php',{
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
          setTimeout(()=>location.reload(), 400);
        } else showToast('Error: '+j.message);
      })
      .catch(()=>showToast('Error de red'));
    })
  );
});
</script>
</body>
</html>
