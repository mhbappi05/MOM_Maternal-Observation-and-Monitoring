<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'] ?? '';  // doctor or patient only

    // Public registration cannot create admin accounts
    if (!in_array($role, ['patient', 'doctor'], true)) {
        echo "Invalid role. Please register as Patient or Doctor. <a href='login.html'>Back</a>";
        exit();
    }

    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Prepare insert statement
        $stmt = $conn->prepare("INSERT INTO users (name, phone, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
        
        if ($stmt === false) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }

        // Bind parameters and execute
        $stmt->bind_param("ssss", $name, $phone, $password, $role);
        $stmt->execute();
        
        // Get the inserted user ID
        $user_id = $conn->insert_id;
        
        // Only create table if user is a patient
        if ($role === 'patient') {
            $table_name = "patient_" . $user_id . "_data";

            $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                heart_rate INT,
                blood_pressure VARCHAR(20),
                body_temperature FLOAT,
                fetal_movement VARCHAR(50),
                oxygen_saturation FLOAT,
                notes TEXT,
                status VARCHAR(50) DEFAULT 'normal'
            )";

            if (!$conn->query($sql)) {
                throw new Exception("Error creating patient table: " . $conn->error);
            }
        }

        // Commit transaction
        $conn->commit();

        // Redirect to login
        header("Location: login.html");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }

    $stmt->close();
    $conn->close();
}
?>
