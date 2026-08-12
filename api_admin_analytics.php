<?php
/**
 * Marketing / engagement analytics for admin.
 * GET → full analytics payload
 */
ini_set('display_errors', '0');
session_start();
include 'db.php';
include 'connections_helper.php';

requireAdmin();

function scalarCount(mysqli $conn, string $sql): int
{
    $r = $conn->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}

$stats = [
    'total_users' => 0,
    'patients' => 0,
    'doctors' => 0,
    'admins' => 0,
    'active_users' => 0,
    'disabled_users' => 0,
    'new_users_7d' => 0,
    'new_users_30d' => 0,
    'new_patients_30d' => 0,
    'new_doctors_30d' => 0,
    'connections_total' => 0,
    'connections_accepted' => 0,
    'connections_pending' => 0,
    'connections_rejected' => 0,
    'acceptance_rate' => 0,
    'messages_total' => 0,
    'messages_7d' => 0,
    'messages_30d' => 0,
    'active_messagers_7d' => 0,
    'patients_with_vitals' => 0,
    'vitals_records_total' => 0,
    'patients_with_connection' => 0,
    'doctors_with_connection' => 0,
    'avg_patients_per_connected_doctor' => 0,
    'reports_open' => 0,
    'reports_total' => 0,
    'reports_7d' => 0,
];

$r = $conn->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $c = (int) $row['c'];
        $stats['total_users'] += $c;
        if ($row['role'] === 'patient') $stats['patients'] = $c;
        if ($row['role'] === 'doctor') $stats['doctors'] = $c;
        if ($row['role'] === 'admin') $stats['admins'] = $c;
    }
}

$r = $conn->query("SELECT is_active, COUNT(*) AS c FROM users WHERE role IN ('patient','doctor') GROUP BY is_active");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ((int) $row['is_active'] === 1) $stats['active_users'] = (int) $row['c'];
        else $stats['disabled_users'] = (int) $row['c'];
    }
}

$stats['new_users_7d'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role IN ('patient','doctor') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['new_users_30d'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role IN ('patient','doctor') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['new_patients_30d'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'patient' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['new_doctors_30d'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'doctor' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

$r = $conn->query("SELECT status, COUNT(*) AS c FROM doctor_patient_connections GROUP BY status");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $c = (int) $row['c'];
        $stats['connections_total'] += $c;
        if ($row['status'] === 'accepted') $stats['connections_accepted'] = $c;
        if ($row['status'] === 'pending') $stats['connections_pending'] = $c;
        if ($row['status'] === 'rejected') $stats['connections_rejected'] = $c;
    }
}
$decided = $stats['connections_accepted'] + $stats['connections_rejected'];
$stats['acceptance_rate'] = $decided > 0
    ? round(($stats['connections_accepted'] / $decided) * 100, 1)
    : 0;

$stats['messages_total'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM messages");
$stats['messages_7d'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['messages_30d'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['active_messagers_7d'] = scalarCount($conn, "SELECT COUNT(DISTINCT sender_id) AS c FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

$stats['patients_with_connection'] = scalarCount($conn, "SELECT COUNT(DISTINCT patient_id) AS c FROM doctor_patient_connections WHERE status = 'accepted'");
$stats['doctors_with_connection'] = scalarCount($conn, "SELECT COUNT(DISTINCT doctor_id) AS c FROM doctor_patient_connections WHERE status = 'accepted'");
$stats['avg_patients_per_connected_doctor'] = $stats['doctors_with_connection'] > 0
    ? round($stats['connections_accepted'] / $stats['doctors_with_connection'], 1)
    : 0;

// Vitals usage across patient_*_data tables
$vitalsPatients = 0;
$vitalsRecords = 0;
$tables = $conn->query("SHOW TABLES LIKE 'patient_%_data'");
if ($tables) {
    while ($t = $tables->fetch_array()) {
        $table = $t[0];
        if (!preg_match('/^patient_\d+_data$/', $table)) continue;
        $cnt = scalarCount($conn, "SELECT COUNT(*) AS c FROM `$table`");
        if ($cnt > 0) {
            $vitalsPatients++;
            $vitalsRecords += $cnt;
        }
    }
}
$stats['patients_with_vitals'] = $vitalsPatients;
$stats['vitals_records_total'] = $vitalsRecords;

$stats['reports_total'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM user_reports");
$stats['reports_open'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM user_reports WHERE status IN ('open','in_progress')");
$stats['reports_7d'] = scalarCount($conn, "SELECT COUNT(*) AS c FROM user_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

// Signup trend last 14 days
$signups = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} day"));
    $signups[$day] = ['date' => $day, 'patients' => 0, 'doctors' => 0, 'total' => 0];
}
$r = $conn->query(
    "SELECT DATE(created_at) AS d, role, COUNT(*) AS c
     FROM users
     WHERE role IN ('patient','doctor') AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at), role"
);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $d = $row['d'];
        if (!isset($signups[$d])) continue;
        $c = (int) $row['c'];
        if ($row['role'] === 'patient') $signups[$d]['patients'] = $c;
        if ($row['role'] === 'doctor') $signups[$d]['doctors'] = $c;
        $signups[$d]['total'] += $c;
    }
}

// Message trend last 14 days
$messagesTrend = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} day"));
    $messagesTrend[$day] = ['date' => $day, 'count' => 0];
}
$r = $conn->query(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c
     FROM messages
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at)"
);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $d = $row['d'];
        if (isset($messagesTrend[$d])) {
            $messagesTrend[$d]['count'] = (int) $row['c'];
        }
    }
}

// Role mix for pie
$roleMix = [
    ['label' => 'Patients', 'value' => $stats['patients']],
    ['label' => 'Doctors', 'value' => $stats['doctors']],
];

// Top active users by messages sent (30d)
$topUsers = [];
$r = $conn->query(
    "SELECT u.id, u.name, u.role, u.phone, COUNT(m.id) AS msg_count
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       AND u.role IN ('patient','doctor')
     GROUP BY u.id, u.name, u.role, u.phone
     ORDER BY msg_count DESC
     LIMIT 8"
);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $topUsers[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'role' => $row['role'],
            'phone' => $row['phone'],
            'msg_count' => (int) $row['msg_count'],
        ];
    }
}

// Marketing snapshot copy helpers
$engagementRate = $stats['patients'] > 0
    ? round(($stats['patients_with_connection'] / $stats['patients']) * 100, 1)
    : 0;
$vitalsAdoption = $stats['patients'] > 0
    ? round(($stats['patients_with_vitals'] / $stats['patients']) * 100, 1)
    : 0;

jsonResponse([
    'success' => true,
    'stats' => $stats,
    'marketing' => [
        'patient_connection_rate' => $engagementRate,
        'vitals_adoption_rate' => $vitalsAdoption,
        'messages_per_active_user_7d' => $stats['active_messagers_7d'] > 0
            ? round($stats['messages_7d'] / $stats['active_messagers_7d'], 1)
            : 0,
    ],
    'charts' => [
        'signups_14d' => array_values($signups),
        'messages_14d' => array_values($messagesTrend),
        'role_mix' => $roleMix,
    ],
    'top_users' => $topUsers,
]);
?>
