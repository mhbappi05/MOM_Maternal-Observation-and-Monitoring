<?php
session_start();
include 'db.php';
include 'connections_helper.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Auth check ---
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: index.html");
    exit();
}

$doctor_id = (int) $_SESSION['id'];

// --- Validate patient_id ---
$patient_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$patient_id) {
    die("Invalid patient ID. Please provide a valid patient ID.");
}

// --- Consent: doctor must be connected to this patient ---
if (!areConnected($conn, $doctor_id, $patient_id)) {
    http_response_code(403);
    die("Access denied. You must have an accepted connection with this patient before viewing their data. <a href='doctor-dashboard.php'>Back to dashboard</a>");
}

// --- Fetch patient info ---
$sql = "SELECT * FROM users WHERE id = ? AND role = 'patient' LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    die("Patient not found. Please check the patient ID.");
}
$patient = $result->fetch_assoc();
$stmt->close();

// --- Check if patient's data table exists ---
$patient_data_table = "patient_" . intval($patient_id) . "_data";
$table_check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($patient_data_table) . "'");
$patient_table_exists = ($table_check && $table_check->num_rows === 1);

// --- Fetch latest vitals ---
$latest_data = [
    'heart_rate' => 'N/A',
    'blood_pressure' => 'N/A',
    'body_temperature' => 'N/A',
    'fetal_movement' => 'N/A',
    'oxygen_saturation' => 'N/A',
    'notes' => 'N/A',
    'status' => 'N/A',
    'timestamp' => 'N/A'
];
if ($patient_table_exists) {
    $sql_data = "SELECT * FROM `$patient_data_table` ORDER BY `timestamp` DESC LIMIT 1";
    $res = $conn->query($sql_data);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $latest_data['heart_rate'] = $row['heart_rate'] ?? 'N/A';
        $latest_data['blood_pressure'] = $row['blood_pressure'] ?? 'N/A';
        $latest_data['body_temperature'] = $row['body_temperature'] ?? 'N/A';
        $latest_data['fetal_movement'] = $row['fetal_movement'] ?? 'N/A';
        $latest_data['oxygen_saturation'] = $row['oxygen_saturation'] ?? 'N/A';
        $latest_data['notes'] = $row['notes'] ?? 'N/A';
        $latest_data['status'] = $row['status'] ?? 'N/A';
        $latest_data['timestamp'] = $row['timestamp'] ?? 'N/A';
    }
}

$lastUpdated = (!empty($latest_data['timestamp']) && $latest_data['timestamp'] !== 'N/A')
    ? date("F j, Y H:i", strtotime($latest_data['timestamp']))
    : "N/A";

// --- Fetch last 5 vitals readings ---
$last5_readings = [];
if ($patient_table_exists) {
    $sql_last5 = "SELECT `timestamp`, `heart_rate`, `oxygen_saturation`, `blood_pressure`, `status`, `fetal_movement` 
                  FROM `$patient_data_table` ORDER BY `timestamp` DESC LIMIT 5";
    $res5 = $conn->query($sql_last5);
    if ($res5 && $res5->num_rows > 0) {
        while ($r = $res5->fetch_assoc()) {
            $last5_readings[] = $r;
        }
    }
}

// --- Ensure doctor_patient_notes table exists ---
$create_table_sql = "
CREATE TABLE IF NOT EXISTS doctor_patient_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    note_title VARCHAR(255) DEFAULT NULL,
    note_content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($create_table_sql)) {
    die("Error creating doctor_patient_notes table: " . $conn->error);
}

// --- Fetch patient info ---
$sql = "SELECT * FROM users WHERE id = ? AND role = 'patient' LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    die("Patient not found. Please check the patient ID.");
}
$patient = $result->fetch_assoc();
$stmt->close();

