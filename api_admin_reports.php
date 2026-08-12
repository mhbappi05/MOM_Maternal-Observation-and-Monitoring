<?php
/**
 * Admin reports management
 * GET  action=list&status=
 * POST action=update  report_id, status, admin_note
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

    $sql = "SELECT r.id, r.reporter_id, r.category, r.subject, r.message, r.status, r.admin_note,
                   r.created_at, r.updated_at, u.name AS reporter_name, u.phone AS reporter_phone, u.role AS reporter_role
            FROM user_reports r
            JOIN users u ON u.id = r.reporter_id
            WHERE 1=1";
    $types = '';
    $params = [];

    if (in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
        $sql .= " AND r.status = ?";
        $types .= 's';
        $params[] = $status;
    }

    if ($q !== '') {
        $sql .= " AND (r.subject LIKE ? OR r.message LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)";
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY FIELD(r.status,'open','in_progress','resolved','closed'), r.created_at DESC LIMIT 200";

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
            'reporter_id' => (int) $row['reporter_id'],
            'reporter_name' => $row['reporter_name'],
            'reporter_phone' => $row['reporter_phone'],
            'reporter_role' => $row['reporter_role'],
            'category' => $row['category'],
            'subject' => $row['subject'],
            'message' => $row['message'],
            'status' => $row['status'],
            'admin_note' => $row['admin_note'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
    $stmt->close();
    jsonResponse(['success' => true, 'reports' => $rows]);
}

if ($method === 'POST' && $action === 'update') {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $adminNote = trim($_POST['admin_note'] ?? '');

    if (!$reportId || !in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid input']);
    }

    $stmt = $conn->prepare(
        "UPDATE user_reports SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->bind_param("ssi", $status, $adminNote, $reportId);
    $ok = $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => $ok, 'message' => $ok ? 'Report updated.' : 'Update failed.']);
}

jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
?>
