<?php
/**
 * User reports API (patient/doctor submit; list own)
 * POST action=create
 * GET  action=mine
 */
ini_set('display_errors', '0');
session_start();
include 'db.php';
include 'connections_helper.php';

requireLogin();

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['patient', 'doctor'], true)) {
    jsonResponse(['success' => false, 'message' => 'Only patients and doctors can submit reports.'], 403);
}

$me = (int) $_SESSION['id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && ($action === 'mine' || $action === '')) {
    $stmt = $conn->prepare(
        "SELECT id, category, subject, message, status, admin_note, created_at, updated_at
         FROM user_reports WHERE reporter_id = ? ORDER BY created_at DESC LIMIT 50"
    );
    $stmt->bind_param("i", $me);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    jsonResponse(['success' => true, 'reports' => $rows]);
}

if ($method === 'POST' && $action === 'create') {
    $category = $_POST['category'] ?? 'other';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $allowed = ['bug', 'abuse', 'privacy', 'feature', 'account', 'other'];
    if (!in_array($category, $allowed, true)) {
        $category = 'other';
    }
    if ($subject === '' || $message === '') {
        jsonResponse(['success' => false, 'message' => 'Subject and message are required.']);
    }
    if (strlen($subject) > 200) {
        jsonResponse(['success' => false, 'message' => 'Subject is too long.']);
    }

    $stmt = $conn->prepare(
        "INSERT INTO user_reports (reporter_id, category, subject, message, status)
         VALUES (?, ?, ?, ?, 'open')"
    );
    $stmt->bind_param("isss", $me, $category, $subject, $message);
    $ok = $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();

    jsonResponse([
        'success' => $ok,
        'message' => $ok ? 'Report submitted to admin. Thank you.' : 'Failed to submit report.',
        'id' => $id
    ]);
}

jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
?>
