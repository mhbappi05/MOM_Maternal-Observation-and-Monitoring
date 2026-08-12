<?php
session_start();
include 'db.php';
include 'connections_helper.php';

requireAdminPage();

$adminId = (int) $_SESSION['id'];
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$stmt->bind_result($adminName);
$stmt->fetch();
$stmt->close();
$adminName = $adminName ?: 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2785/2785544.png">
    <title>Admin Dashboard | MOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/adminstyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark admin-nav mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="admin-dashboard.php">
                <img src="https://cdn-icons-png.flaticon.com/512/2785/2785544.png" alt="MOM" class="logo-img">
                MOM Admin Control
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white-50 small">Signed in as <?= htmlspecialchars($adminName) ?></span>
                <button class="btn btn-outline-light btn-sm" onclick="window.location.href='logout.php';">
                    <i class="bi bi-box-arrow-right"></i> Log Out
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4" style="max-width: 1400px;">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
            <div>
                <h2 class="mb-1">Platform overview</h2>
                <p class="text-muted mb-0">Usage analytics, user control, connections, and user reports.</p>
            </div>
            <div id="openReportsBadge" class="admin-alert-pill" style="display:none;">
                <i class="bi bi-flag-fill"></i> <span id="openReportsCount">0</span> open reports
            </div>
        </div>

        <div class="row g-3 mb-4" id="statsRow">
            <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-label">Total users</div><div class="stat-value" id="statTotalUsers">—</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-label">Patients</div><div class="stat-value" id="statPatients">—</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-label">Doctors</div><div class="stat-value" id="statDoctors">—</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-label">Active accounts</div><div class="stat-value" id="statActive">—</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-label">Msgs (7 days)</div><div class="stat-value" id="statMsg7">—</div></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="stat-card"><div class="stat-label">Open reports</div><div class="stat-value" id="statReportsOpen">—</div></div></div>
        </div>

        <ul class="nav nav-pills admin-tabs mb-3" id="adminTabs">
            <li class="nav-item"><button class="nav-link active" data-tab="analytics">Analytics</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="users">Users</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="create">Create account</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="connections">Connections</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="reports">Reports <span class="badge bg-danger ms-1" id="tabReportsBadge" style="display:none;">0</span></button></li>
        </ul>

        <!-- ANALYTICS TAB -->
        <section class="admin-panel" id="tab-analytics">
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="insight-card"><div class="insight-label">New users (30d)</div><div class="insight-value" id="insNew30">—</div><div class="insight-sub" id="insNewSplit">—</div></div></div>
                <div class="col-md-3"><div class="insight-card"><div class="insight-label">Patient ↔ doctor link rate</div><div class="insight-value" id="insLinkRate">—</div><div class="insight-sub">Patients with ≥1 accepted doctor</div></div></div>
                <div class="col-md-3"><div class="insight-card"><div class="insight-label">Vitals adoption</div><div class="insight-value" id="insVitalsRate">—</div><div class="insight-sub" id="insVitalsSub">—</div></div></div>
                <div class="col-md-3"><div class="insight-card"><div class="insight-label">Connection accept rate</div><div class="insight-value" id="insAcceptRate">—</div><div class="insight-sub" id="insConnSub">—</div></div></div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="card admin-card h-100">
                        <div class="card-header"><h5 class="mb-0">Signups — last 14 days</h5></div>
                        <div class="card-body"><canvas id="signupsChart" height="120"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card admin-card h-100">
                        <div class="card-header"><h5 class="mb-0">User mix</h5></div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <canvas id="roleMixChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <div class="card admin-card h-100">
                        <div class="card-header"><h5 class="mb-0">Messaging activity — last 14 days</h5></div>
                        <div class="card-body"><canvas id="messagesChart" height="120"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card admin-card h-100">
                        <div class="card-header"><h5 class="mb-0">Engagement snapshot</h5></div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0 insight-list" id="engagementList">
                                <li>Loading…</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card admin-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Most active users (messages sent, 30 days)</h5>
                    <span class="text-muted small">Useful for outreach / power-user marketing</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Phone</th>
                                    <th>Messages</th>
                                </tr>
                            </thead>
                            <tbody id="topUsersBody">
                                <tr><td colspan="5" class="text-muted text-center py-4">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- USERS TAB -->
        <section class="admin-panel" id="tab-users" style="display:none;">
            <div class="card admin-card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h5 class="mb-0">Patients &amp; doctors</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <select id="userRoleFilter" class="form-select form-select-sm" style="width:auto;">
                            <option value="all">All roles</option>
                            <option value="patient">Patients</option>
                            <option value="doctor">Doctors</option>
                        </select>
                        <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Search name or phone" style="min-width:220px;">
                        <button type="button" class="btn btn-sm btn-primary" id="userSearchBtn">Search</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th style="min-width:280px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <tr><td colspan="7" class="text-muted text-center py-4">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- CREATE TAB -->
        <section class="admin-panel" id="tab-create" style="display:none;">
            <div class="card admin-card" style="max-width:560px;">
                <div class="card-header"><h5 class="mb-0">Create patient or doctor</h5></div>
                <div class="card-body">
                    <form id="createUserForm">
                        <div class="mb-3">
                            <label class="form-label">Full name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone (11 digits)</label>
                            <input type="tel" name="phone" class="form-control" pattern="[0-9]{11}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Temporary password</label>
                            <input type="text" name="password" class="form-control" minlength="4" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="patient">Patient</option>
                                <option value="doctor">Doctor</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Create account</button>
                        <p class="text-muted small mt-3 mb-0">Admin accounts cannot be created from this form.</p>
                    </form>
                </div>
            </div>
        </section>

        <!-- CONNECTIONS TAB -->
        <section class="admin-panel" id="tab-connections" style="display:none;">
            <div class="card admin-card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h5 class="mb-0">Doctor–patient connections</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <select id="connStatusFilter" class="form-select form-select-sm" style="width:auto;">
                            <option value="all">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <input type="text" id="connSearch" class="form-control form-control-sm" placeholder="Search doctor/patient" style="min-width:220px;">
                        <button type="button" class="btn btn-sm btn-primary" id="connSearchBtn">Search</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Doctor</th>
                                    <th>Patient</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th style="min-width:260px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="connectionsTableBody">
                                <tr><td colspan="6" class="text-muted text-center py-4">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- REPORTS TAB -->
        <section class="admin-panel" id="tab-reports" style="display:none;">
            <div class="card admin-card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h5 class="mb-0">User reports to admin</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <select id="reportStatusFilter" class="form-select form-select-sm" style="width:auto;">
                            <option value="all">All statuses</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                        <input type="text" id="reportSearch" class="form-control form-control-sm" placeholder="Search reports" style="min-width:220px;">
                        <button type="button" class="btn btn-sm btn-primary" id="reportSearchBtn">Search</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>From</th>
                                    <th>Category</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reportsTableBody">
                                <tr><td colspan="7" class="text-muted text-center py-4">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Edit user modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="editUserForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit user</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="editUserName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" id="editUserPhone" class="form-control" pattern="[0-9]{11}" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Reset password (optional)</label>
                        <input type="text" id="editUserPassword" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report detail modal -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" id="reportUpdateForm">
                <div class="modal-header">
                    <h5 class="modal-title">Report #<span id="reportModalId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reportEditId">
                    <p class="mb-1"><strong id="reportModalSubject"></strong></p>
                    <p class="text-muted small mb-3" id="reportModalMeta"></p>
                    <div class="report-message-box mb-3" id="reportModalMessage"></div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select id="reportEditStatus" class="form-select">
                            <option value="open">Open</option>
                            <option value="in_progress">In progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Admin note (visible to reporter)</label>
                        <textarea id="reportEditNote" class="form-control" rows="3" placeholder="Optional reply / internal note shown to user"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update report</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/admin.js"></script>
</body>
</html>
