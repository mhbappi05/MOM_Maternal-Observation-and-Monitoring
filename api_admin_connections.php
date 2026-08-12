<?php
/**
 * Admin connection management API
 * GET  ?action=list&status=all|pending|accepted|rejected&q=
 * POST action=force_status|delete  connection_id=  status=
 */
ini_set('display_errors', '0');
session_start();
include 'db.php';
include 'connections_helper.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && ($action === 'list' || $action === '')) {
    $status = $_GET['status'] ?? 'all';
    $q = trim($_GET['q'] ?? '');
    $like = '%' . $q . '%';

    $sql = "SELECT c.id, c.doctor_id, c.patient_id, c.requested_by, c.status, c.created_at, c.updated_at,
                   d.name AS doctor_name, d.phone AS doctor_phone,
                   p.name AS patient_name, p.phone AS patient_phone
            FROM doctor_patient_connections c
            JOIN users d ON d.id = c.doctor_id
            JOIN users p ON p.id = c.patient_id
            WHERE 1=1";
    $types = '';
    $params = [];

    if (in_array($status, ['pending', 'accepted', 'rejected'], true)) {
        $sql .= " AND c.status = ?";
        $types .= 's';
        $params[] = $status;
    }

    if ($q !== '') {
        $sql .= " AND (d.name LIKE ? OR d.phone LIKE ? OR p.name LIKE ? OR p.phone LIKE ?)";
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY c.updated_at DESC LIMIT 200";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        jsonResponse(['success' => false, 'message' => $conn->error], 500);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'doctor_id' => (int) $row['doctor_id'],
            'patient_id' => (int) $row['patient_id'],
            'doctor_name' => $row['doctor_name'],
            'doctor_phone' => $row['doctor_phone'],
            'patient_name' => $row['patient_name'],
            'patient_phone' => $row['patient_phone'],
            'requested_by' => (int) $row['requested_by'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
    $stmt->close();
    jsonResponse(['success' => true, 'connections' => $rows]);
}

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid method'], 405);
}

$action = $_POST['action'] ?? '';
$connectionId = (int) ($_POST['connection_id'] ?? 0);

if (!$connectionId) {
    jsonResponse(['success' => false, 'message' => 'Missing connection_id']);
}

if ($action === 'force_status') {
    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['pending', 'accepted', 'rejected'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid status']);
    }
    $stmt = $conn->prepare("UPDATE doctor_patient_connections SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $connectionId);
    $ok = $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => $ok, 'message' => $ok ? 'Connection updated.' : 'Update failed.']);
}

if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM doctor_patient_connections WHERE id = ?");
    $stmt->bind_param("i", $connectionId);
    $ok = $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => $ok, 'message' => $ok ? 'Connection removed.' : 'Delete failed.']);
}

jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
?>
