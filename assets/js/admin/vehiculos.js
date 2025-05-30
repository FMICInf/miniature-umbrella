// File: assets/js/admin/vehiculos.js
document.addEventListener('DOMContentLoaded', () => {
  // Recopilar datos iniciales de la tabla
  const rows = Array.from(document.querySelectorAll('#vehTable tbody tr'))
    .map(tr => {
      const cells = tr.children;
      return {
        tr,
        id: cells[1].textContent,
        patente: cells[2].textContent.toLowerCase(),
        marca: cells[3].textContent.toLowerCase(),
        modelo: cells[4].textContent.toLowerCase(),
        anio: parseInt(cells[5].textContent, 10),
        estado: cells[6].textContent,
        disponibilidad: cells[7].textContent
      };
    });

  let filtered = [...rows];
  let currentPage = 1;
  const perPage = 10;

  // ======= Helpers =========
  function renderTable() {
    const tbody = document.querySelector('#vehTable tbody');
    tbody.innerHTML = '';
    const start = (currentPage - 1) * perPage;
    filtered.slice(start, start + perPage)
      .forEach(r => tbody.appendChild(r.tr));
    renderPagination();
  }
  function renderPagination() {
    const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
    const pg = document.getElementById('pagination');
    pg.innerHTML = `
      <button ${currentPage === 1 ? 'disabled' : ''} id="prev">«</button>
      Página ${currentPage} de ${totalPages}
      <button ${currentPage === totalPages ? 'disabled' : ''} id="next">»</button>
    `;
    document.getElementById('prev').onclick = () => { currentPage--; renderTable(); };
    document.getElementById('next').onclick = () => { currentPage++; renderTable(); };
  }
  function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  // ===== Search & Filters =====
  function applyFilters(q, est, disp) {
    filtered = rows.filter(r =>
      (!q || r.patente.includes(q)) &&
      (!est || r.estado === est) &&
      (!disp || r.disponibilidad === disp)
    );
    currentPage = 1;
    renderTable();
  }
  document.getElementById('search').addEventListener('input', e => {
    applyFilters(
      e.target.value.toLowerCase(),
      document.getElementById('filter-estado').value,
      document.getElementById('filter-disponibilidad').value
    );
  });
  ['filter-estado','filter-disponibilidad'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
      applyFilters(
        document.getElementById('search').value.toLowerCase(),
        document.getElementById('filter-estado').value,
        document.getElementById('filter-disponibilidad').value
      );
    });
  });

  // ===== Sorting =====
  document.querySelectorAll('#vehTable th[data-col]').forEach(th => {
    let asc = true;
    th.addEventListener('click', () => {
      const col = th.getAttribute('data-col');
      filtered.sort((a, b) => {
        if (a[col] < b[col]) return asc ? -1 : 1;
        if (a[col] > b[col]) return asc ? 1 : -1;
        return 0;
      });
      asc = !asc;
      renderTable();
    });
  });

  // ===== Export / Import CSV =====
  document.getElementById('exportCsv').addEventListener('click', () => {
    const header = ['ID','Patente','Marca','Modelo','Año','Estado','Disponibilidad'];
    const csv = [header.join(',')].concat(
      rows.map(r => [r.id, r.patente, r.marca, r.modelo, r.anio, r.estado, r.disponibilidad].join(','))
    ).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'vehiculos.csv'; a.click();
    URL.revokeObjectURL(url);
  });
  document.getElementById('importCsv').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = evt => {
      const lines = evt.target.result.trim().split('\n');
      lines.slice(1).forEach(line => {
        const [id,patente,marca,modelo,anio,estado,disponibilidad] = line.split(',');
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><input type="checkbox" class="row-checkbox"/></td>
          <td>${id}</td>
          <td>${patente}</td>
          <td>${marca}</td>
          <td>${modelo}</td>
          <td>${anio}</td>
          <td>${estado}</td>
          <td>${disponibilidad}</td>
          <td><button class="detail-btn">ℹ️</button></td>
          <td>
            <button class="btn btn-edit">Editar</button>
            <button class="btn btn-delete">Eliminar</button>
          </td>
        `;
        document.querySelector('#vehTable tbody').appendChild(tr);
      });
      applyFilters('', '', '');
      showToast('CSV importado (solo cliente)');
    };
    reader.readAsText(file);
  });

  // ===== Batch Action =====
  document.getElementById('select-all').addEventListener('change', e => {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
  });
  document.getElementById('batchMaintenance').addEventListener('click', () => {
    const sel = rows.filter(r => r.tr.querySelector('.row-checkbox').checked);
    if (!sel.length) return showToast('Selecciona al menos uno');
    if (!confirm(`Marcar ${sel.length} vehículo(s) en mantenimiento?`)) return;
    const ids = sel.map(r => r.id).join(',');
    fetch('../update_vehiculo.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `ids=${encodeURIComponent(ids)}&field=estado&value=en_mantenimiento`
    })
    .then(r => r.json()).then(json => {
      if (json.success) {
        sel.forEach(r => {
          r.tr.children[6].textContent = 'en_mantenimiento';
        });
        showToast('Vehículos actualizados');
      } else {
        showToast(json.message);
      }
    });
  });

  // ===== Detail Modal =====
  const detailModal = document.getElementById('detailModal');
  const detailContent = document.getElementById('detailContent');
  detailModal.querySelector('.modal-close').addEventListener('click', () => {
    detailModal.style.display = 'none';
  });
  document.querySelectorAll('.detail-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr').cloneNode(true);
      // Limpiar botones e inputs
      tr.querySelectorAll('input, button').forEach(el => el.remove());
      detailContent.innerHTML = '';
      detailContent.appendChild(tr);
      detailModal.style.display = 'flex';
    });
  });

  // ===== Add/Edit Modal =====
  const modal = document.getElementById('modal'),
        form  = document.getElementById('formVeh'),
        title = document.getElementById('modalTitle'),
        closeBtns = modal.querySelectorAll('.modal-close'),
        cancelBtn = document.getElementById('btn-cancel');

  function showModal(t, data = {}) {
    title.textContent = t;
    Object.keys(data).forEach(k => {
      if (form[k]) form[k].value = data[k];
    });
    modal.style.display = 'flex';
  }
  function hideModal() {
    modal.style.display = 'none';
    form.reset();
  }
  closeBtns.forEach(b => b.addEventListener('click', hideModal));
  cancelBtn.addEventListener('click', hideModal);
  document.getElementById('btn-add').addEventListener('click', () => showModal('Agregar Vehículo'));

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      fetch(`get_vehiculo.php?id=${btn.dataset.id}`)
        .then(r => r.json())
        .then(json => {
          if (json.success) showModal('Editar Vehículo', json.data);
          else showToast(json.message);
        });
    });
  });
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('¿Eliminar vehículo?')) return;
      fetch('delete_vehiculo.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `id=${btn.dataset.id}`
      })
      .then(r => r.json())
      .then(json => {
        if (json.success) location.reload();
        else showToast(json.message);
      });
    });
  });
  form.addEventListener('submit', e => {
    e.preventDefault();
    const url = form.id.value ? 'update_vehiculo.php' : 'create_vehiculo.php';
    fetch(url, {
      method: 'POST',
      body: new URLSearchParams(new FormData(form))
    })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        hideModal();
        location.reload();
      } else {
        showToast(json.message);
      }
    });
  });

  // ===== Chart de estados =====
  const ctx = document.getElementById('vehStatusChart');
  const counts = rows.reduce((acc, r) => {
    acc[r.estado] = (acc[r.estado] || 0) + 1;
    return acc;
  }, {});
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: Object.keys(counts),
      datasets: [{ data: Object.values(counts) }]
    }
  });

  // Render inicial
  renderTable();
});

