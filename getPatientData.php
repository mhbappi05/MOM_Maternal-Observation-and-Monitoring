<?php
include 'db.php';
include 'connections_helper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$doctor_id = (int) $_SESSION['id'];
$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;

if (!$patient_id || !areConnected($conn, $doctor_id, $patient_id)) {
    echo json_encode(['error' => 'Patient not found or not connected to this doctor.']);
    exit();
}

$table = 'patient_' . $patient_id . '_data';
$check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
if (!$check || $check->num_rows === 0) {
    echo json_encode(['error' => 'No vitals table for this patient.']);
    exit();
}

$result = $conn->query("SELECT * FROM `$table` ORDER BY timestamp DESC LIMIT 1");
$row = $result ? $result->fetch_assoc() : null;

if (!$row) {
    echo json_encode(['error' => 'No vitals recorded yet.']);
    exit();
}

echo json_encode([
    'heart_rate' => $row['heart_rate'] ?? null,
    'oxygen_saturation' => $row['oxygen_saturation'] ?? null,
    'blood_pressure' => $row['blood_pressure'] ?? null,
    'body_temperature' => $row['body_temperature'] ?? null,
    'fetal_movement' => $row['fetal_movement'] ?? null,
    'status' => $row['status'] ?? 'normal',
    'timestamp' => $row['timestamp'] ?? null,
]);

$conn->close();
?>
