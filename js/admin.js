function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

async function parseJson(res) {
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    console.error(text);
    throw new Error('Invalid server response');
  }
}

async function adminUsers(action, params = {}) {
  if (action === 'list' || action === 'stats') {
    const qs = new URLSearchParams({ action, ...params });
    const res = await fetch('api_admin_users.php?' + qs.toString());
    return parseJson(res);
  }
  const body = new URLSearchParams({ action, ...params });
  const res = await fetch('api_admin_users.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  });
  return parseJson(res);
}

async function adminConnections(action, params = {}) {
  if (action === 'list') {
    const qs = new URLSearchParams({ action, ...params });
    const res = await fetch('api_admin_connections.php?' + qs.toString());
    return parseJson(res);
  }
  const body = new URLSearchParams({ action, ...params });
  const res = await fetch('api_admin_connections.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  });
  return parseJson(res);
}

async function adminAnalytics() {
  const res = await fetch('api_admin_analytics.php');
  return parseJson(res);
}

async function adminReports(action, params = {}) {
  if (action === 'list') {
    const qs = new URLSearchParams({ action, ...params });
    const res = await fetch('api_admin_reports.php?' + qs.toString());
    return parseJson(res);
  }
  const body = new URLSearchParams({ action, ...params });
  const res = await fetch('api_admin_reports.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  });
  return parseJson(res);
}

document.addEventListener('DOMContentLoaded', () => {
  const usersBody = document.getElementById('usersTableBody');
  const connectionsBody = document.getElementById('connectionsTableBody');
  const reportsBody = document.getElementById('reportsTableBody');
  const editModalEl = document.getElementById('editUserModal');
  const reportModalEl = document.getElementById('reportModal');
  const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
  const reportModal = reportModalEl ? new bootstrap.Modal(reportModalEl) : null;

  let signupsChart;
  let roleMixChart;
  let messagesChart;
  let reportsCache = [];

  document.querySelectorAll('#adminTabs [data-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#adminTabs .nav-link').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      const tab = btn.getAttribute('data-tab');
      document.querySelectorAll('.admin-panel').forEach((p) => (p.style.display = 'none'));
      document.getElementById('tab-' + tab).style.display = 'block';
      if (tab === 'connections') loadConnections();
      if (tab === 'users') loadUsers();
      if (tab === 'reports') loadReports();
      if (tab === 'analytics') loadAnalytics();
    });
  });

  function upsertLineChart(existing, canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return existing;
    if (existing) existing.destroy();
    return new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  async function loadAnalytics() {
    try {
      const data = await adminAnalytics();
      if (!data.success) return;
      const s = data.stats;
      const m = data.marketing;

      document.getElementById('statTotalUsers').textContent = s.patients + s.doctors;
      document.getElementById('statPatients').textContent = s.patients;
      document.getElementById('statDoctors').textContent = s.doctors;
      document.getElementById('statActive').textContent = s.active_users;
      document.getElementById('statMsg7').textContent = s.messages_7d;
      document.getElementById('statReportsOpen').textContent = s.reports_open;

      const badge = document.getElementById('openReportsBadge');
      const tabBadge = document.getElementById('tabReportsBadge');
      if (s.reports_open > 0) {
        badge.style.display = 'inline-flex';
        document.getElementById('openReportsCount').textContent = s.reports_open;
        tabBadge.style.display = 'inline';
        tabBadge.textContent = s.reports_open;
      } else {
        badge.style.display = 'none';
        tabBadge.style.display = 'none';
      }

      document.getElementById('insNew30').textContent = s.new_users_30d;
      document.getElementById('insNewSplit').textContent =
        `${s.new_patients_30d} patients · ${s.new_doctors_30d} doctors · ${s.new_users_7d} in last 7d`;
      document.getElementById('insLinkRate').textContent = m.patient_connection_rate + '%';
      document.getElementById('insVitalsRate').textContent = m.vitals_adoption_rate + '%';
      document.getElementById('insVitalsSub').textContent =
        `${s.patients_with_vitals} patients · ${s.vitals_records_total} vitals records`;
      document.getElementById('insAcceptRate').textContent = s.acceptance_rate + '%';
      document.getElementById('insConnSub').textContent =
        `${s.connections_accepted} accepted · ${s.connections_pending} pending · ${s.connections_rejected} rejected`;

      document.getElementById('engagementList').innerHTML = `
        <li><strong>${s.messages_total}</strong> total messages · <strong>${s.messages_30d}</strong> in 30 days</li>
        <li><strong>${s.active_messagers_7d}</strong> users messaged in last 7 days</li>
        <li><strong>${m.messages_per_active_user_7d}</strong> avg messages / active user (7d)</li>
        <li><strong>${s.patients_with_connection}</strong> patients linked · <strong>${s.doctors_with_connection}</strong> doctors linked</li>
        <li><strong>${s.avg_patients_per_connected_doctor}</strong> avg patients per connected doctor</li>
        <li><strong>${s.disabled_users}</strong> disabled accounts · <strong>${s.reports_7d}</strong> reports this week</li>
      `;

      const signupLabels = data.charts.signups_14d.map((d) => d.date.slice(5));
      signupsChart = upsertLineChart(signupsChart, 'signupsChart', signupLabels, [
        {
          label: 'Patients',
          data: data.charts.signups_14d.map((d) => d.patients),
          borderColor: '#1d4f91',
          backgroundColor: 'rgba(29,79,145,0.15)',
          tension: 0.3,
          fill: true
        },
        {
          label: 'Doctors',
          data: data.charts.signups_14d.map((d) => d.doctors),
          borderColor: '#00a8d5',
          backgroundColor: 'rgba(0,168,213,0.12)',
          tension: 0.3,
          fill: true
        }
      ]);

      const msgLabels = data.charts.messages_14d.map((d) => d.date.slice(5));
      messagesChart = upsertLineChart(messagesChart, 'messagesChart', msgLabels, [
        {
          label: 'Messages',
          data: data.charts.messages_14d.map((d) => d.count),
          borderColor: '#0f2744',
          backgroundColor: 'rgba(15,39,68,0.12)',
          tension: 0.3,
          fill: true
        }
      ]);

      const mixCtx = document.getElementById('roleMixChart');
      if (mixCtx) {
        if (roleMixChart) roleMixChart.destroy();
        roleMixChart = new Chart(mixCtx, {
          type: 'doughnut',
          data: {
            labels: data.charts.role_mix.map((x) => x.label),
            datasets: [{
              data: data.charts.role_mix.map((x) => x.value),
              backgroundColor: ['#1d4f91', '#00a8d5']
            }]
          },
          options: { plugins: { legend: { position: 'bottom' } } }
        });
      }

      const topBody = document.getElementById('topUsersBody');
      if (!data.top_users.length) {
        topBody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-4">No messaging activity in the last 30 days.</td></tr>';
      } else {
        topBody.innerHTML = data.top_users
          .map((u, i) => `<tr>
            <td>${i + 1}</td>
            <td>${escapeHtml(u.name)}</td>
            <td>${u.role === 'doctor' ? '<span class="badge bg-info text-dark">Doctor</span>' : '<span class="badge bg-primary">Patient</span>'}</td>
            <td>${escapeHtml(u.phone)}</td>
            <td><strong>${u.msg_count}</strong></td>
          </tr>`)
          .join('');
      }
    } catch (e) {
      console.error(e);
    }
  }

  async function loadUsers() {
    usersBody.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-4">Loading…</td></tr>';
    try {
      const role = document.getElementById('userRoleFilter').value;
      const q = document.getElementById('userSearch').value.trim();
      const data = await adminUsers('list', { role, q });
      if (!data.success) {
        usersBody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-4">${escapeHtml(data.message || 'Failed')}</td></tr>`;
        return;
      }
      if (!data.users.length) {
        usersBody.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-4">No users found.</td></tr>';
        return;
      }
      usersBody.innerHTML = data.users
        .map((u) => {
          const statusBadge = u.is_active
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Disabled</span>';
          const roleBadge =
            u.role === 'doctor'
              ? '<span class="badge bg-info text-dark">Doctor</span>'
              : '<span class="badge bg-primary">Patient</span>';
          return `<tr>
            <td>${u.id}</td>
            <td>${escapeHtml(u.name)}</td>
            <td>${escapeHtml(u.phone)}</td>
            <td>${roleBadge}</td>
            <td>${statusBadge}</td>
            <td class="small text-muted">${escapeHtml(u.created_at || '—')}</td>
            <td class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary js-edit"
                data-id="${u.id}" data-name="${escapeHtml(u.name)}" data-phone="${escapeHtml(u.phone)}">Edit</button>
              <button type="button" class="btn btn-sm btn-outline-warning js-toggle" data-id="${u.id}">
                ${u.is_active ? 'Disable' : 'Enable'}
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger js-delete" data-id="${u.id}" data-name="${escapeHtml(u.name)}">Delete</button>
            </td>
          </tr>`;
        })
        .join('');
    } catch (e) {
      usersBody.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-4">Failed to load users.</td></tr>';
    }
  }

  async function loadConnections() {
    connectionsBody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-4">Loading…</td></tr>';
    try {
      const status = document.getElementById('connStatusFilter').value;
      const q = document.getElementById('connSearch').value.trim();
      const data = await adminConnections('list', { status, q });
      if (!data.success) {
        connectionsBody.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-4">${escapeHtml(data.message || 'Failed')}</td></tr>`;
        return;
      }
      if (!data.connections.length) {
        connectionsBody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-4">No connections found.</td></tr>';
        return;
      }
      connectionsBody.innerHTML = data.connections
        .map((c) => {
          let badge = 'bg-secondary';
          if (c.status === 'accepted') badge = 'bg-success';
          if (c.status === 'pending') badge = 'bg-warning text-dark';
          if (c.status === 'rejected') badge = 'bg-danger';
          return `<tr>
            <td>${c.id}</td>
            <td><strong>${escapeHtml(c.doctor_name)}</strong><div class="small text-muted">${escapeHtml(c.doctor_phone)}</div></td>
            <td><strong>${escapeHtml(c.patient_name)}</strong><div class="small text-muted">${escapeHtml(c.patient_phone)}</div></td>
            <td><span class="badge ${badge}">${escapeHtml(c.status)}</span></td>
            <td class="small text-muted">${escapeHtml(c.updated_at || '')}</td>
            <td class="admin-actions">
              ${c.status !== 'accepted' ? `<button type="button" class="btn btn-sm btn-success js-conn-status" data-id="${c.id}" data-status="accepted">Accept</button>` : ''}
              ${c.status !== 'rejected' ? `<button type="button" class="btn btn-sm btn-outline-warning js-conn-status" data-id="${c.id}" data-status="rejected">Reject</button>` : ''}
              <button type="button" class="btn btn-sm btn-outline-danger js-conn-delete" data-id="${c.id}">Remove</button>
            </td>
          </tr>`;
        })
        .join('');
    } catch (e) {
      connectionsBody.innerHTML = '<tr><td colspan="6" class="text-danger text-center py-4">Failed to load connections.</td></tr>';
    }
  }

  function reportStatusBadge(status) {
    const map = {
      open: 'bg-danger',
      in_progress: 'bg-warning text-dark',
      resolved: 'bg-success',
      closed: 'bg-secondary'
    };
    return `<span class="badge ${map[status] || 'bg-secondary'}">${escapeHtml(status)}</span>`;
  }

  async function loadReports() {
    reportsBody.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-4">Loading…</td></tr>';
    try {
      const status = document.getElementById('reportStatusFilter').value;
      const q = document.getElementById('reportSearch').value.trim();
      const data = await adminReports('list', { status, q });
      if (!data.success) {
        reportsBody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-4">${escapeHtml(data.message || 'Failed')}</td></tr>`;
        return;
      }
      reportsCache = data.reports || [];
      if (!reportsCache.length) {
        reportsBody.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-4">No reports found.</td></tr>';
        return;
      }
      reportsBody.innerHTML = reportsCache
        .map((r) => `<tr>
          <td>${r.id}</td>
          <td>
            <strong>${escapeHtml(r.reporter_name)}</strong>
            <div class="small text-muted">${escapeHtml(r.reporter_role)} · ${escapeHtml(r.reporter_phone)}</div>
          </td>
          <td>${escapeHtml(r.category)}</td>
          <td>${escapeHtml(r.subject)}</td>
          <td>${reportStatusBadge(r.status)}</td>
          <td class="small text-muted">${escapeHtml(r.created_at)}</td>
          <td><button type="button" class="btn btn-sm btn-outline-primary js-view-report" data-id="${r.id}">Open</button></td>
        </tr>`)
        .join('');
    } catch (e) {
      reportsBody.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-4">Failed to load reports.</td></tr>';
    }
  }

  document.getElementById('userSearchBtn').addEventListener('click', loadUsers);
  document.getElementById('userSearch').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') loadUsers();
  });
  document.getElementById('userRoleFilter').addEventListener('change', loadUsers);
  document.getElementById('connSearchBtn').addEventListener('click', loadConnections);
  document.getElementById('connSearch').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') loadConnections();
  });
  document.getElementById('connStatusFilter').addEventListener('change', loadConnections);
  document.getElementById('reportSearchBtn').addEventListener('click', loadReports);
  document.getElementById('reportSearch').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') loadReports();
  });
  document.getElementById('reportStatusFilter').addEventListener('change', loadReports);

  usersBody.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.js-edit');
    if (editBtn) {
      document.getElementById('editUserId').value = editBtn.dataset.id;
      document.getElementById('editUserName').value = editBtn.dataset.name;
      document.getElementById('editUserPhone').value = editBtn.dataset.phone;
      document.getElementById('editUserPassword').value = '';
      editModal.show();
      return;
    }

    const toggleBtn = e.target.closest('.js-toggle');
    if (toggleBtn) {
      const res = await adminUsers('toggle', { user_id: toggleBtn.dataset.id });
      alert(res.message || (res.success ? 'Done' : 'Failed'));
      if (res.success) {
        loadUsers();
        loadAnalytics();
      }
      return;
    }

    const delBtn = e.target.closest('.js-delete');
    if (delBtn) {
      if (!confirm(`Delete "${delBtn.dataset.name}" permanently? This removes connections and related data.`)) return;
      const res = await adminUsers('delete', { user_id: delBtn.dataset.id });
      alert(res.message || (res.success ? 'Deleted' : 'Failed'));
      if (res.success) {
        loadUsers();
        loadAnalytics();
      }
    }
  });

  connectionsBody.addEventListener('click', async (e) => {
    const statusBtn = e.target.closest('.js-conn-status');
    if (statusBtn) {
      const res = await adminConnections('force_status', {
        connection_id: statusBtn.dataset.id,
        status: statusBtn.dataset.status
      });
      alert(res.message || (res.success ? 'Updated' : 'Failed'));
      if (res.success) {
        loadConnections();
        loadAnalytics();
      }
      return;
    }
    const delBtn = e.target.closest('.js-conn-delete');
    if (delBtn) {
      if (!confirm('Remove this connection permanently?')) return;
      const res = await adminConnections('delete', { connection_id: delBtn.dataset.id });
      alert(res.message || (res.success ? 'Removed' : 'Failed'));
      if (res.success) {
        loadConnections();
        loadAnalytics();
      }
    }
  });

  reportsBody.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-view-report');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    const report = reportsCache.find((r) => r.id === id);
    if (!report) return;
    document.getElementById('reportEditId').value = report.id;
    document.getElementById('reportModalId').textContent = report.id;
    document.getElementById('reportModalSubject').textContent = report.subject;
    document.getElementById('reportModalMeta').textContent =
      `${report.reporter_name} (${report.reporter_role}) · ${report.reporter_phone} · ${report.category} · ${report.created_at}`;
    document.getElementById('reportModalMessage').textContent = report.message;
    document.getElementById('reportEditStatus').value = report.status;
    document.getElementById('reportEditNote').value = report.admin_note || '';
    reportModal.show();
  });

  document.getElementById('reportUpdateForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await adminReports('update', {
      report_id: document.getElementById('reportEditId').value,
      status: document.getElementById('reportEditStatus').value,
      admin_note: document.getElementById('reportEditNote').value
    });
    alert(res.message || (res.success ? 'Updated' : 'Failed'));
    if (res.success) {
      reportModal.hide();
      loadReports();
      loadAnalytics();
    }
  });

  document.getElementById('createUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await adminUsers('create', Object.fromEntries(fd.entries()));
    alert(res.message || (res.success ? 'Created' : 'Failed'));
    if (res.success) {
      e.target.reset();
      loadAnalytics();
      document.querySelector('#adminTabs [data-tab="users"]').click();
      loadUsers();
    }
  });

  document.getElementById('editUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const userId = document.getElementById('editUserId').value;
    const name = document.getElementById('editUserName').value.trim();
    const phone = document.getElementById('editUserPhone').value.trim();
    const password = document.getElementById('editUserPassword').value;

    const updateRes = await adminUsers('update', { user_id: userId, name, phone });
    if (!updateRes.success) {
      alert(updateRes.message || 'Update failed');
      return;
    }

    if (password.trim() !== '') {
      const resetRes = await adminUsers('reset_password', { user_id: userId, password: password.trim() });
      if (!resetRes.success) {
        alert(resetRes.message || 'Password reset failed');
        return;
      }
    }

    alert('User saved.');
    editModal.hide();
    loadUsers();
  });

  loadAnalytics();
});
