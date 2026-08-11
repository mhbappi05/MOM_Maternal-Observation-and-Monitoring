<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

session_start();
include 'db.php';
include 'connections_helper.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$doctor_id = (int) $_SESSION['id'];
$patient_id = isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : 0;
$note_title = trim($_POST['note_title'] ?? '');
$note_content = trim($_POST['note_content'] ?? '');

if (!$patient_id || $note_title === '' || $note_content === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

if (!areConnected($conn, $doctor_id, $patient_id)) {
    echo json_encode(['success' => false, 'message' => 'Not connected with this patient']);
    exit();
}

// Create table if not exists
$create_sql = "
CREATE TABLE IF NOT EXISTS doctor_patient_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    note_title VARCHAR(255) NOT NULL,
    note_content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($create_sql)) {
    echo json_encode(['success' => false, 'message' => 'Table creation failed: ' . $conn->error]);
    exit();
}

// Insert note
$stmt = $conn->prepare("INSERT INTO doctor_patient_notes (patient_id, doctor_id, note_title, note_content) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param("iiss", $patient_id, $doctor_id, $note_title, $note_content);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
}
$stmt->close();
