<?php
session_start();
// Include database connection (No session check for kiosk)
include 'includes/dbcon.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set Philippines timezone (UTC+8)
date_default_timezone_set('Asia/Manila');

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

// Store current student info for display
$current_student = null;
$current_user_type = null;
$user_id = null;
$user_name = null;

// Clear session info if requested (for 5 second timeout)
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    unset($_SESSION['last_scanned_id']);
    unset($_SESSION['last_scanned_name']);
    unset($_SESSION['last_scanned_type']);
    unset($_SESSION['last_scanned_data']);
    unset($_SESSION['last_scanned_msg']);
    unset($_SESSION['last_scanned_msg_type']);
    header("Location: attendance.php");
    exit();
}

// Handle RFID Card Scan / UID Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_uid'])) {
    $rfid_uid = mysqli_real_escape_string($conn, trim($_POST['rfid_uid']));
    
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
            if ($student_found) {
                $student = @mysqli_fetch_assoc($student_result);
                $user_id = mysqli_real_escape_string($conn, $student['student_id']);
                // Re-query for fresh data with joins
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
            
            // Check for active record in attendance table
            $check_query = "SELECT a.*, TIMESTAMPDIFF(SECOND, a.time_in, NOW()) as seconds_elapsed
                           FROM $attendanceTable a
                           WHERE a.admission_id = '$user_id' 
                           AND a.attendance_date = '$current_date' 
                           AND a.time_out IS NULL
                           ORDER BY a.attendance_id DESC LIMIT 1";
            $check_result = @mysqli_query($conn, $check_query);
            $has_active_record = ($check_result && @mysqli_num_rows($check_result) > 0);
            
            if (!$has_active_record) {
                $insert_query = "INSERT INTO $attendanceTable (admission_id, attendance_date, time_in, status) 
                                VALUES ('$user_id', '$current_date', '$current_time', 'Present')";
                if (@mysqli_query($conn, $insert_query)) {
                    $time_in_12h = date('h:i A', strtotime($current_time));
                    $message = "✓ {$user_name} - TIME IN at {$time_in_12h} {$sched_msg}";
                    $message_type = 'success';
                }
            } else {
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
                    }
                }
            }

            // Save to Session for persistence on page reload/clear
            $_SESSION['last_scanned_id'] = $user_id;
            $_SESSION['last_scanned_name'] = $user_name;
            $_SESSION['last_scanned_type'] = $current_user_type;
            $_SESSION['last_scanned_data'] = $current_student;
            $_SESSION['last_scanned_msg'] = $message;
            $_SESSION['last_scanned_msg_type'] = $message_type;
        }
    }
}

// persistence: Load from session if not a fresh scan
if (!$current_student && isset($_SESSION['last_scanned_id'])) {
    $user_id = $_SESSION['last_scanned_id'];
    $user_name = $_SESSION['last_scanned_name'];
    $current_user_type = $_SESSION['last_scanned_type'];
    $current_student = $_SESSION['last_scanned_data'];
    // Optional: Only keep message if it's the very first load after scan? 
    // Actually, user wants it to stick, so we stick.
    $message = $_SESSION['last_scanned_msg'];
    $message_type = $_SESSION['last_scanned_msg_type'];
}

// Fetch today's records
$today_records_query = "
    SELECT a.*, CONCAT(s.first_name, ' ', s.last_name) as student_name, 'Student' as user_type_label
    FROM $attendanceTable a
    INNER JOIN (
        SELECT admission_id, MAX(attendance_id) as max_id FROM $attendanceTable WHERE attendance_date = '$current_date' GROUP BY admission_id
    ) latest ON a.admission_id = latest.admission_id AND a.attendance_id = latest.max_id
    LEFT JOIN students s ON a.admission_id = s.student_id
    WHERE s.student_id IS NOT NULL
    
    UNION
    
    SELECT a.*, CONCAT(e.firstname, ' ', e.lastname) as student_name, 'Staff' as user_type_label
    FROM $attendanceTable a
    INNER JOIN (
        SELECT admission_id, MAX(attendance_id) as max_id FROM $attendanceTable WHERE attendance_date = '$current_date' GROUP BY admission_id
    ) latest ON a.admission_id = latest.admission_id AND a.attendance_id = latest.max_id
    LEFT JOIN employees e ON a.admission_id = e.employee_id
    WHERE e.employee_id IS NOT NULL
    
    ORDER BY attendance_id DESC
