/**
 * Shared frontend helpers for connection requests.
 */
async function connectionApi(action, userId) {
  const body = new URLSearchParams({ action, user_id: String(userId) });
  const res = await fetch('api_connection.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body
  });
  return parseJsonResponse(res);
}

async function searchOppositeUsers(query) {
  const res = await fetch('api_search_users.php?q=' + encodeURIComponent(query));
  return parseJsonResponse(res);
}

async function loadConnectionsList() {
  const res = await fetch('api_connections_list.php');
  return parseJsonResponse(res);
}

async function parseJsonResponse(res) {
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    console.error('Non-JSON API response:', text);
    throw new Error('Invalid server response');
  }
}

function connectionStatusLabel(status) {
  switch (status) {
    case 'accepted': return 'Connected';
    case 'pending_sent': return 'Request sent';
    case 'pending_received': return 'Respond to request';
    case 'rejected': return 'Previously rejected';
    default: return 'Not connected';
  }
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
