<?php
session_start();
include 'db.php';
include 'connections_helper.php';

if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: index.html");
    exit();
}

$doctor_id = $_SESSION['id'];
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $stmt->bind_result($doctor_name);
    $stmt->fetch();
    $stmt->close();
} else {
    die("Database error: " . mysqli_error($conn));
}

// Connected patients only
$connected = [];
$stmt = $conn->prepare(
    "SELECT u.id, u.name, u.phone
     FROM doctor_patient_connections c
     JOIN users u ON u.id = c.patient_id
     WHERE c.doctor_id = ? AND c.status = 'accepted'
     ORDER BY u.name ASC"
);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $connected[] = $row;
}
$stmt->close();

// Incoming pending requests
$incoming = [];
$stmt = $conn->prepare(
    "SELECT u.id, u.name, u.phone, c.id AS connection_id
     FROM doctor_patient_connections c
     JOIN users u ON u.id = c.patient_id
     WHERE c.doctor_id = ? AND c.status = 'pending' AND c.requested_by != ?
     ORDER BY c.created_at DESC"
);
$stmt->bind_param("ii", $doctor_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $incoming[] = $row;
}
$stmt->close();

// Outgoing pending
$outgoing = [];
$stmt = $conn->prepare(
    "SELECT u.id, u.name, u.phone
     FROM doctor_patient_connections c
     JOIN users u ON u.id = c.patient_id
     WHERE c.doctor_id = ? AND c.status = 'pending' AND c.requested_by = ?
     ORDER BY c.created_at DESC"
);
$stmt->bind_param("ii", $doctor_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $outgoing[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2785/2785544.png">
    <title>Doctor Dashboard | MOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/doctorstyle.css">
    <link rel="stylesheet" href="css/connections.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div id="preloader">
        <div class="preloader-content">
            <img src="https://cdn-icons-png.flaticon.com/512/2785/2785544.png" alt="MOM Logo" class="preloader-logo">
            <div class="ecg-line"></div>
            <p class="loading-text">Monitoring vitals...</p>
        </div>
    </div>
    <nav class="navbar navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <img src="https://cdn-icons-png.flaticon.com/512/2785/2785544.png" alt="ECG Logo" class="logo-img">
                MOM - Maternal Observation and Monitoring Dashboard
            </a>
            <button class="btn btn-outline-light" onclick="window.location.href='logout.php';">
                <i class="bi bi-box-arrow-right"></i> Log Out
            </button>
        </div>
    </nav>

    <div class="container-fluid p-4" style="max-width: 1300px;">
        <h2>Welcome, Dr. <?= htmlspecialchars($doctor_name) ?></h2>
        <p class="text-muted mb-4">Patients must accept your connection request (or you accept theirs) before you can monitor or message them.</p>

        <?php if (count($incoming) > 0): ?>
        <div class="card mt-4 border-warning">
            <div class="card-header text-white bg-warning">
                <h5 class="mb-0"><i class="bi bi-bell"></i> Incoming connection requests (<?= count($incoming) ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incoming as $req): ?>
                            <tr data-user-id="<?= (int) $req['id'] ?>">
                                <td><?= htmlspecialchars($req['name']) ?></td>
                                <td><?= htmlspecialchars($req['phone']) ?></td>
                                <td class="conn-actions">
                                    <button type="button" class="btn btn-success btn-sm js-accept" data-user-id="<?= (int) $req['id'] ?>">Accept</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm js-reject" data-user-id="<?= (int) $req['id'] ?>">Reject</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header text-white">
                <h5 class="mb-0">Find a patient</h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="patientSearchInput" class="form-control" placeholder="Search by name or phone (min 2 characters)">
                    <button type="button" id="patientSearchBtn" class="btn btn-primary">Search</button>
                </div>
                <div id="patientSearchResults" class="conn-search-results"></div>
                <?php if (count($outgoing) > 0): ?>
                <hr>
                <h6>Pending requests you sent</h6>
                <ul class="list-group">
                    <?php foreach ($outgoing as $req): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($req['name']) ?> · <?= htmlspecialchars($req['phone']) ?></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-cancel" data-user-id="<?= (int) $req['id'] ?>">Cancel</button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header text-white">
                <h5 class="mb-0">My connected patients</h5>
            </div>
            <div class="card-body">
                <?php if (count($connected) > 0): ?>
                    <div style="max-height: 360px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Monitor</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($connected as $patient): ?>
                                <tr>
                                    <td><?= (int) $patient['id'] ?></td>
                                    <td><?= htmlspecialchars($patient['name']) ?></td>
                                    <td><?= htmlspecialchars($patient['phone']) ?></td>
                                    <td><a href="monitor_patient.php?id=<?= (int) $patient['id'] ?>" class="btn btn-success btn-sm">Monitor</a></td>
                                    <td>
                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#messageModal" data-patient-id="<?= (int) $patient['id'] ?>"
                                            data-patient-name="<?= htmlspecialchars($patient['name']) ?>">
                                            Message
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mb-0 text-muted">No connected patients yet. Search above and send a connection request.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="patientName"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="messages-container" style="max-height: 300px; overflow-y: scroll;"></div>
                <div class="modal-footer">
                    <form id="chatForm" style="display: flex; width: 100%; gap: 1rem; align-items: center;">
                        <input type="hidden" name="receiver_id" id="receiver_id">
                        <textarea name="message" class="form-control" rows="3" required
                            style="flex-grow: 1; height: 50px; border-radius: 12px; border: 2px solid #bdc3c7;"></textarea>
                        <button type="submit" class="btn btn-success">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer bg-dark text-white text-center py-4 mt-4">
        <div class="container">
            <p class="mb-2">© <?php echo date("Y"); ?> MOM - Maternal Observation and Monitoring Dashboard. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/connections.js"></script>
    <script src="js/doctor_connections.js"></script>
    <script src="js/doctor_messenger.js"></script>
    <script src="js/preloader.js"></script>
</body>

</html>
