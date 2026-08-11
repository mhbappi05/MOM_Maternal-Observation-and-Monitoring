<?php
session_start();
include 'db.php';
include 'connections_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Invalid method'], 405);
}

requireLogin();
$me = (int) $_SESSION['id'];

$otherId = 0;
if (isset($_POST['doctor_id'])) {
    $otherId = (int) $_POST['doctor_id'];
} elseif (isset($_POST['patient_id'])) {
    $otherId = (int) $_POST['patient_id'];
}

if (!$otherId) {
    jsonResponse(['status' => 'error', 'message' => 'Missing counterpart id'], 400);
}

$pair = resolveDoctorPatientPair($conn, $me, $otherId);
if (!$pair) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid pair'], 400);
}
[$doctorId, $patientId] = $pair;

if (!areConnected($conn, $doctorId, $patientId)) {
    jsonResponse([
        'status' => 'error',
        'message' => 'Not connected. Accept a connection request before chatting.',
        'messages' => '<p class="text-warning">Not connected with this user yet.</p>'
    ], 403);
}

$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$stmt->bind_result($doctor_name);
$stmt->fetch();
$stmt->close();
$doctor_name = $doctor_name ?: 'Doctor';

$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$stmt->bind_result($patient_name);
$stmt->fetch();
$stmt->close();
$patient_name = $patient_name ?: 'Patient';

$stmt = $conn->prepare(
    "SELECT sender_id, message, created_at FROM messages
     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
     ORDER BY created_at ASC"
);
$stmt->bind_param("iiii", $me, $otherId, $otherId, $me);
$stmt->execute();
$result = $stmt->get_result();

$message_html = '';
if ($result->num_rows > 0) {
    while ($message = $result->fetch_assoc()) {
        $isDoctorMsg = ((int) $message['sender_id'] === $doctorId);
        $messageClass = ((int) $message['sender_id'] === $me) ? 'text-end' : 'text-start';
        $nameDisplay = $isDoctorMsg
            ? 'Dr. ' . htmlspecialchars($doctor_name)
            : htmlspecialchars($patient_name);

        $message_html .= '<div class="message ' . $messageClass . ' mb-3">';
        $message_html .= '<p class="mb-1"><strong>' . $nameDisplay . ':</strong> ' . htmlspecialchars($message['message']) . '</p>';
        $message_html .= '<small class="text-muted">' . htmlspecialchars($message['created_at']) . '</small>';
        $message_html .= '</div>';
    }
} else {
    $message_html = '<p>No messages yet. Start the conversation!</p>';
}
$stmt->close();

jsonResponse(['status' => 'success', 'messages' => $message_html]);
?>
