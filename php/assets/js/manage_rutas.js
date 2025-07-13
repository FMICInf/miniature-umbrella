// assets/js/manage_rutas.js

document.addEventListener('DOMContentLoaded', () => {
  const modal     = document.getElementById('routeModal');
  const form      = document.getElementById('routeForm');
  const titleEl   = document.getElementById('routeModalTitle');
  const btnAdd    = document.getElementById('btn-add-route');
  const btnClose  = modal.querySelector('.modal-close');
  const btnCancel = document.getElementById('routeCancel');
  const toastCont = document.getElementById('toast-container');

  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    toastCont.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  function showModal(mode, data = {}) {
    titleEl.textContent = mode + ' Ruta';
    // Rellenar campos
    ['id','origen','destino','horario_salida','horario_llegada']
      .forEach(name => {
        if (form[name]) form[name].value = data[name] || '';
      });
    // Mostrar modal
    modal.classList.remove('hidden');
    modal.classList.add('active');
  }

  function hideModal() {
    modal.classList.remove('active');
    modal.classList.add('hidden');
    form.reset();
  }

  // + Agregar Ruta
  btnAdd.addEventListener('click', () => {
    showModal('Agregar');
  });

  // Cerrar modal (X y Cancelar)
  btnClose.addEventListener('click', hideModal);
  btnCancel.addEventListener('click', hideModal);

  // Editar Ruta
  document.querySelectorAll('.btn-edit-route').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      fetch(`get_ruta.php?id=${id}`)
        .then(res => res.json())
        .then(json => {
          if (!json.success) {
            showToast(json.message);
          } else {
            showModal('Editar', json.data);
          }
        })
        .catch(() => showToast('Error de red'));
    });
  });

  // Eliminar Ruta
  document.querySelectorAll('.btn-delete-route').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('¿Eliminar esta ruta?')) return;
      fetch('delete_ruta.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(btn.dataset.id)}`
      })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          location.reload();
        } else {
          showToast(json.message);
        }
      })
      .catch(() => showToast('Error de red'));
    });
  });

  // Guardar (Crear o Actualizar)
  form.addEventListener('submit', e => {
    e.preventDefault();
    const id  = form.id.value;
    const url = id ? 'update_ruta.php' : 'create_ruta.php';
    const body = new URLSearchParams(new FormData(form));

    fetch(url, {
      method: 'POST',
      body: body
    })
    .then(res => res.json())
    .then(json => {
      if (json.success) {
        hideModal();
        setTimeout(() => location.reload(), 500);
      } else {
        showToast(json.message);
      }
    })
    .catch(() => showToast('Error de red'));
  });
});
