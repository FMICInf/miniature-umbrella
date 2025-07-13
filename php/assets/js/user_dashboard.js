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

  // Convierte "HH:mm" a minutos desde medianoche
  function timeToMinutes(t) {
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
  }

  // Retorna true si los intervalos [start1,end1) y [start2,end2) se solapan
  function intervalsOverlap(start1, end1, start2, end2) {
    return start1 < end2 && start2 < end1;
  }

  // Bloquear horarios que se solapan con solicitudes confirmadas
  function updateBlockedTimes() {
    const origen  = origenInput.value;
    const destino = destinoInput.value;
    const fecha   = dateInput.value;
    if (!origen || !destino || !fecha) return;

    fetch(`/log/php/get_confirmed_intervals.php?origen=${encodeURIComponent(origen)}&destino=${encodeURIComponent(destino)}&fecha=${encodeURIComponent(fecha)}`)
      .then(res => res.json())
      .then(json => {
        if (!json.success) return showToast(json.message);

        // Habilitar todas las opciones inicialmente
        [timeInput, returnTimeInput].forEach(inp => {
          Array.from(inp.options).forEach(opt => opt.disabled = false);
        });

        // Para cada intervalo ocupado, deshabilitar opciones que solapen
        json.data.forEach(({ horario_salida, hora_regreso }) => {
          if (!hora_regreso) return;  // ignorar si no hay hora regreso
          const busyStart = timeToMinutes(horario_salida);
          const busyEnd = timeToMinutes(hora_regreso);

          [timeInput, returnTimeInput].forEach(inp => {
            Array.from(inp.options).forEach(opt => {
              if (!opt.value) return;
              const optStart = timeToMinutes(opt.value);
              const optEnd = optStart + 30; // asumiendo intervalo 30 min

              if (intervalsOverlap(optStart, optEnd, busyStart, busyEnd)) {
                opt.disabled = true;
              }
            });
          });
        });
      })
      .catch(() => showToast('No se pudieron cargar horarios'));
  }

  // Disparar bloqueo al cambiar origen, destino o fecha
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
        // Aquí podrías actualizar la tabla o refrescar la página si quieres
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
