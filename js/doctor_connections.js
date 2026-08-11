document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('patientSearchInput');
  const btn = document.getElementById('patientSearchBtn');
  const resultsEl = document.getElementById('patientSearchResults');

  async function runSearch() {
    const q = (input.value || '').trim();
    if (q.length < 2) {
      resultsEl.innerHTML = '<p class="text-muted mb-0">Enter at least 2 characters.</p>';
      return;
    }
    resultsEl.innerHTML = '<p class="text-muted mb-0">Searching…</p>';
    try {
      const data = await searchOppositeUsers(q);
      if (!data.success || !data.results.length) {
        resultsEl.innerHTML = '<p class="text-muted mb-0">No patients found.</p>';
        return;
      }
      resultsEl.innerHTML = data.results.map(renderSearchRow).join('');
    } catch (e) {
      resultsEl.innerHTML = '<p class="text-danger mb-0">Search failed.</p>';
    }
  }

  function renderSearchRow(user) {
    let action = '';
    switch (user.connection_status) {
      case 'accepted':
        action = `<a class="btn btn-success btn-sm" href="monitor_patient.php?id=${user.id}">Monitor</a>`;
        break;
      case 'pending_sent':
        action = `<button type="button" class="btn btn-outline-secondary btn-sm js-cancel" data-user-id="${user.id}">Cancel request</button>`;
        break;
      case 'pending_received':
        action = `
          <button type="button" class="btn btn-success btn-sm js-accept" data-user-id="${user.id}">Accept</button>
          <button type="button" class="btn btn-outline-danger btn-sm js-reject" data-user-id="${user.id}">Reject</button>`;
        break;
      default:
        action = `<button type="button" class="btn btn-primary btn-sm js-send" data-user-id="${user.id}">Send request</button>`;
    }
    return `
      <div class="conn-result-row">
        <div>
          <strong>${escapeHtml(user.name)}</strong>
          <div class="text-muted small">${escapeHtml(user.phone)} · ${connectionStatusLabel(user.connection_status)}</div>
        </div>
        <div class="conn-actions">${action}</div>
      </div>`;
  }

  async function handleAction(e) {
    const btnEl = e.target.closest('[data-user-id]');
    if (!btnEl) return;
    const userId = btnEl.getAttribute('data-user-id');
    let action = null;
    if (btnEl.classList.contains('js-send')) action = 'send';
    if (btnEl.classList.contains('js-accept')) action = 'accept';
    if (btnEl.classList.contains('js-reject')) action = 'reject';
    if (btnEl.classList.contains('js-cancel')) action = 'cancel';
    if (!action) return;

    btnEl.disabled = true;
    try {
      const res = await connectionApi(action, userId);
      alert(res.message || (res.success ? 'Done' : 'Failed'));
      if (res.success) location.reload();
    } catch (err) {
      alert('Request failed');
    } finally {
      btnEl.disabled = false;
    }
  }

  btn.addEventListener('click', runSearch);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runSearch();
    }
  });
  document.body.addEventListener('click', handleAction);
});
