<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_reporting(0);
include '../includes/session.php';
include '../includes/dbcon.php';

/* ===========================
   FUNCTIONS TO GET COUNTS
   =========================== */

function getAdminCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM tbl_admin";
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

function getTeachingStaffCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM employees WHERE role_id = 'Teaching Staff'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function getNonTeachingStaffCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM employees WHERE role_id = 'Non-Teaching Staff'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function getBookCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM lib_books";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function getBorrowedBookCount($conn) {
    $sql = "SELECT COUNT(*) AS total FROM lib_books WHERE status='unavailable'";
    $result = mysqli_query($conn, $sql);
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
    /* ===== READABLE LIGHT DASHBOARD STYLING ===== */
    :root {
      --primary: #4B49AC;
      --secondary: #7DA7FB;
      --success: #2ecc71;
      --info: #3498db;
      --warning: #f1c40f;
      --danger: #e74c3c;
      --light-bg: #f5f7ff;
      --text-main: #2c3e50;
      --text-muted: #6c757d;
      --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    * {
      transition: all 0.2s ease-in-out;
    }

    body {
      background-color: var(--light-bg);
      font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
      color: var(--text-main);
    }

    .container-scroller {
      background-color: var(--light-bg);
    }

    .content-wrapper {
      padding: 30px 25px;
      background: var(--light-bg);
    }

    /* ===== WELCOME SECTION ===== */
    .col-md-12.grid-margin h3 {
      font-size: 2rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 8px;
    }

    .col-md-12.grid-margin p {
      font-size: 1rem;
      color: var(--text-muted);
      font-weight: 400;
    }

    /* ===== CARD STYLING ===== */
    .card {
      border: none;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
      background: #ffffff;
    }

    .card:hover {
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
      transform: translateY(-2px);
    }

    /* ===== STAT CARDS ===== */
    .stat-card {
      border: none !important;
      border-radius: 12px;
      padding: 24px;
      color: #ffffff;
      height: 100%;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    .stat-card-blue { background: #4B49AC; }
    .stat-card-green { background: #24d278; }
    .stat-card-orange { background: #ff4747; }
    .stat-card-purple { background: #7DA7FB; }
    .stat-card-red { background: #f3797e; }

    .stat-label {
      font-size: 0.85rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      opacity: 0.85;
      margin-bottom: 8px;
    }

    .stat-number {
      font-size: 2.2rem;
      font-weight: 700;
      margin: 0;
    }

    .stat-icon {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 2.8rem;
      opacity: 0.25;
    }

    /* ===== TABLE STYLING ===== */
    .card-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 20px !important;
      padding-bottom: 10px;
      border-bottom: 2px solid #f3f4f6;
    }

    .table {
      border-radius: 8px;
      overflow: hidden;
    }

    .table thead th {
      background: #f8fafc !important;
      color: #4b5563 !important;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      padding: 15px;
      border-bottom: 1px solid #e5e7eb;
    }

    .table tbody td {
      padding: 16px 15px;
      border-bottom: 1px solid #f3f4f6;
      font-size: 0.9rem;
      color: #374151;
      vertical-align: middle;
    }

    .table tbody tr:hover {
      background-color: #f9fafb;
    }

    /* ===== BADGE STYLING ===== */
    .badge {
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .badge-primary { background: #eff6ff; color: #1e40af; }
    .badge-success { background: #ecfdf5; color: #065f46; }
    .badge-info { background: #f0f9ff; color: #075985; }
    .badge-warning { background: #fffbeb; color: #92400e; }
    .badge-danger { background: #fef2f2; color: #991b1b; }

    /* Theme Switcher Toggle styling */
    .theme-switch {
      display: inline-block;
      height: 24px;
      position: relative;
      width: 44px;
    }

    .theme-switch input { display:none; }

    .slider {
      background-color: #e5e7eb;
      bottom: 0;
      cursor: pointer;
      left: 0;
      position: absolute;
      right: 0;
      top: 0;
      transition: .4s;
    }

    .slider:before {
      background-color: #fff;
      bottom: 4px;
      content: "";
      height: 16px;
      left: 4px;
      position: absolute;
      transition: .4s;
      width: 16px;
    }

    input:checked + .slider { background-color: var(--primary); }
    input:checked + .slider:before { transform: translateX(20px); }
    .slider.round { border-radius: 34px; }
    .slider.round:before { border-radius: 50%; }

    /* ===== RESPONSIVE ADJUSTMENTS ===== */
    @media (max-width: 991px) {
      .content-wrapper {
        padding: 20px 15px;
      }
      .stat-number {
        font-size: 1.8rem;
      }
      .stat-icon {
        font-size: 2.22rem;
      }
    }

    @media (max-width: 767px) {
      .col-md-12.grid-margin h3 {
        font-size: 1.5rem;
      }
      .card-title {
        font-size: 1.1rem;
      }
      .table thead th, .table tbody td {
        padding: 12px 10px;
        font-size: 0.8rem;
      }
      .badge {
        padding: 4px 8px;
        font-size: 0.7rem;
      }
    }

    @media (max-width: 575px) {
      .stat-card {
        padding: 20px 15px;
      }
      .stat-number {
        font-size: 1.5rem;
      }
      .stat-label {
        font-size: 0.75rem;
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
          <div class="row">
            <div class="col-md-12 grid-margin">
              <div class="row">
                <div class="col-12 col-xl-8 mb-4 mb-xl-0">
                  <h3 class="font-weight-bold">
                    Welcome <?php 
                      // Display greeting based on user type
                      if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'staff') {
                        echo htmlspecialchars($_SESSION['role_name'] ?? 'Staff');
                      } else {
                        echo 'Admin';
                      }
                    ?>!!!
                  </h3>
                  <p class="text-muted">
                    <?php 
                      if (isset($_SESSION['firstname']) && isset($_SESSION['lastname'])) {
                        echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']);
                      }
                    ?>
                  </p>
                </div>
                <div class="col-12 col-xl-4 d-flex justify-content-xl-end align-items-center mb-4 mb-xl-0">
                    <div class="theme-switch-wrapper bg-white p-2 px-3 rounded-pill shadow-sm" style="border: 1px solid #e5e7eb; display: inline-flex; align-items: center;">
                        <span class="mr-2 text-muted fw-bold" style="font-size: 0.75rem; color: #6b7280;"><i class="fas fa-moon me-1"></i> Dark Mode</span>
                        <label class="theme-switch mb-0" for="dashboard-dark-mode">
                          <input type="checkbox" id="dashboard-dark-mode">
                          <span class="slider round"></span>
                        </label>
                    </div>
                </div>
              </div>
            </div>
          </div>

          <!-- START STAT CARDS -->
          <div class="row mb-4">
            <!-- Students Count -->
            <div class="col-md-6 col-lg-2 mb-3 mb-lg-0">
              <div class="stat-card stat-card-blue">
                <i class="fas fa-graduation-cap stat-icon"></i>
                <div class="stat-card-content">
                  <div class="stat-label">Students</div>
                  <div class="stat-number"><?php echo getStudentCount($conn); ?></div>
                </div>
              </div>
            </div>
            
            <!-- Teaching Staff Count -->
            <div class="col-md-6 col-lg-2 mb-3 mb-lg-0">
              <div class="stat-card stat-card-green">
                <i class="fas fa-chalkboard-user stat-icon"></i>
                <div class="stat-card-content">
                  <div class="stat-label">Teachers</div>
                  <div class="stat-number"><?php echo getTeachingStaffCount($conn); ?></div>
                </div>
              </div>
            </div>
            
            <!-- Non-Teaching Staff Count -->
            <div class="col-md-6 col-lg-2 mb-3 mb-lg-0">
              <div class="stat-card stat-card-orange">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-card-content">
                  <div class="stat-label">Non-Teaching</div>
                  <div class="stat-number"><?php echo getNonTeachingStaffCount($conn); ?></div>
                </div>
              </div>
            </div>
            
            <!-- Books Available -->
            <div class="col-md-6 col-lg-2 mb-3 mb-lg-0">
              <div class="stat-card stat-card-purple">
                <i class="fas fa-book stat-icon"></i>
                <div class="stat-card-content">
                  <div class="stat-label">Books Available</div>
                  <div class="stat-number"><?php echo getBookCount($conn); ?></div>
                </div>
              </div>
            </div>
            
            <!-- Books Borrowed -->
            <div class="col-md-6 col-lg-2 mb-3 mb-lg-0">
              <div class="stat-card stat-card-red">
                <i class="fas fa-book-open stat-icon"></i>
                <div class="stat-card-content">
                  <div class="stat-label">Borrowed</div>
                  <div class="stat-number"><?php echo getBorrowedBookCount($conn); ?></div>
                </div>
              </div>
            </div>
          </div>
          <!-- END STAT CARDS -->

          <!-- Borrowed Books Table -->
           <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <p class="card-title mb-0">Books Borrowed Table</p>
                  <div class="table-responsive">
                    <table id="borrowedBooksTable" class="table table-striped table-borderless">
                      <thead>
                        <tr>
                          <th>Full Name</th>
                          <th>Book Title</th>
                          <th>Acquisition</th>
                          <th>Borrowed Date</th>
                          <th>Status</th>
                        </tr>  
                      </thead>
                      <tbody>
                        <?php
                        $sql = "SELECT * FROM (
                                  SELECT 
                                    CONCAT(s.first_name, ' ', s.last_name) AS name,
                                    'Student' AS user_type,
                                    b.title, 
                                    an.acquisition_type,
                                    an.donor,
                                    l.borrow_date, 
                                    l.status
                                  FROM lib_rfid_loan l
                                  INNER JOIN students s ON l.uid = s.rfid_number OR l.uid = s.student_id
                                  INNER JOIN lib_books b ON l.book_id = b.book_id
                                  LEFT JOIN lib_accession_numbers an ON l.accession_id = an.id
                                  WHERE l.status NOT IN ('returned', 'Returned')
                                  UNION
                                  SELECT 
                                    CONCAT(e.firstname, ' ', e.lastname) AS name,
                                    e.role_id AS user_type,
                                    b.title, 
                                    an.acquisition_type,
                                    an.donor,
                                    l.borrow_date, 
                                    l.status
                                  FROM lib_rfid_loan l
                                  INNER JOIN employees e ON l.uid = e.rfid_number OR l.uid = CAST(e.employee_id AS CHAR)
                                  INNER JOIN lib_books b ON l.book_id = b.book_id
                                  LEFT JOIN lib_accession_numbers an ON l.accession_id = an.id
                                  WHERE l.status NOT IN ('returned', 'Returned')
                                ) AS combined
                                ORDER BY borrow_date DESC
                                LIMIT 20";
                        $result = mysqli_query($conn, $sql);

                        if ($result && mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $name = $row['name'];
                                $userType = $row['user_type'];
                                $bookTitle = $row['title'];
                                $borrowDate = date("d M Y", strtotime($row['borrow_date']));
                                $status = $row['status'];
                                $acquisitionType = $row['acquisition_type'];
                                $donor = $row['donor'];

                                $badgeClass = "badge-warning";
                                if (strtolower($status) == "returned") {
                                    $badgeClass = "badge-success";
                                } elseif (strtolower($status) == "unavailable") {
                                    $badgeClass = "badge-danger";
                                }

                                $typeLabel = ($userType === 'Student') ? 'Student' : htmlspecialchars($userType);
                                $typeBadge = ($userType === 'Student') ? '<span class="badge" style="background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 0.7rem; margin-left: 8px;">Student</span>' : '<span class="badge" style="background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; font-size: 0.7rem; margin-left: 8px;">Staff</span>';

                                $acquisitionBadge = ($acquisitionType === 'Donated') ? 
                                    '<span class="badge" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-size: 0.7rem;"><i class="fa-solid fa-hand-holding-heart"></i> Donated</span>' : 
                                    '<span class="badge" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size: 0.7rem;"><i class="fa-solid fa-school"></i> Purchased</span>';

                                echo "<tr>
                                        <td><strong>{$name}</strong>{$typeBadge}</td>
                                        <td class='font-weight-bold'>{$bookTitle}</td>
                                        <td>{$acquisitionBadge}</td>
                                        <td>{$borrowDate}</td>
                                        <td class='font-weight-medium'><div class='badge {$badgeClass}'>{$status}</div></td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center text-muted py-5'>
                                    <i class='fas fa-inbox' style='font-size: 2.5rem; margin-bottom: 10px; opacity: 0.5;'></i>
                                    <p style='margin-top: 10px; font-weight: 500;'>No active borrowed books found</p>
                                    <p style='font-size: 0.9rem; opacity: 0.7;'>All books have been returned</p>
                                  </td></tr>";
                        }
                        ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Advanced Table (placeholder removed to clean layout) -->
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
