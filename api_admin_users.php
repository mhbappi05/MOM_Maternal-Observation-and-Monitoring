<?php
/**
 * Admin user management API
 * GET  ?action=list&role=patient|doctor|all&q=
 * GET  ?action=stats
 * POST action=create|update|toggle|reset_password|delete
 */
ini_set('display_errors', '0');
session_start();
include 'db.php';
include 'connections_helper.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action === 'stats') {
    $stats = [
        'patients' => 0,
        'doctors' => 0,
        'admins' => 0,
        'active_users' => 0,
        'disabled_users' => 0,
        'connections_accepted' => 0,
        'connections_pending' => 0,
        'messages' => 0,
    ];

    $r = $conn->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            if ($row['role'] === 'patient') $stats['patients'] = (int) $row['c'];
            if ($row['role'] === 'doctor') $stats['doctors'] = (int) $row['c'];
            if ($row['role'] === 'admin') $stats['admins'] = (int) $row['c'];
        }
    }

    $r = $conn->query("SELECT is_active, COUNT(*) AS c FROM users GROUP BY is_active");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            if ((int) $row['is_active'] === 1) $stats['active_users'] = (int) $row['c'];
            else $stats['disabled_users'] = (int) $row['c'];
        }
    }

    $r = $conn->query("SELECT status, COUNT(*) AS c FROM doctor_patient_connections GROUP BY status");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            if ($row['status'] === 'accepted') $stats['connections_accepted'] = (int) $row['c'];
            if ($row['status'] === 'pending') $stats['connections_pending'] = (int) $row['c'];
        }
    }

    $r = $conn->query("SELECT COUNT(*) AS c FROM messages");
    if ($r && ($row = $r->fetch_assoc())) {
        $stats['messages'] = (int) $row['c'];
    }

    jsonResponse(['success' => true, 'stats' => $stats]);
}

if ($method === 'GET' && ($action === 'list' || $action === '')) {
    $role = $_GET['role'] ?? 'all';
    $q = trim($_GET['q'] ?? '');
    $like = '%' . $q . '%';

    $sql = "SELECT id, name, phone, role, is_active, created_at FROM users WHERE role != 'admin'";
    $types = '';
    $params = [];

    if (in_array($role, ['patient', 'doctor'], true)) {
        $sql .= " AND role = ?";
        $types .= 's';
        $params[] = $role;
    }

    if ($q !== '') {
        $sql .= " AND (name LIKE ? OR phone LIKE ?)";
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY created_at DESC, id DESC LIMIT 200";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        jsonResponse(['success' => false, 'message' => $conn->error], 500);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'role' => $row['role'],
            'is_active' => (int) $row['is_active'] === 1,
            'created_at' => $row['created_at'],
        ];
    }
    $stmt->close();
    jsonResponse(['success' => true, 'users' => $users]);
}

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid method'], 405);
}

$action = $_POST['action'] ?? '';
$adminId = (int) $_SESSION['id'];

