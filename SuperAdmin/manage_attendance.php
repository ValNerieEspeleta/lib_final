<?php
// Include database connection and session
include '../includes/session.php';
include '../includes/dbcon.php';

/**
 * MANAGE ATTENDANCE - Schedule & Warning Management
 * 
 * CONNECTIONS WITH rfid_attendance.php:
 * 
 * 1. SCHEDULE SETUP (This page):
 *    - SuperAdmin configures time_in_start and time_out_deadline for each day
 *    - Stored in attendance_schedule table with day_name as key
 * 
 * 2. RFID SYSTEM ENFORCEMENT (rfid_attendance.php):
 *    - Reads schedule and validates student time in/out based on these times
 *    - Auto-increments late_timeout_warnings when deadline is missed
 * 
 * 3. SYNCHRONIZATION (This page - Records tab):
 *    - Displays attendance records with visual indicators for missed deadlines
 *    - Shows student warning counts (3 limit = cannot borrow books)
 *    - Allows manual editing/deletion of records
 *    - Tracks missed_deadline status from schedule comparison
 * 
 * DATA FLOW:
 * SuperAdmin Sets Schedule → rfid_attendance.php Enforces Rules → 
 * Students Scanned → Warnings Tracked → manage_attendance.php Displays Status
 */

// Initialize variables
$message = '';
$message_type = '';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'records';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 10;

// Create attendance_schedule table if it doesn't exist
$create_schedule_table = "CREATE TABLE IF NOT EXISTS attendance_schedule (
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    day_name VARCHAR(20) NOT NULL,
    time_in_start TIME NOT NULL,
    time_out_deadline TIME NOT NULL,
    warning_limit INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day (day_name)
)";
mysqli_query($conn, $create_schedule_table);

