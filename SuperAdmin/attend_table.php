<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require '../includes/session.php';
require '../includes/dbcon.php';

// Initialize variables
$statusMsg = "";
$historicalResult = null;
$dateFrom = '';
$dateTo = '';
$currentDate = date('Y-m-d');

// ==================== HELPER FUNCTIONS ====================
/**
 * Fetch attendance records for a specific date
 * @param object $conn Database connection
 * @param string $date Date in Y-m-d format
 * @return mysqli_result|false
 */
/**
 * Fetch attendance records for a specific date
 * @param object $conn Database connection
 * @param string $date Date in Y-m-d format
 * @return mysqli_result|false
 */
/**
 * Fetch attendance records for a specific date
 * @param object $conn Database connection
 * @param string $date Date in Y-m-d format
 * @return mysqli_result|false
 */
function fetchAttendanceRecords($conn, $startDate, $endDate = null) {
    if (!$endDate) $endDate = $startDate;
    
    $startDate = mysqli_real_escape_string($conn, $startDate);
    $endDate = mysqli_real_escape_string($conn, $endDate);
    
    $query = "
        SELECT * FROM (
            SELECT 
                a.attendance_id, 
                a.attendance_date,
                a.time_in, 
                a.time_out,
                a.status,
                s.first_name, 
                s.last_name, 
                s.address,
                'Student' AS user_type,
                -- Student Specifics
                s.grade,
                s.strand,
                s.year_id,
                y.year_name,
                s.section_id,
                sec.section_name,
                s.course,
                s.student_id,
                -- Employee Specifics (NULL)
                NULL AS role_id,
                NULL AS assigned_course
            FROM lib_attendance a
            INNER JOIN (
                SELECT MAX(attendance_id) as max_id 
                FROM lib_attendance 
                WHERE attendance_date BETWEEN '$startDate' AND '$endDate'
                GROUP BY attendance_date, admission_id
            ) latest ON a.attendance_id = latest.max_id
            INNER JOIN students s ON a.admission_id = s.student_id
            LEFT JOIN year_levels y ON s.year_id = y.year_id
            LEFT JOIN sections sec ON (s.section_id = sec.section_id OR (s.section_id = sec.section_name AND s.year_id = sec.level))
            WHERE a.attendance_date BETWEEN '$startDate' AND '$endDate'
            
            UNION ALL
            
            SELECT 
                a.attendance_id, 
                a.attendance_date,
                a.time_in, 
                a.time_out,
                a.status,
                e.firstname AS first_name, 
                e.lastname AS last_name, 
                NULL AS address,
                'Employee' AS user_type,
                -- Student Specifics (NULL)
                NULL AS grade,
                NULL AS strand,
                NULL AS year_id,
                NULL AS year_name,
                NULL AS section_id,
                NULL AS section_name,
                NULL AS course,
                e.employee_id AS student_id,
                -- Employee Specifics
                e.role_id AS role_id,
                e.assigned_course
            FROM lib_attendance a
            INNER JOIN (
                SELECT MAX(attendance_id) as max_id 
                FROM lib_attendance 
                WHERE attendance_date BETWEEN '$startDate' AND '$endDate'
                GROUP BY attendance_date, admission_id
            ) latest ON a.attendance_id = latest.max_id
            INNER JOIN employees e ON a.admission_id = e.employee_id
            WHERE a.attendance_date BETWEEN '$startDate' AND '$endDate'
        ) AS combined
        ORDER BY attendance_date DESC, attendance_id DESC
    ";
    
    return mysqli_query($conn, $query);
}

/**
 * Get formatted user type label
 * @param array $row Record from database
 * @return string User type label
 */
function getUserTypeLabel($row) {
    if ($row['user_type'] === 'Student') {
        return '<span class="badge bg-info text-white">Student</span>';
    }
    // For employees, show their specific role if available
    $role = !empty($row['role_id']) ? htmlspecialchars($row['role_id']) : 'Employee';
    return '<span class="badge bg-primary">' . $role . '</span>';
}

/**
 * Get formatted grade/year/role
 * @param array $row Record from database
 * @return string Grade or Year or Role
 */
