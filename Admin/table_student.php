<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

$statusMsg = "";

// ✅ Handle Eligibility Toggle
if (isset($_GET['toggle_eligibility']) && isset($_GET['type'])) {
    $userId = mysqli_real_escape_string($conn, $_GET['toggle_eligibility']);
    $type = $_GET['type'];
    $currentStatus = intval($_GET['current'] ?? 0);
    $newStatus = ($currentStatus == 1) ? 0 : 1;
    
    if ($type === 'student') {
        $updateQuery = "UPDATE students SET eligible_status = $newStatus WHERE student_id = '$userId'";
    } elseif ($type === 'employee') {
        $updateQuery = "UPDATE employees SET eligible_status = $newStatus WHERE employee_id = '$userId'";
    }
    
    if (mysqli_query($conn, $updateQuery)) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ✅ Handle Delete Request for both tables
if (isset($_GET['delete_id']) && isset($_GET['type'])) {
    $deleteId = intval($_GET['delete_id']);
    $type = $_GET['type'];
    
    if ($type === 'employee') {
        $deleteQuery = "DELETE FROM employees WHERE employee_id = $deleteId";
        $successMessage = 'Staff member deleted successfully';
    } else {
        $deleteQuery = "DELETE FROM students WHERE student_id = $deleteId";
        $successMessage = 'Student deleted successfully';
    }
    
    if (mysqli_query($conn, $deleteQuery)) {
        $statusMsg = '<div class="alert alert-success">✅ ' . $successMessage . '!</div>';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ Error deleting user: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    }
}

// ✅ Handle Edit/Update Request for tbl_students
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id']) && isset($_POST['edit_type']) && $_POST['edit_type'] === 'student') {
    $editId    = intval($_POST['edit_id']);
    $uid       = mysqli_real_escape_string($conn, $_POST['uid']);
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $year      = mysqli_real_escape_string($conn, $_POST['year']);
    $section   = mysqli_real_escape_string($conn, $_POST['section']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $address   = mysqli_real_escape_string($conn, $_POST['address']);
    $course    = mysqli_real_escape_string($conn, $_POST['course'] ?? '');

    // Handle section mapping
    $sectionVal = 'NULL';
    if (!empty($section)) {
        $sName = mysqli_real_escape_string($conn, $section);
        $yId = intval($year);
        $checkSection = mysqli_query($conn, "SELECT section_id FROM sections WHERE section_name = '$sName' AND level = '$yId' LIMIT 1");
        if ($rowS = mysqli_fetch_assoc($checkSection)) {
            $sectionVal = $rowS['section_id'];
        } else {
            mysqli_query($conn, "INSERT INTO sections (section_name, level) VALUES ('$sName', '$yId')");
            $sectionVal = mysqli_insert_id($conn);
        }
    }

    $updateQuery = "UPDATE students 
                    SET rfid_number='$uid', first_name='$firstname', last_name='$lastname', year_id='$year', section_id='$sectionVal', 
                        email='$email', address='$address', course='$course'
                    WHERE student_id='$editId'";

    if (mysqli_query($conn, $updateQuery)) {
        $statusMsg = '<div class="alert alert-success">✅ Student updated successfully!</div>';
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ Error updating student: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    }
}

// ✅ Handle Edit/Update Request for employees
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id']) && isset($_POST['edit_type']) && $_POST['edit_type'] === 'employee') {
    $editId    = intval($_POST['edit_id']);
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);

    $updateQuery = "UPDATE employees 
                    SET firstname='$firstname', lastname='$lastname', email='$email'
                    WHERE employee_id=$editId";

    if (mysqli_query($conn, $updateQuery)) {
        $statusMsg = '<div class="alert alert-success">✅ Staff member updated successfully!</div>';
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ Error updating staff member: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    }
}

$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$filterParam = ($filter !== 'all') ? "&filter=" . urlencode($filter) : "";

$whereClause = " WHERE 1=1 ";
if ($filter === 'college') {
    $whereClause .= " AND user_type_label = 'Student' AND (grade IS NULL OR grade = '' OR grade = 0)";
} elseif ($filter === 'highschool') {
    $whereClause .= " AND user_type_label = 'Student' AND grade IS NOT NULL AND grade != '' AND grade != 0";
} elseif ($filter === 'teaching') {
    $whereClause .= " AND user_type_label IN ('Teacher', 'Instructor', 'Professor', 'Faculty')";
} elseif ($filter === 'non-teaching') {
    $whereClause .= " AND user_type_label NOT IN ('Student', 'Teacher', 'Instructor', 'Professor', 'Faculty')";
}

