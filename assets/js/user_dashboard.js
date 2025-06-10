// assets/js/user_dashboard.js
document.addEventListener('DOMContentLoaded', () => {
  const form            = document.getElementById('solForm');
  const roundTripCheck  = document.getElementById('round_trip');
  const returnGroup     = document.getElementById('returnTimeGroup');
  const returnInput     = document.getElementById('hora_regreso');
  const motivoSelect    = document.getElementById('motivo');
  const motivoOtroGroup = document.getElementById('motivoOtroGroup');
  const motivoOtroInput = document.getElementById('motivo_otro');
  const toastContainer  = document.getElementById('toast');

  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    toastContainer.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  // Mostrar/hide hora de regreso
  roundTripCheck.addEventListener('change', () => {
    if (roundTripCheck.checked) {
      returnGroup.classList.remove('hidden');
      returnInput.required = true;
    } else {
      returnGroup.classList.add('hidden');
      returnInput.required = false;
      returnInput.value = '';
    }
  });

  // Mostrar/hide motivo “Otro”
  motivoSelect.addEventListener('change', () => {
    if (motivoSelect.value === 'Otro') {
      motivoOtroGroup.classList.remove('hidden');
      motivoOtroInput.required = true;
    } else {
      motivoOtroGroup.classList.add('hidden');
      motivoOtroInput.required = false;
      motivoOtroInput.value = '';
    }
  });

  // Enviar formulario con FormData (incluye adjunto)
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
        showToast('Solicitud enviada con éxito');
        // TODO: actualizar tabla y métricas en caliente
      } else {
        showToast('Error: ' + json.message);
      }
    })
    .catch(() => showToast('Error de red'));
  });

  // Cancelar solicitud…
  document.querySelectorAll('.btn-cancel').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('¿Cancelar esta solicitud?')) return;
      fetch('/log/php/cancel_solicitud.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'id='+encodeURIComponent(btn.dataset.id)
      })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          btn.disabled = true;
          btn.closest('tr').querySelector('.badge').textContent = 'Cancelada';
          showToast('Solicitud cancelada');
        } else {
          showToast('Error: '+json.message);
        }
      })
      .catch(() => showToast('Error de red'));
    });
  });

  // TODO: Polling si lo necesitas…
});