switch ($action) {
    case 'create': {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        if ($name === '' || $phone === '' || $password === '' || !in_array($role, ['patient', 'doctor'], true)) {
            jsonResponse(['success' => false, 'message' => 'Invalid input. Role must be patient or doctor.']);
        }
        if (!preg_match('/^[0-9]{11}$/', $phone)) {
            jsonResponse(['success' => false, 'message' => 'Phone must be 11 digits.']);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (name, phone, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $name, $phone, $hash, $role);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error ?: 'Insert failed (phone may already exist).');
            }
            $userId = (int) $conn->insert_id;
            $stmt->close();

            if ($role === 'patient') {
                $table = 'patient_' . $userId . '_data';
                $sql = "CREATE TABLE IF NOT EXISTS `$table` (
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
                    throw new Exception('Failed creating patient vitals table.');
                }
            }

            $conn->commit();
            jsonResponse(['success' => true, 'message' => ucfirst($role) . ' account created.', 'id' => $userId]);
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;
    }

    case 'update': {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$userId || $name === '' || $phone === '') {
            jsonResponse(['success' => false, 'message' => 'Missing fields.']);
        }
        if (!preg_match('/^[0-9]{11}$/', $phone)) {
            jsonResponse(['success' => false, 'message' => 'Phone must be 11 digits.']);
        }

        $check = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $check->bind_param("i", $userId);
        $check->execute();
        $check->bind_result($existingRole);
        if (!$check->fetch()) {
            $check->close();
            jsonResponse(['success' => false, 'message' => 'User not found.']);
        }
        $check->close();
        if ($existingRole === 'admin') {
            jsonResponse(['success' => false, 'message' => 'Cannot edit admin accounts here.']);
        }

        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ? AND role IN ('patient','doctor')");
        $stmt->bind_param("ssi", $name, $phone, $userId);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        jsonResponse([
            'success' => $ok,
            'message' => $ok ? 'User updated.' : ('Update failed: ' . $err)
        ]);
        break;
    }

    case 'toggle': {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if (!$userId || $userId === $adminId) {
            jsonResponse(['success' => false, 'message' => 'Invalid user.']);
        }

        $stmt = $conn->prepare("SELECT is_active, role FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($isActive, $role);
        if (!$stmt->fetch()) {
            $stmt->close();
            jsonResponse(['success' => false, 'message' => 'User not found.']);
        }
        $stmt->close();

        if ($role === 'admin') {
            jsonResponse(['success' => false, 'message' => 'Cannot disable admin accounts here.']);
        }

        $newActive = ((int) $isActive === 1) ? 0 : 1;
        $upd = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $upd->bind_param("ii", $newActive, $userId);
        $ok = $upd->execute();
        $upd->close();
        jsonResponse([
            'success' => $ok,
            'message' => $ok ? ($newActive ? 'User enabled.' : 'User disabled.') : 'Toggle failed.',
            'is_active' => (bool) $newActive
        ]);
        break;
    }

    case 'reset_password': {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newPassword = $_POST['password'] ?? '';
        if (!$userId || strlen($newPassword) < 4) {
            jsonResponse(['success' => false, 'message' => 'Provide user and password (min 4 chars).']);
        }

        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($role);
        if (!$stmt->fetch() || $role === 'admin') {
            $stmt->close();
            jsonResponse(['success' => false, 'message' => 'User not found or not allowed.']);
        }
        $stmt->close();

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role IN ('patient','doctor')");
        $upd->bind_param("si", $hash, $userId);
        $ok = $upd->execute();
        $upd->close();
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Password reset.' : 'Reset failed.']);
        break;
    }

    case 'delete': {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if (!$userId || $userId === $adminId) {
            jsonResponse(['success' => false, 'message' => 'Invalid user.']);
        }

        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($role);
        if (!$stmt->fetch() || !in_array($role, ['patient', 'doctor'], true)) {
            $stmt->close();
            jsonResponse(['success' => false, 'message' => 'Only patients/doctors can be deleted.']);
        }
        $stmt->close();

        $conn->begin_transaction();
        try {
            if ($role === 'doctor') {
                $d = $conn->prepare("DELETE FROM doctor_patient_connections WHERE doctor_id = ?");
                $d->bind_param("i", $userId);
                $d->execute();
                $d->close();
                $conn->query("DELETE FROM doctor_patient_notes WHERE doctor_id = " . (int) $userId);
            } else {
                $d = $conn->prepare("DELETE FROM doctor_patient_connections WHERE patient_id = ?");
                $d->bind_param("i", $userId);
                $d->execute();
                $d->close();
                $conn->query("DELETE FROM doctor_patient_notes WHERE patient_id = " . (int) $userId);
                $table = 'patient_' . $userId . '_data';
                $conn->query("DROP TABLE IF EXISTS `$table`");
            }

            $m = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
            $m->bind_param("ii", $userId, $userId);
            $m->execute();
            $m->close();

            $u = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('patient','doctor')");
            $u->bind_param("i", $userId);
            if (!$u->execute() || $u->affected_rows === 0) {
                throw new Exception('User delete failed.');
            }
            $u->close();

            $conn->commit();
            jsonResponse(['success' => true, 'message' => 'User deleted.']);
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
}
?>
