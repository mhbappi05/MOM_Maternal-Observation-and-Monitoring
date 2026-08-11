<?php
session_start();
include 'db.php';
include 'connections_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Invalid request method'], 405);
}

requireLogin();

$receiver_id = isset($_POST['doctor_id']) ? (int) $_POST['doctor_id'] : 0;
$sender_id = (int) $_SESSION['id'];
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$receiver_id || $message === '') {
    jsonResponse(['status' => 'error', 'message' => 'Missing required parameters'], 400);
}

$pair = resolveDoctorPatientPair($conn, $sender_id, $receiver_id);
if (!$pair) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid recipient'], 400);
}
[$doctorId, $patientId] = $pair;

if (!areConnected($conn, $doctorId, $patientId)) {
    jsonResponse([
        'status' => 'error',
        'message' => 'You must be connected before messaging. Send or accept a connection request first.'
    ], 403);
}

$stmt = $conn->prepare(
    "INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())"
);
if (!$stmt) {
    jsonResponse(['status' => 'error', 'message' => $conn->error], 500);
}
$stmt->bind_param("iis", $sender_id, $receiver_id, $message);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    jsonResponse(['status' => 'success', 'message' => 'Message sent!']);
}
jsonResponse(['status' => 'error', 'message' => 'Failed to send message'], 500);
?>
