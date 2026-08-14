<?php
session_start();
include 'db.php'; // ensure schema (users.role, connections, messages)

// Database connection
$host = "localhost";
$dbname = "ecg_monitoring";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in as patient
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: index.html");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Fetch user data
$user_id = $_SESSION['id'];
$tableName = "patient_" . intval($user_id) . "_data";

$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$username = $user ? $user['name'] : "Guest";


// Connected doctors only (accepted consent)
$stmt = $pdo->prepare(
    "SELECT u.id, u.name
     FROM doctor_patient_connections c
     JOIN users u ON u.id = c.doctor_id
     WHERE c.patient_id = ? AND c.status = 'accepted'
     ORDER BY u.name ASC"
);
$stmt->execute([$user_id]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate HTML for doctor list
$doctorListHTML = '';
if ($doctors) {
    foreach ($doctors as $doctor) {
        $doctorListHTML .= '<li><button class="btn btn-link doctor-item" data-doctor-id="' . (int) $doctor['id'] . '" data-doctor-name="' . htmlspecialchars($doctor['name'], ENT_QUOTES) . '">' . htmlspecialchars($doctor['name']) . '</button></li>';
    }
} else {
    $doctorListHTML = '<li class="text-muted px-2">No connected doctors yet. Use “Find &amp; add doctor” below.</li>';
}

// Fetch the conversation between the logged-in user and the selected doctor
$conversationMessages = '';
if (isset($_GET['doctor_id'])) {
    $doctor_id = (int) $_GET['doctor_id'];

    $check = $pdo->prepare(
        "SELECT id FROM doctor_patient_connections
         WHERE doctor_id = ? AND patient_id = ? AND status = 'accepted' LIMIT 1"
    );
    $check->execute([$doctor_id, $user_id]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare("SELECT sender_id, message, created_at FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
        $stmt->execute([$user_id, $doctor_id, $doctor_id, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as $msg) {
            $senderName = ($msg['sender_id'] == $user_id) ? "You" : "Doctor";
            $conversationMessages .= "<div class='message'>";
            $conversationMessages .= "<strong>" . htmlspecialchars($senderName) . ":</strong>";
            $conversationMessages .= "<p>" . htmlspecialchars($msg['message']) . "</p>";
            $conversationMessages .= "<small>" . htmlspecialchars($msg['created_at']) . "</small>";
            $conversationMessages .= "</div>";
        }
    }
}

// Fetch previous records, latest 10 for example
$stmt = $pdo->prepare("SELECT * FROM `$tableName` ORDER BY timestamp DESC LIMIT 10");
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$previousRecordsHTML = '';
if ($records) {
    $previousRecordsHTML .= "<table class='table table-striped'>";
    $previousRecordsHTML .= "<thead><tr>
        <th>Date</th>
        <th>Heart Rate</th>
        <th>Blood Pressure</th>
        <th>Temperature</th>
        <th>Fetal Movement</th>
        <th>Oxygen Saturation</th>
        <th>Notes</th>
    </tr></thead><tbody>";

    foreach ($records as $rec) {
        $previousRecordsHTML .= "<tr>
            <td>" . htmlspecialchars($rec['timestamp']) . "</td>
            <td>" . htmlspecialchars($rec['heart_rate'] ?? '--') . "</td>
            <td>" . htmlspecialchars($rec['blood_pressure'] ?? '--') . "</td>
            <td>" . htmlspecialchars($rec['body_temperature'] ?? '--') . "</td>
            <td>" . htmlspecialchars($rec['fetal_movement'] ?? '--') . "</td>
            <td>" . htmlspecialchars($rec['oxygen_saturation'] ?? '--') . "</td>
            <td>" . htmlspecialchars($rec['notes'] ?? '') . "</td>
        </tr>";
    }

    $previousRecordsHTML .= "</tbody></table>";
} else {
    $previousRecordsHTML = "<p>No previous records found.</p>";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2785/2785544.png">
    <title>MOM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/chatbot.css"> <!-- Chatbot CSS -->
    <link rel="stylesheet" href="css/messenger.css"> <!-- messenger CSS -->
    <link rel="stylesheet" href="css/connections.css">
</head>

<body>
    <script>
        const patientId = <?= json_encode($_SESSION["id"] ?? null) ?>;
    </script>

    <!-- Ambient floating hearts — purely decorative, ignored by assistive tech -->
    <div class="ambient-hearts" aria-hidden="true">
        <span>♥</span>
        <span>♥</span>
        <span>♥</span>
        <span>♥</span>
        <span>♥</span>
        <span>♥</span>
    </div>

    <!-- Themed Preloader -->
    <div id="preloader">
        <div class="preloader-content">
            <img src="img/logo-heart.svg" alt="MOM Logo" class="preloader-logo">
            <div class="ecg-line"></div>
            <p class="loading-text">Getting things cozy for you and baby... 💕</p>
        </div>
    </div>
    <!-- Welcome Message -->
    <div id="welcomeMessage">
        <div class="welcome-content">
            <h4>🌸 Hi <?php echo htmlspecialchars($username); ?>, welcome back!</h4>
            <p>You and your little one are doing great. Let's see how you're both feeling today. 💖</p>
        </div>
    </div>

    <nav class="navbar navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="ecg.php">
                <img src="img/logo-mom.svg" alt="ECG Logo" class="logo-img">
                MOM - Maternal Observation and Monitoring Dashboard
            </a>
            <!-- Added logout button -->
            <button class="btn btn-outline-light" id="logoutButton">
                <i class="bi bi-box-arrow-right"></i> Log Out
            </button>
        </div>
    </nav>
    <div class="container">

        <!-- Thematic hero scene: a quiet moment under the moon -->
        <div class="hero-banner">
            <img src="img/hero-scene.svg" alt="" role="presentation">
            <div class="hero-banner-caption">
                <span class="hero-banner-eyebrow">Every heartbeat counts</span>
                <p>A calm space to check in on you and your little one.</p>
            </div>
        </div>

        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="dashboard-title">Your Pregnancy Journey</h1>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">Last updated: <span id="last-update">Just now</span></span>
                </div>
            </div>
        </div>

        <div class="patient-info">
            <img src="img/mom-avatar.svg" alt="Patient Avatar"
                class="patient-avatar">
            <div class="patient-details">
                <h4>Hi, <?php echo htmlspecialchars($username); ?> 🌷</h4>
                <a href="#" class="messenger-icon" id="openMessenger">
                    <h4>Consult with the Doctor</h4>
                    <i class="bi bi-chat-dots"></i>
                </a>
            </div>
        </div>

        <!-- Doctor connections (consent-based) -->
        <div class="conn-panel" id="doctorConnectionsPanel">
            <h5><i class="bi bi-people"></i> Your doctors</h5>
            <p class="conn-empty mb-3">Search by name or phone, send a request, and message only after both sides are connected.</p>

            <div class="conn-tabs" role="tablist">
                <button type="button" class="active" data-conn-tab="connected">Connected</button>
                <button type="button" data-conn-tab="incoming">Requests</button>
                <button type="button" data-conn-tab="find">Find &amp; add</button>
            </div>

            <div id="connTabConnected">
                <ul class="conn-list" id="patientConnectedList"></ul>
            </div>
            <div id="connTabIncoming" style="display:none;">
                <h6 class="mt-2">Incoming</h6>
                <ul class="conn-list" id="patientIncomingList"></ul>
                <h6 class="mt-3">Sent by you</h6>
                <ul class="conn-list" id="patientOutgoingList"></ul>
            </div>
            <div id="connTabFind" style="display:none;">
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="doctorSearchInput" class="form-control" placeholder="Doctor name or phone">
                    <button type="button" id="doctorSearchBtn" class="btn btn-primary">Search</button>
                </div>
                <div id="doctorSearchResults" class="conn-search-results"></div>
            </div>
        </div>

        <!-- Messenger UI -->
        <div class="messenger-container" id="messengerContainer" style="display: none;">
            <div class="messenger-header d-flex align-items-center">
                <button class="back-button" id="backToDoctors" style="display:none;">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <h4 class="flex-grow-1 mb-0" id="messengerHeader">Consult with the Doctor</h4>
                <button class="close-messenger" id="closeMessenger">&times;</button>
            </div>


            <!-- Doctor List -->
            <div id="doctorList" style="display: block;">
                <h5>Select a connected doctor</h5>
                <ul id="doctorListItems">
                    <!-- Doctors will be loaded here -->
                    <?php echo $doctorListHTML; ?>
                </ul>
            </div>

            <!-- Chat UI, hidden initially -->
            <div id="chatUI" style="display: none;">
                <div class="messenger-body" id="messengerBody">
                    <!-- Messages will be loaded here -->
                    <?php echo $conversationMessages; ?>
                </div>
                <div class="messenger-footer">
                    <input type="text" id="messageInput" placeholder="Type your message...">
                    <button id="sendMessageBtn"><i class="bi bi-send"></i></button>
                </div>
            </div>
        </div>



        <!-- Temperature, Fetal Movement, Oxygen -->
        <div class="row">
            <!-- Mother Blood Pressure -->
            <div class="col-md-3">
                <div class="card metric-card mb-4">
                    <div class="metric-icon">
                        <i class="bi bi-droplet-half"></i>
                    </div>
                    <h5>Blood Pressure</h5>
                    <p id="bp_mother" class="metric-value">-- / -- mmHg</p>
                </div>
            </div>

            <!-- Mother Body Temperature -->
            <div class="col-md-3">
                <div class="card metric-card mb-4">
                    <div class="metric-icon">
                        <i class="bi bi-thermometer-half"></i>
                    </div>
                    <h5>Body Temperature</h5>
                    <p id="temperature_mother" class="metric-value">-- °C</p>
                </div>
            </div>

            <!-- Fetal Movement -->
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-icon">
                        <i class="bi bi-activity"></i> <!-- fetal movement icon -->
                    </div>
                    <h5>Fetal Movement</h5>
                    <p id="fetal_movement" class="metric-value">-- kicks/min</p>
                </div>
            </div>

            <!-- Mother Oxygen -->
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-icon">
                        <i class="bi bi-droplet"></i> <!-- oxygen icon -->
                    </div>
                    <h5>Oxygen Saturation</h5>
                    <p id="oxygen_mother" class="metric-value">--%</p>
                </div>
            </div>

        </div>

        <!-- Charts and Metrics -->
        <div class="row">
            <!-- Mother Heart Rate -->
            <div class="col-md-6">
                <div class="card chart-container mb-4">
                    <div class="chart-header">
                        <h5 class="chart-title">Heart Rate</h5>
                        <div class="chart-stats">
                            <span id="mother_ecg_stats">Rate: -- bpm</span>
                        </div>
                    </div>
                    <div class="ecg-chart">
                        <canvas id="heartRateChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-8">
                <div class="card health-card">
                    <h5 class="health-title"><i class="bi bi-activity"></i> Health Suggestions</h5>
                    <br>
                    <div id="health_suggestions" class="suggestions-container">
                        <p class="suggestion-info">Monitoring vitals...</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Chatbot -->
        <div class="chatbot-container">
            <button class="chatbot-toggle">
                <i class="bi bi-chat-dots"></i>
            </button>
            <div class="chatbox">
                <div class="chatbox-header">
                    <span>Health Assistant</span>
                    <button class="close-chat">&times;</button>
                </div>
                <div class="chatbox-body" id="chatbox-body">
                    <div class="bot-message">Hello! How can I assist you today?</div>
                </div>
                <div class="chatbox-footer">
                    <input type="text" id="chat-input" placeholder="Ask about ECG, oxygen, etc.">
                    <button id="send-btn"><i class="bi bi-send"></i></button>
                </div>
            </div>
        </div>


        <div class="previous-records-section">
            <h3>Previous Vitals Records</h3>
            <?php echo $previousRecordsHTML; ?>
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
        <script src="js/preloader.js"></script>
        <script src="js/chatbot.js"></script>
        <script src="js/ecg.js"></script>
        <script src="js/connections.js"></script>
        <script src="js/patient_connections.js"></script>
        <script src="js/messenger.js"></script>
        <script src="js/report.js"></script>
</body>

</html>