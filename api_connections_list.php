<?php
/**
 * List connections for the logged-in user.
 * Returns: connected, incoming (pending for me), outgoing (pending I sent)
 */
session_start();
include 'db.php';
include 'connections_helper.php';

requireLogin();

$me = (int) $_SESSION['id'];
$myRole = $_SESSION['role'];

if ($myRole === 'doctor') {
    $sql = "SELECT c.id AS connection_id, c.status, c.requested_by, c.created_at, c.updated_at,
                   u.id AS user_id, u.name, u.phone, u.role
            FROM doctor_patient_connections c
            JOIN users u ON u.id = c.patient_id
            WHERE c.doctor_id = ?
            ORDER BY c.updated_at DESC";
} else {
    $sql = "SELECT c.id AS connection_id, c.status, c.requested_by, c.created_at, c.updated_at,
                   u.id AS user_id, u.name, u.phone, u.role
            FROM doctor_patient_connections c
            JOIN users u ON u.id = c.doctor_id
            WHERE c.patient_id = ?
            ORDER BY c.updated_at DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $me);
$stmt->execute();
$result = $stmt->get_result();

$connected = [];
$incoming = [];
$outgoing = [];

while ($row = $result->fetch_assoc()) {
    $item = [
        'connection_id' => (int) $row['connection_id'],
        'user_id' => (int) $row['user_id'],
        'name' => $row['name'],
        'phone' => $row['phone'],
        'role' => $row['role'],
        'status' => $row['status'],
        'requested_by' => (int) $row['requested_by'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];

    if ($row['status'] === 'accepted') {
        $connected[] = $item;
    } elseif ($row['status'] === 'pending') {
        if ((int) $row['requested_by'] === $me) {
            $outgoing[] = $item;
        } else {
            $incoming[] = $item;
        }
    }
}
$stmt->close();

jsonResponse([
    'success' => true,
    'connected' => $connected,
    'incoming' => $incoming,
    'outgoing' => $outgoing,
]);
?>
