<?php
/**
 * Manage connection requests.
 * POST JSON/form: action = send|accept|reject|cancel, user_id = other party
 */
session_start();
include 'db.php';
include 'connections_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid method'], 405);
}

$action = trim($_POST['action'] ?? '');
$otherId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$me = (int) $_SESSION['id'];
$myRole = $_SESSION['role'];

if (!$otherId || $otherId === $me || !in_array($action, ['send', 'accept', 'reject', 'cancel'], true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$pair = resolveDoctorPatientPair($conn, $me, $otherId);
if (!$pair) {
    jsonResponse(['success' => false, 'message' => 'You can only connect with the opposite role (doctor ↔ patient).'], 400);
}
[$doctorId, $patientId] = $pair;
$existing = getConnectionRow($conn, $doctorId, $patientId);

switch ($action) {
    case 'send':
        if ($existing) {
            if ($existing['status'] === 'accepted') {
                jsonResponse(['success' => false, 'message' => 'Already connected.']);
            }
            if ($existing['status'] === 'pending') {
                jsonResponse(['success' => false, 'message' => 'A request is already pending.']);
            }
            // Re-send after rejection
            $stmt = $conn->prepare(
                "UPDATE doctor_patient_connections
                 SET status = 'pending', requested_by = ?, updated_at = NOW()
                 WHERE doctor_id = ? AND patient_id = ?"
            );
            $stmt->bind_param("iii", $me, $doctorId, $patientId);
            $ok = $stmt->execute();
            $stmt->close();
            jsonResponse(['success' => $ok, 'message' => $ok ? 'Connection request sent.' : 'Failed to send request.']);
        }

        $stmt = $conn->prepare(
            "INSERT INTO doctor_patient_connections (doctor_id, patient_id, requested_by, status)
             VALUES (?, ?, ?, 'pending')"
        );
        $stmt->bind_param("iii", $doctorId, $patientId, $me);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        jsonResponse([
            'success' => $ok,
            'message' => $ok ? 'Connection request sent.' : ('Failed: ' . $err)
        ]);
        break;

    case 'accept':
        if (!$existing || $existing['status'] !== 'pending') {
            jsonResponse(['success' => false, 'message' => 'No pending request to accept.']);
        }
        if ((int) $existing['requested_by'] === $me) {
            jsonResponse(['success' => false, 'message' => 'You cannot accept your own request. Wait for the other party.']);
        }
        $stmt = $conn->prepare(
            "UPDATE doctor_patient_connections SET status = 'accepted', updated_at = NOW()
             WHERE doctor_id = ? AND patient_id = ? AND status = 'pending'"
        );
        $stmt->bind_param("ii", $doctorId, $patientId);
        $ok = $stmt->execute();
        $stmt->close();
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Connection accepted.' : 'Failed to accept.']);
        break;

    case 'reject':
        if (!$existing || $existing['status'] !== 'pending') {
            jsonResponse(['success' => false, 'message' => 'No pending request to reject.']);
        }
        if ((int) $existing['requested_by'] === $me) {
            jsonResponse(['success' => false, 'message' => 'Use cancel to withdraw your own request.']);
        }
        $stmt = $conn->prepare(
            "UPDATE doctor_patient_connections SET status = 'rejected', updated_at = NOW()
             WHERE doctor_id = ? AND patient_id = ? AND status = 'pending'"
        );
        $stmt->bind_param("ii", $doctorId, $patientId);
        $ok = $stmt->execute();
        $stmt->close();
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Connection request rejected.' : 'Failed to reject.']);
        break;

    case 'cancel':
        if (!$existing || $existing['status'] !== 'pending') {
            jsonResponse(['success' => false, 'message' => 'No pending request to cancel.']);
        }
        if ((int) $existing['requested_by'] !== $me) {
            jsonResponse(['success' => false, 'message' => 'You can only cancel requests you sent.']);
        }
        $stmt = $conn->prepare(
            "DELETE FROM doctor_patient_connections WHERE doctor_id = ? AND patient_id = ? AND status = 'pending'"
        );
        $stmt->bind_param("ii", $doctorId, $patientId);
        $ok = $stmt->execute();
        $stmt->close();
        jsonResponse(['success' => $ok, 'message' => $ok ? 'Request cancelled.' : 'Failed to cancel.']);
        break;
}
?>