// ✅ Fetch records from both students and employees tables
$query = "
    SELECT * FROM (
        SELECT 
            s.student_id AS id,
            s.rfid_number,
            s.first_name,
            s.last_name,
            s.middle_name,
            s.suffix,
            s.year_id,
            s.section_id,
            s.grade,
            s.strand,
            s.course,
            s.email,
            s.address,
            s.phone_number,
            s.gender,
            s.profile_picture,
            s.status,
            s.created_at,
            'student' AS record_type,
            'Student' AS user_type_label,
            COALESCE(y.year_name, CAST(s.year_id AS CHAR)) AS year_name,
            COALESCE(sec.section_name, CAST(s.section_id AS CHAR)) AS section_name,
            s.eligible_status,
            (SELECT COUNT(*) FROM lib_rfid_loan WHERE student_id = s.student_id AND status = 'borrowed') as borrowed_count
        FROM students s
        LEFT JOIN year_levels y ON s.year_id = y.year_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        WHERE s.archived = 0
        UNION
        SELECT 
            e.employee_id AS id,
            e.rfid_number,
            e.firstname AS first_name,
            e.lastname AS last_name,
            '' AS middle_name,
            '' AS suffix,
            NULL AS year_id,
            NULL AS section_id,
            NULL AS grade,
            NULL AS strand,
            e.assigned_course AS course,
            e.email,
            '' AS address,
            '' AS phone_number,
            '' AS gender,
            e.profile_pic AS profile_picture,
            'active' AS status,
            NOW() AS created_at,
            'employee' AS record_type,
            COALESCE(r.role_name, CAST(e.role_id AS CHAR)) AS user_type_label,
            COALESCE(r.role_name, CAST(e.role_id AS CHAR)) AS year_name,
            e.assigned_course AS section_name,
            e.eligible_status,
            (SELECT COUNT(*) FROM lib_rfid_loan WHERE student_id = e.employee_id AND status = 'borrowed') as borrowed_count
        FROM employees e
        LEFT JOIN roles r ON e.role_id = r.role_id
    ) AS combined
    $whereClause
    ORDER BY created_at DESC
