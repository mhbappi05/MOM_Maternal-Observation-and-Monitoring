<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "ecg_monitoring";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS ecg_monitoring";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($database);
} else {
    die("Error creating database: " . $conn->error);
}

// Create users table if not exists
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(11) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating table: " . $conn->error);
}

// Ensure role column exists / includes admin
$roleCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($roleCheck && $roleCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient'");
} else {
    // Expand ENUM safely for existing DBs
    $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('patient', 'doctor', 'admin') NOT NULL DEFAULT 'patient'");
}

// Ensure is_active column
$activeCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($activeCheck && $activeCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
}

// Ensure created_at column
$createdCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if ($createdCheck && $createdCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
}

// Messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pair (sender_id, receiver_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) !== TRUE) {
    die("Error creating messages table: " . $conn->error);
}

// Consent-based doctor–patient connections
$sql = "CREATE TABLE IF NOT EXISTS doctor_patient_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    requested_by INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pair (doctor_id, patient_id),
    INDEX idx_doctor_status (doctor_id, status),
    INDEX idx_patient_status (patient_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) !== TRUE) {
    die("Error creating connections table: " . $conn->error);
}

// User reports to admin
$sql = "CREATE TABLE IF NOT EXISTS user_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    category ENUM('bug','abuse','privacy','feature','account','other') NOT NULL DEFAULT 'other',
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    admin_note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reporter (reporter_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql) !== TRUE) {
    die("Error creating reports table: " . $conn->error);
}

// Seed default admin if none exists
// Phone: 01000000000  Password: Admin@123
$adminCheck = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if ($adminCheck && $adminCheck->num_rows === 0) {
    $adminName = 'System Admin';
    $adminPhone = '01000000000';
    $adminPass = password_hash('Admin@123', PASSWORD_DEFAULT);
    $adminRole = 'admin';
    $stmt = $conn->prepare("INSERT INTO users (name, phone, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
    if ($stmt) {
        $stmt->bind_param("ssss", $adminName, $adminPhone, $adminPass, $adminRole);
        $stmt->execute();
        $stmt->close();
    }
}
?>
