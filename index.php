<?php
// index.php
require_once 'functions.php';

// Initialize
initializeTables($conn);
initializeStudents($conn);

// Get selected date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_date_info = getSelectedDateInfo($selected_date);

// Get all data for display
$students_result = getStudents($conn);
$total_students = getTotalStudents($conn);
$total_payments = getPaymentsForDate($conn, $selected_date);
$total_collection = getTotalCollection($conn);
$paid_counts = getPaymentSummary($conn, $selected_date);
$dates_result = getRecentDates($conn);
$dates_with_payments = getDatesWithPayments($conn);
$monthly_totals = getMonthlyTotals($conn);
$days_with_payments = getDaysWithPaymentCount($conn);

// Calculate derived values
$total_slots_today = $total_students;
$unpaid_today = $total_slots_today - $total_payments;
$expected_weekly = $total_students * 5 * 5;

// Get message from session
$display_message = '';
$display_message_type = '';
if (isset($_SESSION['message'])) {
    $display_message = $_SESSION['message'];
    $display_message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
$day_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$day_abbr = getDayAbbr();
$display_days = [
    'mon' => ['label' => 'Monday', 'color' => 'blue'],
    'tue' => ['label' => 'Tuesday', 'color' => 'indigo'],
    'thu' => ['label' => 'Thursday', 'color' => 'purple'],
    'fri' => ['label' => 'Friday', 'color' => 'green'],
    'sat' => ['label' => 'Saturday', 'color' => 'orange']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Class Fund Tracker</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .modal-transition { transition: opacity 0.3s ease; }
        .history-scroll::-webkit-scrollbar { width: 6px; }
        .history-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .history-scroll::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
        .history-scroll::-webkit-scrollbar-thumb:hover { background: #555; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.3s ease; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="mb-8 bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">Class Fund Tracker</h1>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="bg-white/20 rounded-lg px-4 py-2">
                        <span class="text-sm">Database: </span>
                        <span class="font-semibold">✅ Connected</span>
                    </div>
                    <button onclick="showClearAllModal()" 
                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-sm transition flex items-center"
                        title="Clear ALL history">
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Clear All History
                    </button>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($display_message): ?>
        <div class="mb-6 fade-in">
            <div class="bg-<?php echo $display_message_type == 'success' ? 'green' : 'yellow'; ?>-100 border-l-4 border-<?php echo $display_message_type == 'success' ? 'green' : 'yellow'; ?>-500 text-<?php echo $display_message_type == 'success' ? 'green' : 'yellow'; ?>-700 p-4 rounded shadow">
                <div class="flex items-center">
                    <svg class="h-6 w-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="font-bold"><?php echo $display_message; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Day Info Card -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6 border-l-4 border-blue-500">
            <div class="flex flex-wrap items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Selected Date</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo date('F j, Y', strtotime($selected_date)); ?></p>
                        <p class="text-sm <?php echo ($selected_date_info['day_of_week'] == 'Wednesday') ? 'text-yellow-600' : 'text-green-600'; ?> font-semibold">
                            <?php echo $selected_date_info['day_of_week']; ?>
                            <?php if($selected_date_info['day_of_week'] == 'Wednesday'): ?>
                                (Rest Day - No Classes)
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="flex space-x-4 mt-4 md:mt-0">
                    <div class="text-center">
                        <p class="text-sm text-gray-600">Expected Weekly</p>
                        <p class="text-xl font-bold text-gray-800">₱<?php echo number_format($expected_weekly); ?></p>
                        <p class="text-xs text-gray-500">(<?php echo $total_students; ?> students × 5 days)</p>
                    </div>
                    <div class="text-center border-l pl-4">
                        <p class="text-sm text-gray-600">Total Collected</p>
                        <p class="text-xl font-bold text-green-600">₱<?php echo number_format($total_collection, 2); ?></p>
                        <p class="text-xs text-gray-500">All time</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500">Students</h3>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_students; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500">Paid Today</h3>
                <p class="text-2xl font-bold text-blue-600"><?php echo $total_payments; ?></p>
                <p class="text-xs text-gray-500">/ <?php echo $total_slots_today; ?> students</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500">Collection Today</h3>
                <p class="text-2xl font-bold text-green-600">₱<?php echo ($total_payments * 5); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500">Unpaid Today</h3>
                <p class="text-2xl font-bold text-red-600"><?php echo max(0, $unpaid_today); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500">Days with Payment</h3>
                <p class="text-2xl font-bold text-purple-600"><?php echo $days_with_payments; ?></p>
            </div>
        </div>
        
        <!-- Day Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <?php foreach ($display_days as $day_key => $day_info): 
                $count = $paid_counts[$day_key] ?? 0;
                $is_active_day = ($selected_date_info['day_key'] == $day_key);
            ?>
            <div class="bg-white rounded-lg shadow p-3 border-t-4 <?php echo $is_active_day ? 'border-blue-500 ring-2 ring-blue-200' : 'border-' . $day_info['color'] . '-500 opacity-70'; ?>">
                <p class="text-xs font-medium text-gray-500"><?php echo $day_info['label']; ?></p>
                <p class="text-lg font-bold text-<?php echo $day_info['color']; ?>-600"><?php echo $count; ?>/<?php echo $total_students; ?></p>
                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                    <div class="bg-<?php echo $day_info['color']; ?>-600 h-1.5 rounded-full" style="width: <?php echo ($total_students > 0) ? round(($count / $total_students) * 100) : 0; ?>%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">₱<?php echo $count * 5; ?></p>
                <?php if ($is_active_day): ?>
                    <span class="text-xs font-semibold text-blue-600 block mt-1">⬅ Active today</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Payment History by Date -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                    <svg class="h-5 w-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Payment History by Date
                </h2>
                <span class="text-sm text-gray-500">Total Collected: ₱<?php echo number_format($total_collection, 2); ?></span>
            </div>
            
            <?php if ($dates_with_payments && $dates_with_payments->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Day</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Payments</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Collection</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($date_row = $dates_with_payments->fetch_assoc()): 
                            $date = $date_row['payment_date'];
                            $is_current = ($date == $selected_date);
                        ?>
                        <tr class="<?php echo $is_current ? 'bg-blue-50' : 'hover:bg-gray-50'; ?>">
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium <?php echo $is_current ? 'text-blue-600' : 'text-gray-900'; ?>">
                                <?php echo date('F j, Y', strtotime($date)); ?>
                                <?php if($is_current): ?>
                                    <span class="ml-2 text-xs bg-blue-200 text-blue-800 px-2 py-0.5 rounded-full">current</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600">
                                <?php echo $date_row['day_name']; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-600">
                                <?php echo $date_row['payment_count']; ?>/<?php echo $total_students; ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                ₱<?php echo number_format($date_row['date_total'], 2); ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="?date=<?php echo $date; ?>" class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-full text-xs transition flex items-center">
                                        <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        View
                                    </a>
                                    <button onclick="showDeleteModal('<?php echo $date; ?>', '<?php echo date('F j, Y', strtotime($date)); ?>', <?php echo $date_row['date_total']; ?>)" 
                                        class="text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1 rounded-full text-xs transition flex items-center">
                                        <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8 text-gray-500">
                <svg class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p>No payment records yet.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Monthly Summary -->
        <?php if ($monthly_totals && $monthly_totals->num_rows > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <?php while ($month = $monthly_totals->fetch_assoc()): ?>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <p class="text-xs font-medium text-gray-500"><?php echo $month['month_name']; ?></p>
                <p class="text-xl font-bold text-purple-600">₱<?php echo number_format($month['month_total'], 2); ?></p>
                <p class="text-xs text-gray-500 mt-1"><?php echo $month['days_count']; ?> days with payments</p>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
        
        <!-- Controls -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <h2 class="text-lg font-semibold text-gray-700">📅 Select Date:</h2>
                    <div class="flex space-x-2">
                        <input type="date" id="currentDate" value="<?php echo $selected_date; ?>" class="border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button onclick="loadStats()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex items-center">
                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            View
                        </button>
                    </div>
                </div>
                
                <?php if($selected_date_info['day_of_week'] != 'Wednesday' && $selected_date_info['day_of_week'] != 'Sunday'): ?>
                <div class="flex space-x-2">
                    <button onclick="markAllPaid()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition flex items-center">
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Mark All Paid (<?php echo $selected_date_info['day_abbr']; ?>)
                    </button>
                    <button onclick="resetAllUnpaid()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition flex items-center">
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Reset All (<?php echo $selected_date_info['day_abbr']; ?>)
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Dates -->
            <?php if ($dates_result && $dates_result->num_rows > 0): ?>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-600 mb-2 flex items-center">
                    <svg class="h-4 w-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Recent payment dates:
                </p>
                <div class="flex flex-wrap gap-2">
                    <?php while ($date_row = $dates_result->fetch_assoc()): 
                        $date = $date_row['payment_date'];
                        $is_active = ($date == $selected_date);
                        $day_name = date('l', strtotime($date));
                    ?>
                    <a href="?date=<?php echo $date; ?>" class="<?php echo $is_active ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'; ?> px-3 py-1 rounded-full text-sm transition flex items-center">
                        <?php echo date('M d, Y', strtotime($date)) . ' (' . substr($day_name, 0, 3) . ')'; ?>
                        <?php if($is_active): ?>
                            <svg class="h-3 w-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        <?php endif; ?>
                    </a>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Students Table (AJAX rendered) -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                            <?php 
                            foreach ($day_labels as $index => $label): 
                                $is_current_day = ($day_abbr[$selected_date_info['day_of_week']] ?? '') == $label;
                                $is_wednesday = ($label == 'Wed');
                                
                                $header_class = "px-6 py-3 text-center text-xs font-medium uppercase tracking-wider";
                                if ($is_wednesday) {
                                    $header_class .= " bg-gray-100 text-gray-400";
                                } elseif ($is_current_day) {
                                    $header_class .= " bg-blue-50 text-blue-600 border-b-2 border-blue-400";
                                } else {
                                    $header_class .= " text-gray-500";
                                }
                            ?>
                            <th class="<?php echo $header_class; ?>">
                                <div class="flex flex-col items-center">
                                    <span><?php echo $label; ?></span>
                                    <?php if ($is_current_day): ?>
                                        <span class="text-[10px] font-normal bg-blue-100 text-blue-800 px-1 rounded mt-1">active</span>
                                    <?php endif; ?>
                                    <?php if ($is_wednesday): ?>
                                        <span class="text-[10px] font-normal text-gray-500 mt-1">rest</span>
                                    <?php endif; ?>
                                </div>
                            </th>
                            <?php endforeach; ?>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">History</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Dynamically populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="mt-6 bg-white rounded-lg shadow-lg p-4">
            <div class="flex flex-wrap items-center gap-6 text-sm">
                <div class="flex items-center">
                    <span class="w-4 h-4 bg-green-500 rounded-full inline-block mr-2 shadow-sm"></span>
                    <span>Paid (₱5)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-4 h-4 bg-red-500 rounded-full inline-block mr-2 shadow-sm"></span>
                    <span>Unpaid</span>
                </div>
                <div class="flex items-center">
                    <span class="w-4 h-4 bg-gray-200 rounded inline-block mr-2 text-center text-gray-500 font-bold">—</span>
                    <span>Rest day or other days</span>
                </div>
                <div class="flex items-center">
                    <span class="w-4 h-4 bg-blue-50 border-2 border-blue-300 rounded inline-block mr-2"></span>
                    <span>Active day for selected date</span>
                </div>
                <div class="flex items-center">
                    <svg class="h-4 w-4 text-blue-600 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Only the day matching selected date is clickable</span>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500 border-t pt-4">
            <p>💾 Data stored in MySQL database • Permanent • Accessible anywhere</p>
            <p class="mt-1">Last updated: <?php echo date('F j, Y g:i A'); ?></p>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 modal-transition">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Delete Payment Date</h3>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600">Are you sure you want to delete all payments for:</p>
                <p id="deleteDateText" class="text-md font-bold text-gray-800 mt-1"></p>
                <p id="deleteAmountText" class="text-sm text-red-600 mt-1"></p>
                <p class="text-xs text-gray-500 mt-3">This action cannot be undone.</p>
            </div>
            <form method="POST" action="process.php" id="deleteForm">
                <input type="hidden" name="action" value="delete_date">
                <input type="hidden" name="delete_date" id="deleteDateInput">
                <input type="hidden" name="payment_date" value="<?php echo $selected_date; ?>">
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeDeleteModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded text-sm">Cancel</button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">Delete</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- History Modal -->
    <div id="historyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 modal-transition">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900" id="modalStudentName">Payment History</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="paymentHistory" class="max-h-96 overflow-y-auto history-scroll px-2">
                <div class="text-center text-gray-500 py-4">Loading...</div>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="closeModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded text-sm">Close</button>
            </div>
        </div>
    </div>

    <!-- Clear Student History Modal -->
    <div id="clearHistoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 modal-transition">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Clear Payment History</h3>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600">Are you sure you want to clear all history for:</p>
                <p id="clearStudentName" class="text-md font-bold text-gray-800 mt-1"></p>
                <p class="text-xs text-red-600 mt-3 font-semibold">⚠️ This will delete ALL transaction logs permanently!</p>
                <p class="text-xs text-gray-500 mt-1">The current balance will remain intact. Only the history records will be deleted.</p>
            </div>
            <form method="POST" action="process.php" id="clearHistoryForm">
                <input type="hidden" name="action" value="clear_student_history">
                <input type="hidden" name="student_id" id="clearStudentId">
                <input type="hidden" name="student_name" id="clearStudentNameInput">
                <input type="hidden" name="current_date" id="clearCurrentDate" value="<?php echo $selected_date; ?>">
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeClearHistoryModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded text-sm">Cancel</button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">Clear History</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Clear ALL History Modal -->
    <div id="clearAllModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 modal-transition">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 text-red-600">⚠️ DANGER ZONE ⚠️</h3>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600">You are about to delete <span class="font-bold">ALL</span> payment history for <span class="font-bold">ALL STUDENTS</span>.</p>
                <p class="text-xs text-red-600 mt-3">This action CANNOT be undone!</p>
                <p class="text-xs text-gray-500 mt-2">Type <span class="font-mono bg-gray-100 px-2 py-1 rounded">CONFIRM</span> to proceed:</p>
                <input type="text" id="confirmInput" class="mt-2 w-full border rounded px-3 py-2 text-sm" placeholder="Type CONFIRM here">
            </div>
            <form method="POST" action="process.php" id="clearAllForm">
                <input type="hidden" name="action" value="clear_all_history">
                <input type="hidden" name="confirm" id="confirmValue">
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeClearAllModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded text-sm">Cancel</button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm" id="clearAllBtn" disabled>Clear ALL History</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // GLOBAL VARIABLES
        // ============================================
        let currentDate = '<?php echo $selected_date; ?>';
        let currentStats = null;
        const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const dayAbbr = <?php echo json_encode($day_abbr); ?>;

        // ============================================
        // INITIALIZATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            
            // Setup form submissions
            setupFormSubmissions();
        });

        document.getElementById('currentDate').addEventListener('change', function(e) {
            currentDate = e.target.value;
            loadStats();
        });

        // ============================================
        // SETUP FORM SUBMISSIONS
        // ============================================
        function setupFormSubmissions() {
            // Delete form
            const deleteForm = document.getElementById('deleteForm');
            if (deleteForm) {
                deleteForm.addEventListener('submit', handleDeleteSubmit);
            }
            
            // Clear history form
            const clearHistoryForm = document.getElementById('clearHistoryForm');
            if (clearHistoryForm) {
                clearHistoryForm.addEventListener('submit', handleClearHistorySubmit);
            }
            
            // Clear all form
            const clearAllForm = document.getElementById('clearAllForm');
            if (clearAllForm) {
                clearAllForm.addEventListener('submit', handleClearAllSubmit);
            }
            
            // Confirm input for clear all
            const confirmInput = document.getElementById('confirmInput');
            if (confirmInput) {
                confirmInput.addEventListener('input', function(e) {
                    const clearBtn = document.getElementById('clearAllBtn');
                    clearBtn.disabled = e.target.value !== 'CONFIRM';
                });
            }
        }

        // ============================================
        // HANDLE DELETE SUBMIT
        // ============================================
        function handleDeleteSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            fetch('./process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeDeleteModal();
                
                if (data.success) {
                    showMessage(data.message, data.message_type);
                    
                    if (data.data) {
                        currentStats = data.data;
                        renderTable();
                        updateStatsDisplay();
                    } else {
                        loadStats();
                    }
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error deleting date', 'error');
            });
        }

        // ============================================
        // HANDLE CLEAR HISTORY SUBMIT
        // ============================================
        function handleClearHistorySubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            fetch('./process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeClearHistoryModal();
                
                if (data.success) {
                    showMessage(data.message, data.message_type);
                    
                    if (data.data) {
                        currentStats = data.data;
                        renderTable();
                        updateStatsDisplay();
                    } else {
                        loadStats();
                    }
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error clearing history', 'error');
            });
        }

        // ============================================
        // HANDLE CLEAR ALL SUBMIT
        // ============================================
        function handleClearAllSubmit(e) {
            e.preventDefault();
            
            if (!validateClearAll()) return;
            
            const formData = new FormData(e.target);
            
            fetch('./process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeClearAllModal();
                
                if (data.success) {
                    showMessage(data.message, data.message_type);
                    
                    if (data.data) {
                        currentStats = data.data;
                        renderTable();
                        updateStatsDisplay();
                    } else {
                        loadStats();
                    }
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error clearing all history', 'error');
            });
        }

        // ============================================
        // LOAD STATS FROM SERVER
        // ============================================
        function loadStats() {
            fetch('./process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_stats&selected_date=' + currentDate
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentStats = data.data;
                    renderTable();
                    updateStatsDisplay();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // ============================================
        // RENDER TABLE
        // ============================================
        function renderTable() {
            const tbody = document.getElementById('studentTableBody');
            if (!currentStats) return;
            
            tbody.innerHTML = '';
            
            const selectedDayOfWeek = new Date(currentDate).toLocaleDateString('en-US', { weekday: 'long' });
            const selectedDayAbbr = dayAbbr[selectedDayOfWeek] || '';
            
            currentStats.students.forEach((student, index) => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                
                let rowHtml = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${index + 1}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${student.name}
                    </td>
                `;

                days.forEach(day => {
                    const isPaid = student.payments[day] || false;
                    const isWednesday = (day === 'wed');
                    const isCurrentDay = (dayLabels[days.indexOf(day)] === selectedDayAbbr);
                    
                    let cellClass = "px-6 py-4 whitespace-nowrap text-center";
                    if (isWednesday) {
                        cellClass += " bg-gray-50";
                    } else if (isCurrentDay) {
                        cellClass += " bg-blue-50/30";
                    }
                    
                    rowHtml += `<td class="${cellClass}">`;
                    
                    if (isWednesday || !isCurrentDay) {
                        rowHtml += '<span class="text-gray-400 text-lg font-medium">—</span>';
                    } else {
                        rowHtml += `
                            <button onclick="togglePayment(${student.id}, '${currentDate}', '${day}')" 
                                class="w-8 h-8 rounded-full ${isPaid ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'} 
                                text-white font-semibold transition transform hover:scale-110"
                                title="${isPaid ? 'Click to mark as UNPAID' : 'Click to mark as PAID (₱5)'}">
                                ${isPaid ? '✓' : '✗'}
                            </button>
                        `;
                    }
                    
                    rowHtml += '</td>';
                });

                rowHtml += `
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="flex items-center justify-center space-x-1">
                            <button onclick="viewHistory(${student.id}, '${student.name.replace(/'/g, "\\'")}')" 
                                class="text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded text-xs transition flex items-center"
                                title="View history">
                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                View
                            </button>
                            <button onclick="showClearHistoryModal(${student.id}, '${student.name.replace(/'/g, "\\'")}')" 
                                class="text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2 py-1 rounded text-xs transition flex items-center"
                                title="Clear history">
                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Clear
                            </button>
                        </div>
                    </td>
                `;

                row.innerHTML = rowHtml;
                tbody.appendChild(row);
            });
        }

        // ============================================
        // UPDATE STATS DISPLAY
        // ============================================
        function updateStatsDisplay() {
            if (!currentStats) return;
            
            // Update the stats grid at the top
            document.getElementById('totalStudents').textContent = currentStats.total_students;
            document.getElementById('totalCollection').textContent = `₱${currentStats.total_collection}`;
            document.getElementById('paidToday').textContent = currentStats.total_payments;
            
            const totalSlots = currentStats.total_students * 5;
            document.getElementById('unpaidToday').textContent = totalSlots - currentStats.total_payments;
            
            // Update day summary cards if needed
            // This would require more complex DOM manipulation
        }

        // ============================================
        // TOGGLE PAYMENT (AJAX)
        // ============================================
        function togglePayment(studentId, date, day) {
            fetch('./process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle&student_id=' + studentId + '&payment_date=' + date + '&day=' + day
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, data.message_type);
                    currentStats = data.data;
                    renderTable();
                    updateStatsDisplay();
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // ============================================
        // MARK ALL PAID (AJAX)
        // ============================================
        function markAllPaid() {
            if (!confirm('Mark all as PAID for ' + new Date(currentDate).toLocaleDateString() + '?')) return;
            
            fetch('./process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_paid&payment_date=' + currentDate
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, data.message_type);
                    currentStats = data.data;
                    renderTable();
                    updateStatsDisplay();
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // ============================================
        // RESET ALL (AJAX)
        // ============================================
        function resetAllUnpaid() {
            if (!confirm('⚠️ RESET all payments for ' + new Date(currentDate).toLocaleDateString() + '? This cannot be undone!')) return;
            
            fetch('./process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reset_all&payment_date=' + currentDate
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, data.message_type);
                    currentStats = data.data;
                    renderTable();
                    updateStatsDisplay();
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // ============================================
        // VIEW HISTORY (AJAX)
        // ============================================
        function viewHistory(studentId, studentName) {
            const modal = document.getElementById('historyModal');
            const modalName = document.getElementById('modalStudentName');
            const historyDiv = document.getElementById('paymentHistory');
            
            modalName.textContent = studentName;
            historyDiv.innerHTML = '<div class="text-center text-gray-500 py-4">Loading history...</div>';
            modal.classList.remove('hidden');
            
            fetch('./process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=view_history&student_id=' + studentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const history = data.data.history;
                    const balance = data.data.balance;
                    const totals = data.data.totals;
                    
                    if (history.length > 0) {
                        let historyHtml = '';
                        
                        history.forEach(record => {
                            const phDate = new Date(record.action_timestamp).toLocaleString('en-PH', {
                                year: 'numeric', month: 'numeric', day: 'numeric',
                                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
                            });
                            
                            const amount = parseFloat(record.amount);
                            let actionColor = record.action === 'paid' ? 'bg-green-100 text-green-800' : 
                                             record.action === 'unpaid' ? 'bg-red-100 text-red-800' : 
                                             'bg-gray-100 text-gray-800';
                            
                            historyHtml += `
                                <div class="border-b border-gray-200 py-3 hover:bg-gray-50 px-2 rounded">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">${record.payment_date} (${record.day_of_week})</p>
                                            <p class="text-xs text-gray-600">${record.action === 'paid' ? 'Paid' : record.action === 'unpaid' ? 'Unpaid' : 'Deleted'} • ₱${amount}</p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded ${actionColor}">${record.action}</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">${phDate}</p>
                                </div>
                            `;
                        });
                        
                        historyHtml = `
                            <div class="bg-gray-50 p-3 rounded-lg mb-3 sticky top-0">
                                <p class="text-sm font-semibold">Summary</p>
                                <div class="grid grid-cols-2 gap-2 text-xs mt-2">
                                    <div class="col-span-2 bg-blue-50 p-2 rounded">
                                        <span class="text-gray-700 font-medium">Current Balance:</span>
                                        <span class="text-blue-600 font-bold text-sm ml-2">₱${balance}</span>
                                    </div>
                                    <div><span class="text-gray-500">Total Paid:</span> <span class="text-green-600 ml-1">₱${totals.paid}</span></div>
                                    <div><span class="text-gray-500">Total Unpaid:</span> <span class="text-red-600 ml-1">₱${totals.unpaid}</span></div>
                                    <div><span class="text-gray-500">Total Deleted:</span> <span class="text-gray-600 ml-1">₱${totals.deleted}</span></div>
                                    <div><span class="text-gray-500">Transactions:</span> <span class="text-gray-700 ml-1">${totals.transactions}</span></div>
                                </div>
                            </div>
                            ${historyHtml}
                        `;
                        
                        historyDiv.innerHTML = historyHtml;
                    } else {
                        historyDiv.innerHTML = '<div class="text-center text-gray-500 py-8">No payment history yet.</div>';
                    }
                }
            })
            .catch(error => {
                historyDiv.innerHTML = '<div class="text-center text-red-500 py-4">Error loading history.</div>';
                console.error('Error:', error);
            });
        }

        // ============================================
        // SHOW MESSAGE
        // ============================================
        function showMessage(message, type) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `mb-6 fade-in`;
            msgDiv.innerHTML = `
                <div class="bg-${type === 'success' ? 'green' : type === 'warning' ? 'yellow' : 'red'}-100 border-l-4 border-${type === 'success' ? 'green' : type === 'warning' ? 'yellow' : 'red'}-500 text-${type === 'success' ? 'green' : type === 'warning' ? 'yellow' : 'red'}-700 p-4 rounded shadow">
                    <div class="flex items-center">
                        <svg class="h-6 w-6 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="font-bold">${message}</p>
                    </div>
                </div>
            `;
            
            const header = document.querySelector('.mb-8');
            header.parentNode.insertBefore(msgDiv, header.nextSibling);
            
            setTimeout(() => {
                msgDiv.remove();
            }, 3000);
        }

        // ============================================
        // CLEAR HISTORY MODAL FUNCTIONS
        // ============================================
        function showClearHistoryModal(studentId, studentName) {
            document.getElementById('clearStudentId').value = studentId;
            document.getElementById('clearStudentName').textContent = studentName;
            document.getElementById('clearStudentNameInput').value = studentName;
            document.getElementById('clearCurrentDate').value = currentDate;
            document.getElementById('clearHistoryModal').classList.remove('hidden');
        }

        function closeClearHistoryModal() {
            document.getElementById('clearHistoryModal').classList.add('hidden');
        }

        function showClearAllModal() {
            document.getElementById('clearAllModal').classList.remove('hidden');
            document.getElementById('confirmInput').value = '';
            document.getElementById('clearAllBtn').disabled = true;
        }

        function closeClearAllModal() {
            document.getElementById('clearAllModal').classList.add('hidden');
        }

        function validateClearAll() {
            const confirmValue = document.getElementById('confirmInput').value;
            if (confirmValue !== 'CONFIRM') {
                alert('Please type CONFIRM to proceed');
                return false;
            }
            document.getElementById('confirmValue').value = confirmValue;
            return true;
        }

        // ============================================
        // DELETE MODAL FUNCTIONS
        // ============================================
        function showDeleteModal(date, formattedDate, amount) {
            document.getElementById('deleteDateText').textContent = formattedDate;
            document.getElementById('deleteAmountText').textContent = '₱' + amount.toFixed(2);
            document.getElementById('deleteDateInput').value = date;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        function closeModal() {
            document.getElementById('historyModal').classList.add('hidden');
        }

        // ============================================
        // GLOBAL EVENT HANDLERS
        // ============================================
        window.onclick = function(event) {
            const modal = document.getElementById('historyModal');
            const deleteModal = document.getElementById('deleteModal');
            const clearModal = document.getElementById('clearHistoryModal');
            const clearAllModal = document.getElementById('clearAllModal');
            
            if (event.target == modal) modal.classList.add('hidden');
            if (event.target == deleteModal) deleteModal.classList.add('hidden');
            if (event.target == clearModal) clearModal.classList.add('hidden');
            if (event.target == clearAllModal) clearAllModal.classList.add('hidden');
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeDeleteModal();
                closeClearHistoryModal();
                closeClearAllModal();
            }
        });
    </script>
</body>
</html>