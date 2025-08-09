<?php
session_start();
include 'db.php';

// Check if the user is logged in and is a doctor
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.html");
    exit();
}

// Fetch the doctor's name from the database
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

// Handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (!empty($search)) {
    // Search for patients matching name or phone
    $sql = "SELECT id, name, phone FROM users WHERE role = 'patient' AND (name LIKE ? OR phone LIKE ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $searchTerm = "%$search%";
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        echo "<p class='text-danger'>Error fetching patients: " . $conn->error . "</p>";
    }
} else {
    // Fetch all patients if no search
    $sql = "SELECT id, name, phone FROM users WHERE role = 'patient'";
    $result = $conn->query($sql);
    if (!$result) {
        echo "<p class='text-danger'>Error fetching patients: " . $conn->error . "</p>";
    }
}


// Handle message submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = trim($_POST['message']);

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iis", $doctor_id, $receiver_id, $message);
            if ($stmt->execute()) {
                // Redirect to refresh the page and show the new message
                header("Location: ?patient_id=" . $receiver_id);
                exit();
            } else {
                echo "<script>console.log('Error sending message: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        } else {
            echo "<script>console.log('Database prepare error: " . $conn->error . "');</script>";
        }
    }
}

// Fetch messages between doctor and patient
$patient_name = "";
$messages = null; // Initialize variable

if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];

    // Get patient name
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $stmt->bind_result($patient_name);
        $stmt->fetch();
        $stmt->close();
    }

    // Retrieve messages
    $stmt = $conn->prepare("SELECT sender_id, message, created_at FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    if ($stmt) {
        $stmt->bind_param("iiii", $doctor_id, $patient_id, $patient_id, $doctor_id);
        $stmt->execute();
        $messages = $stmt->get_result();
        $stmt->close();
    } else {
        echo "<p class='text-danger'>Error fetching messages: " . $conn->error . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2785/2785544.png">
    <title>Doctor Dashboard | ECG Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/doctorstyle.css">
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

        <div class="card mt-4">
            <div class="card-header text-white">
                <h5>Search for a Patient</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search by name or phone"
                        value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header text-white">
                <h5>Search Results</h5>
            </div>
            <div class="card-body">
                <?php if ($result && $result->num_rows > 0): ?>
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
                    </table>

                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <tbody>
                                <?php while ($patient = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $patient['id'] ?></td>
                                        <td><?= htmlspecialchars($patient['name']) ?></td>
                                        <td><?= htmlspecialchars($patient['phone']) ?></td>
                                        <td><a href="monitor_patient.php?id=<?= $patient['id'] ?>"
                                                class="btn btn-success btn-sm">Monitor</a></td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#messageModal" data-patient-id="<?= $patient['id'] ?>"
                                                data-patient-name="<?= htmlspecialchars($patient['name']) ?>">
                                                Message
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <p>No patients found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="patientName"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="messages-container" style="max-height: 300px; overflow-y: scroll;">
                    <!-- Messages will be loaded here -->
                </div>
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

    <script src="js/doctor_messenger.js"></script>
    <script src="js/preloader.js"></script>
</body>

</html>