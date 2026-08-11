<?php
session_start();
include 'db.php';
include 'connections_helper.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo "Unauthorized access.";
    exit();
}

$patient_id = isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$doctor_id = (int) $_SESSION['id'];

if (!$patient_id || $message === '') {
    http_response_code(400);
    echo "Invalid input.";
    exit();
}

if (!areConnected($conn, $doctor_id, $patient_id)) {
    http_response_code(403);
    echo "Not connected with this patient.";
    exit();
}

$sql = "INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $doctor_id, $patient_id, $message);

if ($stmt->execute()) {
    echo "Message sent successfully.";
} else {
    http_response_code(500);
    echo "Failed to send message.";
}
$stmt->close();
?>
