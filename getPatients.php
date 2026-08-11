<?php
include 'db.php';
include 'connections_helper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode([]);
    exit();
}

$doctor_id = (int) $_SESSION['id'];

$query = "SELECT u.id, u.name, u.phone
          FROM doctor_patient_connections c
          JOIN users u ON u.id = c.patient_id
          WHERE c.doctor_id = ? AND c.status = 'accepted' AND u.role = 'patient'
          ORDER BY u.name ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode($patients);

$stmt->close();
$conn->close();
?>
