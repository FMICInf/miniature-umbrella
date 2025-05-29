import { Calendar } from 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js';
import localeEs from 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js';

document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('calendar');
    const calendar = new Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: JSON.parse('<?= $eventsJson ?>')
    });
    calendar.render();
});


document.querySelectorAll('.btn-cancel').forEach(btn => {
  btn.addEventListener('click', () => {
    if (!confirm('¿Cancelar esta solicitud?')) return;
    fetch('cancel_solicitud.php', {
      method:'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'id=' + btn.dataset.id
    })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        // Cambia badge y métricas aquí
        btn.disabled = true;
        btn.closest('tr').querySelector('.badge').textContent = 'Cancelada';
        showToast('Solicitud cancelada');
      } else {
        showToast('Error: '+json.message);
      }
    });
  });
});


