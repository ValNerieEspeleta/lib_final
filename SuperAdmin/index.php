<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../includes/session.php';
include '../includes/dbcon.php';

/* ===========================
   FUNCTIONS TO GET COUNTS
   =========================== */

function getAdminCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM employees";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function getStudentCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM students";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function getBookCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM lib_books";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function getBorrowedBookCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM lib_rfid_loan WHERE status != 'returned'";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <?php include "partials/head.php";?>
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --primary: #667eea;
      --secondary: #764ba2;
      --success: #28a745;
      --warning: #ffc107;
      --danger: #dc3545;
      --light: #f8f9fa;
      --dark: #2d3748;
    }

    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
    }

    .content-wrapper {
      padding: 30px 20px;
    }

    .welcome-section {
      margin-bottom: 40px;
    }

    .welcome-section h3 {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 10px;
    }

    .welcome-section p {
      color: #7c8db0;
      font-size: 1rem;
    }

    /* Modern Card Styling */
    .card-modern {
      border: none;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      transition: all 0.3s ease;
      height: 100%;
      position: relative;
      background: white;
    }

    .card-modern:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .card-modern::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .card-modern .card-body {
      padding: 30px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .card-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      margin-bottom: 20px;
      color: white;
    }

    .card-icon.admin {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .card-icon.student {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .card-icon.book {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .card-icon.borrowed {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .card-label {
      font-size: 0.9rem;
      color: #7c8db0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
      margin-bottom: 15px;
    }

    .card-value {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 10px;
    }

    .card-footer-text {
      font-size: 0.85rem;
      color: #a0aec0;
      margin-top: 10px;
    }

    /* Dashboard Grid */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }

    /* Table Card */
    .table-card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .table-card .card-body {
      padding: 30px;
    }

    .table-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .table-title i {
      color: var(--primary);
    }

    /* Table Styling */
    .table {
      margin-bottom: 0;
    }

    .table thead th {
      background: #f8fafc !important;
      color: #4b5563 !important;
      border-bottom: 1px solid #e2e8f0;
      font-weight: 600;
      padding: 15px;
      text-transform: uppercase;
      font-size: 0.85rem;
      letter-spacing: 0.5px;
    }

    .table tbody td {
      padding: 15px;
      border-bottom: 1px solid #e2e8f0;
      color: #4a5568;
    }

    .table tbody tr {
      transition: background-color 0.3s ease;
    }

    .table tbody tr:hover {
      background-color: #f7fafc;
    }

    .badge {
      padding: 8px 12px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
    }

    .badge-success {
      background-color: #d4edda;
      color: #155724;
    }

    .badge-warning {
      background-color: #fff3cd;
      color: #856404;
    }

    .badge-danger {
      background-color: #f8d7da;
      color: #721c24;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #a0aec0;
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 15px;
      opacity: 0.5;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 768px) {
      .content-wrapper {
        padding: 20px 15px;
      }

      .welcome-section {
        margin-bottom: 25px;
      }

      .welcome-section h3 {
        font-size: 1.8rem;
      }

      .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .card-modern .card-body {
        padding: 20px;
      }

      .card-value {
        font-size: 2rem;
      }

      .table-card .card-body {
        padding: 15px;
      }

      .table thead th, .table tbody td {
        padding: 10px;
        font-size: 0.8rem;
      }
    }

    @media (max-width: 480px) {
      .welcome-section h3 {
        font-size: 1.5rem;
      }
      
      .card-icon {
        width: 50px;
        height: 50px;
        font-size: 22px;
      }
    }
  </style>
</head>
<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.html -->
    <?php include "partials/navbar.php";?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_settings-panel.html -->
      <?php include "partials/settings-panel.php";?>
     
      <!-- partial -->
      <!-- partial:partials/_sidebar.html -->
      <?php include "partials/sidebar.php";?>
      <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="welcome-section d-flex justify-content-between align-items-center flex-wrap">
            <div>
              <h3><i class="fas fa-chart-line"></i> Dashboard</h3>
              <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Super Admin'); ?></strong> - Here's your system overview</p>
            </div>
            <div class="theme-switch-wrapper bg-white p-2 px-3 rounded-pill shadow-sm mb-3" style="border: 1px solid #eee;">
                <span class="mr-2 text-muted font-weight-bold" style="font-size: 0.8rem;"><i class="fas fa-moon"></i> Dark Mode</span>
                <label class="theme-switch mb-0" for="dashboard-dark-mode">
                  <input type="checkbox" id="dashboard-dark-mode">
                  <span class="slider round"></span>
                </label>
            </div>
          </div>

          <!-- START MODERN CARDS -->
          <div class="dashboard-grid">
            <!-- Staff Count -->
            <div class="card-modern">
              <div class="card-body">
                <div class="card-icon admin">
                  <i class="fas fa-users"></i>
                </div>
                <div class="card-label">Staff Members</div>
                <div class="card-value"><?php echo getAdminCount($conn); ?></div>
                <div class="card-footer-text">Total employees</div>
              </div>
            </div>
            
            <!-- Registered Students -->
            <div class="card-modern">
              <div class="card-body">
                <div class="card-icon student">
                  <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="card-label">Registered Students</div>
                <div class="card-value"><?php echo getStudentCount($conn); ?></div>
                <div class="card-footer-text">Active students</div>
              </div>
            </div>
            
            <!-- Books Available -->
            <div class="card-modern">
              <div class="card-body">
                <div class="card-icon book">
                  <i class="fas fa-book"></i>
                </div>
                <div class="card-label">Books Available</div>
                <div class="card-value"><?php echo getBookCount($conn); ?></div>
                <div class="card-footer-text">Total collection</div>
              </div>
            </div>
            
            <!-- Books Borrowed -->
            <div class="card-modern">
              <div class="card-body">
                <div class="card-icon borrowed">
                  <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="card-label">Books Borrowed</div>
                <div class="card-value"><?php echo getBorrowedBookCount($conn); ?></div>
                <div class="card-footer-text">Currently on loan</div>
              </div>
            </div>
          </div>
          <!-- END MODERN CARDS -->

          <!-- Borrowed Books Table -->
          <div class="table-card">
            <div class="card-body">
              <div class="table-title">
                <i class="fas fa-book-open"></i> Recently Borrowed Books
              </div>
              <div class="table-responsive">
                <table id="borrowedBooksTable" class="table table-hover">
                  <thead>
                    <tr>
                      <th><i class="fa-solid fa-user"></i> Full Name</th>
                      <th><i class="fa-solid fa-book"></i> Book Title</th>
                      <th><i class="fa-solid fa-hand-holding-heart"></i> Acquisition</th>
                      <th><i class="fa-solid fa-calendar"></i> Borrowed Date</th>
                      <th><i class="fa-solid fa-tag"></i> Status</th>
                    </tr>  
                  </thead>
                  <tbody>
                    <?php
                    $sql = "SELECT DISTINCT
                              COALESCE(s.first_name, e.firstname) AS first_name, 
                              COALESCE(s.last_name, e.lastname) AS last_name,
                              b.title, 
                              an.acquisition_type,
                              an.donor,
                              l.borrow_date, 
                              l.status
                            FROM lib_rfid_loan l
                            LEFT JOIN students s ON l.student_id = s.student_id
                            LEFT JOIN employees e ON l.student_id = e.employee_id
                            INNER JOIN lib_books b ON l.book_id = b.book_id
                            LEFT JOIN lib_accession_numbers an ON l.accession_id = an.id
                            GROUP BY first_name, last_name, b.title, l.borrow_date, l.status, an.acquisition_type, an.donor
                            ORDER BY l.borrow_date DESC LIMIT 10";
                    $result = mysqli_query($conn, $sql);

                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $studentName = $row['first_name'] . " " . $row['last_name'];
                            $bookTitle = $row['title'];
                            $borrowDate = date("d M Y", strtotime($row['borrow_date']));
                            $status = $row['status'];
                            $acquisitionType = $row['acquisition_type'];

                            $badgeClass = "badge-warning";
                            if (strtolower($status) == "returned") {
                                $badgeClass = "badge-success";
                            } elseif (strtolower($status) == "unavailable") {
                                $badgeClass = "badge-danger";
                            }

                            $acquisitionBadge = ($acquisitionType === 'Donated') ? 
                                '<span class="badge" style="background: #fdf2f2; color: #9b1c1c; border: 1px solid #fbd5d5; font-size: 0.75rem;"><i class="fa-solid fa-hand-holding-heart"></i> Donated</span>' : 
                                '<span class="badge" style="background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; font-size: 0.75rem;"><i class="fa-solid fa-school"></i> Purchased</span>';

                            echo "<tr>
                                    <td><strong>{$studentName}</strong></td>
                                    <td>{$bookTitle}</td>
                                    <td>{$acquisitionBadge}</td>
                                    <td>{$borrowDate}</td>
                                    <td><span class='badge {$badgeClass}'>{$status}</span></td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center'><div class='empty-state'><i class='fa-solid fa-inbox'></i><p>No borrowed books found</p></div></td></tr>";
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        <?php include 'partials/footer.php'; ?>
        <!-- partial -->
      </div>
      <!-- main-panel ends -->
    </div>   
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <!-- plugins:js -->
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <script src="static/vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="static/js/dataTables.select.min.js"></script>

  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="static/js/off-canvas.js"></script>
  <script src="static/js/hoverable-collapse.js"></script>
  <script src="static/js/template.js"></script>
  <script src="static/js/settings.js"></script>
  <script src="static/js/todolist.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="static/js/dashboard.js"></script>
  <script src="static/js/Chart.roundedBarCharts.js"></script>

  <!-- Initialize DataTable -->
  <script>
    $(document).ready(function () {
        $('#borrowedBooksTable').DataTable({
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50, 100],
            "ordering": false,
            "searching": false,
            "scrollY": "400px",
            "scrollCollapse": true,
            "paging": true
        });
    });

    // Dashboard Dark Mode Toggle Logic
    document.addEventListener('DOMContentLoaded', function() {
        const dashboardToggle = document.getElementById('dashboard-dark-mode');
        const settingsToggle = document.getElementById('dark-mode-toggle');
        
        function updateTheme(isDark) {
            if (isDark) {
                document.body.classList.add('dark-mode');
                document.documentElement.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark-mode');
                document.documentElement.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            }
            // Sync toggles
            if (dashboardToggle) dashboardToggle.checked = isDark;
            if (settingsToggle) settingsToggle.checked = isDark;
        }

        if (dashboardToggle) {
            if (localStorage.getItem('theme') === 'dark') {
                dashboardToggle.checked = true;
            }
            dashboardToggle.addEventListener('change', function() {
                updateTheme(this.checked);
            });
        }
    });
  </script>
  <!-- End custom js for this page-->
</body>

</html>