";
$today_records_result = mysqli_query($conn, $today_records_query);
$today_records = [];
if ($today_records_result) { while ($row = mysqli_fetch_assoc($today_records_result)) { $today_records[] = $row; } }

$total_timed_in = count($today_records);
$total_timed_out = 0; $currently_in = 0;
foreach ($today_records as $record) { if (!empty($record['time_out'])) $total_timed_out++; else $currently_in++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Library Attendance Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="static/css/dark-mode.css">
    <script>
      (function() {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark') {
          document.documentElement.classList.add('dark-mode');
          document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('dark-mode');
          });
        }
      })();
    </script>
    <style>
        :root { 
            --primary: #4f46e5; 
            --primary-dark: #4338ca; 
            --secondary: #64748b;
            --light-bg: #f1f5f9; 
            --white: #ffffff; 
            --text-dark: #1e293b;
            --text-gray: #64748b; 
            --success-green: #10b981; 
            --warning-orange: #f59e0b; 
            --danger-red: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f1f5f9; padding: 2rem; min-height: 100vh; color: var(--text-dark); }
        
        .attendance-wrapper { 
            max-width: 1300px; 
            margin: 0 auto; 
            display: grid; 
            grid-template-columns: 420px 1fr; 
            gap: 2rem; 
            align-items: start;
        }
        
        /* Left Panel - Student Info */
        .left-panel .current-student-card { 
            background: var(--white); 
            border-radius: 16px; 
            overflow: hidden; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 2rem;
        }
        .student-card-header { 
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%); 
            color: var(--white); 
            padding: 1.5rem; 
            font-weight: 700; 
            text-align: center;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        .profile-section { padding: 2.5rem 1.5rem 1.5rem; text-align: center; }
        .profile-pic-container { 
            width: 160px; 
            height: 160px; 
            margin: 0 auto; 
            border-radius: 50%; 
            border: 5px solid var(--white); 
            box-shadow: 0 0 20px rgba(79, 70, 229, 0.3);
            overflow: hidden; 
            background: #f8fafc;
        }
        .profile-pic-container img { width: 100%; height: 100%; object-fit: cover; }
        
        .info-grid { padding: 0 1.5rem 2rem; display: flex; flex-direction: column; gap: 1rem; }
        .info-box { 
            background: #f8fafc; 
            padding: 1rem; 
            border-radius: 8px; 
            text-align: center; 
            border: 1px solid #e2e8f0;
        }
        .info-label { font-size: 0.75rem; font-weight: 600; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
        .info-value { font-size: 1rem; font-weight: 700; color: var(--text-dark); }
        
        /* Right Panel - Scanner & Logs */
        .right-panel { display: flex; flex-direction: column; gap: 2rem; }
        
        .scanner-card { 
            background: var(--white); 
            border-radius: 16px; 
            padding: 3rem 2rem; 
            text-align: center; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .clock-display { font-size: 4rem; font-weight: 800; color: var(--primary); font-variant-numeric: tabular-nums; line-height: 1; margin-bottom: 0.5rem; }
        .date-display { color: var(--text-gray); font-size: 1.1rem; margin-bottom: 2.5rem; font-weight: 500; }
        
        .rfid-input-field { 
            width: 100%; 
            max-width: 500px;
            height: 64px; 
            border-radius: 12px; 
            border: 2px solid #e2e8f0; 
            text-align: center; 
            font-size: 1.25rem; 
            font-weight: 600; 
            color: var(--primary); 
            background: #f8fafc; 
            transition: all 0.2s;
            margin: 1rem auto;
            display: block;
        }
        .rfid-input-field:focus { outline: none; border-color: var(--primary); background: var(--white); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 1.5rem; 
            margin-top: 3rem; 
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .stat-item { 
            background: #f8fafc; 
            border-radius: 12px; 
            padding: 1.5rem; 
            border: 1px solid #e2e8f0;
        }
        .stat-count { display: block; font-size: 2.5rem; font-weight: 800; color: var(--primary); line-height: 1; margin-top: 0.5rem; }
        .stat-label { font-size: 0.85rem; color: var(--text-gray); font-weight: 600; text-transform: uppercase; }
        
        /* Records Table */
        .records-wrapper { 
            background: var(--white); 
            border-radius: 16px; 
            overflow: hidden; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .records-header { 
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%); 
            color: var(--white); 
            padding: 1.25rem 2rem; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 1rem;
            font-size: 1.1rem;
        }
        .record-list { max-height: 500px; overflow-y: auto; }
        .record-item { 
            display: grid; 
            grid-template-columns: 2fr 1fr 1fr auto; 
            align-items: center; 
            padding: 1.25rem 2rem; 
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.1s;
        }
        .record-item:hover { background: #f8fafc; }
        
        .rec-name { font-weight: 700; color: var(--text-dark); font-size: 1.05rem; }
        .rec-role { font-size: 0.85rem; color: var(--text-gray); margin-top: 2px; }
        
        .rec-time { font-family: 'Courier New', monospace; font-weight: 600; color: var(--text-dark); }
        .rec-time label { display: block; font-size: 0.65rem; color: var(--text-gray); text-transform: uppercase; font-family: sans-serif; margin-bottom: 2px; }
        
        .status-badge { 
            padding: 0.35rem 1rem; 
            border-radius: 9999px; 
            font-size: 0.75rem; 
            font-weight: 700; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-in { background: #dcfce7; color: #166534; }
        .status-out { background: #fee2e2; color: #991b1b; }
        
        .alert-box { 
            padding: 1rem; 
            border-radius: 8px; 
            margin-bottom: 1.5rem; 
            font-weight: 500; 
            display: flex; 
            align-items: center; 
            gap: 0.75rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert-info { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
        
        /* Placeholder */
        .ready-to-scan { 
            text-align: center; 
            padding: 5rem 2rem; 
            color: var(--text-gray);
            border: 3px dashed #cbd5e1;
            border-radius: 20px;
            margin: 0;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }
        .ready-icon { font-size: 4rem; color: #cbd5e1; margin-bottom: 1.5rem; display: block; }
        
        @media (max-width: 1024px) {
            .attendance-wrapper { grid-template-columns: 1fr; max-width: 700px; }
            .left-panel .current-student-card { position: static; margin-bottom: 2rem; }
        }
        @media (max-width: 640px) {
            body { padding: 1rem; }
            .clock-display { font-size: 2.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .record-item { grid-template-columns: 1fr 1fr; gap: 1rem; text-align: center; }
            .record-item > div:first-child { grid-column: span 2; text-align: center; }
        }
    </style>
</head>
<body onload="document.getElementById('rfidInput').focus()">
    <div class="theme-toggle-attendance" style="position: fixed; top: 10px; right: 20px; z-index: 1000;">
        <label class="theme-switch" for="dark-mode-toggle">
            <input type="checkbox" id="dark-mode-toggle">
            <span class="slider round"></span>
        </label>
    </div>
    <div class="attendance-wrapper">
        <div class="left-panel">
            <?php if ($current_student): ?>
                <div class="current-student-card">
                    <div class="student-card-header">
                        <i class="fas fa-user-circle"></i> 
                        <?php 
                            if ($current_user_type === 'Employee') {
                                $role = $current_student['display_role'] ?? 'Staff';
                                if (stripos($role, 'Teaching') !== false && stripos($role, 'Non-Teaching') === false) {
                                    echo 'Teaching Staff';
                                } elseif (stripos($role, 'Non-Teaching') !== false) {
                                    echo 'Non-Teaching Staff';
                                } else {
                                    echo htmlspecialchars($role);
                                }
                            } else {
                                $dept = $current_student['department'] ?? '';
                                if ($dept === 'Elementary') {
                                    echo 'Elementary Student';
                                } elseif ($dept === 'Junior High') {
                                    echo 'Junior High Student (IHS)';
                                } elseif ($dept === 'Senior High') {
                                    echo 'Senior High Student';
                                } else {
                                    echo 'College Student';
                                }
                            }
                        ?>
                    </div>
                    <div class="profile-section">
                        <div class="profile-pic-container">
                            <?php 
                                $profile_image = 'img/defaulticon.png';
                                if ($current_user_type === 'Employee' && !empty($current_student['profile_pic'])) {
                                    $profile_image = $current_student['profile_pic'];
                                } elseif ($current_user_type === 'Student' && !empty($current_student['profile_picture'])) {
                                    $profile_image = $current_student['profile_picture'];
                                }
                                
                                // Fix relative paths
                                if (strpos($profile_image, '../') === 0) {
                                    $profile_image = substr($profile_image, 3);
                                }
                                
                                // Check if file exists in uploads
                                if (!file_exists($profile_image) && file_exists('uploads/' . $profile_image)) {
                                    $profile_image = 'uploads/' . $profile_image;
                                }
                            ?>
                            <img src="<?php echo htmlspecialchars($profile_image); ?>" onerror="this.src='img/defaulticon.png'">
                        </div>
                    </div>
                    <div class="info-grid">
                        <div class="info-box"><div class="info-label"><?php echo $current_user_type === 'Employee' ? 'Employee ID' : 'Student ID'; ?></div><div class="info-value"><?php echo $user_id; ?></div></div>
                        <div class="info-box"><div class="info-label">Full Name</div><div class="info-value"><?php echo $user_name; ?></div></div>
                        
                        <?php if ($current_user_type === 'Employee'): ?>
                            <div class="info-box"><div class="info-label">Role</div><div class="info-value" style="color:var(--primary)"><?php echo htmlspecialchars($current_student['display_role'] ?? 'Staff'); ?></div></div>
                            <div class="info-box"><div class="info-label">Department</div><div class="info-value" style="color:#8b5cf6"><?php echo htmlspecialchars($current_student['assigned_course'] ?? 'N/A'); ?></div></div>
                        <?php else: ?>
                            <?php 
                                $dept = $current_student['department'] ?? '';
                                $isLower = in_array($dept, ['Elementary', 'Junior High', 'Senior High']);
                            ?>
                            <?php if ($isLower): ?>
                                <div class="info-box"><div class="info-label">Grade</div><div class="info-value"><span style="background:var(--success-green); color:white; padding: 0.2rem 0.6rem; border-radius:4px;"><?php echo (is_numeric($current_student['grade']) ? 'Grade ' : '') . htmlspecialchars($current_student['grade']); ?></span></div></div>
                                <div class="info-box"><div class="info-label">Section</div><div class="info-value" style="color:#8b5cf6"><?php echo htmlspecialchars($current_student['section_name'] ?? $current_student['section_id'] ?? 'N/A'); ?></div></div>
                                <?php if ($dept === 'Senior High'): ?>
                                    <div class="info-box"><div class="info-label">Strand</div><div class="info-value"><span style="background:var(--warning-orange); color:white; padding: 0.2rem 0.6rem; border-radius:4px;"><?php echo htmlspecialchars($current_student['strand'] ?? 'N/A'); ?></span></div></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="info-box"><div class="info-label">Year</div><div class="info-value" style="color:var(--primary)"><?php echo htmlspecialchars($current_student['year_name'] ?? 'N/A'); ?></div></div>
                                <div class="info-box"><div class="info-label">Section</div><div class="info-value" style="color:#8b5cf6"><?php echo htmlspecialchars($current_student['section_name'] ?? 'N/A'); ?></div></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="ready-to-scan">
                    <i class="fas fa-id-card-alt ready-icon"></i>
                    <h2 class="ready-title">Ready to Scan</h2>
                    <p class="ready-text">Tap your RFID card on the reader to view profile and record attendance.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="right-panel">
            <div class="scanner-card">
                <h2 style="color:var(--primary); font-weight:800; display:flex; justify-content:center; align-items:center; gap:0.5rem;"><i class="fas fa-fingerprint"></i> Library Attendance Monitoring</h2>
                <p style="color:var(--text-gray); font-size:0.85rem; margin-bottom:1.5rem;">Tap RFID card - Students & Staff</p>
                <div class="clock-display" id="clock">00:00:00 AM</div>
                <div class="date-display" id="date">Loading...</div>
                <?php if ($message): ?>
                    <div class="alert-box alert-<?php echo $message_type; ?>"><i class="fas <?php echo ($message_type === 'warning') ? 'fa-clock' : 'fa-check-circle'; ?> fa-lg"></i><span><?php echo $message; ?></span></div>
                <?php endif; ?>
                <form method="POST" action=""><input type="text" name="rfid_uid" id="rfidInput" class="rfid-input-field" placeholder="Scan RFID or Enter UID..." onblur="this.focus()" autocomplete="off"></form>
                <div style="margin-top:1.5rem; color:var(--text-gray); font-size:0.8rem; font-weight:600;"><p><i class="fas fa-lightbulb"></i> Auto-focused • <i class="fas fa-hourglass-half"></i> 10 min interval</p></div>
                <div class="stats-grid">
                    <div class="stat-item"><div style="font-size:0.75rem;">Timed In Today</div><div class="stat-count"><?php echo $total_timed_in; ?></div></div>
                    <div class="stat-item"><div style="font-size:0.75rem;">Currently In</div><div class="stat-count"><?php echo $currently_in; ?></div></div>
                    <div class="stat-item"><div style="font-size:0.75rem;">Completed</div><div class="stat-count"><?php echo $total_timed_out; ?></div></div>
                </div>
            </div>
            <div class="records-wrapper">
                <div class="records-header"><i class="fas fa-list-ul"></i> Today's Library Attendance Monitoring Records</div>
                <div style="max-height:400px; overflow-y:auto; padding:1.5rem;">
                    <?php if (empty($today_records)): ?>
                        <div style="text-align:center; padding:2rem; color:var(--text-gray);">No attendance recorded for today yet.</div>
                    <?php else: ?>
                        <?php foreach($today_records as $record): ?>
                            <div class="record-item">
                                <div><div style="font-weight:700; color:#1e293b;"><?php echo htmlspecialchars($record['student_name']); ?></div><div style="font-size:0.8rem; color:var(--text-gray);"><?php echo $record['user_type_label']; ?></div></div>
                                <div style="text-align:center;"><small style="display:block; font-size:0.65rem; color:var(--text-gray); font-weight:700;">TIME IN</small><span style="font-family:monospace; font-weight:700; color:var(--primary);"><?php echo date('h:i A', strtotime($record['time_in'])); ?></span></div>
                                <div style="text-align:center;"><small style="display:block; font-size:0.65rem; color:var(--text-gray); font-weight:700;">TIME OUT</small><span style="font-family:monospace; font-weight:700; color:var(--primary);"><?php echo !empty($record['time_out']) ? date('h:i A', strtotime($record['time_out'])) : '--:-- --'; ?></span></div>
                                <div><div class="rec-status-badge <?php echo !empty($record['time_out']) ? 'rec-status-out' : 'rec-status-in'; ?>"><?php echo !empty($record['time_out']) ? 'OUT' : 'IN'; ?></div></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer style="margin-top: 5rem; padding: 4rem 0; background: #ffffff; border-top: 1px solid #e2e8f0; color: #334155; position: relative;">
        <!-- Delicate gradient accent -->
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #6366f1, #8b5cf6);"></div>
        
        <div class="footer-content" style="max-width: 1300px; margin: 0 auto; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 3rem;">
            <!-- Brand Section -->
            <div style="display: flex; align-items: center; gap: 2rem;">
                <div style="background: #ffffff; padding: 12px; border-radius: 16px; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
                    <img src="img/srclogo.png" alt="SRC Logo" style="width: 65px; height: auto;">
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 1.4rem; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">Santa Rita College of Pampanga</h4>
                    <p style="margin: 0.3rem 0; font-size: 0.95rem; color: #64748b; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-map-marker-alt" style="color: #6366f1;"></i> San Jose, Santa Rita, Pampanga
                    </p>
                    <div style="margin-top: 0.5rem; font-size: 0.8rem; background: #f1f5f9; color: #475569; display: inline-block; padding: 4px 12px; border-radius: 20px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                        Dev: Val Nerie O. Espeleta
                    </div>
                </div>
            </div>

            <!-- Social & Status Section -->
            <div style="display: flex; align-items: center; gap: 4rem;">
                <div style="text-align: right; border-right: 2px solid #f1f5f9; padding-right: 3rem;">
                    <a href="https://www.facebook.com/valnerie.espeleta" target="_blank" style="display: inline-flex; align-items: center; gap: 0.75rem; background: #1877f2; color: #ffffff; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem; transition: 0.3s; margin-bottom: 0.75rem; box-shadow: 0 4px 15px rgba(24, 119, 242, 0.25);">
                        <i class="fab fa-facebook-f"></i> Follow Official FB
                    </a>
                    <p style="margin: 0; font-size: 0.9rem; color: #94a3b8; font-weight: 500;">&copy; <?php echo date('Y'); ?> <span style="color: #6366f1; font-weight: 700;">SRC</span> Library System</p>
                </div>

                <!-- Status Indicator -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px;">System Status</span>
                    <div style="display: inline-flex; align-items: center; gap: 10px; background: #f0fdf4; padding: 6px 16px; border-radius: 30px; border: 1px solid #dcfce7;">
                        <span style="width: 10px; height: 10px; background: #22c55e; border-radius: 50%; display: inline-block; position: relative;">
                            <span style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #22c55e; border-radius: 50%; animation: pulse 2s infinite; opacity: 0.5;"></span>
                        </span>
                        <span style="color: #15803d; font-size: 0.9rem; font-weight: 700;">Operational</span>
                    </div>
                </div>
            </div>
        </div>

        <style>
            @keyframes pulse {
                0% { transform: scale(1); opacity: 0.5; }
                70% { transform: scale(3); opacity: 0; }
                100% { transform: scale(3); opacity: 0; }
            }
        </style>
    </footer>
    <script>
        function updateTime() {
            const now = new Date();
            document.getElementById('clock').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            document.getElementById('date').innerHTML = '<i class="far fa-calendar-alt"></i> ' + now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        }
        setInterval(updateTime, 1000); updateTime();

        // Theme Toggle Logic
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        if (darkModeToggle) {
            if (localStorage.getItem('theme') === 'dark') {
                darkModeToggle.checked = true;
            }
            darkModeToggle.addEventListener('change', function() {
                if (this.checked) {
                    document.body.classList.add('dark-mode');
                    document.documentElement.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-mode');
                    document.documentElement.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                }
            });
        }

        // Auto-clear student info after 8 seconds (matching SuperAdmin feel)
        <?php if ($current_student): ?>
        setTimeout(function() {
            window.location.href = 'attendance.php?action=clear';
        }, 8000);
        <?php endif; ?>
    </script>
</body>
</html>
