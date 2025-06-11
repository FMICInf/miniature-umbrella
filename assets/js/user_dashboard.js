// assets/js/user_dashboard.js
document.addEventListener('DOMContentLoaded', () => {
  const origenInput    = document.getElementById('origen');
  const destinoInput   = document.getElementById('destino');
  const dateInput      = document.getElementById('fecha_solicitada');
  const timeInput      = document.getElementById('horario_salida');
  const returnTimeInput= document.getElementById('hora_regreso');
  const roundTripCheck = document.getElementById('round_trip');
  const returnGroup    = document.getElementById('returnTimeGroup');
  const motivoSelect   = document.getElementById('motivo');
  const motivoOtroGrp  = document.getElementById('motivoOtroGroup');
  const motivoOtroIn   = document.getElementById('motivo_otro');
  const adjInput       = document.getElementById('adjunto');
  const form           = document.getElementById('solForm');
  const toastCont      = document.getElementById('toast');

  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    toastCont.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  // Mostrar/ocultar regreso
  roundTripCheck.addEventListener('change', () => {
    if (roundTripCheck.checked) {
      returnGroup.classList.remove('hidden');
      returnTimeInput.required = true;
    } else {
      returnGroup.classList.add('hidden');
      returnTimeInput.required = false;
      returnTimeInput.value = '';
    }
  });

  // Mostrar/ocultar motivo otro
  motivoSelect.addEventListener('change', () => {
    if (motivoSelect.value === 'Otro') {
      motivoOtroGrp.classList.remove('hidden');
      motivoOtroIn.required = true;
    } else {
      motivoOtroGrp.classList.add('hidden');
      motivoOtroIn.required = false;
      motivoOtroIn.value = '';
    }
  });

  // Función para bloquear horarios ya asignados
  function updateBlockedTimes() {
    const origen  = origenInput.value;
    const destino = destinoInput.value;
    const fecha   = dateInput.value;
    if (!origen || !destino || !fecha) return;
    fetch(`/log/php/get_assigned_times.php?origen=${encodeURIComponent(origen)}&destino=${encodeURIComponent(destino)}&fecha=${encodeURIComponent(fecha)}`)
      .then(res => res.json())
      .then(json => {
        if (!json.success) return showToast(json.message);
        // primero habilitar todas las opciones
        [timeInput, returnTimeInput].forEach(inp => {
          Array.from(inp.options).forEach(opt => opt.disabled = false);
        });
        // deshabilitar las ocupadas
        json.data.forEach(slot => {
          const start = slot.horario_salida;
          const end   = slot.hora_regreso;
          [timeInput, returnTimeInput].forEach(inp => {
            Array.from(inp.options).forEach(opt => {
              if (opt.value === start || (end && opt.value === end)) {
                opt.disabled = true;
              }
            });
          });
        });
      })
      .catch(() => showToast('No se pudieron cargar horarios'));
  }

  // Disparar bloqueo al cambiar ruta o fecha
  [origenInput, destinoInput, dateInput].forEach(el =>
    el.addEventListener('change', updateBlockedTimes)
  );

  // Enviar solicitud
  form.addEventListener('submit', e => {
    e.preventDefault();
    const data = new FormData(form);
    fetch('/log/php/create_solicitud.php', {
      method: 'POST',
      body: data
    })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        showToast('Solicitud enviada');
        // actualizar tabla/métricas si lo deseas…
      } else {
        showToast('Error: ' + json.message);
      }
    })
    .catch(() => showToast('Error de red'));
  });

  // Cancelar solicitudes
  document.querySelectorAll('.btn-cancel').forEach(btn =>
    btn.addEventListener('click', () => {
      if (!confirm('¿Cancelar esta solicitud?')) return;
      fetch('/log/php/cancel_solicitud.php', {
        method: 'POST',
        headers:{ 'Content-Type':'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(btn.dataset.id)
      })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          btn.disabled = true;
          btn.closest('tr').querySelector('.badge').textContent = 'Cancelada';
          showToast('Solicitud cancelada');
        } else showToast('Error: ' + json.message);
      })
      .catch(() => showToast('Error de red'));
    })
  );
});
