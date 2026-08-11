<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    // Check user role
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();
        
        if (password_verify($password, $hashed_password)) {
            $_SESSION['id'] = $id;
            $_SESSION['role'] = $role;

            // Redirect based on role
            if ($role === 'patient') {
                header("Location: ecg.php");
            } elseif ($role === 'doctor') {
                header("Location: doctor-dashboard.php");
            } else {
                echo "Invalid role assigned.";
            }
            exit();
        } else {
            echo "Invalid credentials. <a href='login.html'>Try again</a>";
        }
    } else {
        echo "No user found with this phone number.";
    }

    $stmt->close();
    $conn->close();
}
?>