function getGradeYear($row) {
    if ($row['user_type'] === 'Student') {
        // Elementary / JHS / SHS: Show Grade
        if (!empty($row['grade'])) {
            $g = htmlspecialchars($row['grade']);
            return (is_numeric($g)) ? 'Grade ' . $g : $g;
        }
        // College: Show Year Level
        if (!empty($row['year_name'])) {
            return htmlspecialchars($row['year_name']);
        }
        // Fallback to year_id mapping if year_name is missing
        if (!empty($row['year_id']) && is_numeric($row['year_id'])) {
            $yearMap = [
                '1' => '1st Year',
                '2' => '2nd Year',
                '3' => '3rd Year',
                '4' => '4th Year'
            ];
            return isset($yearMap[$row['year_id']]) ? $yearMap[$row['year_id']] : 'Year ' . htmlspecialchars($row['year_id']);
        }
        return 'N/A';
    }
    // Employee: Show Role ID as requested
    return !empty($row['role_id']) ? htmlspecialchars($row['role_id']) : 'Staff';
}

/**
 * Get formatted section/course/department
 * @param array $row Record from database
 * @return string Section or Course
 */
function getSectionCourse($row) {
    if ($row['user_type'] === 'Student') {
        $details = [];
        
        // Priority 1: Strand (High School/SHS)
        if (!empty($row['strand'])) {
            $details[] = htmlspecialchars($row['strand']);
        }
        
        // Priority 2: Course (College)
        if (!empty($row['course']) && empty($row['strand'])) {
            $details[] = htmlspecialchars($row['course']);
        }
        
        // Priority 3: Section Name (from JOIN or direct string)
        $sec = "";
        if (!empty($row['section_name'])) {
            $sec = htmlspecialchars($row['section_name']);
        } elseif (!empty($row['section_id'])) {
            $sec = htmlspecialchars($row['section_id']);
        }

        if (!empty($sec)) {
            // If it's just a short code, prefix it
            if (strlen($sec) <= 3 && is_numeric($sec) === false) {
                $details[] = 'Sec ' . $sec;
            } else {
                $details[] = $sec;
            }
        }
        
        return !empty($details) ? implode(' - ', $details) : '-';
    }
    // Employee: Show Department
    return !empty($row['assigned_course']) ? htmlspecialchars($row['assigned_course']) : '-';
}


/**
 * Get status badge HTML
 * @param string $status Status from database
 * @return string HTML badge
 */
function getStatusBadge($status) {
    $statusLower = strtolower($status ?? '');
    
    switch ($statusLower) {
        case 'present':
            return '<span class="badge bg-success badge-status">Active</span>';
        case 'late':
            return '<span class="badge bg-warning badge-status">Late</span>';
        case 'absent':
            return '<span class="badge bg-danger badge-status">Absent</span>';
        default:
            return '<span class="badge bg-secondary badge-status">' . htmlspecialchars($status ?? 'N/A') . '</span>';
    }
}

// ==================== HANDLE REPORT REQUEST ====================
if (isset($_REQUEST['report_date']) && !empty($_REQUEST['report_date'])) {
    $dateFrom = $_REQUEST['report_date'];
    $dateTo = isset($_REQUEST['report_date_to']) && !empty($_REQUEST['report_date_to']) ? $_REQUEST['report_date_to'] : $dateFrom;
    
    // Validate date formats
    $df = DateTime::createFromFormat('Y-m-d', $dateFrom);
    $dt = DateTime::createFromFormat('Y-m-d', $dateTo);
    
    if ($df && $dt) {
        $historicalResult = fetchAttendanceRecords($conn, $dateFrom, $dateTo);
        if (!$historicalResult) {
            $statusMsg = '<div class="alert alert-danger">❌ Error fetching records. Please try again.</div>';
        }
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ Invalid date range provided.</div>';
    }
}

// ==================== FETCH TODAY'S ATTENDANCE ====================
$attendanceResult = fetchAttendanceRecords($conn, $currentDate);
if (!$attendanceResult) {
    $statusMsg = '<div class="alert alert-danger">❌ Error loading attendance records.</div>';
}

