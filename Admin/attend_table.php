<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

// Initialize variables
$statusMsg = "";
$historicalResult = null;
$dateFrom = '';
$dateTo = '';
$currentDate = date('Y-m-d');

// ==================== HELPER FUNCTIONS ====================
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
            FROM attendance a
            INNER JOIN (
                SELECT MAX(attendance_id) as max_id 
                FROM attendance 
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
                e.assigned_course AS address,
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
                COALESCE(r.role_name, CAST(e.role_id AS CHAR)) AS role_id,
                e.assigned_course
            FROM attendance a
            INNER JOIN (
                SELECT MAX(attendance_id) as max_id 
                FROM attendance 
                WHERE attendance_date BETWEEN '$startDate' AND '$endDate'
                GROUP BY attendance_date, admission_id
            ) latest ON a.attendance_id = latest.max_id
            INNER JOIN employees e ON a.admission_id = e.employee_id
            LEFT JOIN roles r ON e.role_id = r.role_id
            WHERE a.attendance_date BETWEEN '$startDate' AND '$endDate'
        ) AS combined
        ORDER BY attendance_date DESC, attendance_id DESC
    ";
    
    return mysqli_query($conn, $query);
}

function getUserTypeLabel($row) {
    if ($row['user_type'] === 'Student') {
        return '<span class="badge bg-info text-white">Student</span>';
    }
    $role = !empty($row['role_id']) ? htmlspecialchars($row['role_id']) : 'Employee';
    return '<span class="badge bg-primary text-white">' . $role . '</span>';
}

function getGradeYear($row) {
    if ($row['user_type'] === 'Student') {
        if (!empty($row['grade'])) return 'Grade ' . htmlspecialchars($row['grade']);
        if (!empty($row['year_name'])) return htmlspecialchars($row['year_name']);
        if (!empty($row['year_id'])) {
            $yearMap = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];
            return isset($yearMap[$row['year_id']]) ? $yearMap[$row['year_id']] : 'Year ' . htmlspecialchars($row['year_id']);
        }
        return 'N/A';
    }
    return !empty($row['role_id']) ? htmlspecialchars($row['role_id']) : 'Staff';
}

function getSectionCourse($row) {
    if ($row['user_type'] === 'Student') {
        if (!empty($row['strand'])) return htmlspecialchars($row['strand']);
        $details = [];
        if (!empty($row['course'])) $details[] = htmlspecialchars($row['course']);
        if (!empty($row['section_name'])) {
            $val = htmlspecialchars($row['section_name']);
            $details[] = (strlen($val) <= 2) ? 'Section ' . $val : $val;
        } elseif (!empty($row['section_id'])) {
            $val = htmlspecialchars($row['section_id']);
            $details[] = (strlen($val) <= 2) ? 'Section ' . $val : $val;
        }
        return !empty($details) ? implode(' - ', $details) : '-';
    }
    return !empty($row['assigned_course']) ? htmlspecialchars($row['assigned_course']) : '-';
}

function getStatusBadge($status) {
    $statusLower = strtolower($status ?? '');
    switch ($statusLower) {
        case 'present': return '<span class="badge bg-success badge-status text-white">Active</span>';
        case 'late': return '<span class="badge bg-warning badge-status text-dark">Late</span>';
        case 'absent': return '<span class="badge bg-danger badge-status text-white">Absent</span>';
        default: return '<span class="badge bg-secondary badge-status text-white">' . htmlspecialchars($status ?? 'N/A') . '</span>';
    }
}

// ==================== HANDLE REPORT REQUEST ====================
if (isset($_REQUEST['report_date']) && !empty($_REQUEST['report_date'])) {
    $dateFrom = $_REQUEST['report_date'];
    $dateTo = isset($_REQUEST['report_date_to']) && !empty($_REQUEST['report_date_to']) ? $_REQUEST['report_date_to'] : $dateFrom;
    
    $df = DateTime::createFromFormat('Y-m-d', $dateFrom);
    $dt = DateTime::createFromFormat('Y-m-d', $dateTo);
    
    if ($df && $dt) {
        $historicalResult = fetchAttendanceRecords($conn, $dateFrom, $dateTo);
    } else {
        $statusMsg = '<div class="alert alert-danger shadow-sm border-0 border-start border-danger border-4">❌ Invalid date range provided.</div>';
    }
}

