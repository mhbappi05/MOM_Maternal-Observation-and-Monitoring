<?php
session_start();

$conn = new mysqli("localhost", "root", "", "ecg_monitoring");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Use $_SESSION['id'] instead of 'patient_id'
if (!isset($_SESSION["id"])) {
    echo "Error: Patient not identified.";
    exit;
}

$patient_id = intval($_SESSION["id"]);
$table = "patient_" . $patient_id . "_data";

// Check table exists
$check = $conn->query("SHOW TABLES LIKE '$table'");
if ($check->num_rows === 0) {
    echo "Error: Table '$table' does not exist.";
    exit;
}

// Decode JSON
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo "Invalid or missing JSON data.";
    exit;
}

// Prepare statement
$stmt = $conn->prepare("INSERT INTO `$table` (
    timestamp,
    heart_rate,
    blood_pressure,
    body_temperature,
    fetal_movement,
    oxygen_saturation,
    notes,
    status
) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo "Prepare failed: " . $conn->error;
    exit;
}

// Bind and execute
$stmt->bind_param(
    "ssdiiss",
    $data["heart_rate"],
    $data["blood_pressure"],
    $data["body_temperature"],
    $data["fetal_movement"],
    $data["oxygen_saturation"],
    $data["notes"],
    $data["status"]
);

if ($stmt->execute()) {
    echo "Vitals saved successfully";
} else {
    echo "Execute failed: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