";
$studentResult = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include "partials/head.php";?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .action-icons i {
      cursor: pointer;
      font-size: 1.1rem;
      padding: 8px;
      border-radius: 5px;
      transition: all 0.3s ease;
    }
    .action-icons i.edit { 
      color: #007bff; 
    }
    .action-icons i.edit:hover {
      background: rgba(0, 123, 255, 0.1);
      transform: scale(1.1);
    }
    .action-icons i.delete { 
      color: #dc3545; 
    }
    .action-icons i.delete:hover {
      background: rgba(220, 53, 69, 0.1);
      transform: scale(1.1);
    }
    .action-icons .delete-link {
      text-decoration: none;
    }

    /* Light Badge Styles */
    .badge {
      font-weight: 600;
      padding: 0.5em 0.75em;
    }
    .badge-light-primary { background-color: #e0e7ff !important; color: #4338ca !important; border: 1px solid #c7d2fe; }
    .badge-light-info { background-color: #e0f2fe !important; color: #0369a1 !important; border: 1px solid #bae6fd; }
    .badge-light-success { background-color: #dcfce7 !important; color: #15803d !important; border: 1px solid #bbf7d0; }
    .badge-light-warning { background-color: #fef3c7 !important; color: #b45309 !important; border: 1px solid #fde68a; }
    .badge-light-danger { background-color: #fee2e2 !important; color: #991b1b !important; border: 1px solid #fecaca; }
    .badge-light-secondary { background-color: #f3f4f6 !important; color: #374151 !important; border: 1px solid #e5e7eb; }
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
              <div class="card shadow-sm rounded-3">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <h4 class="card-title mb-0">📊 User Records</h4>
                    
                    <div class="btn-group my-2" role="group" aria-label="User Filter">
                      <a href="?filter=all" class="btn btn-sm <?= ($filter == 'all') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-users me-1"></i> All
                      </a>
                      <a href="?filter=college" class="btn btn-sm <?= ($filter == 'college') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-user-graduate me-1"></i> College
                      </a>
                      <a href="?filter=highschool" class="btn btn-sm <?= ($filter == 'highschool') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-school me-1"></i> High School
                      </a>
                      <a href="?filter=teaching" class="btn btn-sm <?= ($filter == 'teaching') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-chalkboard-teacher me-1"></i> Teaching
                      </a>
                      <a href="?filter=non-teaching" class="btn btn-sm <?= ($filter == 'non-teaching') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-user-tie me-1"></i> Non-Teaching
                      </a>
                    </div>

                    <div class="d-flex my-2" style="max-width: 300px;">
                      <div class="input-group">
                        <span class="input-group-text bg-primary text-white border-0">
                          <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="customSearch" class="form-control" placeholder="Search records...">
                      </div>
                    </div>
                  </div>
                  <div class="mb-3">
                    <?php
                      // Count different user types
                      $countResult = mysqli_query($conn, "
                        SELECT 
                          'student' AS type, COUNT(*) as cnt FROM students WHERE archived = 0
                        UNION
                        SELECT 'employee' AS type, COUNT(*) as cnt FROM employees
                      ");
                      $studentCount = 0;
                      $employeeCount = 0;
                      while($countRow = mysqli_fetch_assoc($countResult)) {
                        if($countRow['type'] === 'student') $studentCount = $countRow['cnt'];
                        if($countRow['type'] === 'employee') $employeeCount = $countRow['cnt'];
                      }
                    ?>
                    <span class="badge badge-primary me-2">👤 Students: <?= $studentCount; ?></span>
                    <span class="badge badge-success me-2">👥 Teaching Staff: <span id="teachingCount">-</span></span>
                    <span class="badge badge-warning">👨‍💼 Non-Teaching Staff: <span id="nonteachingCount">-</span></span>
                  </div>
                  <?php
                    // Define visibility flags based on filter
                    $isAll = ($filter === 'all');
                    $isCollege = ($filter === 'college');
                    $isHS = ($filter === 'highschool');
                    $isStaff = ($filter === 'teaching' || $filter === 'non-teaching');

                    $showDept = ($isAll || $isCollege || $isStaff);
                    $showYearRole = ($isAll || $isCollege || $isStaff);
                    $showGradeStrand = ($isAll || $isHS);

                    $deptLabel = 'Course / Dept';
                    if ($isCollege) $deptLabel = 'Course';
                    if ($isStaff) $deptLabel = 'Department';
                  ?>
                  <div class="table-responsive">
                    <table id="studentTable" class="table table-hover">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>RFID</th>
                          <th>Name</th>
                          <th>Type</th>
                          <?php if ($showDept): ?><th><?= $deptLabel; ?></th><?php endif; ?>
                          <?php if ($showYearRole): ?><th>Year / Role</th><?php endif; ?>
                          <?php if ($showYearRole): ?><th>Section</th><?php endif; ?>
                          <?php if ($showGradeStrand): ?><th>Grade</th><?php endif; ?>
                          <?php if ($showGradeStrand): ?><th>Strand</th><?php endif; ?>
                          <th>Email</th>
                          <th>Address</th>
                          <th>Borrow</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($studentResult && mysqli_num_rows($studentResult) > 0): ?>
                          <?php while ($row = mysqli_fetch_assoc($studentResult)): ?>
                            <tr>
                              <td><?= htmlspecialchars($row['id']); ?></td>
                              <td><?= htmlspecialchars($row['rfid_number'] ?? 'N/A'); ?></td>
                              <td>
                                <?php 
                                $name = trim(htmlspecialchars($row['first_name'] . ' ' . 
                                        ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . 
                                        $row['last_name'] . 
                                        ($row['suffix'] ? ' ' . $row['suffix'] : '')));
                                echo $name ?: 'N/A';
                                ?>
                              </td>
                              <td>
                                <?php 
                                if ($row['record_type'] === 'student') {
                                    echo '<span class="badge badge-light-info">Student</span>';
                                } else {
                                    echo '<span class="badge badge-light-primary">Staff</span>';
                                }
                                ?>
                              </td>
                              
                              <?php if ($showDept): ?>
                              <td><?= htmlspecialchars($row['course'] ?? 'N/A'); ?></td>
                              <?php endif; ?>

                              <?php if ($showYearRole): ?>
                              <td><?php 
                                if ($row['record_type'] === 'student') {
                                    echo '<span class="badge badge-light-info">' . htmlspecialchars($row['year_name'] ?? 'N/A') . '</span>';
                                } else {
                                    echo '<span class="badge badge-light-warning">' . htmlspecialchars($row['user_type_label'] ?? 'N/A') . '</span>';
                                }
                              ?></td>
                              <td><?php 
                                if ($row['record_type'] === 'student') {
                                    echo '<span class="badge badge-light-primary">' . htmlspecialchars($row['section_name'] ?? 'N/A') . '</span>';
                                } else {
                                    echo '<span class="text-muted small">—</span>';
                                }
                              ?></td>
                              <?php endif; ?>

                              <?php if ($showGradeStrand): ?>
                              <td>
                                <?php if ($row['record_type'] === 'student' && !empty($row['grade'])): ?>
                                  <span class="badge badge-light-success"><?php echo htmlspecialchars($row['grade']); ?></span>
                                <?php else: ?>
                                  <span class="text-muted small">—</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <?php if ($row['record_type'] === 'student' && !empty($row['strand'])): ?>
                                  <span class="badge badge-light-warning"><?php echo htmlspecialchars($row['strand']); ?></span>
                                <?php else: ?>
                                  <span class="text-muted small">—</span>
                                <?php endif; ?>
                              </td>
                              <?php endif; ?>

                              <td><?= htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                              <td><?= htmlspecialchars($row['address'] ?? 'N/A'); ?></td>
                              <td>
                                <?php 
                                  $bCount = intval($row['borrowed_count'] ?? 0);
                                  $isManualRestricted = ($row['eligible_status'] == 0);
                                  
                                  if ($bCount >= 3): ?>
                                    <span class="badge badge-light-danger" title="3/3 Books Borrowed">
                                      Limit Reached (3)
                                    </span>
                                  <?php elseif ($isManualRestricted): ?>
                                    <span class="badge badge-light-danger" title="Manually Restricted">
                                      Restricted
                                    </span>
                                  <?php else: ?>
                                    <span class="badge badge-light-success" title="<?= $bCount; ?>/3 Books Borrowed">
                                      Eligible (<?= $bCount; ?>)
                                    </span>
                                  <?php endif; ?>
                              </td>
                              <td class="action-icons">
                                <a href="?toggle_eligibility=<?= htmlspecialchars($row['id']); ?>&type=<?= $row['record_type']; ?>&current=<?= $row['eligible_status']; ?>" 
                                   class="me-2" style="color: <?= ($row['eligible_status'] == 1) ? '#dc3545' : '#28a745'; ?>; text-decoration: none;" 
                                   title="<?= ($row['eligible_status'] == 1) ? 'Disable Borrowing' : 'Enable Borrowing'; ?>">
                                  <i class="fas <?= ($row['eligible_status'] == 1) ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                </a>
                                <?php if ($row['record_type'] === 'student'): ?>
                                  <i class="fa-solid fa-pencil edit-user" 
                                    data-id="<?= $row['id']; ?>"
                                    data-type="student"
                                    data-rfid="<?= htmlspecialchars($row['rfid_number'] ?? ''); ?>"
                                    data-firstname="<?= htmlspecialchars($row['first_name']); ?>"
                                    data-lastname="<?= htmlspecialchars($row['last_name']); ?>"
                                    data-year="<?= htmlspecialchars($row['year_id']); ?>"
                                    data-section="<?= htmlspecialchars($row['section_name'] ?? ''); ?>"
                                    data-course="<?= htmlspecialchars($row['course']); ?>"
                                    data-email="<?= htmlspecialchars($row['email']); ?>"
                                    data-address="<?= htmlspecialchars($row['address'] ?? ''); ?>"
                                    title="Edit Student"></i>
                                <?php else: ?>
                                  <i class="fa-solid fa-pencil edit-user" 
                                    data-id="<?= $row['id']; ?>"
                                    data-type="employee"
                                    data-rfid="<?= htmlspecialchars($row['rfid_number'] ?? ''); ?>"
                                    data-firstname="<?= htmlspecialchars($row['first_name']); ?>"
                                    data-lastname="<?= htmlspecialchars($row['last_name']); ?>"
                                    data-email="<?= htmlspecialchars($row['email']); ?>"
                                    data-course="<?= htmlspecialchars($row['course']); ?>"
                                    title="Edit Staff"></i>
                                <?php endif; ?>
                                <a href="?delete_id=<?= $row['id']; ?>&type=<?= $row['record_type']; ?>" 
                                   class="delete-link" 
                                   onclick="return confirm('Are you sure you want to delete this <?= $row['record_type']; ?>?');" 
                                   title="Delete <?= ucfirst($row['record_type']); ?>">
                                  <i class="fa-solid fa-trash delete"></i>
                                </a>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="8" class="text-center">No User records found.</td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include 'partials/footer.php'; ?>
      </div>
    </div>   
  </div>

  <!-- Edit Student Modal -->
  <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="">
          <input type="hidden" name="edit_type" value="student">
          <div class="modal-header">
            <h5 class="modal-title" id="editStudentModalLabel">Edit Student</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="hidden" name="edit_type" id="edit_type">
            <div class="mb-3">
              <label for="uid" class="form-label">UID</label>
              <input type="text" class="form-control" name="uid" id="uid" readonly>
            </div>
            <div class="mb-3">
              <label for="firstname" class="form-label">First Name</label>
              <input type="text" class="form-control" name="firstname" id="firstname" required>
            </div>
            <div class="mb-3">
              <label for="lastname" class="form-label">Last Name</label>
              <input type="text" class="form-control" name="lastname" id="lastname" required>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="course_modal" class="form-label">Course</label>
                <input type="text" class="form-control" name="course" id="course_modal">
              </div>
              <div class="col-md-4 mb-3">
                <label for="year" class="form-label">Grade/Year</label>
                <input type="text" class="form-control" name="year" id="year" required>
              </div>
              <div class="col-md-4 mb-3">
                <label for="section" class="form-label">Section</label>
                <input type="text" class="form-control" name="section" id="section" required>
              </div>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" name="email" id="email" required>
            </div>
            <div class="mb-3">
              <label for="address" class="form-label">Address</label>
              <input type="text" class="form-control" name="address" id="address" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Student</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- JS Scripts -->
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="static/vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="static/vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="static/js/dataTables.select.min.js"></script>
  <script src="static/js/off-canvas.js"></script>
  <script src="static/js/hoverable-collapse.js"></script>
  <script src="static/js/template.js"></script>
  <script src="static/js/settings.js"></script>
  <script src="static/js/todolist.js"></script>
  <script src="static/js/dashboard.js"></script>
  <script src="static/js/Chart.roundedBarCharts.js"></script>

  <!-- DataTable + Modal Script -->
  <script>
    $(document).ready(function () {
        var table = $('#studentTable').DataTable({
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50, 100],
            "ordering": false,
            "searching": true,
            "scrollY": "400px",
            "scrollCollapse": true,
            "paging": true,
            "dom": 'lrtip' // Hide default search box
        });

        // Custom search box logic
        $('#customSearch').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Count Teaching and Non-Teaching Staff
        var teachingCount = 0;
        var nonteachingCount = 0;
        
        $('#studentTable tbody tr').each(function() {
          var badgeText = $(this).find('td:eq(3)').text().trim();
          var roleText = $(this).find('td:eq(5)').text().trim().toLowerCase();
          
          if (badgeText.includes('Staff')) {
            if (roleText.includes('teaching')) {
              teachingCount++;
            } else if (roleText.includes('non')) {
              nonteachingCount++;
            }
          }
        });
        
        $('#teachingCount').text(teachingCount);
        $('#nonteachingCount').text(nonteachingCount);

        // Edit User (Student or Staff)
        $(document).on('click', '.edit-user', function() {
          var id = $(this).data('id');
          var type = $(this).data('type');
          var rfid = $(this).data('rfid');
          var firstname = $(this).data('firstname');
          var lastname = $(this).data('lastname');
          var email = $(this).data('email');
          var course = $(this).data('course') || '';
          
          $('#edit_id').val(id);
          $('#edit_type').val(type);
          $('#uid').val(rfid);
          $('#firstname').val(firstname);
          $('#lastname').val(lastname);
          $('#email').val(email);
          $('#course_modal').val(course);
          
          if (type === 'student') {
            $('#year').val($(this).data('year')).prop('required', true).parent().show();
            $('#section').val($(this).data('section')).prop('required', true).parent().show();
            $('#address').val($(this).data('address') || '').parent().show();
            $('#editStudentModalLabel').text('Edit Student');
          } else {
            // Hide student specific fields for staff
            $('#year').prop('required', false).parent().hide();
            $('#section').prop('required', false).parent().hide();
            $('#address').parent().hide();
            $('#editStudentModalLabel').text('Edit Staff Member');
          }
          
          $('#editStudentModal').modal('show');
        });
    });
  </script>
</body>
</html>
