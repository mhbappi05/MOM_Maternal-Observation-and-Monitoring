<?php
/**
 * Search opposite-role users by name or phone for connection requests.
 * GET/POST: q (query string)
 */
ini_set('display_errors', '0');
session_start();
include 'db.php';
include 'connections_helper.php';

requireLogin();

$q = trim($_GET['q'] ?? $_POST['q'] ?? '');
if ($q === '' || strlen($q) < 2) {
    jsonResponse(['success' => true, 'results' => []]);
}

$me = (int) $_SESSION['id'];
$myRole = $_SESSION['role'];

if (!in_array($myRole, ['doctor', 'patient'], true)) {
    jsonResponse(['success' => false, 'message' => 'Only doctors and patients can search for connections.'], 403);
}

$targetRole = $myRole === 'doctor' ? 'patient' : 'doctor';
$like = '%' . $q . '%';

$stmt = $conn->prepare(
    "SELECT id, name, phone, role FROM users
     WHERE role = ? AND id != ? AND (name LIKE ? OR phone LIKE ?)
     ORDER BY name ASC LIMIT 25"
);
if (!$stmt) {
    jsonResponse(['success' => false, 'message' => $conn->error], 500);
}
$stmt->bind_param("siss", $targetRole, $me, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$results = [];
while ($user = $result->fetch_assoc()) {
    $uid = (int) $user['id'];
    if ($myRole === 'doctor') {
        $doctorId = $me;
        $patientId = $uid;
    } else {
        $doctorId = $uid;
        $patientId = $me;
    }
    $row = getConnectionRow($conn, $doctorId, $patientId);
    $status = relativeConnectionStatus($row, $me);

    $results[] = [
        'id' => $uid,
        'name' => $user['name'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'connection_status' => $status,
        'connection_id' => $row ? (int) $row['id'] : null,
    ];
}
$stmt->close();

jsonResponse(['success' => true, 'results' => $results]);
?>
