
// Archivo: assets/css/js/admin/vehiculos.js
document.addEventListener('DOMContentLoaded', () => {
  const modal    = document.getElementById('modal');
  const form     = document.getElementById('formVeh');
  const title    = document.getElementById('modalTitle');
  const closeBtn = document.querySelector('.modal-close');
  const cancelBtn= document.getElementById('btn-cancel');

  // Funciones para mostrar/ocultar modal
  function showModal(t, data = {}) {
    title.textContent = t;
    Object.keys(data).forEach(key => {
      if (form[key]) form[key].value = data[key];
    });
    modal.style.display = 'flex';
  }
  function hideModal() {
    modal.style.display = 'none';
    form.reset();
  }

  // Toast de feedback
  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  // Abrir modal para nuevo vehículo
  document.getElementById('btn-add').addEventListener('click', () => {
    showModal('Agregar Vehículo');
  });

  // Cerrar modal
  closeBtn.addEventListener('click', hideModal);
  cancelBtn.addEventListener('click', hideModal);

  // Editar vehículo
  document.querySelectorAll('.btn-edit').forEach(btn =>
    btn.addEventListener('click', () => {
      fetch(`get_vehiculo.php?id=${btn.dataset.id}`)
        .then(res => res.json())
        .then(json => {
          if (json.success) {
            showModal('Editar Vehículo', json.data);
          } else {
            showToast(json.message);
          }
        });
    })
  );

  // Eliminar vehículo
  document.querySelectorAll('.btn-delete').forEach(btn =>
    btn.addEventListener('click', () => {
      if (!confirm('¿Eliminar vehículo?')) return;
      fetch('delete_vehiculo.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `id=${btn.dataset.id}`
      })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          location.reload();
        } else {
          showToast(json.message);
        }
      });
    })
  );

  // Guardar (crear o actualizar)
  form.addEventListener('submit', e => {
    e.preventDefault();
    const id  = form.id.value;
    const url = id ? 'update_vehiculo.php' : 'create_vehiculo.php';
    fetch(url, {
      method: 'POST',
      body: new URLSearchParams(new FormData(form))
    })
    .then(res => res.json())
    .then(json => {
      if (json.success) {
        hideModal();
        location.reload();
      } else {
        showToast(json.message);
      }
    });
  });
});

