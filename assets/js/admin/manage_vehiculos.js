// Archivo: assets/css/js/admin/manage_vehiculos.js

document.addEventListener('DOMContentLoaded', () => {
  const modal      = document.getElementById('modal');
  const form       = document.getElementById('formVeh');
  const title      = document.getElementById('modalTitle');
  const closeBtn   = document.querySelector('.modal-close');
  const cancelBtn  = document.getElementById('btn-cancel');

  // Mostrar modal (Agregar/Editar)
  function showModal(mode, data = {}) {
    title.textContent = mode + ' Vehículo';
    // Rellena o limpia todos los campos incluidos 'capacidad'
    ['id','patente','marca','modelo','anio','estado','disponibilidad','capacidad']
      .forEach(name => {
        if (form[name] !== undefined) {
          form[name].value = data[name] ?? '';
        }
      });
    modal.style.display = 'flex';
  }

  // Ocultar modal
  function hideModal() {
    modal.style.display = 'none';
    form.reset();
  }

  // Toast de feedback
  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  // Abrir modal para nuevo vehículo
  document.getElementById('btn-add').addEventListener('click', () => {
    showModal('Agregar');
  });

  // Cerrar modal
  closeBtn.addEventListener('click', hideModal);
  cancelBtn.addEventListener('click', hideModal);

  // Editar vehículo
  document.querySelectorAll('.btn-edit').forEach(btn =>
    btn.addEventListener('click', () => {
      fetch(`/log/php/admin/get_vehiculo.php?id=${btn.dataset.id}`)
        .then(res => res.json())
        .then(json => {
          if (!json.success) {
            showToast(json.message);
          } else {
            showModal('Editar', json.data);
          }
        })
        .catch(() => showToast('Error de red al cargar vehículo'));
    })
  );

  // Eliminar vehículo
  document.querySelectorAll('.btn-delete').forEach(btn =>
    btn.addEventListener('click', () => {
      if (!confirm('¿Eliminar este vehículo?')) return;
      fetch('/log/php/admin/delete_vehiculo.php', {
        method: 'POST',
        headers: { 'Content-Type':'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(btn.dataset.id)}`
      })
      .then(res => res.json())
      .then(json => {
        if (json.success) location.reload();
        else showToast(json.message);
      })
      .catch(() => showToast('Error de red al eliminar'));
    })
  );

  // Guardar (crear o actualizar)
  form.addEventListener('submit', e => {
    e.preventDefault();
    const id  = form.id.value;
    const url = id
      ? '/log/php/admin/update_vehiculo.php'
      : '/log/php/admin/create_vehiculo.php';
    const data = new URLSearchParams(new FormData(form));

    fetch(url, {
      method: 'POST',
      body: data
    })
    .then(res => res.json())
    .then(json => {
      if (!json.success) {
        showToast(json.message);
      } else {
        hideModal();
        setTimeout(() => location.reload(), 500);
      }
    })
    .catch(() => showToast('Error de red al guardar'));
  });
});