// --- Fetch doctor's own notes for this patient ---
$doctor_notes = [];
$sql_doc_notes = "SELECT id, note_title, note_content, created_at FROM doctor_patient_notes WHERE patient_id = ? AND doctor_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt_doc_notes = $conn->prepare($sql_doc_notes);
if ($stmt_doc_notes) {
    $stmt_doc_notes->bind_param("ii", $patient_id, $doctor_id);
    $stmt_doc_notes->execute();
    $res_doc_notes = $stmt_doc_notes->get_result();
    if ($res_doc_notes && $res_doc_notes->num_rows > 0) {
        while ($rn = $res_doc_notes->fetch_assoc()) {
            $doctor_notes[] = $rn;
        }
    }
    $stmt_doc_notes->close();
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Monitor Patient | MOM</title>
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2785/2785544.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <link rel="stylesheet" href="css/monitorstyle.css" />
    <link rel="stylesheet" href="css/doctorstyle.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <nav class="navbar navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="doctor-dashboard.php">
                <img src="https://cdn-icons-png.flaticon.com/512/2785/2785544.png" alt="ECG Logo" class="logo-img"> MOM
                - Maternal Observation and Monitoring Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span
                    class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="doctor-dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Patient Monitoring</a></li>
                </ul>
                <div class="d-flex">
                    <button class="btn btn-outline-light" id="logoutButton"><i class="bi bi-box-arrow-right"></i> Log
                        Out</button>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-container container">
        <?php if (isset($patient) && is_array($patient)): ?>
            <div class="patient-header d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2><i class="fas fa-user-circle me-2"></i><?= htmlspecialchars($patient['name']) ?></h2>
                    <div class="d-flex align-items-center mt-2">
                        <div><i class="fas fa-phone me-2"></i><?= htmlspecialchars($patient['phone']) ?></div>
                        <div class="ms-4"><i class="fas fa-calendar-alt me-2"></i>Patient ID:
                            <?= htmlspecialchars($patient_id) ?>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge bg-success p-2">Online</span>
                    <div class="timestamp mt-1">Last updated: <span
                            id="lastUpdated"><?= htmlspecialchars($lastUpdated) ?></span></div>
                    <div class="small text-muted">Server time: <span id="nowTime"><?= date("F j, Y H:i") ?></span></div>
                </div>
            </div>

            <!-- Vital cards -->
            <div class="row mb-5">
                <div class="col-md-3">
                    <div class="card vital-card">
                        <div class="card-body">
                            <div class="vital-icon text-primary"><i class="fas fa-heartbeat"></i></div>
                            <h1 class="vital-value"><?= htmlspecialchars($latest_data['heart_rate'] ?? '-') ?>
                                <small>bpm</small>
                            </h1>
                            <p class="vital-label">HEART RATE</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card vital-card">
                        <div class="card-body">
                            <div class="vital-icon text-info"><i class="fas fa-lungs"></i></div>
                            <h1 class="vital-value">
                                <?= htmlspecialchars($latest_data['oxygen_saturation'] ?? '-') ?><small>%</small>
                            </h1>
                            <p class="vital-label">OXYGEN SATURATION</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card vital-card">
                        <div class="card-body">
                            <div class="vital-icon text-warning"><i class="fas fa-tachometer-alt"></i></div>
                            <h1 class="vital-value"><?= htmlspecialchars($latest_data['blood_pressure'] ?? '-') ?></h1>
                            <p class="vital-label">BLOOD PRESSURE</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card vital-card">
                        <div class="card-body">
                            <div class="vital-icon text-secondary"><i class="fas fa-thermometer-half"></i></div>
                            <h1 class="vital-value">
                                <?= htmlspecialchars($latest_data['body_temperature'] ?? '-') ?><small>°F</small>
                            </h1>
                            <p class="vital-label">TEMPERATURE</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card vital-card">
                        <div class="card-body">
                            <div class="vital-icon text-danger"><i class="fas fa-baby"></i></div>
                            <h1 class="vital-value"><?= htmlspecialchars($latest_data['fetal_movement'] ?? '-') ?></h1>
                            <p class="vital-label">FETAL MOVEMENTS (last hour)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional patient data: latest readings + notes -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header bg-asphalt">
                            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Latest Readings</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Heart Rate</th>
                                        <th>O2 Sat</th>
                                        <th>BP</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($last5_readings)): ?>
                                        <?php foreach ($last5_readings as $reading): ?>
                                            <?php
                                            $status = strtolower($reading['status'] ?? 'n/a');
                                            $badge_class = 'bg-secondary';
                                            if ($status === 'normal')
                                                $badge_class = 'bg-success';
                                            elseif ($status === 'warning')
                                                $badge_class = 'bg-warning text-dark';
                                            elseif ($status === 'critical')
                                                $badge_class = 'bg-danger';
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars(date("H:i", strtotime($reading['timestamp'] ?? 'now'))) ?>
                                                </td>
                                                <td><?= htmlspecialchars($reading['heart_rate'] ?? '-') ?> bpm</td>
                                                <td><?= htmlspecialchars($reading['oxygen_saturation'] ?? '-') ?>%</td>
                                                <td><?= htmlspecialchars($reading['blood_pressure'] ?? '-') ?></td>
                                                <td><span
                                                        class="badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($reading['status'] ?? 'N/A')) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No recent readings available.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Patient Notes (doctor's notes + optional patient_notes) -->
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header bg-asphalt d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-white">
                                <i class="fas fa-notes-medical me-2"></i>Patient Notes
                            </h5>
                            <div>
                                <button class="btn btn-sm btn-primary px-3 py-1 rounded-pill shadow-sm"
                                    style="font-weight: 500;" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                    <i class="fas fa-plus me-1"></i> Add Note
                                </button>
                            </div>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <?php if (!empty($doctor_notes)): ?>
                                <?php foreach ($doctor_notes as $note): ?>
                                    <div class="history-item mb-3">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($note['note_title'] ?? 'Untitled') ?></strong>
                                            <small><?= htmlspecialchars(date("M d, Y H:i", strtotime($note['created_at'] ?? 'now'))) ?></small>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($note['note_content'] ?? '')) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No personal doctor notes yet. Add one using "Add Note".</p>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="quick-actions mb-4">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactPatientModal"><i
                        class="fas fa-phone me-1"></i> Contact Patient</button>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#prescribeMedicationModal"><i
                        class="fas fa-prescription me-1"></i> Prescribe Medication</button>
                <a href="doctor-dashboard.php" class="btn btn-outline-secondary float-end"><i
                        class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            </div>

            <!-- Add Doctor's Private Note Modal -->
            <div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header ">
                            <h5 class="modal-title" id="addNoteModalLabel">
                                <i class="fas fa-user-md me-2"></i> Add Private Patient Note
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="patientId" value="<?= $patient_id ?>">
                            <div class="mb-3">
                                <label for="noteTitle" class="form-label">Note Title</label>
                                <input type="text" class="form-control" id="noteTitle" placeholder="Enter note title">
                            </div>
                            <div class="mb-3">
                                <label for="noteContent" class="form-label">Note Content</label>
                                <textarea class="form-control" id="noteContent" rows="4"
                                    placeholder="Enter note details"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-success" id="saveNoteButton">
                                <i class="fas fa-save me-1"></i> Save Note
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Patient Modal -->
            <div class="modal fade" id="contactPatientModal" tabindex="-1" aria-labelledby="contactPatientModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="contactPatientModalLabel">Contact Patient</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Name:</strong> <?= htmlspecialchars($patient['name']) ?></p>
                            <p><strong>Phone Number:</strong> <?= htmlspecialchars($patient['phone']) ?></p>
                            <div class="mb-3">
                                <label for="messageText" class="form-label">Message</label>
                                <textarea class="form-control" id="messageText" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="sendMessageButton">Send Message</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prescribe Medication Modal (kept as-is) -->
            <div class="modal fade" id="prescribeMedicationModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>Prescribe Medication</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Name:</strong> <?= htmlspecialchars($patient['name']) ?></p>
                            <p><strong>Phone Number:</strong> <?= htmlspecialchars($patient['phone']) ?></p>
                            <div class="mb-3">
                                <label for="medicationText" class="form-label">Medication</label>
                                <textarea id="medicationText" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="extraNoteText" class="form-label">Extra Note</label>
                                <textarea id="extraNoteText" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button id="sendMedicationButton" class="btn btn-primary">Send Medication</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger mt-4">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>Error</h4>
                <p>Unable to load patient data. Please check the patient ID and try again.</p>
                <a href="doctor-dashboard.php" class="btn btn-outline-secondary mt-3"><i class="fas fa-arrow-left me-1"></i>
                    Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer bg-dark text-white text-center py-4 mt-4">
        <div class="container">
            <p class="mb-2">© <?php echo date("Y"); ?> MOM - Maternal Observation and Monitoring Dashboard. All
                rights reserved.</p>
            <div class="social-icons">
                <a href="https://facebook.com/mhbappi05" target="_blank" class="text-white mx-2">
                    <i class="bi bi-facebook"></i>
                </a>
                <a href="https://instagram.com/mhbappi05" target="_blank" class="text-white mx-2">
                    <i class="bi bi-instagram"></i>
                </a>
                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=mhbappi05@gmail.com" target="_blank"
                    class="text-white mx-2">
                    <i class="bi bi-envelope"></i>
                </a>
                <a href="https://github.com/mhbappi05" target="_blank" class="text-white mx-2">
                    <i class="bi bi-github"></i>
                </a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Do not overwrite server-provided lastUpdated; show server time separately
        function updateNowTime() {
            const now = new Date();
            document.getElementById('nowTime').textContent = now.toLocaleString();
        }
        setInterval(updateNowTime, 60000);
        updateNowTime();

        document.getElementById("logoutButton").addEventListener("click", function () {
            window.location.href = "login.html";
        });

        // Send message to patient (keeps your existing ajax endpoint)
        $('#sendMessageButton').on('click', function () {
            const messageText = $('#messageText').val();
            const patientId = <?= $patient_id ?>;
            $.ajax({
                url: 'send_message_Contact_patient.php',
                type: 'POST',
                data: { patient_id: patientId, message: messageText },
                success: function () {
                    alert('Message sent successfully!');
                    $('#contactPatientModal').modal('hide');
                },
                error: function () { alert('Failed to send message. Please try again.'); }
            });
        });

        // Prescribe medication
        $('#sendMedicationButton').on('click', function () {
            const medicationText = $('#medicationText').val();
            const extraNoteText = $('#extraNoteText').val();
            const patientId = <?= $patient_id ?>;
            const message = `**Medication Prescribed**\n\n**Medication:** ${medicationText}\n**Note:** ${extraNoteText}`;
            $.ajax({
                url: 'send_message_pescribe_medi.php',
                type: 'POST',
                data: { patient_id: patientId, message: message },
                success: function () { alert('Medication prescribed successfully!'); $('#prescribeMedicationModal').modal('hide'); },
                error: function () { alert('Failed to prescribe medication. Please try again.'); }
            });
        });

        //notes
        document.getElementById('saveNoteButton').addEventListener('click', function () {
            const patientId = document.getElementById('patientId').value;
            const noteTitle = document.getElementById('noteTitle').value.trim();
            const noteContent = document.getElementById('noteContent').value.trim();

            if (!noteTitle || !noteContent) {
                alert('Please fill both the note title and content.');
                return;
            }

            fetch('save_doctor_note.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded', // or 'application/json' if you change PHP
                },
                body: new URLSearchParams({
                    patient_id: patientId,
                    note_title: noteTitle,
                    note_content: noteContent
                })
            })
                .then(response => response.text())  // get raw text first
                .then(text => {
                    console.log('Response text:', text); // debug: see raw response

                    try {
                        const data = JSON.parse(text);

                        if (data.success) {
                            alert('Note saved successfully!');
                            // Optionally clear form inputs
                            document.getElementById('noteTitle').value = '';
                            document.getElementById('noteContent').value = '';
                            // Optionally reload notes or refresh page to show new note
                            location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to save note.'));
                        }
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        alert('Unexpected server response. Check console for details.');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Network or server error occurred.');
                });
        });

    </script>
</body>

</html>