<?php
// Include database connection and session
include '../includes/session.php';
include '../includes/dbcon.php';

// Set Philippines timezone (UTC+8)
date_default_timezone_set('Asia/Manila');

/**
 * RFID ATTENDANCE SYSTEM - Professional Implementation
 * 
 * Features:
 * - Real-time attendance tracking with live clock
 * - Simple time in/out functionality
 * - 10-minute minimum between time in and time out
 * - RFID card scanning and UID validation
 * - Open time (no schedule restrictions)
 * - Professional UI with responsive design
 */

// Initialize response variables
$message = '';
$message_type = '';

// Get current date/time in Philippines timezone
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$current_datetime = date('Y-m-d H:i:s'); // Full datetime for timestamps

// Check which attendance table exists
$attendanceTable = 'lib_attendance'; // default
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'attendance'");
if ($checkTable && mysqli_num_rows($checkTable) > 0) {
    $attendanceTable = 'attendance';
} elseif (mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'lib_attendance'")) == 0) {
    // Neither exists, create lib_attendance
    mysqli_query($conn, "CREATE TABLE lib_attendance (
        attendance_id INT PRIMARY KEY AUTO_INCREMENT,
        admission_id VARCHAR(255) NOT NULL,
        attendance_date DATE NOT NULL,
        time_in VARCHAR(50),
        time_out VARCHAR(50),
        status VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// ✅ Ensure attendance table has proper structure (using detected table)
@mysqli_query($conn, "ALTER TABLE $attendanceTable MODIFY COLUMN admission_id VARCHAR(50)");

// ✅ Fix for "Field 'schedule_id' doesn't have a default value"
// We check if schedule_id exists and make it NULLABLE
$schedCheck = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$attendanceTable' AND COLUMN_NAME = 'schedule_id' AND TABLE_SCHEMA = DATABASE()");
if ($schedCheck && mysqli_num_rows($schedCheck) > 0) {
    @mysqli_query($conn, "ALTER TABLE $attendanceTable MODIFY COLUMN schedule_id INT NULL");
}

// ✅ Store current student info for display
$current_student = null;
$current_user_type = null;

// ✅ Handle RFID Card Scan / UID Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_uid'])) {
    $rfid_uid = mysqli_real_escape_string($conn, trim($_POST['rfid_uid']));
    
    // Validate input
    if (empty($rfid_uid)) {
        $message = 'Please scan or enter RFID UID';
        $message_type = 'warning';
    } else {
        // ✅ FIRST: Try to find student by RFID Number or Student ID
        $student_query = "SELECT s.*, y.year_name, sec.section_name, s.grade, s.strand, s.section_id, 'Student' as user_type
                         FROM students s 
                         LEFT JOIN year_levels y ON s.year_id = y.year_id 
                         LEFT JOIN sections sec ON (s.section_id = sec.section_id OR (s.section_id = sec.section_name AND s.year_id = sec.level))
                         WHERE s.rfid_number = '$rfid_uid' OR s.student_id = '$rfid_uid' LIMIT 1";
        $student_result = @mysqli_query($conn, $student_query);
        $student_found = ($student_result && @mysqli_num_rows($student_result) > 0);
        
        // ✅ SECOND: If not found, try employees table
        $employee_found = false;
        if (!$student_found) {
            $employee_query = "SELECT e.*, 'Employee' as user_type, COALESCE(r.role_name, e.role_id) as year_name, e.assigned_course as section_name, COALESCE(r.role_name, e.role_id) as display_role
                              FROM employees e 
                              LEFT JOIN roles r ON e.role_id = r.role_id
                              WHERE e.rfid_number = '$rfid_uid' OR e.employee_id = '$rfid_uid' LIMIT 1";
            $employee_result = @mysqli_query($conn, $employee_query);
            $employee_found = ($employee_result && @mysqli_num_rows($employee_result) > 0);
        }
        
        if (!$student_found && !$employee_found) {
            $message = 'User not found. RFID/ID not registered in system.';
            $message_type = 'danger';
        } else {
            // ✅ Determine which data to use
            if ($student_found) {
                $student = @mysqli_fetch_assoc($student_result);
                $user_id = mysqli_real_escape_string($conn, $student['student_id']);
                // Re-query the student with joins to guarantee year_name/section_name are returned
                $fresh_q = "SELECT s.*, y.year_name, sec.section_name, s.grade, s.strand, s.section_id, s.year_id
                            FROM students s
                            LEFT JOIN year_levels y ON s.year_id = y.year_id
                            LEFT JOIN sections sec ON (s.section_id = sec.section_id OR (s.section_id = sec.section_name AND s.year_id = sec.level))
                            WHERE s.student_id = '$user_id' LIMIT 1";
                $fresh_r = @mysqli_query($conn, $fresh_q);
                if ($fresh_r && @mysqli_num_rows($fresh_r) > 0) {
                    $student = @mysqli_fetch_assoc($fresh_r);
                }
                $user_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
                $current_student = $student;
                $current_user_type = 'Student';

                // ✅ Check for Schedule
                $sGrade = mysqli_real_escape_string($conn, $student['grade']);
                $sSection = mysqli_real_escape_string($conn, $student['section_name'] ?? $student['section_id'] ?? "");
                $sStrand = mysqli_real_escape_string($conn, $student['strand'] ?? "");
                
                $sched_q = "SELECT * FROM lib_schedule 
                            WHERE grade_id = '$sGrade' 
                            AND (section_id = '$sSection' OR section_id = (SELECT section_id FROM sections WHERE section_name = '$sSection' LIMIT 1))
                            AND (strand = '$sStrand' OR strand IS NULL OR strand = '')
                            AND sched_date = '$current_date'
                            AND '$current_time' BETWEEN start_time AND end_time
                            LIMIT 1";
                $sched_r = mysqli_query($conn, $sched_q);
                $has_sched = (mysqli_num_rows($sched_r) > 0);
                $sched_msg = $has_sched ? " [SCHEDULED SESSION]" : "";
            } else {
                $student = @mysqli_fetch_assoc($employee_result);
                $user_id = mysqli_real_escape_string($conn, $student['employee_id']);
                $user_name = htmlspecialchars($student['firstname'] . ' ' . $student['lastname']);
                $current_student = $student;
                $current_user_type = 'Employee';
                $sched_msg = "";
            }
            
            // ✅ Check if user has active time-in record today (no time out yet)
            $check_query = "SELECT a.*, 
                           TIMESTAMPDIFF(SECOND, a.time_in, NOW()) as seconds_elapsed
                           FROM $attendanceTable a
                           WHERE a.admission_id = '$user_id' 
                           AND a.attendance_date = '$current_date' 
                           AND a.time_out IS NULL 
                           ORDER BY a.attendance_id DESC
                           LIMIT 1";
            $check_result = @mysqli_query($conn, $check_query);
            $has_active_record = ($check_result && @mysqli_num_rows($check_result) > 0);
            
            if (!$has_active_record) {
                // =============== TIME IN LOGIC ===============
                $insert_query = "INSERT INTO $attendanceTable (admission_id, attendance_date, time_in, status) 
                                VALUES ('$user_id', '$current_date', '$current_time', 'Present')";
                
                if (@mysqli_query($conn, $insert_query)) {
                    $time_in_12h = date('h:i A', strtotime($current_time));
                    $message = "✓ {$user_name} - TIME IN at {$time_in_12h} {$sched_msg}";
                    $message_type = 'success';
                } else {
                    $message = 'Error recording time in: ' . mysqli_error($conn);
                    $message_type = 'danger';
                }
            } else {
                // =============== TIME OUT LOGIC ===============
                $existing = @mysqli_fetch_assoc($check_result);
                $seconds_elapsed = intval($existing['seconds_elapsed'] ?? 0);
                $minutes_elapsed = $seconds_elapsed / 60;
                $minimum_minutes = 10;
                
                if ($minutes_elapsed < $minimum_minutes) {
                    $minutes_remaining = ceil($minimum_minutes - $minutes_elapsed);
                    $time_in_display = date('h:i A', strtotime($existing['time_in']));
                    $message = "⏱️ {$user_name} - Tapped in at {$time_in_display}. Please wait {$minutes_remaining} more minute(s) before timing out.";
                    $message_type = 'warning';
                } else {
                    $attendance_id = intval($existing['attendance_id']);
                    $update_query = "UPDATE $attendanceTable SET time_out = '$current_time' WHERE attendance_id = $attendance_id";
                    
                    if (@mysqli_query($conn, $update_query)) {
                        $time_out_12h = date('h:i A', strtotime($current_time));
                        $message = "✓ {$user_name} - TIME OUT at {$time_out_12h} {$sched_msg}";
                        $message_type = 'info';
                    } else {
                        $message = 'Error recording time out: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                }
            }
        }
    }
}

// ✅ Fetch today's attendance records
$today_records_query = "
    SELECT a.*, CONCAT(s.first_name, ' ', s.last_name) as student_name, 'Student' as user_type_label
    FROM $attendanceTable a
    INNER JOIN (
        SELECT admission_id, MAX(attendance_id) as max_id
        FROM $attendanceTable
        WHERE attendance_date = '$current_date'
        GROUP BY admission_id
    ) latest ON a.admission_id = latest.admission_id AND a.attendance_id = latest.max_id
    LEFT JOIN students s ON a.admission_id = s.student_id
    WHERE s.student_id IS NOT NULL
    UNION
    SELECT a.*, CONCAT(e.firstname, ' ', e.lastname) as student_name, 'Staff' as user_type_label
    FROM $attendanceTable a
    INNER JOIN (
        SELECT admission_id, MAX(attendance_id) as max_id
        FROM $attendanceTable
        WHERE attendance_date = '$current_date'
        GROUP BY admission_id
    ) latest ON a.admission_id = latest.admission_id AND a.attendance_id = latest.max_id
    LEFT JOIN employees e ON a.admission_id = e.employee_id
    WHERE e.employee_id IS NOT NULL
    ORDER BY attendance_id DESC
";
$today_records_result = @mysqli_query($conn, $today_records_query);
$today_records = [];
if ($today_records_result) {
    while ($row = mysqli_fetch_assoc($today_records_result)) {
        $today_records[] = $row;
    }
}

// ✅ Get statistics
$total_timed_in = count($today_records);
$total_timed_out = 0;
$currently_in = 0;

foreach ($today_records as $record) {
    if (!empty($record['time_out'])) {
        $total_timed_out++;
    } else {
        $currently_in++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "partials/head.php"; ?>
    <title>Attendance System - RFID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }

        body {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-scroller {
            background: transparent;
        }

        .content-wrapper {
            padding: 2rem;
        }

        .attendance-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 2rem;
            align-items: start;
        }

        /* LEFT PANEL */
        .left-panel {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* RIGHT PANEL */
        .right-panel {
            display: flex;
            flex-direction: column;
        }

        /* Main Scanner Card */
        .scanner-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            padding: 2rem;
            margin-bottom: 0;
        }

        .scanner-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .scanner-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 0.25rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .scanner-title i {
            font-size: 1.5rem;
        }

        .scanner-subtitle {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 0;
        }

        .current-time {
            font-size: 2.75rem;
            font-weight: 700;
            color: var(--primary);
            margin: 1.5rem 0 0.5rem 0;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.1em;
            text-align: center;
        }

        .date-display {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        /* RFID Input */
        .rfid-input-group {
            margin: 2rem 0;
        }

        .rfid-input {
            width: 100%;
            font-size: 1.25rem;
            padding: 1rem 1.5rem;
            border: 2px solid var(--primary);
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            letter-spacing: 0.05em;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .rfid-input:focus {
            outline: none;
            border-color: var(--primary-dark);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            background: white;
        }

        .message-container {
            margin: 1.5rem 0;
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .alert i {
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: #fee2e2;
            color: #7f1d1d;
            border-left: 4px solid var(--danger);
        }

        .alert-warning {
            background: #fef3c7;
            color: #78350f;
            border-left: 4px solid var(--warning);
        }

        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border-left: 4px solid var(--info);
        }

        /* Right panel messaging */
        .right-panel .message-container {
            margin-bottom: 1rem;
        }

        .right-panel .alert {
            border-radius: 10px;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin: 1.5rem 0 0 0;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.2);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        /* Stats Grid - Side by Side Layout */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        /* Today's Records */
        .records-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .records-header {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            color: white;
            padding: 1.5rem;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .records-content {
            padding: 1.5rem;
        }

        .record-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1.5rem;
            align-items: center;
            padding: 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }

        .record-item:last-child {
            border-bottom: none;
        }

        .record-item:hover {
            background-color: #f9fafb;
        }

        .record-name {
            font-weight: 600;
            color: #111827;
        }

        .record-time {
            text-align: center;
            font-size: 0.9rem;
        }

        .time-label {
            color: #6b7280;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .time-value {
            color: #111827;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
        }

        .record-status {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-in {
            background: #d1fae5;
            color: #065f46;
            border-left: 3px solid #10b981;
        }

        .status-out {
            background: #fee2e2;
            color: #7f1d1d;
            border-left: 3px solid #ef4444;
        }

        .no-records {
            text-align: center;
            padding: 3rem;
            color: #9ca3af;
        }

        .no-records i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Hint Text */
        .hint-text {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 1rem;
            text-align: center;
        }

        .hint-text i {
            margin-right: 0.5rem;
        }

        /* Current Student Card */
        .current-student-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #f5f3ff 100%);
            border: 2px solid var(--primary);
            border-radius: 12px;
            margin: 2rem 0;
            overflow: hidden;
        }

        .student-card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            color: white;
            padding: 1rem;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .profile-image-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .profile-image {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
            background: #f0f9ff;
        }

        .student-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            padding: 1.5rem;
        }

        .info-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .info-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .student-info-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .attendance-wrapper {
                grid-template-columns: 350px 1fr;
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .attendance-wrapper {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .scanner-title {
                font-size: 1.5rem;
            }

            .current-time {
                font-size: 2rem;
            }

            .record-item {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .record-time {
                display: flex;
                justify-content: space-around;
            }

            .scanner-card {
                padding: 1.5rem;
            }
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
                    <div class="attendance-wrapper">
                        <!-- LEFT PANEL: Student Info (Result) -->
                        <div class="left-panel">
                            <?php if ($current_student): ?>
                                <div class="current-student-card" style="margin-top: 0; animation: slideInLeft 0.5s ease-out;">
                                    <div class="student-card-header">
                                        <i class="fas fa-user-circle"></i> 
                                        <?php echo $current_user_type === 'Employee' ? 'Current Employee' : 'Current Student'; ?>
                                    </div>
                                    <!-- Profile Picture -->
                                    <div class="profile-image-container">
                                        <?php
                                            $profile_image = '../img/defaulticon.png';
                                            
                                            if ($current_user_type === 'Employee' && !empty($current_student['profile_pic'])) {
                                                $pic_path = $current_student['profile_pic'];
                                                if (file_exists('../uploads/' . $pic_path)) {
                                                    $profile_image = '../uploads/' . $pic_path;
                                                } elseif (file_exists($pic_path)) {
                                                    $profile_image = $pic_path;
                                                }
                                            } elseif ($current_user_type === 'Student' && !empty($current_student['profile_picture'])) {
                                                $pic_path = $current_student['profile_picture'];
                                                if (file_exists('../uploads/' . $pic_path)) {
                                                    $profile_image = '../uploads/' . $pic_path;
                                                } elseif (file_exists($pic_path)) {
                                                    $profile_image = $pic_path;
                                                }
                                            }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile Picture" class="profile-image">
                                    </div>
                                    <div class="student-info-grid">
                                        <!-- Row 1: ID and Name -->
                                        <div class="info-item">
                                            <div class="info-label"><?php echo $current_user_type === 'Employee' ? 'Employee ID' : 'Student ID'; ?></div>
                                            <div class="info-value">
                                                <?php echo $current_user_type === 'Employee' ? 
                                                    htmlspecialchars($current_student['employee_id'] ?? '') : 
                                                    htmlspecialchars($current_student['student_id'] ?? ''); ?>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Full Name</div>
                                            <div class="info-value">
                                                <?php echo $current_user_type === 'Employee' ? 
                                                    htmlspecialchars(($current_student['firstname'] ?? '') . ' ' . ($current_student['lastname'] ?? '')) : 
                                                    htmlspecialchars(($current_student['first_name'] ?? '') . ' ' . ($current_student['last_name'] ?? '')); ?>
                                            </div>
                                        </div>

                                        <!-- Row 2: Dynamic Info based on User Type -->
                                        <?php if ($current_user_type === 'Student'): ?>
                                            <?php 
                                            // Robust check for High School: grade is 7-12
                                            $grade = (int)($current_student['grade'] ?? 0);
                                            $isHighSchool = ($grade >= 7 && $grade <= 12);
                                            ?>
                                            
                                            <?php if ($isHighSchool): ?>
                                                <!-- High School: Grade and Strand -->
                                                <div class="info-item">
                                                    <div class="info-label">Grade</div>
                                                    <div class="info-value">
                                                        <span style="background: #10b981; color: white; padding: 0.4rem 0.8rem; border-radius: 6px; display: inline-block; font-weight: bold;">
                                                            Grade <?php echo htmlspecialchars($current_student['grade']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="info-item">
                                                    <div class="info-label">Strand</div>
                                                    <div class="info-value">
                                                        <span style="background: #f59e0b; color: white; padding: 0.4rem 0.8rem; border-radius: 6px; display: inline-block; font-weight: bold;">
                                                            <?php echo !empty($current_student['strand']) ? htmlspecialchars($current_student['strand']) : 'N/A'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <!-- College: Year and Section -->
                                                <div class="info-item">
                                                    <div class="info-label">Year Level</div>
                                                    <div class="info-value" style="color: var(--primary); font-weight: bold;">
                                                        <?php 
                                                        if (!empty($current_student['year_name'])) {
                                                            echo htmlspecialchars($current_student['year_name']);
                                                        } elseif (!empty($current_student['year_id'])) {
                                                            $yearMap = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
                                                            echo isset($yearMap[$current_student['year_id']]) ? $yearMap[$current_student['year_id']] : 'Year ' . htmlspecialchars($current_student['year_id']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="info-item">
                                                    <div class="info-label">Section</div>
                                                    <div class="info-value" style="color: #6366f1; font-weight: bold;">
                                                        <?php 
                                                        if (!empty($current_student['section_name'])) {
                                                            echo htmlspecialchars($current_student['section_name']);
                                                        } elseif (!empty($current_student['section_id'])) {
                                                            echo htmlspecialchars($current_student['section_id']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Staff/Employee: Role and Department -->
                                            <div class="info-item">
                                                <div class="info-label">Role</div>
                                                <div class="info-value" style="color: #ec4899; font-weight: bold;">
                                                    <?php echo !empty($current_student['display_role']) ? htmlspecialchars($current_student['display_role']) : 'Staff'; ?>
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">Department</div>
                                                <div class="info-value" style="color: #8b5cf6; font-weight: bold;">
                                                    <?php echo !empty($current_student['assigned_course']) ? htmlspecialchars($current_student['assigned_course']) : 'N/A'; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Placeholder when no student is scanned -->
                                <div class="scanner-card" style="text-align: center; color: #9ca3af; padding: 4rem 2rem; border: 2px dashed #cbd5e1; box-shadow: none; background: rgba(255,255,255,0.5);">
                                    <i class="fas fa-id-badge" style="font-size: 5rem; margin-bottom: 2rem; color: #cbd5e1;"></i>
                                    <h3>Ready to Scan</h3>
                                    <p>Tap your RFID card on the reader to view profile and record attendance.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- RIGHT PANEL: Scanner & Today's Attendance Records -->
                        <div class="right-panel">
                            
                            <!-- Main Scanner Card (Clock & Input) -->
                            <div class="scanner-card" style="margin-bottom: 2rem;">
                                <!-- Header Title at TOP -->
                                <div class="scanner-header">
                                    <h1 class="scanner-title">
                                        <i class="fas fa-id-card"></i> Library Attendance Monitoring
                                    </h1>
                                    <p class="scanner-subtitle">Tap RFID card - Students & Staff</p>
                                </div>

                                <!-- Real-time Clock - Below Title -->
                                <div class="current-time" id="currentTime">00:00:00</div>
                                <div class="date-display">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('l, F d, Y'); ?>
                                </div>
                                <?php if (!empty($message)): ?>
                                    <div class="message-container">
                                        <div class="alert alert-<?php echo $message_type; ?>">
                                            <i class="fas fa-<?php 
                                                echo ($message_type === 'success') ? 'check-circle' : 
                                                     (($message_type === 'warning') ? 'clock' : 
                                                     (($message_type === 'info') ? 'sign-out-alt' : 'exclamation-circle')); 
                                            ?>"></i>
                                            <span><?php echo $message; ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- RFID Input Form -->
                                <form method="POST" action="" id="rfidForm">
                                    <div class="rfid-input-group">
                                        <input 
                                            type="text" 
                                            name="rfid_uid" 
                                            class="rfid-input" 
                                            placeholder="Scan RFID or Enter UID..."
                                            autocomplete="off"
                                            autofocus
                                            id="rfidInput">
                                    </div>
                                </form>

                                <div class="hint-text">
                                    <i class="fas fa-lightbulb"></i> 
                                    Auto-focused - Tap RFID card to scan
                                </div>
                                <div class="hint-text">
                                    <i class="fas fa-hourglass-half"></i> 
                                    Wait 10 minutes before timing out
                                </div>

                                <!-- Statistics -->
                                <div class="stats-grid">
                                    <div class="stat-card">
                                        <div class="stat-label">Timed In Today</div>
                                        <div class="stat-number"><?php echo $total_timed_in; ?></div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-label">Currently In</div>
                                        <div class="stat-number"><?php echo $currently_in; ?></div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-label">Completed</div>
                                        <div class="stat-number"><?php echo $total_timed_out; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Records Section -->
                            <div class="records-section">
                                <div class="records-header">
                                    <i class="fas fa-list"></i> Today's Attendance Records
                                </div>
                                <div class="records-content">
                                    <?php if (count($today_records) > 0): ?>
                                        <?php foreach ($today_records as $record): ?>
                                            <div class="record-item">
                                                <div class="record-name">
                                                    <?php echo htmlspecialchars($record['student_name'] ?? 'Unknown'); ?>
                                                    <br><small style="color: #6b7280; font-weight: normal;">
                                                        <?php echo htmlspecialchars($record['user_type_label'] ?? 'User'); ?>
                                                    </small>
                                                </div>
                                                <div class="record-time">
                                                    <div class="time-label">Time In</div>
                                                    <div class="time-value">
                                                        <?php echo date('h:i A', strtotime($record['time_in'])); ?>
                                                    </div>
                                                </div>
                                                <div class="record-time">
                                                    <div class="time-label">Time Out</div>
                                                    <div class="time-value">
                                                        <?php echo !empty($record['time_out']) ? date('h:i A', strtotime($record['time_out'])) : '—'; ?>
                                                    </div>
                                                </div>
                                            <div class="record-status <?php echo empty($record['time_out']) ? 'status-in' : 'status-out'; ?>">
                                                <?php echo empty($record['time_out']) ? 'IN' : 'OUT'; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-records">
                                            <i class="fas fa-inbox"></i>
                                            <p>No attendance records for today yet.</p>
                                            <p style="font-size: 0.9rem;">Tap your RFID card to record your attendance.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include 'partials/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="static/vendors/js/vendor.bundle.base.js"></script>
    <script src="static/vendors/chart.js/Chart.min.js"></script>
    <script src="static/js/off-canvas.js"></script>
    <script src="static/js/hoverable-collapse.js"></script>
    <script src="static/js/misc.js"></script>

    <script>
        // Real-time clock update
        function updateTime() {
            const now = new Date();
            const hours = String(now.getHours() % 12 || 12).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const period = now.getHours() >= 12 ? 'PM' : 'AM';
            
            document.getElementById('currentTime').textContent = 
                hours + ':' + minutes + ':' + seconds + ' ' + period;
        }
        
        updateTime();
        setInterval(updateTime, 1000);

        // Keep RFID input focused
        const rfidInput = document.getElementById('rfidInput');
        
        rfidInput.addEventListener('blur', function() {
            this.focus();
        });

        // Handle form submission
        const rfidForm = document.getElementById('rfidForm');
        rfidForm.addEventListener('submit', function(e) {
            const value = rfidInput.value.trim();
            if (!value) {
                e.preventDefault();
                rfidInput.focus();
                return false;
            }
            
            // Clear after submission
            setTimeout(() => {
                rfidInput.value = '';
                rfidInput.focus();
            }, 300);
        });

        // Focus on load
        window.addEventListener('load', function() {
            rfidInput.value = '';
            rfidInput.focus();
        });
    </script>
</body>
</html>
