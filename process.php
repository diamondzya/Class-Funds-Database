<?php
/**
 * PROCESS.PHP - AJAX Form Handler for Class Fund Tracker
 * Returns JSON responses for all actions
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load functions
require_once 'functions.php';

// Initialize tables
initializeTables($conn);
initializeStudents($conn);

// Set JSON header for all responses
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'message_type' => '',
    'data' => null
];

// Check if action is set
if (!isset($_POST['action'])) {
    $response['message'] = 'No action specified';
    echo json_encode($response);
    exit();
}

$action = $_POST['action'];

// ============================================
// ACTION: TOGGLE (Paid/Unpaid)
// ============================================
if ($action == 'toggle') {
    
    $student_id = intval($_POST['student_id'] ?? 0);
    $payment_date = $conn->real_escape_string($_POST['payment_date'] ?? '');
    $day = $conn->real_escape_string($_POST['day'] ?? '');
    
    if ($student_id > 0 && $payment_date && $day && $day != 'wed') {
        
        // Get student name
        $student_result = $conn->query("SELECT full_name FROM students WHERE id = $student_id");
        $student_name = $student_result->fetch_assoc()['full_name'];
        
        // Check if payment exists
        $check = $conn->query("SELECT id FROM payments WHERE student_id = $student_id AND payment_date = '$payment_date' AND day_of_week = '$day'");
        
        if ($check->num_rows > 0) {
            // Delete payment (mark as unpaid)
            $conn->query("DELETE FROM payments WHERE student_id = $student_id AND payment_date = '$payment_date' AND day_of_week = '$day'");
            $conn->query("INSERT INTO payment_history (student_id, student_name, payment_date, day_of_week, amount, action) 
                         VALUES ($student_id, '" . $conn->real_escape_string($student_name) . "', '$payment_date', '$day', 5, 'unpaid')");
            $response['message'] = "Marked as UNPAID";
        } else {
            // Add payment (mark as paid)
            $conn->query("INSERT INTO payments (student_id, payment_date, day_of_week, amount) VALUES ($student_id, '$payment_date', '$day', 5)");
            $conn->query("INSERT INTO payment_history (student_id, student_name, payment_date, day_of_week, amount, action) 
                         VALUES ($student_id, '" . $conn->real_escape_string($student_name) . "', '$payment_date', '$day', 5, 'paid')");
            $response['message'] = "Marked as PAID";
        }
        
        $response['success'] = true;
        $response['message_type'] = 'success';
        
        // Get updated stats
        $response['data'] = getUpdatedStats($conn, $payment_date);
    }
}

// ============================================
// ACTION: MARK ALL PAID
// ============================================
elseif ($action == 'mark_all_paid') {
    
    $payment_date = $conn->real_escape_string($_POST['payment_date'] ?? '');
    $selected_date_info = getSelectedDateInfo($payment_date);
    $target_day = $selected_date_info['day_key'];
    
    if (!in_array($target_day, ['wed', 'sun'])) {
        $students_all = $conn->query("SELECT id, full_name FROM students");
        $count = 0;
        
        while ($s = $students_all->fetch_assoc()) {
            $check = $conn->query("SELECT id FROM payments WHERE student_id = {$s['id']} AND payment_date = '$payment_date' AND day_of_week = '$target_day'");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO payments (student_id, payment_date, day_of_week, amount) VALUES ({$s['id']}, '$payment_date', '$target_day', 5)");
                $conn->query("INSERT INTO payment_history (student_id, student_name, payment_date, day_of_week, amount, action) 
                             VALUES ({$s['id']}, '" . $conn->real_escape_string($s['full_name']) . "', '$payment_date', '$target_day', 5, 'paid')");
                $count++;
            }
        }
        
        $response['success'] = true;
        $response['message'] = "Marked all as PAID for " . $selected_date_info['day_of_week'] . " ($count students)";
        $response['message_type'] = 'success';
        $response['data'] = getUpdatedStats($conn, $payment_date);
    } else {
        $response['message'] = "Cannot mark payments for " . $selected_date_info['day_of_week'];
        $response['message_type'] = 'error';
    }
}

// ============================================
// ACTION: RESET ALL
// ============================================
elseif ($action == 'reset_all') {
    
    $payment_date = $conn->real_escape_string($_POST['payment_date'] ?? '');
    $selected_date_info = getSelectedDateInfo($payment_date);
    $target_day = $selected_date_info['day_key'];
    
    if (!in_array($target_day, ['wed', 'sun'])) {
        $conn->query("DELETE FROM payments WHERE payment_date = '$payment_date' AND day_of_week = '$target_day'");
        $response['success'] = true;
        $response['message'] = "Reset payments for " . $selected_date_info['day_of_week'];
        $response['message_type'] = 'warning';
        $response['data'] = getUpdatedStats($conn, $payment_date);
    } else {
        $response['message'] = "No payments to reset for " . $selected_date_info['day_of_week'];
        $response['message_type'] = 'error';
    }
}

// ============================================
// ACTION: VIEW HISTORY (AJAX)
// ============================================
elseif ($action == 'view_history') {
    
    $student_id = intval($_POST['student_id']);
    
    // Get history records with converted time
    $history_result = $conn->query("
        SELECT 
            *,
            CONVERT_TZ(action_timestamp, '+00:00', '+08:00') as ph_time 
        FROM payment_history 
        WHERE student_id = $student_id 
        ORDER BY action_timestamp DESC 
        LIMIT 50
    ");
    
    $history = [];
    while ($row = $history_result->fetch_assoc()) {
        $row['action_timestamp'] = $row['ph_time'];
        unset($row['ph_time']);
        $history[] = $row;
    }
    
    // Compute current balance
    $balance_result = $conn->query("SELECT SUM(amount) as balance FROM payments WHERE student_id = $student_id");
    $current_balance = $balance_result->fetch_assoc()['balance'] ?? 0;
    
    // Compute totals
    $totals_result = $conn->query("
        SELECT 
            SUM(CASE WHEN action = 'paid' THEN amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN action = 'unpaid' THEN amount ELSE 0 END) as total_unpaid,
            SUM(CASE WHEN action = 'deleted' THEN amount ELSE 0 END) as total_deleted,
            COUNT(*) as total_transactions
        FROM payment_history 
        WHERE student_id = $student_id
    ");
    $totals = $totals_result->fetch_assoc();
    
    $response['success'] = true;
    $response['data'] = [
        'history' => $history,
        'balance' => floatval($current_balance),
        'totals' => [
            'paid' => floatval($totals['total_paid'] ?? 0),
            'unpaid' => floatval($totals['total_unpaid'] ?? 0),
            'deleted' => floatval($totals['total_deleted'] ?? 0),
            'transactions' => intval($totals['total_transactions'] ?? 0)
        ]
    ];
}

// ============================================
// ACTION: DELETE DATE
// ============================================
elseif ($action == 'delete_date') {
    
    $delete_date = $conn->real_escape_string($_POST['delete_date'] ?? '');
    
    if ($delete_date) {
        // Save to history before deleting
        $payments_to_delete = $conn->query("
            SELECT p.student_id, s.full_name as student_name, p.day_of_week, p.amount 
            FROM payments p
            JOIN students s ON p.student_id = s.id
            WHERE p.payment_date = '$delete_date'
        ");
        
        while ($p = $payments_to_delete->fetch_assoc()) {
            $conn->query("INSERT INTO payment_history (student_id, student_name, payment_date, day_of_week, amount, action) 
                         VALUES ({$p['student_id']}, '" . $conn->real_escape_string($p['student_name']) . "', '$delete_date', '{$p['day_of_week']}', {$p['amount']}, 'deleted')");
        }
        
        // Delete payments
        $conn->query("DELETE FROM payments WHERE payment_date = '$delete_date'");
        
        $response['success'] = true;
        $response['message'] = "Deleted all payments for " . date('F j, Y', strtotime($delete_date));
        $response['message_type'] = 'warning';
        $response['data'] = getUpdatedStats($conn, date('Y-m-d'));
    }
}

// ============================================
// ACTION: CLEAR STUDENT HISTORY
// ============================================
elseif ($action == 'clear_student_history') {
    
    $student_id = intval($_POST['student_id'] ?? 0);
    $student_name = $conn->real_escape_string($_POST['student_name'] ?? '');
    
    if ($student_id > 0) {
        $conn->query("DELETE FROM payment_history WHERE student_id = $student_id");
        
        $response['success'] = true;
        $response['message'] = "Cleared history for " . $student_name;
        $response['message_type'] = 'warning';
    }
}

// ============================================
// ACTION: CLEAR ALL HISTORY
// ============================================
elseif ($action == 'clear_all_history') {
    
    if (isset($_POST['confirm']) && $_POST['confirm'] == 'CONFIRM') {
        $conn->query("DELETE FROM payment_history");
        $response['success'] = true;
        $response['message'] = "ALL history has been cleared!";
        $response['message_type'] = 'warning';
    } else {
        $response['message'] = "Clear all history cancelled - type CONFIRM to proceed";
        $response['message_type'] = 'error';
    }
}

// ============================================
// ACTION: GET UPDATED STATS
// ============================================
elseif ($action == 'get_stats') {
    
    $selected_date = $conn->real_escape_string($_POST['selected_date'] ?? date('Y-m-d'));
    $response['success'] = true;
    $response['data'] = getUpdatedStats($conn, $selected_date);
}

// Return JSON response
echo json_encode($response);
exit();

// ============================================
// HELPER FUNCTION: Get updated stats
// ============================================
function getUpdatedStats($conn, $selected_date) {
    
    $total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
    
    $total_payments = 0;
    $total_payments_result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE payment_date = '$selected_date'");
    if ($total_payments_result) {
        $total_payments = $total_payments_result->fetch_assoc()['count'] ?? 0;
    }
    
    $total_collection = $conn->query("SELECT SUM(amount) as total FROM payments")->fetch_assoc()['total'] ?? 0;
    
    $paid_counts = ['mon' => 0, 'tue' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0];
    $paid_summary = $conn->query("SELECT day_of_week, COUNT(*) as count FROM payments WHERE payment_date = '$selected_date' GROUP BY day_of_week");
    while ($row = $paid_summary->fetch_assoc()) {
        if (isset($paid_counts[$row['day_of_week']])) {
            $paid_counts[$row['day_of_week']] = $row['count'];
        }
    }
    
    // Get all students with their payment status for today
    $students_result = $conn->query("SELECT * FROM students ORDER BY full_name");
    $students_data = [];
    while ($student = $students_result->fetch_assoc()) {
        $student_payments = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as $day) {
            $paid_check = $conn->query("SELECT id FROM payments WHERE student_id = {$student['id']} AND payment_date = '$selected_date' AND day_of_week = '$day'");
            $student_payments[$day] = ($paid_check && $paid_check->num_rows > 0);
        }
        $students_data[] = [
            'id' => $student['id'],
            'name' => $student['full_name'],
            'payments' => $student_payments
        ];
    }
    
    return [
        'total_students' => $total_students,
        'total_payments' => $total_payments,
        'total_collection' => $total_collection,
        'paid_counts' => $paid_counts,
        'students' => $students_data
    ];
}
?>