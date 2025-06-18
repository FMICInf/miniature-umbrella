document.addEventListener('DOMContentLoaded', function() {
  var calendarEl = document.getElementById('calendar');

  // Obtener vehículos únicos para asignar colores
  const vehiculos = [...new Set(window.calendarEvents.map(e => e.extendedProps.vehiculo))];
  const colorMap = {};
  const colors = ['#1E90FF','#FF6347','#32CD32','#FFD700','#6A5ACD','#FF69B4','#20B2AA','#FFA500','#9ACD32'];
  vehiculos.forEach((v, i) => colorMap[v] = colors[i % colors.length]);

  // Asignar color a eventos según vehículo
  window.calendarEvents.forEach(ev => {
    ev.color = colorMap[ev.extendedProps.vehiculo] || '#3788d8';
  });

  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    locale: 'es',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },
    height: 'auto',
    contentHeight: 'auto',
    eventTimeFormat: {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    },
    eventDidMount: function(info) {
      const start = info.event.start;
      const end = info.event.end;
      const vehiculo = info.event.extendedProps.vehiculo;

      function formatTime(date) {
        return date ? date.toLocaleTimeString('es-CL', {hour: '2-digit', minute: '2-digit', hour12: false}) : '-';
      }

      const horaInicio = formatTime(start);
      const horaFin = formatTime(end);

      let titleText = 'Hora inicio: ' + horaInicio;
      if (end) {
        titleText += '\nHora fin: ' + horaFin;
      }
      titleText += '\nVehículo: ' + vehiculo;

      info.el.setAttribute('title', titleText);
    },
    events: window.calendarEvents,

    dateClick: function(info) {
      calendar.changeView('timeGridDay', info.dateStr);
    }
  });

  calendar.render();

  // Filtros de fecha
  document.getElementById('applyFilter').addEventListener('click', function() {
    var from = document.getElementById('fromDate').value;
    var to   = document.getElementById('toDate').value;
    var filtered = window.calendarEvents.filter(function(ev) {
      return (!from || ev.start >= from) && (!to || ev.start <= to);
    });
    calendar.removeAllEvents();
    calendar.addEventSource(filtered);
  });

  document.getElementById('resetFilter').addEventListener('click', function() {
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value   = '';
    calendar.removeAllEvents();
    calendar.addEventSource(window.calendarEvents);
  });
});
