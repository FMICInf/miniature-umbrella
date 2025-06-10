// assets/js/calendar.js
document.addEventListener('DOMContentLoaded', function() {
  var calendarEl = document.getElementById('calendar');

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
      hour:   '2-digit',
      minute: '2-digit',
      hour12: false
    },
    eventDidMount: function(info) {
      var hora      = info.event.extendedProps.hora;
      var conductor = info.event.extendedProps.conductor;
      info.el.setAttribute('title',
        'Hora: ' + hora + '\nConductor: ' + conductor
      );
    },
    events: window.calendarEvents,

    // ← Aquí añadimos dateClick:
    dateClick: function(info) {
      // Cambia a la vista diaria de ese día
      calendar.changeView('timeGridDay', info.dateStr);
    }
  });

  calendar.render();

  // filtros…
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