// Handle schedule setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_schedule') {
        $day_name = mysqli_real_escape_string($conn, $_POST['day_name']);
        $time_in_start = mysqli_real_escape_string($conn, $_POST['time_in_start']);
        $time_out_deadline = mysqli_real_escape_string($conn, $_POST['time_out_deadline']);
        
        $check_query = "SELECT * FROM attendance_schedule WHERE day_name = '$day_name'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $update_query = "UPDATE attendance_schedule SET time_in_start = '$time_in_start', time_out_deadline = '$time_out_deadline' WHERE day_name = '$day_name'";
            if (mysqli_query($conn, $update_query)) {
                $message = '✓ Schedule updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating schedule: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
        } else {
            $insert_query = "INSERT INTO attendance_schedule (day_name, time_in_start, time_out_deadline) VALUES ('$day_name', '$time_in_start', '$time_out_deadline')";
            if (mysqli_query($conn, $insert_query)) {
                $message = '✓ Schedule created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error creating schedule: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
        }
    }
    
    if ($action === 'update_time') {
        $attendance_id = intval($_POST['attendance_id']);
        $time_in = mysqli_real_escape_string($conn, $_POST['time_in']);
        $time_out = mysqli_real_escape_string($conn, $_POST['time_out']);
        
        $update_query = "UPDATE attendance SET time_in = '$time_in' " . 
                       ($time_out ? ", time_out = '$time_out'" : "") . 
                       " WHERE attendance_id = '$attendance_id'";
        
        if (mysqli_query($conn, $update_query)) {
            $message = '✓ Attendance updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating attendance: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
    
    if ($action === 'delete_attendance') {
        $attendance_id = intval($_POST['attendance_id']);
        
        // Get the attendance record first
        $get_record = "SELECT a.*, s.student_id FROM attendance a 
                       LEFT JOIN students s ON a.admission_id = s.student_id
                       WHERE a.attendance_id = '$attendance_id'";
        $get_result = mysqli_query($conn, $get_record);
        $att_record = mysqli_fetch_assoc($get_result);
        
        // If student had no time out, remove one warning when deleting
        if (empty($att_record['time_out']) && !empty($att_record['student_id'])) {
            $remove_warn = "UPDATE students SET late_timeout_warnings = 
                           GREATEST(0, late_timeout_warnings - 1) 
                           WHERE student_id = '" . mysqli_real_escape_string($conn, $att_record['student_id']) . "'";
            mysqli_query($conn, $remove_warn);
        }
        
        $delete_query = "DELETE FROM attendance WHERE attendance_id = '$attendance_id'";
        
        if (mysqli_query($conn, $delete_query)) {
            $message = '✓ Attendance record deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting attendance: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
    
    // Sync attendance status based on schedule every page load
    if ($tab === 'records') {
        $sync_query = "SELECT a.attendance_id, a.attendance_date, a.time_in, a.time_out, 
                       s.student_id, sch.time_out_deadline
                       FROM attendance a
                       LEFT JOIN students s ON a.admission_id = s.student_id
                       LEFT JOIN attendance_schedule sch ON DATE_FORMAT(a.attendance_date, '%W') = sch.day_name
                       WHERE a.time_out IS NULL
                       AND a.attendance_date <= CURDATE()";
        
        $sync_result = mysqli_query($conn, $sync_query);
        
        if ($sync_result) {
            while ($sync_row = mysqli_fetch_assoc($sync_result)) {
                if (!empty($sync_row['time_out_deadline'])) {
                    // Check if deadline has passed and student hasn't time out
                    if ($sync_row['time_in'] < $sync_row['time_out_deadline']) {
                        // Add warning if not already at max
                        if (!empty($sync_row['student_id'])) {
                            $check_warned = "SELECT late_timeout_warnings FROM students WHERE student_id = '" . 
                                          mysqli_real_escape_string($conn, $sync_row['student_id']) . "'";
                            $check_result = mysqli_query($conn, $check_warned);
                            $student_row = mysqli_fetch_assoc($check_result);
                            
                            if ($student_row['late_timeout_warnings'] < 3) {
                                $add_warn = "UPDATE students SET late_timeout_warnings = late_timeout_warnings + 1 
                                           WHERE student_id = '" . mysqli_real_escape_string($conn, $sync_row['student_id']) . "'";
                                @mysqli_query($conn, $add_warn);
                            }
                        }
                    }
                }
            }
        }
    }
}

// Fetch schedule for all days
$schedule_query = "SELECT * FROM attendance_schedule ORDER BY FIELD(day_name, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
$schedule_result = mysqli_query($conn, $schedule_query);
$schedule = [];
while ($row = mysqli_fetch_assoc($schedule_result)) {
    $schedule[$row['day_name']] = $row;
}

// Fetch attendance records for selected date
$query = "SELECT a.*, s.first_name, s.last_name, s.course, s.student_id, s.late_timeout_warnings
          FROM attendance a
          LEFT JOIN students s ON a.admission_id = s.student_id
          WHERE a.attendance_date = '$selected_date'
          ORDER BY a.time_in DESC";
$result = mysqli_query($conn, $query);
$total_records = mysqli_num_rows($result);
$total_pages = ceil($total_records / $records_per_page);

// Ensure page is within valid range
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

$offset = ($page - 1) * $records_per_page;

// Re-query with LIMIT
$query = "SELECT a.*, s.first_name, s.last_name, s.course, s.student_id, s.late_timeout_warnings
          FROM attendance a
          LEFT JOIN students s ON a.admission_id = s.student_id
          WHERE a.attendance_date = '$selected_date'
          ORDER BY a.time_in DESC
          LIMIT $records_per_page OFFSET $offset";
$result = mysqli_query($conn, $query);
$records = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Get schedule for this date to check if time out deadline was missed
    $att_date = $row['attendance_date'];
    $att_day = date('l', strtotime($att_date));
    $schedule_check = "SELECT * FROM attendance_schedule WHERE day_name = '$att_day' LIMIT 1";
    $schedule_check_result = mysqli_query($conn, $schedule_check);
    $day_schedule = mysqli_fetch_assoc($schedule_check_result);
    
    if ($day_schedule) {
        $timeout_deadline = $day_schedule['time_out_deadline'];
        // Check if student timed in before deadline but didn't time out
        if (empty($row['time_out']) && $row['time_in'] < $timeout_deadline) {
            $row['missed_deadline'] = true;
        } else {
            $row['missed_deadline'] = false;
        }
    }
    $records[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "partials/head.php"; ?>
    <title>Manage Attendance - Time Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .manage-attendance-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .manage-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .manage-header h1 {
            margin: 0;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .manage-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .date-selector {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-selector input {
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            min-width: 200px;
        }
        
        .date-selector button {
            padding: 12px 25px;
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid white;
            color: white;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .date-selector button:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .message-box {
            margin: 20px auto;
            padding: 20px;
            border-radius: 10px;
            max-width: 1160px;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .records-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
            overflow-x: auto;
        }
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .records-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .records-table th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }
        
        .records-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .records-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .student-info {
            font-weight: 600;
            color: #333;
        }
        
        .student-meta {
            font-size: 0.9rem;
            color: #666;
            margin-top: 3px;
        }
        
        .time-display {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #333;
        }
        
        .edit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }
        
        .edit-btn:hover {
            transform: translateY(-2px);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            font-family: 'Courier New', monospace;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #ccc;
            color: #333;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-secondary:hover {
            background: #999;
            color: white;
        }
        
        .no-records {
            text-align: center;
            padding: 50px;
            color: #999;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        /* Tabs Navigation */
        .tabs-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 3px solid #eee;
        }

        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 15px 25px;
            background: white;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: bold;
            color: #666;
            transition: all 0.3s;
            text-decoration: none;
            margin-bottom: -3px;
        }

        .tab-btn:hover {
            color: #667eea;
        }

        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        /* Schedule Container */
        .schedule-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .schedule-card h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .schedule-card .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .days-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .day-setup-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 12px;
            padding: 20px;
            border-left: 5px solid #667eea;
        }

        .day-setup-card h3 {
            color: #333;
            margin: 0 0 15px 0;
            font-size: 1.1rem;
        }

        .day-form {
            margin-bottom: 15px;
        }

        .day-form .form-group {
            margin-bottom: 12px;
        }

        .day-form .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .day-form .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .day-form .form-group small {
            display: block;
            font-size: 0.75rem;
            color: #666;
            margin-top: 3px;
        }

        .btn-save {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
        }

        .schedule-display {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
        }

        .schedule-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 0.9rem;
            border-bottom: 1px solid #eee;
        }

        .schedule-item:last-child {
            border-bottom: none;
        }

        .schedule-item .label {
            font-weight: bold;
            color: #666;
        }

        .schedule-item .value {
            color: #667eea;
            font-weight: bold;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            color: #1565c0;
            font-size: 0.9rem;
        }

        .info-box i {
            margin-right: 10px;
        }

        .records-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .date-selector-container {
            margin-bottom: 30px;
        }

        .date-selector-container form {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .date-selector-container input {
            padding: 12px;
            border: 2px solid #667eea;
            border-radius: 8px;
        }

        .date-selector-container button {
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .date-selector-container button:hover {
            transform: translateY(-2px);
        }

        /* Warnings Display */
        .warnings-container {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .warnings-container h3 {
            color: #856404;
            margin: 0 0 15px 0;
            font-size: 1.2rem;
        }

        .warnings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .warning-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #ffc107;
        }

        .warning-card.blocked {
            border-left-color: #dc3545;
            background: #f8d7da;
        }

        .student-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .warning-count {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #856404;
            font-weight: bold;
        }

        .blocked-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #dc3545;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .warning-badge {
            display: inline-block;
            background: #ffc107;
            color: #856404;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-top: 5px;
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .pagination-info {
            color: #666;
            font-weight: bold;
        }

        .pagination-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .pagination-btn {
            padding: 10px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .pagination-btn:hover:not(:disabled) {
            transform: translateY(-2px);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-number {
            display: flex;
            gap: 5px;
        }

        .page-number {
            padding: 8px 12px;
            background: white;
            border: 2px solid #667eea;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            color: #667eea;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .page-number:hover {
            background: #667eea;
            color: white;
        }

        .page-number.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }

        .delete-btn:hover {
            transform: translateY(-2px);
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container-scroller">
        <?php include "partials/navbar.php"; ?>
        <div class="container-fluid page-body-wrapper">
            <?php include "partials/sidebar.php"; ?>
            
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="manage-attendance-container">
                        <!-- Header -->
                        <div class="manage-header">
                            <h1><i class="fas fa-clock"></i> Manage Attendance System</h1>
                            <p>Set schedules and manage student attendance times</p>
                        </div>

                        <!-- Tabs Navigation -->
                        <div class="tabs-navigation">
                            <a href="?tab=schedule" class="tab-btn <?php echo $tab === 'schedule' ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-alt"></i> Schedule Setup
                            </a>
                            <a href="?tab=records&date=<?php echo $selected_date; ?>" class="tab-btn <?php echo $tab === 'records' ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i> Attendance Records
                            </a>
                        </div>
                        
                        <!-- Message Display -->
                        <?php if (!empty($message)): ?>
                            <div class="message-box alert-<?php echo $message_type; ?>">
                                <i class="fas fa-<?php echo ($message_type === 'success') ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <!-- SCHEDULE SETUP TAB -->
                        <?php if ($tab === 'schedule'): ?>
                        <div class="schedule-container">
                            <div class="schedule-card">
                                <h2><i class="fas fa-cogs"></i> Configure Daily Schedule</h2>
                                <p class="subtitle">Set time-in start time and time-out deadline for each day</p>

                                <div class="days-grid">
                                    <?php 
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    foreach ($days as $day):
                                        $daySchedule = $schedule[$day] ?? null;
                                    ?>
                                    <div class="day-setup-card">
                                        <h3><?php echo $day; ?></h3>
                                        <form method="POST" action="" class="day-form">
                                            <input type="hidden" name="action" value="update_schedule">
                                            <input type="hidden" name="day_name" value="<?php echo $day; ?>">
                                            
                                            <div class="form-group">
                                                <label><i class="fas fa-sign-in-alt"></i> Time In Start</label>
                                                <input type="time" name="time_in_start" value="<?php echo $daySchedule['time_in_start'] ?? '08:00'; ?>" required>
                                                <small>Students can time in from this time</small>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label><i class="fas fa-sign-out-alt"></i> Time Out Deadline</label>
                                                <input type="time" name="time_out_deadline" value="<?php echo $daySchedule['time_out_deadline'] ?? '17:00'; ?>" required>
                                                <small>Must time out before this time</small>
                                            </div>
                                            
                                            <button type="submit" class="btn-save">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                        </form>
                                        
                                        <?php if ($daySchedule): ?>
                                        <div class="schedule-display">
                                            <div class="schedule-item">
                                                <span class="label">Time In Start:</span>
                                                <span class="value"><?php echo date('h:i A', strtotime($daySchedule['time_in_start'])); ?></span>
                                            </div>
                                            <div class="schedule-item">
                                                <span class="label">Time Out Deadline:</span>
                                                <span class="value"><?php echo date('h:i A', strtotime($daySchedule['time_out_deadline'])); ?></span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="info-box">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Warning System:</strong> Students get 1 warning for each missed time-out. After 3 warnings, they cannot borrow books until eligible status is reset.
                                </div>
                            </div>
                        </div>

                        <!-- ATTENDANCE RECORDS TAB -->
                        <?php else: ?>
                        <div class="records-container">
                            <!-- Date Selector -->
                            <div class="date-selector-container">
                                <form method="GET" style="display: flex; gap: 15px; align-items: center;">
                                    <input type="hidden" name="tab" value="records">
                                    <input type="date" name="date" value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
                                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                                </form>
                            </div>
                            <!-- Records Table -->
                        <div class="records-table-container">
                            <?php if (count($records) > 0): ?>
                                <!-- Statistics -->
                                <div class="stats-row">
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo count($records); ?></div>
                                        <div class="stat-label">Total Records</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value"><?php 
                                            $with_timeout = 0;
                                            foreach ($records as $r) {
                                                if (!empty($r['time_out'])) $with_timeout++;
                                            }
                                            echo $with_timeout;
                                        ?></div>
                                        <div class="stat-label">Completed (Timed Out)</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo count($records) - $with_timeout; ?></div>
                                        <div class="stat-label">Active (No Time Out)</div>
                                    </div>
                                </div>

                                <!-- Students with Warnings -->
                                <?php 
                                $warnings_query = "SELECT * FROM students WHERE late_timeout_warnings > 0 ORDER BY late_timeout_warnings DESC";
                                $warnings_result = mysqli_query($conn, $warnings_query);
                                $students_with_warnings = [];
                                while ($row = mysqli_fetch_assoc($warnings_result)) {
                                    $students_with_warnings[] = $row;
                                }
                                ?>

                                <?php if (count($students_with_warnings) > 0): ?>
                                <div class="warnings-container">
                                    <h3><i class="fas fa-exclamation-triangle"></i> Students with Warnings</h3>
                                    <div class="warnings-grid">
                                        <?php foreach ($students_with_warnings as $student): ?>
                                        <div class="warning-card <?php echo $student['late_timeout_warnings'] >= 3 ? 'blocked' : ''; ?>">
                                            <div class="student-name">
                                                <i class="fas fa-user-circle"></i>
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </div>
                                            <div class="warning-info">
                                                <span class="warning-count">
                                                    <i class="fas fa-exclamation"></i>
                                                    <?php echo $student['late_timeout_warnings']; ?>/3 Warnings
                                                </span>
                                                <?php if ($student['late_timeout_warnings'] >= 3): ?>
                                                <span class="blocked-badge">
                                                    <i class="fas fa-ban"></i> Cannot Borrow Books
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <table class="records-table">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-user"></i> Student Name</th>
                                            <th><i class="fas fa-sign-in-alt"></i> Time In</th>
                                            <th><i class="fas fa-sign-out-alt"></i> Time Out</th>
                                            <th><i class="fas fa-cog"></i> Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td>
                                                    <div class="student-info">
                                                        <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                        <?php if ($record['late_timeout_warnings'] > 0): ?>
                                                        <span class="warning-badge">⚠️ <?php echo $record['late_timeout_warnings']; ?> Warning(s)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="student-meta">
                                                        <?php echo htmlspecialchars($record['student_id']); ?> • <?php echo htmlspecialchars($record['course'] ?? 'N/A'); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="time-display">
                                                        <?php echo date('h:i A', strtotime($record['time_in'])); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="time-display">
                                                        <?php echo !empty($record['time_out']) ? date('h:i A', strtotime($record['time_out'])) : '<span style="color: #999;">--:-- --</span>'; ?>
                                                    </div>
                                                    <?php if ($record['missed_deadline']): ?>
                                                    <span class="warning-badge" style="background: #dc3545; color: white; margin-top: 5px;">
                                                        ⚠️ Missed Deadline
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 8px;">
                                                        <button class="edit-btn" onclick="openEditModal(<?php echo $record['attendance_id']; ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button class="delete-btn" onclick="confirmDelete(<?php echo $record['attendance_id']; ?>)">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <!-- Pagination Controls -->
                                <?php if ($total_pages > 1): ?>
                                <div class="pagination-container">
                                    <div class="pagination-info">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                                    </div>
                                    <div class="pagination-controls">
                                        <?php if ($page > 1): ?>
                                        <a href="?tab=records&date=<?php echo $selected_date; ?>&page=1" class="pagination-btn" title="First Page">
                                            <i class="fas fa-step-backward"></i>
                                        </a>
                                        <a href="?tab=records&date=<?php echo $selected_date; ?>&page=<?php echo $page - 1; ?>" class="pagination-btn" title="Previous Page">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                        <?php else: ?>
                                        <button class="pagination-btn" disabled>
                                            <i class="fas fa-step-backward"></i>
                                        </button>
                                        <button class="pagination-btn" disabled>
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                        <?php endif; ?>

                                        <div class="pagination-number">
                                            <?php 
                                            $start_page = max(1, $page - 2);
                                            $end_page = min($total_pages, $page + 2);
                                            
                                            if ($start_page > 1): ?>
                                                <span style="padding: 10px; color: #999;">...</span>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <?php if ($i == $page): ?>
                                                    <span class="page-number active"><?php echo $i; ?></span>
                                                <?php else: ?>
                                                    <a href="?tab=records&date=<?php echo $selected_date; ?>&page=<?php echo $i; ?>" class="page-number"><?php echo $i; ?></a>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            
                                            <?php if ($end_page < $total_pages): ?>
                                                <span style="padding: 10px; color: #999;">...</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($page < $total_pages): ?>
                                        <a href="?tab=records&date=<?php echo $selected_date; ?>&page=<?php echo $page + 1; ?>" class="pagination-btn" title="Next Page">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                        <a href="?tab=records&date=<?php echo $selected_date; ?>&page=<?php echo $total_pages; ?>" class="pagination-btn" title="Last Page">
                                            <i class="fas fa-step-forward"></i>
                                        </a>
                                        <?php else: ?>
                                        <button class="pagination-btn" disabled>
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                        <button class="pagination-btn" disabled>
                                            <i class="fas fa-step-forward"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-records">
                                    <i class="fas fa-calendar-times" style="font-size: 3rem; opacity: 0.5; margin-bottom: 15px;"></i>
                                    <p><strong>No attendance records for <?php echo date('F j, Y', strtotime($selected_date)); ?></strong></p>
                                    <p>Select a different date or wait for students to scan their RFID cards.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php include 'partials/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-edit"></i> Edit Attendance Time
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_time">
                <input type="hidden" name="attendance_id" id="attendance_id">
                
                <div class="form-group">
                    <label for="time_in"><i class="fas fa-sign-in-alt"></i> Time In (Format: HH:MM)</label>
                    <input type="time" id="time_in" name="time_in" required>
                </div>
                
                <div class="form-group">
                    <label for="time_out"><i class="fas fa-sign-out-alt"></i> Time Out (Format: HH:MM) - Optional</label>
                    <input type="time" id="time_out" name="time_out">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i> Confirm Delete
            </div>
            <div style="padding: 20px; text-align: center;">
                <p style="font-size: 1.1rem; margin-bottom: 20px; color: #333;">
                    Are you sure you want to delete this attendance record? This action cannot be undone.
                </p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_attendance">
                <input type="hidden" name="attendance_id" id="delete_attendance_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-primary" style="background: #dc3545;">
                        <i class="fas fa-trash"></i> Delete Record
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="static/vendors/js/vendor.bundle.base.js"></script>
    <script src="static/js/off-canvas.js"></script>
    <script src="static/js/hoverable-collapse.js"></script>
    <script src="static/js/misc.js"></script>
    
    <script>
        const records = <?php echo json_encode($records); ?>;
        
        function openEditModal(attendanceId) {
            const record = records.find(r => r.attendance_id == attendanceId);
            if (record) {
                document.getElementById('attendance_id').value = attendanceId;
                document.getElementById('time_in').value = record.time_in.substring(0, 5);
                document.getElementById('time_out').value = record.time_out ? record.time_out.substring(0, 5) : '';
                document.getElementById('editModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function confirmDelete(attendanceId) {
            document.getElementById('delete_attendance_id').value = attendanceId;
            document.getElementById('deleteModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        });
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
  