<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/../db/db_connect.php';

function ensureAdmin()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Forbidden']);
        exit();
    }
}

// Ensure tables exist (idempotent)
$conn->query("CREATE TABLE IF NOT EXISTS membership_profiles (
    user_id INT(11) NOT NULL PRIMARY KEY,
    year_of_membership YEAR NULL,
    age_upon_membership INT(11) NULL,
    certification ENUM('Honorary','Regular') DEFAULT 'Regular',
    membership_fee DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mp_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$conn->query("CREATE TABLE IF NOT EXISTS membership_dues (
    user_id INT(11) NOT NULL,
    year YEAR NOT NULL,
    status ENUM('Paid','Unpaid','Waived') DEFAULT 'Unpaid',
    amount DECIMAL(10,2) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, year),
    CONSTRAINT fk_md_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    if ($action === 'list') {
        ensureAdmin();
        $sql = "SELECT u.user_id, u.first_name, u.last_name, COALESCE(m.membership_status, 'inactive') AS membership_status,
                       mp.year_of_membership, mp.age_upon_membership, mp.certification, mp.membership_fee
                FROM users u
                LEFT JOIN members m ON m.user_id = u.user_id
                LEFT JOIN membership_profiles mp ON mp.user_id = u.user_id
                WHERE u.role IN ('member')
                ORDER BY u.last_name, u.first_name";
        $res = $conn->query($sql);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        echo json_encode(['status' => true, 'data' => $rows]);
    } elseif ($action === 'grid') {
        // Return dynamic years and all members with dues map for each year
        ensureAdmin();
        $startYear = 2014;
        $endYear = intval(date('Y'));
        $years = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            $years[] = $y;
        }
        $sql = "SELECT u.user_id, u.first_name, u.last_name, COALESCE(m.membership_status, 'inactive') AS membership_status,
                       mp.year_of_membership, mp.age_upon_membership, mp.certification, mp.membership_fee
                FROM users u
                LEFT JOIN members m ON m.user_id = u.user_id
                LEFT JOIN membership_profiles mp ON mp.user_id = u.user_id
                WHERE u.role IN ('member')
                ORDER BY u.last_name, u.first_name";
        $res = $conn->query($sql);
        $members = [];
        while ($row = $res->fetch_assoc()) {
            $user_id = intval($row['user_id']);
            // Fetch dues for this member
            $duesMap = [];
            $stmt = $conn->prepare("SELECT year, status, amount FROM membership_dues WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $r2 = $stmt->get_result();
            while ($d = $r2->fetch_assoc()) {
                $duesMap[intval($d['year'])] = ['status' => $d['status'], 'amount' => $d['amount']];
            }
            $stmt->close();
            // Fill missing years with defaults
            $dues = [];
            for ($y = $startYear; $y <= $endYear; $y++) {
                if (!isset($duesMap[$y])) {
                    $dues[(string)$y] = ['status' => ($y == 2021 ? 'Waived' : 'Unpaid'), 'amount' => null];
                } else {
                    $dues[(string)$y] = $duesMap[$y];
                }
            }
            $row['dues'] = $dues;
            $members[] = $row;
        }
        echo json_encode(['status' => true, 'years' => $years, 'members' => $members]);
    } elseif ($action === 'get_member') {
        ensureAdmin();
        $user_id = intval($_GET['user_id'] ?? 0);
        if (!$user_id) {
            echo json_encode(['status' => false, 'message' => 'Missing user_id']);
            exit;
        }
        // Profile
        $stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, COALESCE(m.membership_status,'inactive') AS membership_status,
                                        mp.year_of_membership, mp.age_upon_membership, mp.certification, mp.membership_fee
                                 FROM users u
                                 LEFT JOIN members m ON m.user_id = u.user_id
                                 LEFT JOIN membership_profiles mp ON mp.user_id = u.user_id
                                 WHERE u.user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $prof = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$prof) {
            echo json_encode(['status' => false, 'message' => 'User not found']);
            exit;
        }

        // Dues: build from 2014..current year with defaults (2021 waived if missing)
        $startYear = 2014;
        $endYear = intval(date('Y'));
        $duesMap = [];
        $stmt2 = $conn->prepare("SELECT year, status, amount FROM membership_dues WHERE user_id = ?");
        $stmt2->bind_param('i', $user_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($d = $res2->fetch_assoc()) {
            $duesMap[intval($d['year'])] = $d;
        }
        $stmt2->close();
        $dues = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            if (!isset($duesMap[$y])) {
                $dues[] = ['year' => $y, 'status' => ($y == 2021 ? 'Waived' : 'Unpaid'), 'amount' => null];
            } else {
                $dues[] = ['year' => intval($duesMap[$y]['year']), 'status' => $duesMap[$y]['status'], 'amount' => $duesMap[$y]['amount']];
            }
        }
        echo json_encode(['status' => true, 'profile' => $prof, 'dues' => $dues]);
    } elseif ($action === 'save_profile') {
        ensureAdmin();
        $user_id = intval($_POST['user_id'] ?? 0);
        $year_of_membership = $_POST['year_of_membership'] !== '' ? intval($_POST['year_of_membership']) : null;
        $age = $_POST['age_upon_membership'] !== '' ? intval($_POST['age_upon_membership']) : null;
        $cert = $_POST['certification'] ?? 'Regular';
        $fee = $_POST['membership_fee'] !== '' ? floatval($_POST['membership_fee']) : null;
        $status = $_POST['membership_status'] ?? null; // optional, updates members table
        if (!$user_id) {
            echo json_encode(['status' => false, 'message' => 'Missing user_id']);
            exit;
        }

        // Upsert profile
        $stmt = $conn->prepare("INSERT INTO membership_profiles (user_id, year_of_membership, age_upon_membership, certification, membership_fee)
                                VALUES (?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE year_of_membership=VALUES(year_of_membership), age_upon_membership=VALUES(age_upon_membership), certification=VALUES(certification), membership_fee=VALUES(membership_fee)");
        $stmt->bind_param('iiisd', $user_id, $year_of_membership, $age, $cert, $fee);
        $ok = $stmt->execute();
        $stmt->close();

        if ($status === 'active' || $status === 'inactive') {
            // Ensure members row exists then update
            $conn->query("INSERT IGNORE INTO members (user_id, membership_status) VALUES ($user_id, 'inactive')");
            $stmt2 = $conn->prepare("UPDATE members SET membership_status = ? WHERE user_id = ?");
            $stmt2->bind_param('si', $status, $user_id);
            $stmt2->execute();
            $stmt2->close();
        }

        echo json_encode(['status' => (bool)$ok]);
    } elseif ($action === 'save_dues') {
        ensureAdmin();
        $user_id = intval($_POST['user_id'] ?? 0);
        $dues_json = $_POST['dues'] ?? '[]';
        $dues = json_decode($dues_json, true);
        if (!$user_id || !is_array($dues)) {
            echo json_encode(['status' => false, 'message' => 'Invalid payload']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO membership_dues (user_id, year, status, amount) VALUES (?,?,?,?)
                                ON DUPLICATE KEY UPDATE status=VALUES(status), amount=VALUES(amount)");
        foreach ($dues as $d) {
            $y = intval($d['year']);
            $s = in_array($d['status'], ['Paid', 'Unpaid', 'Waived'], true) ? $d['status'] : 'Unpaid';
            $a = isset($d['amount']) && $d['amount'] !== '' ? floatval($d['amount']) : null;
            $stmt->bind_param('iisd', $user_id, $y, $s, $a);
            $stmt->execute();
        }
        $stmt->close();
        echo json_encode(['status' => true]);
    } elseif ($action === 'send_notice') {
        ensureAdmin();
        $type = $_POST['type'] ?? '';
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id || !in_array($type, ['membership_fee', 'annual_dues'], true)) {
            echo json_encode(['status' => false, 'message' => 'Invalid payload']);
            exit;
        }
        // Pull amount and build payment payload
        if ($type === 'membership_fee') {
            $stmt = $conn->prepare("SELECT COALESCE(mp.membership_fee,0) AS amount FROM membership_profiles mp WHERE mp.user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            $amount = isset($row['amount']) ? floatval($row['amount']) : 0.0;
            if ($amount <= 0) {
                echo json_encode(['status' => false, 'message' => 'Membership fee amount is missing']);
                exit;
            }
            // Create a payment record via direct insert
            $payment_type = 'Membership Fee';
            $status = 'New';
            $due_date = date('Y-m-d', strtotime('+30 days'));
            $stmt2 = $conn->prepare("INSERT INTO payments (user_id, payment_type, amount, status, due_date) VALUES (?,?,?,?,?)");
            $stmt2->bind_param('isdss', $user_id, $payment_type, $amount, $status, $due_date);
            $ok = $stmt2->execute();
            $stmt2->close();
            echo json_encode(['status' => (bool)$ok]);
        } else { // annual_dues
            $year = intval($_POST['year'] ?? 0);
            $amount = isset($_POST['amount']) && $_POST['amount'] !== '' ? floatval($_POST['amount']) : null;
            if (!$year) {
                echo json_encode(['status' => false, 'message' => 'Missing year']);
                exit;
            }
            // If amount not provided, fall back to dues amount table if exists
            if ($amount === null) {
                $stmt = $conn->prepare("SELECT amount FROM membership_dues WHERE user_id = ? AND year = ?");
                $stmt->bind_param('ii', $user_id, $year);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                $amount = isset($row['amount']) ? floatval($row['amount']) : 0.0;
            }
            if ($amount <= 0) {
                echo json_encode(['status' => false, 'message' => 'Annual dues amount is missing']);
                exit;
            }
            $payment_type = 'Annual Dues ' . $year;
            $status = 'New';
            $due_date = date('Y-m-d', strtotime('+30 days'));
            $stmt2 = $conn->prepare("INSERT INTO payments (user_id, payment_type, amount, status, due_date) VALUES (?,?,?,?,?)");
            $stmt2->bind_param('isdss', $user_id, $payment_type, $amount, $status, $due_date);
            $ok = $stmt2->execute();
            $stmt2->close();
            echo json_encode(['status' => (bool)$ok]);
        }
    } elseif ($action === 'payment_state') {
        // Return quick state of membership fee and annual dues payments for given user (and year for dues)
        ensureAdmin();
        $user_id = intval($_GET['user_id'] ?? 0);
        $year = isset($_GET['year']) ? intval($_GET['year']) : null;
        if (!$user_id) {
            echo json_encode(['status' => false, 'message' => 'Missing user_id']);
            exit;
        }
        $state = [
            'membership_fee' => null,
            'annual_dues' => null
        ];
        // Membership fee latest status
        $q1 = "SELECT status FROM payments WHERE user_id = ? AND payment_type = 'Membership Fee' AND is_archived = 0 ORDER BY payment_id DESC LIMIT 1";
        $stmt1 = $conn->prepare($q1);
        $stmt1->bind_param('i', $user_id);
        $stmt1->execute();
        $r1 = $stmt1->get_result();
        if ($row = $r1->fetch_assoc()) {
            $state['membership_fee'] = $row['status'];
        }
        $stmt1->close();
        // Annual dues for a specific year
        if ($year) {
            $pt = 'Annual Dues ' . $year;
            $q2 = "SELECT status FROM payments WHERE user_id = ? AND payment_type = ? AND is_archived = 0 ORDER BY payment_id DESC LIMIT 1";
            $stmt2 = $conn->prepare($q2);
            $stmt2->bind_param('is', $user_id, $pt);
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            if ($row2 = $r2->fetch_assoc()) {
                $state['annual_dues'] = $row2['status'];
            }
            $stmt2->close();
        }
        echo json_encode(['status' => true, 'state' => $state]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
