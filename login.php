<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.html");
    exit();
}

$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT id, password, role, is_active FROM users WHERE phone = ? LIMIT 1");
if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "No user found with this phone number. <a href='login.html'>Try again</a>";
    exit();
}

if (!(int) $user['is_active']) {
    echo "This account has been disabled. Contact the administrator. <a href='login.html'>Back</a>";
    exit();
}

if (!password_verify($password, $user['password'])) {
    echo "Invalid credentials. <a href='login.html'>Try again</a>";
    exit();
}

$_SESSION['id'] = (int) $user['id'];
$_SESSION['role'] = $user['role'];

if ($user['role'] === 'patient') {
    header("Location: ecg.php");
} elseif ($user['role'] === 'doctor') {
    header("Location: doctor-dashboard.php");
} elseif ($user['role'] === 'admin') {
    header("Location: admin-dashboard.php");
} else {
    echo "Invalid role assigned.";
}
exit();
?>