// Handle AJAX Request for live table update
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    if ($attendanceResult && mysqli_num_rows($attendanceResult) > 0) {
        while ($row = mysqli_fetch_assoc($attendanceResult)) {
            $fullname = htmlspecialchars($row['first_name'] . " " . $row['last_name']);
            $address = htmlspecialchars($row['address'] ?? '-');
            $userType = getUserTypeLabel($row);
            $gradeYear = getGradeYear($row);
            $sectionCourse = getSectionCourse($row);
            $statusBadge = getStatusBadge($row['status']);
            $timeIn = !empty($row['time_in']) ? date("g:i:s A", strtotime($row['time_in'])) : 'N/A';
            $timeOut = !empty($row['time_out']) ? date("g:i:s A", strtotime($row['time_out'])) : 'N/A';
            
            echo "<tr>
                    <td><strong>{$fullname}</strong></td>
                    <td>{$gradeYear}</td>
                    <td>{$sectionCourse}</td>
                    <td>{$address}</td>
                    <td>{$userType}</td>
                    <td>{$statusBadge}</td>
                    <td>{$timeIn}</td>
                    <td>{$timeOut}</td>
                  </tr>";
        }
    } else {
        echo '<tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-inbox"></i></div><p><strong>No attendance records for today.</strong></p></div></td></tr>';
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include "partials/head.php";?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* == PROFESSIONAL STYLING == */
    
    :root {
      --primary: #007bff;
      --success: #28a745;
      --warning: #ffc107;
      --danger: #dc3545;
      --secondary: #6c757d;
      --light-bg: #f8f9fa;
      --border-color: #dee2e6;
    }

    body {
      background-color: var(--light-bg);
    }

    /* Card Styling */
    .card {
      border: 1px solid var(--border-color);
      border-radius: 0.5rem;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      transition: box-shadow 0.3s ease;
    }

    .card:hover {
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .card-body {
      padding: 1.5rem;
    }

    .card-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #212529;
      margin-bottom: 1rem;
    }

    .card-description {
      font-size: 0.875rem;
      color: #6c757d;
      margin-bottom: 1.5rem;
    }

    /* Table Styling */
    .table {
      margin-bottom: 0;
    }

    .table thead th {
      background-color: var(--light-bg);
      font-weight: 600;
      border-bottom: 2px solid var(--border-color);
      padding: 1rem;
      color: #495057;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
    }

    .table tbody td {
      padding: 1rem;
      vertical-align: middle;
      border-top: 1px solid var(--border-color);
    }

    .table tbody tr {
      transition: background-color 0.2s ease;
    }

    .table tbody tr:hover {
      background-color: rgba(0, 123, 255, 0.05);
    }

    /* Badge Styling */
    .badge-status {
      font-size: 0.75rem;
      padding: 0.5rem 0.75rem;
      border-radius: 0.375rem;
      font-weight: 500;
      display: inline-block;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .badge.bg-success { background-color: #28a745 !important; }
    .badge.bg-warning { background-color: #ffc107 !important; color: #000 !important; }
    .badge.bg-danger { background-color: #dc3545 !important; }
    .badge.bg-secondary { background-color: #6c757d !important; }

    /* Form Controls */
    .report-controls {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }

    .report-controls .form-group {
      margin-bottom: 0;
      flex: 0 1 auto;
    }

    .form-control {
      border: 1px solid var(--border-color);
      border-radius: 0.375rem;
      padding: 0.625rem 0.875rem;
      font-size: 0.875rem;
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    /* Button Styling */
    .btn {
      padding: 0.625rem 1.25rem;
      font-size: 0.875rem;
      font-weight: 500;
      border-radius: 0.375rem;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: #0056b3;
      transform: translateY(-1px);
      box-shadow: 0 0.5rem 1rem rgba(0, 123, 255, 0.3);
    }

    .btn-success {
      background-color: var(--success);
      color: white;
    }

    .btn-success:hover {
      background-color: #218838;
      transform: translateY(-1px);
      box-shadow: 0 0.5rem 1rem rgba(40, 167, 69, 0.3);
    }

    /* Table Responsive */
    .table-responsive {
      border-radius: 0.375rem;
      overflow: hidden;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: #6c757d;
    }

    .empty-state-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
      opacity: 0.5;
    }

    /* Print Media */
    @media print {
      .no-print {
        display: none !important;
      }

      # printableArea {
        width: 100%;
        margin: 0;
        padding: 0;
      }

      .table {
        font-size: 0.875rem;
      }

      .table thead th {
        background-color: #f0f0f0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      body {
        background: white;
      }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .report-controls {
        flex-direction: column;
        align-items: stretch;
      }

      .report-controls .form-group,
      .report-controls .btn {
        width: 100%;
      }

      .table {
        font-size: 0.875rem;
      }

      .table thead th,
      .table tbody td {
        padding: 0.75rem 0.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="container-scroller">
    <?php include "partials/navbar.php";?>
    <div class="container-fluid page-body-wrapper">
      <?php include "partials/settings-panel.php";?>
      <?php include "partials/sidebar.php";?>
      <div class="main-panel">
        <div class="content-wrapper">
          <!-- Status Messages -->
          <?php if ($statusMsg): ?>
            <?php echo $statusMsg; ?>
          <?php endif; ?>

          <!-- ==================== TODAY'S ATTENDANCE ==================== -->
          <div class="row">
            <div class="col-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">
                    <i class="fas fa-calendar-alt"></i> Today's Attendance
                  </h4>
                  <p class="card-description">
                    <?php echo date('l, F j, Y'); ?>
                  </p>

                  <div class="table-responsive">
                    <table id="attendanceTable" class="table table-hover">
                      <thead>
                        <tr>
                          <th><i class="fas fa-user"></i> Full Name</th>
                          <th><i class="fas fa-graduation-cap"></i> Grade/Year</th>
                          <th><i class="fas fa-book"></i> Section/Strand </th>
                          <th><i class="fas fa-map-marker-alt"></i> Address</th>
                          <th><i class="fas fa-id-card"></i> User Type</th>
                          <th><i class="fas fa-check-circle"></i> Status</th>
                          <th><i class="fas fa-sign-in-alt"></i> Time In</th>
                          <th><i class="fas fa-sign-out-alt"></i> Time Out</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($attendanceResult && mysqli_num_rows($attendanceResult) > 0): ?>
                          <?php while ($row = mysqli_fetch_assoc($attendanceResult)): ?>
                            <?php
                              $fullname = htmlspecialchars($row['first_name'] . " " . $row['last_name']);
                              $address = htmlspecialchars($row['address'] ?? '-');
                              $userType = getUserTypeLabel($row);
                              $gradeYear = getGradeYear($row);
                              $sectionCourse = getSectionCourse($row);
                              $statusBadge = getStatusBadge($row['status']);
                              $timeIn = !empty($row['time_in']) ? date("g:i:s A", strtotime($row['time_in'])) : 'N/A';
                              $timeOut = !empty($row['time_out']) ? date("g:i:s A", strtotime($row['time_out'])) : 'N/A';
                            ?>
                            <tr>
                              <td><strong><?php echo $fullname; ?></strong></td>
                              <td><?php echo $gradeYear; ?></td>
                              <td><?php echo $sectionCourse; ?></td>
                              <td><?php echo $address; ?></td>
                              <td><?php echo $userType; ?></td>
                              <td><?php echo $statusBadge; ?></td>
                              <td><?php echo $timeIn; ?></td>
                              <td><?php echo $timeOut; ?></td>
                            </tr>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="8">
                              <div class="empty-state">
                                <div class="empty-state-icon">
                                  <i class="fas fa-inbox"></i>
                                </div>
                                <p><strong>No attendance records for today.</strong></p>
                              </div>
                            </td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ==================== ATTENDANCE HISTORY REPORT ==================== -->
          <div class="row">
            <div class="col-12 grid-margin stretch-card">
              <div class="card" id="historySection">
                <div class="card-body">
                  <h4 class="card-title">
                    <i class="fas fa-history"></i> Attendance History
                  </h4>
                  <p class="card-description">
                    Select a date to generate a detailed attendance report.
                  </p>

                    <form method="POST" action="#historySection" class="report-controls no-print">
                      <div class="row w-100">
                        <div class="col-md-2">
                          <div class="form-group">
                            <label for="report_date" class="form-label">
                              <i class="fas fa-calendar"></i> Date From
                            </label>
                            <input 
                              type="date" 
                              class="form-control" 
                              id="report_date" 
                              name="report_date" 
                              value="<?php echo htmlspecialchars($dateFrom); ?>" 
                              required
                            >
                          </div>
                        </div>
                        <div class="col-md-2">
                          <div class="form-group">
                            <label for="report_date_to" class="form-label">
                              <i class="fas fa-calendar"></i> Date To
                            </label>
                            <input 
                              type="date" 
                              class="form-control" 
                              id="report_date_to" 
                              name="report_date_to" 
                              value="<?php echo htmlspecialchars($dateTo); ?>" 
                              required
                            >
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="form-group">
                            <label for="prepared_by" class="form-label">
                              <i class="fas fa-user-edit"></i> Prepared By
                            </label>
                            <select class="form-control" id="prepared_by" name="prepared_by">
                              <option value="MR. JOHN DEXTER N. GARCIA|Library Technician">MR. JOHN DEXTER N. GARCIA</option>
                              <option value="MS.DIANA ROSE B. RONQUILLO|Library  Staff">MS. DIANA ROSE B. RONQUILLO</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group">
                            <label for="noted_by" class="form-label">
                              <i class="fas fa-user-check"></i> Noted By
                            </label>
                            <select class="form-control" id="noted_by" name="noted_by">
                              <option value=" KURL BRYAN L. DUQUE,LPT|Assistant Librarian">KURL BRYAN L. DUQUE,LPT</option>
                              <option value=" MARITA G. VALERIO,RL,MALIS| Consultant, Library Programs and Services">MARITA G. VALERIO</option>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                          <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Generate
                          </button>
                        </div>
                      </div>
                    </form>

                    <!-- Recent Activity Removed -->

                  <!-- Report Results -->
                  <?php if ($historicalResult): ?>
                    <hr>
                    <div id="printableArea">
                      <div class="no-print" style="margin-bottom: 2rem;">
                        <h4 class="text-center" style="margin-bottom: 0.5rem;">
                          <strong>Attendance Report</strong>
                        </h4>
                        <!-- Date Range Removed as requested -->
                      </div>

                      <div class="table-responsive">
                        <table class="table table-bordered">
                          <thead>
                            <tr>
                              <th><i class="fas fa-calendar-alt"></i> Date</th>
                              <th><i class="fas fa-user"></i> Full Name</th>
                              <th><i class="fas fa-graduation-cap"></i> Grade/Year</th>
                              <th><i class="fas fa-book"></i> Section/Strand</th>
                              <th><i class="fas fa-map-marker-alt"></i> Address</th>
                              <th><i class="fas fa-id-card"></i> User Type</th>
                              <th><i class="fas fa-check-circle"></i> Status</th>
                              <th><i class="fas fa-sign-in-alt"></i> Time In</th>
                              <th><i class="fas fa-sign-out-alt"></i> Time Out</th>
                            </tr>
                          </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($historicalResult) > 0): ?>
                                                <?php while ($row = mysqli_fetch_assoc($historicalResult)): ?>
                                                    <?php
                                                        $fullname = htmlspecialchars($row['first_name'] . " " . $row['last_name']);
                                                        $gradeYear = getGradeYear($row);
                                                        $sectionCourse = getSectionCourse($row);
                                                        $address = htmlspecialchars($row['address'] ?? '-');
                                                        $userType = getUserTypeLabel($row);
                                                        $statusBadge = getStatusBadge($row['status']);
                                                        $timeIn = !empty($row['time_in']) ? date("g:i:s A", strtotime($row['time_in'])) : 'N/A';
                                                        $timeOut = !empty($row['time_out']) ? date("g:i:s A", strtotime($row['time_out'])) : 'N/A';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(date("M j, Y", strtotime($row['attendance_date']))); ?></td>
                                                        <td><strong><?php echo $fullname; ?></strong></td>
                                                        <td><?php echo $gradeYear; ?></td>
                                                        <td><?php echo $sectionCourse; ?></td>
                                                        <td><?php echo $address; ?></td>
                                                        <td><?php echo $userType; ?></td>
                                                        <td><?php echo $statusBadge; ?></td>
                                                        <td><?php echo $timeIn; ?></td>
                                                        <td><?php echo $timeOut; ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">No attendance records found for the selected date.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                      </div>
                    </div>

                    <button onclick="printReport()" class="btn btn-success mt-3 no-print">
                      <i class="fas fa-print"></i> Print Report
                    </button>
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

  <!-- ==================== SCRIPTS ==================== -->
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <script src="static/vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="static/vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>

  <script>
    $(document).ready(function() {
      // Function to initialize DataTable safely
      function initDataTable() {
        if ($.fn.DataTable.isDataTable('#attendanceTable')) {
          $('#attendanceTable').DataTable().destroy();
        }
        return $('#attendanceTable').DataTable({
          pageLength: 10,
          lengthMenu: [5, 10, 25, 50, 100],
          ordering: false,
          searching: true,
          paging: true,
          info: true,
          retrieve: true
        });
      }

      let table = initDataTable();

      // Live Refresh Logic
      function refreshTable() {
        const info = table.page.info();
        if (info.page === 0 && $('.dataTables_filter input').val() === "") {
          $.get(window.location.pathname + '?ajax=1', function(data) {
            if (data.trim().length > 0 && !data.includes('<!DOCTYPE')) {
              table.destroy();
              $('#attendanceTable tbody').html(data);
              table = initDataTable();
            }
          });
        }
      }

      setInterval(refreshTable, 5000);
    });

    function printReport() {
      const preparedBy = document.getElementById('prepared_by') ? document.getElementById('prepared_by').value : 'N/A';
      const notedBy = document.getElementById('noted_by') ? document.getElementById('noted_by').value : 'N/A';
      const printUrl = window.location.pathname;
      
      const printWindow = window.open('', '', 'width=1000,height=800');
      if (!printWindow) {
        alert('Please allow popups to print the report.');
        return;
      }
      
      const content = document.getElementById('printableArea').innerHTML;
      
      printWindow.document.write(`
        <html>
        <head>
          <title></title>
          <style>
            @page { size: auto; margin: 0mm; }
            body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px 40px; color: #333; min-height: 98vh; display: flex; flex-direction: column; box-sizing: border-box; }
            table { width: 100%; border-collapse: collapse; margin-top: 30px; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px 8px; text-align: left; font-size: 11px; }
            th { background-color: #f8f9fa; font-weight: bold; color: #444; }
            td:nth-child(2) { white-space: nowrap; text-transform: uppercase; font-weight: 500; }
            .content-wrapper { flex: 1; }
            .signature-section { margin-top: auto; display: flex; flex-direction: row; justify-content: space-between; padding: 20px 20px 0 20px; page-break-inside: avoid; }
            .signature-box { text-align: left; }
            .signature-box .label { font-size: 15px; font-weight: 500; margin-bottom: 25px; color: #000; }
            .signature-box .name { font-weight: bold; font-size: 16px; color: #000; text-transform: uppercase; }
            .signature-box .title { font-size: 15px; color: #444; }
            @media print {
              .no-print { display: none; }
            }
          </style>
        </head>
        <body>
          <div class="header" style="display: flex; align-items: center; justify-content: center; gap: 40px; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px;">
            <img src="../static/images/src_logo.jpg" style="height: 80px; width: 80px; object-fit: contain;">
            <div style="text-align: center;">
              <h3 style="margin: 0; font-size: 18px; font-weight: bold; color: #000; text-transform: uppercase;">SANTA RITA COLLEGE OF PAMPANGA</h3>
              <p style="margin: 2px 0; font-size: 13px; color: #333;">Carlos Mariano St,.San Jose,Sta.Rita ,Pampanga</p>
              <h2 style="margin: 8px 0 0 0; color: #1a202c; text-transform: uppercase; letter-spacing: 1px; font-size: 22px;">Attendance Report</h2>
            </div>
            <img src="../static/images/library_logo.jpg" style="height: 110px; width: 110px; object-fit: contain;">
          </div>
          <div class="content-wrapper">
            ${content}
          </div>
           <div class="signature-section">
            <div class="signature-box">
              <div class="label">Prepared by:</div>
              <div class="name">${preparedBy.split('|')[0]}</div>
              <div class="title">${preparedBy.split('|')[1] || ''}</div>
            </div>
            <div class="signature-box" style="text-align: left;">
              <div class="label">Noted by:</div>
              <div class="name">${notedBy.split('|')[0]}</div>
              <div class="title">${notedBy.split('|')[1] || ''}</div>
            </div>
          </div>
        </body>
        </html>
      `);
      
      printWindow.document.close();
      
      // Wait for content to load then print
      setTimeout(() => {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
      }, 500);
    }
  </script>
</body>
</html>