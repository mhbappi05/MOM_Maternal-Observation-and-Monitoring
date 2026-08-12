/**
 * Shared report-to-admin UI for patient & doctor dashboards.
 * Expects Bootstrap modal markup with ids used below, or injects a floating button + modal.
 */
(function () {
  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function ensureModal() {
    if (document.getElementById('userReportModal')) return;

    const wrap = document.createElement('div');
    wrap.innerHTML = `
      <button type="button" id="openReportBtn" class="btn btn-warning report-fab" title="Report to admin">
        <i class="bi bi-flag"></i> Report
      </button>
      <div class="modal fade" id="userReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
          <form class="modal-content" id="userReportForm">
            <div class="modal-header">
              <h5 class="modal-title">Report to Admin</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted small">Send a bug, safety concern, account issue, or feature request to the platform admin.</p>
              <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                  <option value="bug">Bug / technical issue</option>
                  <option value="abuse">Abuse / misconduct</option>
                  <option value="privacy">Privacy concern</option>
                  <option value="account">Account problem</option>
                  <option value="feature">Feature request</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" maxlength="200" required placeholder="Short summary">
              </div>
              <div class="mb-3">
                <label class="form-label">Details</label>
                <textarea name="message" class="form-control" rows="4" required placeholder="Describe what happened"></textarea>
              </div>
              <hr>
              <h6 class="mb-2">Your recent reports</h6>
              <div id="myReportsList" class="small text-muted">Loading…</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-warning">Submit report</button>
            </div>
          </form>
        </div>
      </div>`;
    document.body.appendChild(wrap);

    if (!document.getElementById('reportFabStyle')) {
      const style = document.createElement('style');
      style.id = 'reportFabStyle';
      style.textContent = `
        .report-fab {
          position: fixed;
          right: 18px;
          bottom: 18px;
          z-index: 1050;
          border-radius: 999px;
          box-shadow: 0 8px 20px rgba(0,0,0,.18);
          font-weight: 600;
        }
        #myReportsList .report-item {
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          padding: .55rem .7rem;
          margin-bottom: .45rem;
          background: #f8fafc;
          color: #334155;
        }
      `;
      document.head.appendChild(style);
    }
  }

  async function loadMine() {
    const box = document.getElementById('myReportsList');
    if (!box) return;
    try {
      const res = await fetch('api_report.php?action=mine');
      const data = await res.json();
      if (!data.success || !data.reports.length) {
        box.innerHTML = 'No reports submitted yet.';
        return;
      }
      box.innerHTML = data.reports
        .map((r) => `<div class="report-item">
          <strong>${escapeHtml(r.subject)}</strong>
          <div>${escapeHtml(r.category)} · ${escapeHtml(r.status)} · ${escapeHtml(r.created_at)}</div>
          ${r.admin_note ? `<div class="mt-1"><em>Admin: ${escapeHtml(r.admin_note)}</em></div>` : ''}
        </div>`)
        .join('');
    } catch (e) {
      box.innerHTML = 'Could not load your reports.';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    ensureModal();
    const modalEl = document.getElementById('userReportModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const modal = new bootstrap.Modal(modalEl);

    document.getElementById('openReportBtn').addEventListener('click', () => {
      loadMine();
      modal.show();
    });

    document.getElementById('userReportForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append('action', 'create');
      const res = await fetch('api_report.php', { method: 'POST', body: new URLSearchParams(fd) });
      const data = await res.json();
      alert(data.message || (data.success ? 'Submitted' : 'Failed'));
      if (data.success) {
        e.target.reset();
        loadMine();
      }
    });
  });
})();
