<?php
/**
 * Consent-based doctor–patient connection helpers.
 */

function requireLogin(): void
{
    if (!isset($_SESSION['id'], $_SESSION['role'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
}

function jsonResponse(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

/**
 * Returns true if doctor and patient have an accepted connection.
 */
function areConnected(mysqli $conn, int $doctorId, int $patientId): bool
{
    $stmt = $conn->prepare(
        "SELECT id FROM doctor_patient_connections
         WHERE doctor_id = ? AND patient_id = ? AND status = 'accepted' LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ii", $doctorId, $patientId);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}

/**
 * Given two user IDs, figure out which is doctor and which is patient.
 * Returns [doctor_id, patient_id] or null.
 */
function resolveDoctorPatientPair(mysqli $conn, int $userA, int $userB): ?array
{
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE id IN (?, ?)");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ii", $userA, $userB);
    $stmt->execute();
    $result = $stmt->get_result();
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[(int) $row['id']] = $row['role'];
    }
    $stmt->close();

    if (count($roles) !== 2) {
        return null;
    }

    if ($roles[$userA] === 'doctor' && $roles[$userB] === 'patient') {
        return [$userA, $userB];
    }
    if ($roles[$userA] === 'patient' && $roles[$userB] === 'doctor') {
        return [$userB, $userA];
    }
    return null;
}

/**
 * Ensure the current session user is allowed to message the other user.
 */
function requireAcceptedConnection(mysqli $conn, int $otherUserId): void
{
    requireLogin();
    $me = (int) $_SESSION['id'];
    $pair = resolveDoctorPatientPair($conn, $me, $otherUserId);
    if (!$pair) {
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Invalid doctor/patient pair.'], 400);
    }
    [$doctorId, $patientId] = $pair;
    if (!areConnected($conn, $doctorId, $patientId)) {
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'You must be connected before messaging or sharing data. Send or accept a connection request first.'
        ], 403);
    }
}

/**
 * Connection row between doctor and patient, or null.
 */
function getConnectionRow(mysqli $conn, int $doctorId, int $patientId): ?array
{
    $stmt = $conn->prepare(
        "SELECT * FROM doctor_patient_connections WHERE doctor_id = ? AND patient_id = ? LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ii", $doctorId, $patientId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Human-friendly status relative to the current user.
 */
function relativeConnectionStatus(?array $row, int $currentUserId): string
{
    if (!$row) {
        return 'none';
    }
    if ($row['status'] === 'accepted') {
        return 'accepted';
    }
    if ($row['status'] === 'rejected') {
        return 'rejected';
    }
    // pending
    if ((int) $row['requested_by'] === $currentUserId) {
        return 'pending_sent';
    }
    return 'pending_received';
}
?>
