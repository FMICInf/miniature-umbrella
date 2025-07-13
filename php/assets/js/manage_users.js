// assets/js/manage_users.js
document.addEventListener('DOMContentLoaded', () => {
  const modal      = document.getElementById('userModal');
  const form       = document.getElementById('userForm');
  const titleEl    = document.getElementById('userModalTitle');
  const btnAdd     = document.getElementById('btn-add-user');
  const btnClose   = modal.querySelector('.modal-close');
  const btnCancel  = document.getElementById('userCancel');
  const toastCont  = document.getElementById('toast-container');

  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    toastCont.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  function showModal(mode, data = {}) {
    titleEl.textContent = mode + ' Usuario';
    ['id','nombre','email','rol'].forEach(name => {
      if (form[name]) form[name].value = data[name] || '';
    });
    form.password.value = '';
    modal.classList.add('active');
  }

  function hideModal() {
    modal.classList.remove('active');
    form.reset();
  }

  // Agregar Usuario
  btnAdd.addEventListener('click', () => showModal('Agregar'));

  // Cerrar modal
  btnClose.addEventListener('click', hideModal);
  btnCancel.addEventListener('click', hideModal);

  // Editar Usuario
  document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', () => {
      fetch(`get_user.php?id=${btn.dataset.id}`)
        .then(r => r.json())
        .then(json => {
          if (!json.success) return showToast(json.message);
          showModal('Editar', json.data);
        })
        .catch(() => showToast('Error de red'));
    });
  });

  // Eliminar Usuario
  document.querySelectorAll('.btn-delete-user').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('¿Eliminar usuario?')) return;
      fetch('delete_user.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id=${encodeURIComponent(btn.dataset.id)}`
      })
      .then(r => r.json())
      .then(json => {
        if (json.success) location.reload();
        else showToast(json.message);
      })
      .catch(() => showToast('Error de red'));
    });
  });

  // Guardar Usuario (Crear o Actualizar)
  form.addEventListener('submit', e => {
    e.preventDefault();
    const id  = form.id.value;
    const url = id ? 'update_user.php' : 'create_user.php';
    const data = new URLSearchParams(new FormData(form));

    fetch(url, {
      method: 'POST',
      body: data
    })
    .then(r => r.json())
    .then(json => {
      if (!json.success) return showToast(json.message);
      hideModal();
      setTimeout(() => location.reload(), 500);
    })
    .catch(() => showToast('Error de red'));
  });
});
