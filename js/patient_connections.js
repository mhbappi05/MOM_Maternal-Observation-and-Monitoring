document.addEventListener('DOMContentLoaded', () => {
  const connectedList = document.getElementById('patientConnectedList');
  const incomingList = document.getElementById('patientIncomingList');
  const outgoingList = document.getElementById('patientOutgoingList');
  const searchInput = document.getElementById('doctorSearchInput');
  const searchBtn = document.getElementById('doctorSearchBtn');
  const searchResults = document.getElementById('doctorSearchResults');

  if (!connectedList) return;

  document.querySelectorAll('[data-conn-tab]').forEach((tabBtn) => {
    tabBtn.addEventListener('click', () => {
      document.querySelectorAll('[data-conn-tab]').forEach((b) => b.classList.remove('active'));
      tabBtn.classList.add('active');
      const tab = tabBtn.getAttribute('data-conn-tab');
      document.getElementById('connTabConnected').style.display = tab === 'connected' ? 'block' : 'none';
      document.getElementById('connTabIncoming').style.display = tab === 'incoming' ? 'block' : 'none';
      document.getElementById('connTabFind').style.display = tab === 'find' ? 'block' : 'none';
    });
  });

  function empty(text) {
    return `<li class="conn-empty">${escapeHtml(text)}</li>`;
  }

  async function refreshLists() {
    const data = await loadConnectionsList();
    if (!data.success) return;

    if (!data.connected.length) {
      connectedList.innerHTML = empty('No connected doctors yet.');
    } else {
      connectedList.innerHTML = data.connected
        .map(
          (d) => `<li>
            <div><strong>${escapeHtml(d.name)}</strong><div class="text-muted small">${escapeHtml(d.phone)}</div></div>
            <button type="button" class="btn btn-sm btn-info js-open-chat" data-doctor-id="${d.user_id}" data-doctor-name="${escapeHtml(d.name)}">Message</button>
          </li>`
        )
        .join('');
    }

    if (!data.incoming.length) {
      incomingList.innerHTML = empty('No incoming requests.');
    } else {
      incomingList.innerHTML = data.incoming
        .map(
          (d) => `<li>
            <div><strong>Dr. ${escapeHtml(d.name)}</strong><div class="text-muted small">${escapeHtml(d.phone)}</div></div>
            <div class="conn-actions">
              <button type="button" class="btn btn-success btn-sm js-accept" data-user-id="${d.user_id}">Accept</button>
              <button type="button" class="btn btn-outline-danger btn-sm js-reject" data-user-id="${d.user_id}">Reject</button>
            </div>
          </li>`
        )
        .join('');
    }

    if (!data.outgoing.length) {
      outgoingList.innerHTML = empty('No pending requests you sent.');
    } else {
      outgoingList.innerHTML = data.outgoing
        .map(
          (d) => `<li>
            <div><strong>Dr. ${escapeHtml(d.name)}</strong><div class="text-muted small">${escapeHtml(d.phone)}</div></div>
            <button type="button" class="btn btn-outline-secondary btn-sm js-cancel" data-user-id="${d.user_id}">Cancel</button>
          </li>`
        )
        .join('');
    }
  }

  function renderSearchRow(user) {
    let action = '';
    switch (user.connection_status) {
      case 'accepted':
        action = `<button type="button" class="btn btn-info btn-sm js-open-chat" data-doctor-id="${user.id}" data-doctor-name="${escapeHtml(user.name)}">Message</button>`;
        break;
      case 'pending_sent':
        action = `<button type="button" class="btn btn-outline-secondary btn-sm js-cancel" data-user-id="${user.id}">Cancel</button>`;
        break;
      case 'pending_received':
        action = `
          <button type="button" class="btn btn-success btn-sm js-accept" data-user-id="${user.id}">Accept</button>
          <button type="button" class="btn btn-outline-danger btn-sm js-reject" data-user-id="${user.id}">Reject</button>`;
        break;
      default:
        action = `<button type="button" class="btn btn-primary btn-sm js-send" data-user-id="${user.id}">Send request</button>`;
    }
    return `<div class="conn-result-row">
      <div>
        <strong>Dr. ${escapeHtml(user.name)}</strong>
        <div class="text-muted small">${escapeHtml(user.phone)} · ${connectionStatusLabel(user.connection_status)}</div>
      </div>
      <div class="conn-actions">${action}</div>
    </div>`;
  }

  async function runSearch() {
    const q = (searchInput.value || '').trim();
    if (q.length < 2) {
      searchResults.innerHTML = '<p class="text-muted mb-0">Enter at least 2 characters.</p>';
      return;
    }
    searchResults.innerHTML = '<p class="text-muted mb-0">Searching…</p>';
    try {
      const data = await searchOppositeUsers(q);
      if (!data.success || !data.results.length) {
        searchResults.innerHTML = '<p class="text-muted mb-0">No doctors found.</p>';
        return;
      }
      searchResults.innerHTML = data.results.map(renderSearchRow).join('');
    } catch (e) {
      console.error(e);
      searchResults.innerHTML = '<p class="text-danger mb-0">Search failed. Please try again.</p>';
    }
  }

  async function handleClick(e) {
    const chatBtn = e.target.closest('.js-open-chat');
    if (chatBtn) {
      const doctorId = chatBtn.getAttribute('data-doctor-id');
      const openMessenger = document.getElementById('openMessenger');
      if (openMessenger) openMessenger.click();
      if (typeof selectDoctor === 'function') {
        // Ensure list item exists for name lookup
        selectDoctor(doctorId);
        const back = document.getElementById('backToDoctors');
        if (back) back.style.display = 'inline-block';
      }
      return;
    }

    const btnEl = e.target.closest('.js-send, .js-accept, .js-reject, .js-cancel');
    if (!btnEl) return;
    const userId = btnEl.getAttribute('data-user-id');
    let action = null;
    if (btnEl.classList.contains('js-send')) action = 'send';
    if (btnEl.classList.contains('js-accept')) action = 'accept';
    if (btnEl.classList.contains('js-reject')) action = 'reject';
    if (btnEl.classList.contains('js-cancel')) action = 'cancel';
    if (!action) return;

    btnEl.disabled = true;
    const res = await connectionApi(action, userId);
    alert(res.message || (res.success ? 'Done' : 'Failed'));
    if (res.success) {
      await refreshLists();
      if (action === 'accept') location.reload();
      else if (searchInput.value.trim().length >= 2) runSearch();
    }
    btnEl.disabled = false;
  }

  searchBtn.addEventListener('click', runSearch);
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runSearch();
    }
  });
  document.getElementById('doctorConnectionsPanel').addEventListener('click', handleClick);

  refreshLists().catch(() => {});
});