// ==================== FETCH TODAY'S ATTENDANCE ====================
$attendanceResult = fetchAttendanceRecords($conn, $currentDate);

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
    :root {
      --primary: #4B49AC;
      --success: #2ecc71;
      --warning: #f1c40f;
      --danger: #e74c3c;
      --secondary: #6c757d;
      --light-bg: #f5f7ff;
      --border-color: #ebeef5;
    }

    body { background-color: var(--light-bg); color: #2c3e50; }
    .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    .card-title { font-weight: 700; color: #1a202c; display: flex; align-items: center; gap: 0.5rem; }
    
    .table thead th {
      background-color: #f8fafc;
      color: #4b5563;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      border-bottom: 2px solid var(--border-color);
    }
    .table tbody td { padding: 1rem; border-bottom: 1px solid var(--border-color); }
    .report-controls { background: #fff; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
    .btn { border-radius: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.025em; padding: 0.75rem 1.5rem; }
    .signature-section { margin-top: 80px; display: flex; justify-content: space-between; padding: 0 50px; }
    .signature-box { text-align: center; width: 250px; }
    .signature-line { border-top: 1.5px solid #000; margin-top: 0px; padding-top: 5px; font-weight: bold; font-size: 0.9rem; }
    @media print { .no-print { display: none !important; } .card { box-shadow: none !important; } }
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
          <?php if ($statusMsg) echo $statusMsg; ?>

          <div class="row">
            <div class="col-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title"><i class="fas fa-calendar-day text-primary"></i> Today's Attendance Monitoring</h4>
                  <p class="text-muted small mb-4"><?php echo date('l, F j, Y'); ?></p>

                  <div class="table-responsive">
                    <table id="attendanceTable" class="table table-hover">
                      <thead>
                        <tr>
                          <th>Full Name</th>
                          <th>Grade/Year</th>
                          <th>Section/Strand</th>
                          <th>Address/Course</th>
                          <th>User Type</th>
                          <th>Status</th>
                          <th>Time In</th>
                          <th>Time Out</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($attendanceResult && mysqli_num_rows($attendanceResult) > 0): ?>
                          <?php while ($row = mysqli_fetch_assoc($attendanceResult)): ?>
                            <tr>
                              <td><strong><?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></strong></td>
                              <td><?php echo getGradeYear($row); ?></td>
                              <td><?php echo getSectionCourse($row); ?></td>
                              <td><?php echo htmlspecialchars($row['address'] ?? '-'); ?></td>
                              <td><?php echo getUserTypeLabel($row); ?></td>
                              <td><?php echo getStatusBadge($row['status']); ?></td>
                              <td><?php echo !empty($row['time_in']) ? date("g:i:s A", strtotime($row['time_in'])) : 'N/A'; ?></td>
                              <td><?php echo !empty($row['time_out']) ? date("g:i:s A", strtotime($row['time_out'])) : 'N/A'; ?></td>
                            </tr>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <tr><td colspan="8" class="text-center py-5"><div class="text-muted"><i class="fas fa-inbox fa-3x mb-3"></i><p>No records found for today.</p></div></td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- History Section -->
          <div class="row" id="historySection">
            <div class="col-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title mb-4"><i class="fas fa-history text-primary"></i> Historical Reports</h4>
                  
                  <form method="POST" action="#historySection" class="report-controls no-print">
                    <div class="row w-100">
                      <div class="col-md-2">
                        <label class="small fw-bold">Date From</label>
                        <input type="date" class="form-control" name="report_date" value="<?php echo htmlspecialchars($dateFrom); ?>" required>
                      </div>
                      <div class="col-md-2">
                        <label class="small fw-bold">Date To</label>
                        <input type="date" class="form-control" name="report_date_to" value="<?php echo htmlspecialchars($dateTo); ?>" required>
                      </div>
                      <div class="col-md-3">
                        <label class="small fw-bold">Prepared By</label>
                        <select class="form-control" id="prepared_by">
                          <option value="MR. JOHN DEXTER N. GARCIA|Library Technician">MR. JOHN DEXTER N. GARCIA</option>
                          <option value="MS.DIANA ROSE B. RONQUILLO|Library Staff">MS. DIANA ROSE B. RONQUILLO</option>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="small fw-bold">Noted By</label>
                        <select class="form-control" id="noted_by">
                          <option value=" KURL BRYAN L. DUQUE,LPT|Assistant Librarian">KURL BRYAN L. DUQUE,LPT</option>
                          <option value=" MARITA G. VALERIO,RL,MALIS| Consultant, Library Programs and Services">MARITA G. VALERIO</option>
                        </select>
                      </div>
                      <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Generate</button>
                      </div>
                    </div>
                  </form>

                  <!-- Recent Activity Removed -->

                  <?php if ($historicalResult): ?>
                    <div id="printableArea">
                      <div class="text-center mb-5 no-print">
                        <h3 class="fw-bold m-0">Attendance Report</h3>
                        <!-- Date Range Removed as requested -->
                      </div>
                      <div class="table-responsive">
                        <table class="table table-bordered">
                          <thead>
                            <tr>
                              <th>Date</th>
                              <th>Full Name</th>
                              <th>Grade/Year</th>
                              <th>Section/Strand</th>
                              <th>Address/Course</th>
                              <th>User Type</th>
                              <th>Status</th>
                              <th>Time In</th>
                              <th>Time Out</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php while ($row = mysqli_fetch_assoc($historicalResult)): ?>
                              <tr>
                                <td><?php echo htmlspecialchars(date("M j, Y", strtotime($row['attendance_date']))); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></strong></td>
                                <td><?php echo getGradeYear($row); ?></td>
                                <td><?php echo getSectionCourse($row); ?></td>
                                <td><?php echo htmlspecialchars($row['address'] ?? '-'); ?></td>
                                <td><?php echo getUserTypeLabel($row); ?></td>
                                <td><?php echo getStatusBadge($row['status']); ?></td>
                                <td><?php echo !empty($row['time_in']) ? date("g:i:s A", strtotime($row['time_in'])) : 'N/A'; ?></td>
                                <td><?php echo !empty($row['time_out']) ? date("g:i:s A", strtotime($row['time_out'])) : 'N/A'; ?></td>
                              </tr>
                            <?php endwhile; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <button onclick="printReport()" class="btn btn-success mt-4 no-print"><i class="fas fa-print me-2"></i> Print Report</button>
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
        // Only refresh if we are not currently searching or on a different page to avoid jumping
        const info = table.page.info();
        if (info.page === 0 && $('.dataTables_filter input').val() === "") {
          $.get(window.location.pathname + '?ajax=1', function(data) {
            if (data.trim().length > 0 && !data.includes('<!DOCTYPE')) {
              // Destroy table before updating HTML to prevent UI duplication
              table.destroy();
              $('#attendanceTable tbody').html(data);
              // Re-initialize
              table = initDataTable();
            }
          });
        }
      }

      setInterval(refreshTable, 5000);
    });

    function printReport() {
      const printWindow = window.open('', '', 'width=1000,height=600');
      const preparedBy = document.getElementById('prepared_by').value;
      const notedBy = document.getElementById('noted_by').value;
      const content = document.getElementById('printableArea').innerHTML;
      
      printWindow.document.write(`
        <html><head><title></title>
        <style>
          @page { size: auto; margin: 0mm; }
          body { font-family: sans-serif; padding: 20px 40px; color: #333; min-height: 98vh; display: flex; flex-direction: column; box-sizing: border-box; }
          table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 20px; }
          th, td { border: 1px solid #ddd; padding: 10px 8px; text-align: left; font-size: 11px; }
          th { background: #f8f9fa; font-weight: bold; }
          td:nth-child(2) { white-space: nowrap; text-transform: uppercase; font-weight: 500; }
          .content-wrapper { flex: 1; }
          .signature-section { margin-top: auto; display: flex; flex-direction: row; justify-content: space-between; padding: 20px 20px 0 20px; page-break-inside: avoid; }
          .signature-box { text-align: left; }
          .signature-box .label { font-size: 15px; font-weight: 500; margin-bottom: 25px; color: #000; }
          .signature-box .name { font-weight: bold; font-size: 16px; color: #000; text-transform: uppercase; }
          .signature-box .title { font-size: 15px; color: #444; }
          @media print { .no-print { display: none; } }
        </style></head>
        <body>
          <div class="header" style="display: flex; align-items: center; justify-content: center; gap: 40px; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px;">
            <img src="../static/images/src_logo.jpg" style="height: 80px; width: 80px; object-fit: contain;">
            <div style="text-align: center;">
              <h3 style="margin: 0; font-size: 18px; font-weight: bold; color: #000; text-transform: uppercase; font-family: sans-serif;">SANTA RITA COLLEGE OF PAMPANGA</h3>
              <p style="margin: 2px 0; font-size: 13px; color: #333; font-family: sans-serif;">Carlos Mariano St,.San Jose,Sta.Rita ,Pampanga</p>
              <h2 style="margin: 8px 0 0 0; color: #1a202c; text-transform: uppercase; letter-spacing: 1px; font-size: 22px; font-family: sans-serif;">Attendance Report</h2>
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
        </body></html>`);
      printWindow.document.close();
      setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    }
  </script>
</body>
</html>
