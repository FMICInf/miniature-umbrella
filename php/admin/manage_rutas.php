<?php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || $_SESSION['rol']!=='admin') { header('Location: ../index.php'); exit; }
try {
    $stmt = $pdo->query("SELECT id, origen, destino, horario_salida, horario_llegada, distancia_km FROM rutas ORDER BY creado_at DESC");
    $rutas = $stmt->fetchAll();
} catch(PDOException $e) { die('Error BD: '.$e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Admin – Gestionar Rutas</title>
<link rel="stylesheet" href="../../assets/css/style.css"></head>
<body>
<header class="header-inner"><h1>Gestionar Rutas</h1><nav><ul class="menu">
<li><a href="../admin_dashboard.php">Dashboard</a></li>
<li><a href="manage_solicitudes.php">Solicitudes</a></li>
<li><a href="manage_asignaciones.php">Asignaciones</a></li>
<li><a href="manage_vehiculos.php">Vehículos</a></li>
<li><a href="manage_rutas.php" class="active">Rutas</a></li>
<li><a href="../logout.php">Cerrar sesión</a></li>
</ul></nav></header>
<main class="container">
<section class="card">
<button id="btn-add-route" class="btn">+ Agregar Ruta</button>
<table><thead><tr><th>ID</th><th>Origen</th><th>Destino</th><th>Salida</th><th>Llegada</th><th>Distancia (km)</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($rutas as $r): ?><tr data-id="<?=$r['id']?>">
<td><?=$r['id']?></td>
<td><?=htmlspecialchars($r['origen'])?></td>
<td><?=htmlspecialchars($r['destino'])?></td>
<td><?=$r['horario_salida']?></td>
<td><?=$r['horario_llegada']?></td>
<td><?=$r['distancia_km']?></td>
<td><button class="btn btn-edit-route" data-id="<?=$r['id']?>">Editar</button>
<button class="btn btn-delete-route" data-id="<?=$r['id']?>">Eliminar</button></td>
</tr><?php endforeach; ?></tbody></table>
</section>
</main>

<!-- Modal rutas -->
<div id="routeModal" class="modal hidden"><form id="routeForm" class="modal-content">
<h2 id="routeModalTitle">Agregar Ruta</h2>
<label>Origen:<input name="origen" required></label>
<label>Destino:<input name="destino" required></label>
<label>Salida:<input name="horario_salida" type="time" required></label>
<label>Llegada:<input name="horario_llegada" type="time"></label>
<label>Distancia (km):<input name="distancia_km" type="number" step="0.01"></label>
<input type="hidden" name="id">
<div class="modal-actions"><button type="submit" class="btn btn-save-route">Guardar</button><button type="button" id="routeCancel" class="btn btn-cancel">Cancelar</button></div>
</form></div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const showToast=msg=>{const c=document.getElementById('toast'),t=document.createElement('div');t.className='toast';t.textContent=msg;c.appendChild(t);setTimeout(()=>c.removeChild(t),3000);
 };
 const routeModal=document.getElementById('routeModal'), routeForm=document.getElementById('routeForm');
 // Agregar ruta
 document.getElementById('btn-add-route').onclick=()=>{document.getElementById('routeModalTitle').textContent='Agregar Ruta';routeForm.reset();routeModal.classList.remove('hidden');};
 document.getElementById('routeCancel').onclick=()=>routeModal.classList.add('hidden');
 // Editar ruta
 document.querySelectorAll('.btn-edit-route').forEach(btn=>btn.onclick=()=>{
  const id=btn.dataset.id;
  fetch(`get_ruta.php?id=${id}`).then(r=>r.json()).then(json=>{
    if(json.success){document.getElementById('routeModalTitle').textContent='Editar Ruta'; for(let k in json.data) if(routeForm[k])routeForm[k].value=json.data[k]; routeModal.classList.remove('hidden');}
    else showToast(json.message);
  });
 });
 // Eliminar ruta
 document.querySelectorAll('.btn-delete-route').forEach(btn=>btn.onclick=()=>{if(confirm('Eliminar ruta?')){fetch('delete_ruta.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${btn.dataset.id}`}).then(r=>r.json()).then(j=>j.success?location.reload():alert(j.message));}});
 // Guardar ruta
 routeForm.onsubmit=e=>{e.preventDefault();const id=routeForm.id.value;const url=id?'update_ruta.php':'create_ruta.php';const body=new URLSearchParams(new FormData(routeForm));fetch(url,{method:'POST',body}).then(r=>r.json()).then(j=>{if(j.success)location.reload();else showToast(j.message);});};
});
</script>
