<?php
// functions.php
// functions.php - add this at the very top
date_default_timezone_set('Asia/Manila');
require_once 'config.php';

// Initialize tables
function initializeTables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        payment_date DATE NOT NULL,
        day_of_week VARCHAR(10) NOT NULL,
        amount DECIMAL(5,2) DEFAULT 5.00,
        paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        UNIQUE KEY unique_daily_payment (student_id, payment_date, day_of_week)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS payment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        payment_date DATE NOT NULL,
        day_of_week VARCHAR(10) NOT NULL,
        amount DECIMAL(5,2),
        action VARCHAR(20),
        action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_date (student_id, payment_date)
    )");
}

// Insert students if empty
function initializeStudents($conn) {
    $check = $conn->query("SELECT COUNT(*) as count FROM students");
    $row = $check->fetch_assoc();
    
    if ($row['count'] == 0) {
        $students = [
            "ALMADRA, DON MICHAEL ONTIMARA",
            "ARAÑO, YANCEL GABRIEL RONA",
            "ARMOREDA, IAN RODELL AGUTEP",
            "CUSTODIO, JOHN LLOYD MOMO",
            "DE LEON, CLARENCE JAY ROSAS",
            "ESPIRITU, RAIN HARRIE TINAGAN",
            "GULAPA, JOHN ALEXIS TESORERO",
            "LLEGO, DEDRICK JIRO INAT",
            "MADRIAGA, CYRUS DANIEL AQUINO",
            "MAGLIPON, LEBRON JAMES MATEMATICO",
            "MANLANGIT, RAIN OLATAN",
            "PAJARES, EURY ANDREAI ESTILO",
            "PAJO DARREN ASTILLERO",
            "PALERO, SEAN LLOYD AVELLANA",
            "PASCUA, JOHN LOUIS TUBAL",
            "PASCUA, KARL REINIEL CABACUNGAN",
            "POQUIZ, CEDRIC CARL BORJA",
            "ROSALIOS, KHEVIN CARL PIDONG",
            "SARDIDO, MANUEL JURADO",
            "TANATE, ADRIAN VILLANOS",
            "VELASCO, JOSHUA SAPUES",
            "YERRO, LANCE SIMON ROSAS",
            "DAYAG, ARWILDAN ARTETA",
            "FERNANDEZ, RHIAN DAYAGANON",
            "GARCIA, AIRA MAE ELLAN",
            "GUIZON, NICOLE GUELAS",
            "MANGALIMAN, KCZELEEN SAN JOSE",
            "NIDO, BRIANNA GIELYN ALANO",
            "GASPAR, STANLEY"
        ];
        
        foreach ($students as $student) {
            $escaped = $conn->real_escape_string($student);
            $conn->query("INSERT INTO students (full_name) VALUES ('$escaped')");
        }
    }
}

// Get day abbreviation mapping
function getDayAbbr() {
    return [
        'Monday' => 'Mon', 'Tuesday' => 'Tue', 'Wednesday' => 'Wed', 
        'Thursday' => 'Thu', 'Friday' => 'Fri', 'Saturday' => 'Sat', 'Sunday' => 'Sun'
    ];
}

// Get day to key mapping
function getDayToKey() {
    return [
        'Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 
        'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat'
    ];
}

// Get selected date info
function getSelectedDateInfo($selected_date) {
    $day_abbr = getDayAbbr();
    $selected_day_of_week = date('l', strtotime($selected_date));
    $selected_day_abbr = $day_abbr[$selected_day_of_week] ?? '';
    $selected_day_key = strtolower(substr($selected_day_abbr, 0, 3));
    
    return [
        'date' => $selected_date,
        'day_of_week' => $selected_day_of_week,
        'day_abbr' => $selected_day_abbr,
        'day_key' => $selected_day_key
    ];
}

// Get all students
function getStudents($conn) {
    return $conn->query("SELECT * FROM students ORDER BY full_name");
}

// Get total students count
function getTotalStudents($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM students");
    return $result->fetch_assoc()['count'];
}

// Get payments for a specific date
function getPaymentsForDate($conn, $date) {
    $result = $conn->query("SELECT COUNT(*) as count FROM payments WHERE payment_date = '$date'");
    return $result->fetch_assoc()['count'] ?? 0;
}

// Get total collection
function getTotalCollection($conn) {
    $result = $conn->query("SELECT SUM(amount) as total FROM payments");
    return $result->fetch_assoc()['total'] ?? 0;
}

// Get payment summary for a date
function getPaymentSummary($conn, $date) {
    $paid_counts = ['mon' => 0, 'tue' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0];
    $summary = $conn->query("SELECT day_of_week, COUNT(*) as count FROM payments WHERE payment_date = '$date' GROUP BY day_of_week");
    
    while ($row = $summary->fetch_assoc()) {
        if (isset($paid_counts[$row['day_of_week']])) {
            $paid_counts[$row['day_of_week']] = $row['count'];
        }
    }
    
    return $paid_counts;
}

// Get recent dates
function getRecentDates($conn) {
    return $conn->query("SELECT DISTINCT payment_date FROM payments ORDER BY payment_date DESC LIMIT 10");
}

// Get dates with payments summary
function getDatesWithPayments($conn) {
    return $conn->query("
        SELECT 
            payment_date,
            DAYNAME(payment_date) as day_name,
            COUNT(*) as payment_count,
            SUM(amount) as date_total
        FROM payments 
        GROUP BY payment_date 
        ORDER BY payment_date DESC
        LIMIT 30
    ");
}

// Get monthly totals
function getMonthlyTotals($conn) {
    return $conn->query("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            DATE_FORMAT(payment_date, '%M %Y') as month_name,
            COUNT(DISTINCT payment_date) as days_count,
            SUM(amount) as month_total
        FROM payments 
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
}

// Get days count with payments
function getDaysWithPaymentCount($conn) {
    $result = $conn->query("SELECT COUNT(DISTINCT payment_date) as count FROM payments");
    return $result->fetch_row()[0];
}

// Check if student paid on specific date and day
function isPaid($conn, $student_id, $date, $day) {
    $check = $conn->query("SELECT id FROM payments WHERE student_id = $student_id AND payment_date = '$date' AND day_of_week = '$day'");
    return $check && $check->num_rows > 0;
}
?>