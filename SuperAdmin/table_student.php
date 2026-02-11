<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require '../includes/session.php';
require '../includes/dbcon.php';

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
        $msg = ($newStatus == 1) ? "enabled" : "disabled";
        $statusMsg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <strong>Success!</strong> Borrowing '.$msg.'!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    }
}

// ✅ Handle Delete Request for both tables
if (isset($_GET['delete_id']) && isset($_GET['type'])) {
    $deleteId = mysqli_real_escape_string($conn, $_GET['delete_id']);
    $type = $_GET['type'];
    
    if ($type === 'student') {
        // Get rfid_number before deleting
        $rfidQuery = "SELECT rfid_number FROM students WHERE student_id = '$deleteId'";
        $rfidResult = mysqli_query($conn, $rfidQuery);
        if ($rfidResult && $rfidRow = mysqli_fetch_assoc($rfidResult)) {
            $rfidNumber = $rfidRow['rfid_number'];
            if ($rfidNumber) {
                @mysqli_query($conn, "UPDATE lib_rfid_auth SET inuse = 0 WHERE uid = '" . mysqli_real_escape_string($conn, $rfidNumber) . "'");
            }
        }
        
        // Delete from students table
        $deleteQuery = "DELETE FROM students WHERE student_id = '$deleteId'";
    } elseif ($type === 'employee') {
        // Get rfid_number before deleting from employees
        $rfidQuery = "SELECT rfid_number FROM employees WHERE employee_id = '$deleteId'";
        $rfidResult = mysqli_query($conn, $rfidQuery);
        if ($rfidResult && $rfidRow = mysqli_fetch_assoc($rfidResult)) {
            $rfidNumber = $rfidRow['rfid_number'];
            if ($rfidNumber) {
                @mysqli_query($conn, "UPDATE lib_rfid_auth SET inuse = 0 WHERE uid = '" . mysqli_real_escape_string($conn, $rfidNumber) . "'");
            }
        }
        
        // Delete from employees table
        $deleteQuery = "DELETE FROM employees WHERE employee_id = '$deleteId'";
    } else {
        // Delete from lib_regulars
        $deleteQuery = "DELETE FROM lib_regulars WHERE id = '$deleteId'";
    }
    
    if (mysqli_query($conn, $deleteQuery)) {
        $statusMsg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <strong>Success!</strong> User deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        $statusMsg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> ' . htmlspecialchars(mysqli_error($conn)) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    }
}

// ✅ Handle Edit/Update Request for students
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id']) && isset($_POST['edit_type']) && $_POST['edit_type'] === 'student') {
    $editId    = mysqli_real_escape_string($conn, $_POST['edit_id']);
    $rfid      = mysqli_real_escape_string($conn, $_POST['rfid_number']);
    $firstname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $year      = intval($_POST['year_id']);
    $section   = intval($_POST['section_id']);
    $grade     = !empty($_POST['grade']) ? intval($_POST['grade']) : NULL;
    $strand    = !empty($_POST['strand']) ? mysqli_real_escape_string($conn, $_POST['strand']) : NULL;
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $address   = mysqli_real_escape_string($conn, $_POST['address']);
    $course    = mysqli_real_escape_string($conn, $_POST['course'] ?? '');

    // Handle RFID: set to NULL if empty to avoid duplicate key errors
    $rfidValue = empty($rfid) ? 'NULL' : "'$rfid'";
    $gradeValue = $grade === NULL ? 'NULL' : $grade;
    $strandValue = $strand === NULL ? 'NULL' : "'$strand'";

    // Handle section as a string name and map/create section entry
    $sectionVal = 'NULL';
    if (!empty($_POST['section_id'])) {
        $sName = mysqli_real_escape_string($conn, $_POST['section_id']);
        $checkSection = mysqli_query($conn, "SELECT section_id FROM sections WHERE section_name = '$sName' AND level = '$year' LIMIT 1");
        if ($rowS = mysqli_fetch_assoc($checkSection)) {
            $sectionVal = $rowS['section_id'];
        } else {
            mysqli_query($conn, "INSERT INTO sections (section_name, level) VALUES ('$sName', '$year')");
            $sectionVal = mysqli_insert_id($conn);
        }
    }

    $updateQuery = "UPDATE students 
                    SET rfid_number=$rfidValue, 
                        first_name='$firstname', 
                        last_name='$lastname', 
                        year_id='$year', 
                        section_id=$sectionVal,
                        grade=$gradeValue,
                        strand=$strandValue,
                        email='$email', 
                        address='$address',
                        course='$course' 
                    WHERE student_id='$editId'";

    if (mysqli_query($conn, $updateQuery)) {
        $statusMsg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <strong>Success!</strong> Student updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        $statusMsg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> ' . htmlspecialchars(mysqli_error($conn)) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    }
}

// ✅ Handle Edit/Update Request for lib_regulars
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id']) && isset($_POST['edit_type']) && $_POST['edit_type'] === 'regular') {
    $editId    = intval($_POST['edit_id']);
    $firstname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $address   = mysqli_real_escape_string($conn, $_POST['address']);

    $updateQuery = "UPDATE lib_regulars 
                    SET firstname='$firstname', lastname='$lastname', email='$email', address='$address'
                    WHERE id=$editId";

    if (mysqli_query($conn, $updateQuery)) {
        $statusMsg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <strong>Success!</strong> User updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        $statusMsg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> ' . htmlspecialchars(mysqli_error($conn)) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    }
}

// ✅ Handle Edit/Update Request for employees
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id']) && isset($_POST['edit_type']) && $_POST['edit_type'] === 'employee') {
    $editId    = mysqli_real_escape_string($conn, $_POST['edit_id']);
    $firstname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $address   = mysqli_real_escape_string($conn, $_POST['address']);
    $course    = mysqli_real_escape_string($conn, $_POST['course'] ?? '');

    // Check if address column exists before updating
    $addrCheck = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'employees' AND COLUMN_NAME = 'address' AND TABLE_SCHEMA = DATABASE()");
    $addressUpdateSql = (mysqli_num_rows($addrCheck) > 0) ? ", address='$address'" : "";

    $updateQuery = "UPDATE employees 
                    SET firstname='$firstname', 
                        lastname='$lastname', 
                        email='$email'
                        $addressUpdateSql,
                        assigned_course='$course' 
                    WHERE employee_id='$editId'";

    if (mysqli_query($conn, $updateQuery)) {
        $statusMsg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <strong>Success!</strong> Staff updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        $statusMsg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> ' . htmlspecialchars(mysqli_error($conn)) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    }
}

// ✅ Fetch records from both students and employees tables
// ✅ Pagination & Search Configuration
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

// Check if address column exists in employees table to avoid Fatal Error
$colCheck = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'employees' AND COLUMN_NAME = 'address' AND TABLE_SCHEMA = DATABASE()");
$employeeAddressSql = (mysqli_num_rows($colCheck) > 0) ? "e.address" : "'' AS address";

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$searchParam = !empty($search) ? "&search=" . urlencode($search) : "";
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$filterParam = ($filter !== 'all') ? "&filter=" . urlencode($filter) : "";

$whereClause = " WHERE 1=1 ";
if (!empty($search)) {
    $whereClause .= " AND (full_name LIKE '%$search%' 
                     OR email LIKE '%$search%' 
                     OR rfid_number LIKE '%$search%' 
                     OR role LIKE '%$search%' 
                     OR grade LIKE '%$search%' 
                     OR strand LIKE '%$search%' 
                     OR department LIKE '%$search%')";
}

if ($filter === 'college') {
    $whereClause .= " AND role = 'Student' AND department = 'Student'";
} elseif ($filter === 'elementary') {
    $whereClause .= " AND role = 'Student' AND department = 'Elementary'";
} elseif ($filter === 'jhs') {
    $whereClause .= " AND role = 'Student' AND department = 'Junior High'";
} elseif ($filter === 'shs') {
    $whereClause .= " AND role = 'Student' AND department = 'Senior High'";
} elseif ($filter === 'teaching') {
    $whereClause .= " AND (role IN ('Teacher', 'Instructor', 'Professor', 'Faculty', 'Teaching Staff') OR role LIKE '%Teaching%')";
} elseif ($filter === 'non-teaching') {
    $whereClause .= " AND role NOT IN ('Student', 'Teacher', 'Instructor', 'Professor', 'Faculty', 'Teaching Staff') AND role NOT LIKE '%Teaching%'";
}

// ✅ Get Total Count for Pagination
$countQuery = "
    SELECT COUNT(*) as total FROM (
        SELECT 
            CONCAT(s.first_name, ' ', s.last_name) AS full_name,
            s.email,
            s.rfid_number,
            'Student' as role,
            s.course as department,
            s.grade,
            s.strand,
            s.department as student_dept
        FROM students s
        UNION ALL
        SELECT 
            CONCAT(e.firstname, ' ', e.lastname) AS full_name,
            e.email,
            e.rfid_number,
            COALESCE(r.role_name, 'Staff') as role,
            e.assigned_course as department,
            NULL as grade,
            NULL as strand,
            'Staff' as student_dept
        FROM employees e
        LEFT JOIN roles r ON e.role_id = r.role_id
    ) as combined_count
    $whereClause
    " . (!empty($filter) && $filter !== 'all' && $filter !== 'teaching' && $filter !== 'non-teaching' ? " AND student_dept = '" . ($filter === 'college' ? 'Student' : ($filter === 'elementary' ? 'Elementary' : ($filter === 'jhs' ? 'Junior High' : 'Senior High'))) . "'" : "") . "
";
$countResult = mysqli_query($conn, $countQuery);
$countRow = mysqli_fetch_assoc($countResult);
$total_rows = $countRow ? intval($countRow['total']) : 0;
$total_pages = ceil($total_rows / $records_per_page);

// ✅ Fetch records
$studentQuery = "
    SELECT * FROM (
        SELECT 
            s.student_id AS user_id,
            s.rfid_number,
            CONCAT(s.first_name, ' ', s.last_name) AS full_name,
            s.first_name,
            s.last_name,
            s.middle_name,
            s.suffix,
            s.year_id,
            y.year_name,
            s.section_id,
            sect.section_name,
            s.grade,
            s.strand,
            s.email,
            s.address,
            NULL as phone_number,
            NULL as gender,
            s.profile_picture,
            s.status,
            s.created_at,
            'Student' AS role,
            'student' AS source,
            s.course AS department,
            s.eligible_status,
            (SELECT COUNT(*) FROM lib_rfid_loan WHERE student_id = s.student_id AND status = 'borrowed') as borrowed_count
        FROM students s
        LEFT JOIN year_levels y ON s.year_id = y.year_id
        LEFT JOIN sections sect ON s.section_id = sect.section_id
        
        UNION ALL
        
        SELECT 
            e.employee_id AS user_id,
            e.rfid_number,
            CONCAT(e.firstname, ' ', e.lastname) AS full_name,
            e.firstname AS first_name,
            e.lastname AS last_name,
            NULL AS middle_name,
            NULL AS suffix,
            NULL AS year_id,
            NULL as year_name,
            NULL AS section_id,
            e.assigned_course as section_name,
            NULL AS grade,
            NULL AS strand,
            e.email,
            $employeeAddressSql,
            NULL AS phone_number,
            NULL AS gender,
            e.profile_pic AS profile_picture,
            'active' AS status,
            NOW() AS created_at,
            COALESCE(r.role_name, 'Staff') AS role,
            'employee' AS source,
            e.assigned_course AS department,
            e.eligible_status,
            (SELECT COUNT(*) FROM lib_rfid_loan WHERE student_id = e.employee_id AND status = 'borrowed') as borrowed_count
        FROM employees e
        LEFT JOIN roles r ON e.role_id = r.role_id
    ) AS combined
    $whereClause
    ORDER BY created_at DESC
    LIMIT $offset, $records_per_page
";

$studentResult = mysqli_query($conn, $studentQuery);

if (!$studentResult) {
    die('Query Error: ' . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include "partials/head.php";?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .btn-edit, .btn-delete {
      background: none;
      border: none;
      padding: 8px 12px;
      cursor: pointer;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      border-radius: 5px;
    }
    
    .btn-edit {
      color: #007bff;
    }
    
    .btn-edit:hover {
      background-color: rgba(0, 123, 255, 0.1);
      color: #0056b3;
      transform: scale(1.15);
    }
    
    .btn-delete {
      color: #dc3545;
    }
    
    .btn-delete:hover {
      background-color: rgba(220, 53, 69, 0.1);
      color: #c82333;
      transform: scale(1.15);
    }
    
    .action-icons {
      display: flex;
      gap: 5px;
      align-items: center;
      justify-content: flex-start;
    }

    /* Light Badge Styles for better visibility */
    .badge {
      font-weight: 600;
      padding: 0.5em 0.75em;
      letter-spacing: 0.3px;
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
          <div class="row">
            <div class="col-12 grid-margin stretch-card">
              <div class="card shadow-sm rounded-3">
                <div class="card-body">
                  <?php if (isset($statusMsg) && !empty($statusMsg)): ?>
                    <div class="mb-3">
                      <?php echo $statusMsg; ?>
                    </div>
                  <?php endif; ?>
                  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <h4 class="card-title mb-0">📊 Borrower's Records</h4>
                    
                    <div class="btn-group my-2" role="group" aria-label="User Filter">
                      <a href="?filter=all<?= $searchParam ?>" class="btn btn-sm <?= ($filter == 'all') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-users me-1"></i> All
                      </a>
                      <a href="?filter=elementary<?= $searchParam ?>" class="btn btn-sm <?= ($filter == 'elementary') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-child me-1"></i> Elementary
                      </a>
                      <a href="?filter=jhs<?= $searchParam ?>" class="btn btn-sm <?= ($filter == 'jhs') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-school me-1"></i> JHS
                      </a>
                      <a href="?filter=shs<?= $searchParam ?>" class="btn btn-sm <?= ($filter == 'shs') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-graduation-cap me-1"></i> SHS
                      </a>
                      <a href="?filter=college<?= $searchParam ?>" class="btn btn-sm <?= ($filter == 'college') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-user-graduate me-1"></i> College
                      </a>
                      <a href="?filter=teaching<?= $searchParam ?>" class="btn btn-sm <?= ($filter == 'teaching') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-chalkboard-teacher me-1"></i> Teaching
                      </a>
                      <a href="?filter=non-teaching<?= $searchParam ?>" class="btn btn-sm <?= ($filter == 'non-teaching') ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-user-tie me-1"></i> Non-Teaching
                      </a>
                    </div>

                    <form method="GET" action="" class="d-flex my-2" style="max-width: 300px;">
                      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
                      <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?= htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">
                          <i class="fas fa-search"></i>
                        </button>
                        <?php if(!empty($search)): ?>
                          <a href="table_student.php?filter=<?= urlencode($filter) ?>" class="btn btn-outline-secondary" title="Clear Search">
                            <i class="fas fa-times"></i>
                          </a>
                        <?php endif; ?>
                      </div>
                    </form>
                  </div>
                  <?php
                    // Define visibility flags based on filter
                    $isAll = ($filter === 'all');
                    $isCollege = ($filter === 'college');
                    $isElementary = ($filter === 'elementary');
                    $isJHS = ($filter === 'jhs');
                    $isSHS = ($filter === 'shs');
                    $isStaff = ($filter === 'teaching' || $filter === 'non-teaching');

                    $showDept = ($isAll || $isCollege || $isStaff);
                    $showYearSection = ($isAll || $isCollege);
                    $showGradeSection = ($isAll || $isElementary || $isJHS || $isSHS);
                    $showStrand = ($isAll || $isSHS);

                    // Dynamic label for Dept/Course
                    $deptLabel = 'Department';
                    if ($isCollege) $deptLabel = 'Course';
                    if ($isAll) $deptLabel = 'Dept / Course';
                  ?>
                  <div class="table-responsive" style="max-width: 100%; overflow-x: auto;">
                    <table id="studentTable" class="table table-hover" style="min-width: 1000px;">
                      <colgroup>
                        <col style="width: 50px;">
                        <col style="width: 100px;">
                        <col style="min-width: 180px;">
                        <col style="width: 100px;">
                        <?php if ($showDept): ?><col style="width: 120px;"><?php endif; ?>
                        <?php if ($showYearSection): ?><col style="width: 90px;"><?php endif; ?>
                        <?php if ($showGradeSection): ?><col style="width: 60px;"><?php endif; ?>
                        <?php if ($showGradeSection): ?><col style="width: 90px;"><?php endif; ?>
                        <?php if ($showStrand): ?><col style="width: 60px;"><?php endif; ?>
                        <col style="min-width: 150px;">
                        <col style="width: 80px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                      </colgroup>
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>RFID</th>
                          <th>Name</th>
                          <th>Role</th>
                          <?php if ($showDept): ?><th><?php echo $deptLabel; ?></th><?php endif; ?>
                          <?php if ($showYearSection): ?><th>Year</th><?php endif; ?>
                          <?php if ($showGradeSection): ?><th>Grade</th><?php endif; ?>
                          <?php if ($showGradeSection): ?><th>Section</th><?php endif; ?>
                          <?php if ($showStrand): ?><th>Strand</th><?php endif; ?>
                          <th>Email</th>
                          <th>Address</th>
                          <th>Borrow</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($studentResult && mysqli_num_rows($studentResult) > 0): ?>
                          <?php while ($row = mysqli_fetch_assoc($studentResult)): ?>
                            <?php
                              // Determine display values
                              $roleLabel = htmlspecialchars($row['role']);
                              $roleBadgeClass = ($row['role'] === 'Student') ? 'badge-light-info' : 'badge-light-primary';
                              
                              // YEAR
                              $yearDisplay = '-';
                              if ($row['source'] === 'student') {
                                  if (!empty($row['year_name'])) {
                                      $yearDisplay = '<span class="badge badge-light-info">' . htmlspecialchars($row['year_name']) . '</span>';
                                  } elseif (!empty($row['year_id'])) {
                                      $yearMap = ['1'=>'1st Year', '2'=>'2nd Year', '3'=>'3rd Year', '4'=>'4th Year'];
                                      $yearText = isset($yearMap[$row['year_id']]) ? $yearMap[$row['year_id']] : 'Year ' . htmlspecialchars($row['year_id']);
                                      $yearDisplay = '<span class="badge badge-light-info">' . $yearText . '</span>';
                                  } else {
                                      $yearDisplay = '<span class="text-muted small">N/A</span>';
                                  }
                              }

                              // SECTION / DEPT
                              $sectionDisplay = '-';
                              if ($row['source'] === 'student') {
                                  
                                  if (!empty($row['section_name'])) {
                                      $sectionText = htmlspecialchars($row['section_name']);
                                      $sectionDisplay = '<span class="badge badge-light-primary">' . $sectionText . '</span>';
                                  } elseif (!empty($row['section_id'])) {
                                      $sectionText = htmlspecialchars($row['section_id']);
                                      $sectionDisplay = '<span class="badge badge-light-primary">' . $sectionText . '</span>';
                                  } else {
                                      $sectionDisplay = '<span class="text-muted small">N/A</span>';
                                  }
                              } else {
                                  // For employees, we now show Department in a separate column, so section is N/A
                                  $sectionDisplay = '<span class="text-muted">—</span>';
                              }
                              
                              // Status Badge
                              $statusClass = ($row['status'] === 'active') ? 'badge-light-success' : 'badge-light-warning';
                            ?>
                            <tr>
                              <td class="text-muted small align-middle"><?php echo htmlspecialchars($row['user_id'] ?? ''); ?></td>
                              <td class="align-middle"><code><?php echo htmlspecialchars($row['rfid_number'] ?? 'N/A'); ?></code></td>
                              <td class="align-middle"><strong><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></strong></td>
                              <td class="align-middle"><span class="badge <?php echo $roleBadgeClass; ?>"><?php echo $roleLabel; ?></span></td>
                              
                              <?php if ($showDept): ?>
                              <td class="align-middle">
                                <?php if (!empty($row['department'])): ?>
                                  <span class="badge <?php echo ($row['source'] === 'employee') ? 'badge-light-primary' : 'badge-light-info'; ?>">
                                    <?php echo htmlspecialchars($row['department'] ?? ''); ?>
                                  </span>
                                <?php else: ?>
                                  <span class="text-muted small">—</span>
                                <?php endif; ?>
                              </td>
                              <?php endif; ?>

                              <?php if ($showYearSection): ?>
                              <td class="align-middle"><?php echo $yearDisplay; ?></td>
                              <?php endif; ?>

                              <?php if ($showGradeSection): ?>
                              <td class="align-middle">
                                <?php if ($row['source'] === 'student' && !empty($row['grade'])): ?>
                                  <span class="badge badge-light-success"><?php echo htmlspecialchars($row['grade'] ?? ''); ?></span>
                                <?php else: ?>
                                  <span class="text-muted small">—</span>
                                <?php endif; ?>
                              </td>
                              <td class="align-middle">
                                <?php if ($row['source'] === 'student'): ?>
                                    <?php if (!empty($row['section_name'])): ?>
                                        <span class="badge badge-light-primary"><?php echo htmlspecialchars($row['section_name']); ?></span>
                                    <?php elseif (!empty($row['section_id'])): ?>
                                        <span class="badge badge-light-primary"><?php echo htmlspecialchars($row['section_id']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                  <span class="text-muted small">—</span>
                                <?php endif; ?>
                              </td>
                              <?php endif; ?>

                              <?php if ($showStrand): ?>
                              <td class="align-middle">
                                <?php if ($row['source'] === 'student' && !empty($row['strand'])): ?>
                                  <span class="badge badge-light-warning"><?php echo htmlspecialchars($row['strand'] ?? ''); ?></span>
                                <?php else: ?>
                                  <span class="text-muted small">—</span>
                                <?php endif; ?>
                              </td>
                              <?php endif; ?>
                               <td class="align-middle text-muted"><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                               <td class="align-middle text-muted"><?php echo htmlspecialchars($row['address'] ?? 'N/A'); ?></td>
                               <td class="align-middle">
                                 <?php 
                                   $bCount = intval($row['borrowed_count'] ?? 0);
                                   $isManualRestricted = ($row['eligible_status'] == 0);
                                   
                                   if ($bCount >= 3): ?>
                                     <span class="badge badge-light-danger" data-bs-toggle="tooltip" title="3/3 Books Borrowed">
                                       <i class="fas fa-exclamation-triangle me-1"></i> Limit Reached (3)
                                     </span>
                                   <?php elseif ($isManualRestricted): ?>
                                     <span class="badge badge-light-danger" data-bs-toggle="tooltip" title="Manually Restricted by Admin">
                                       <i class="fas fa-user-slash me-1"></i> Restricted
                                     </span>
                                   <?php else: ?>
                                     <span class="badge badge-light-success" data-bs-toggle="tooltip" title="<?= $bCount; ?>/3 Books Borrowed">
                                       <i class="fas fa-check-circle me-1"></i> Eligible (<?= $bCount; ?>)
                                     </span>
                                   <?php endif; ?>
                               </td>
                               <td class="align-middle" style="white-space: nowrap;">
                                <span class="badge <?php echo $statusClass; ?>">
                                  <?php echo htmlspecialchars(ucfirst($row['status'] ?? 'N/A')); ?>
                                </span>
                              </td>
                              <td class="align-middle">
                                <div class="action-icons">
                                  <?php if ($row['source'] === 'student'): ?>
                                    <a href="?toggle_eligibility=<?= htmlspecialchars($row['user_id'] ?? ''); ?>&type=<?= $row['source']; ?>&current=<?= $row['eligible_status']; ?>&page=<?= $page; ?><?= $searchParam; ?>" 
                                       class="btn-edit" style="color: <?= ($row['eligible_status'] == 1) ? '#dc3545' : '#28a745'; ?>" 
                                       data-bs-toggle="tooltip" title="<?= ($row['eligible_status'] == 1) ? 'Disable Borrowing' : 'Enable Borrowing'; ?>">
                                      <i class="fas <?= ($row['eligible_status'] == 1) ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                    </a>
                                    <button type="button" class="btn-edit edit-btn" data-bs-toggle="tooltip" title="Edit User"
                                      data-id="<?= htmlspecialchars($row['user_id'] ?? ''); ?>"
                                      data-type="student"
                                      data-rfid="<?= htmlspecialchars($row['rfid_number'] ?? ''); ?>"
                                      data-firstname="<?= htmlspecialchars($row['first_name'] ?? ''); ?>"
                                      data-lastname="<?= htmlspecialchars($row['last_name'] ?? ''); ?>"
                                      data-year="<?= intval($row['year_id'] ?? 0); ?>"
                                      data-section="<?= htmlspecialchars($row['section_name'] ?? ''); ?>" 
                                      data-grade="<?= htmlspecialchars($row['grade'] ?? ''); ?>"
                                      data-strand="<?= htmlspecialchars($row['strand'] ?? ''); ?>"
                                      data-email="<?= htmlspecialchars($row['email'] ?? ''); ?>"
                                      data-address="<?= htmlspecialchars($row['address'] ?? ''); ?>"
                                      data-course="<?= htmlspecialchars($row['department'] ?? ''); ?>">
                                      <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-delete delete-btn" data-bs-toggle="tooltip" title="Delete User"
                                      data-id="<?= htmlspecialchars($row['user_id'] ?? ''); ?>"
                                      data-type="student">
                                      <i class="fas fa-trash-alt"></i>
                                    </button>
                                  <?php else: ?>
                                    <a href="?toggle_eligibility=<?= htmlspecialchars($row['user_id'] ?? ''); ?>&type=<?= $row['source']; ?>&current=<?= $row['eligible_status']; ?>&page=<?= $page; ?><?= $searchParam; ?>" 
                                       class="btn-edit" style="color: <?= ($row['eligible_status'] == 1) ? '#dc3545' : '#28a745'; ?>" 
                                       data-bs-toggle="tooltip" title="<?= ($row['eligible_status'] == 1) ? 'Disable Borrowing' : 'Enable Borrowing'; ?>">
                                       <i class="fas <?= ($row['eligible_status'] == 1) ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                     </a>
                                     <button type="button" class="btn-edit edit-btn" data-bs-toggle="tooltip" title="Edit User"
                                       data-id="<?= htmlspecialchars($row['user_id'] ?? ''); ?>"
                                       data-type="employee"
                                       data-rfid="<?= htmlspecialchars($row['rfid_number'] ?? ''); ?>"
                                       data-firstname="<?= htmlspecialchars($row['first_name'] ?? ''); ?>"
                                       data-lastname="<?= htmlspecialchars($row['last_name'] ?? ''); ?>"
                                       data-email="<?= htmlspecialchars($row['email'] ?? ''); ?>"
                                       data-address="<?= htmlspecialchars($row['address'] ?? ''); ?>"
                                       data-course="<?= htmlspecialchars($row['department'] ?? ''); ?>">
                                       <i class="fas fa-edit"></i>
                                     </button>
                                     <button type="button" class="btn-delete delete-btn" data-bs-toggle="tooltip" title="Delete User"
                                       data-id="<?= htmlspecialchars($row['user_id'] ?? ''); ?>"
                                       data-type="employee">
                                       <i class="fas fa-trash-alt"></i>
                                     </button>
                                   <?php endif; ?>
                                </div>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="<?= 7 + ($showDept ? 1 : 0) + ($showYearSection ? 2 : 0) + ($showGradeStrand ? 2 : 0) ?>" class="text-center text-muted py-4">
                              <i class="fas fa-inbox" style="font-size: 2rem; margin-right: 10px;"></i>
                              <p>No user records found.</p>
                            </td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                  
                  <!-- Pagination Controls -->
                  <?php if ($total_pages > 1): ?>
                    <?php 
                      // Preserve search in pagination links
                      // $searchParam is already defined at the top
                    ?>
                    <nav aria-label="User Table Pagination" class="mt-4">
                      <ul class="pagination justify-content-center">
                        <!-- Previous Link -->
                        <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                          <a class="page-link" href="<?= ($page <= 1) ? '#' : "?page=".($page - 1).$searchParam . $filterParam; ?>" tabindex="-1" aria-disabled="true">Previous</a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                          <li class="page-item <?php if($page == $i) { echo 'active'; } ?>">
                            <a class="page-link" href="?page=<?= $i . $searchParam . $filterParam; ?>"><?= $i; ?></a>
                          </li>
                        <?php endfor; ?>
                        
                        <!-- Next Link -->
                        <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
                          <a class="page-link" href="<?= ($page >= $total_pages) ? '#' : "?page=".($page + 1).$searchParam . $filterParam; ?>">Next</a>
                        </li>
                      </ul>
                    </nav>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit"></i> Edit User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editForm" method="POST" action="">
        <div class="modal-body">
          <input type="hidden" name="edit_id" id="edit_id">
          <input type="hidden" name="edit_type" id="edit_type" value="student">
          
          <div class="mb-3">
            <label for="rfid_number" class="form-label">RFID Number:</label>
            <input type="text" name="rfid_number" id="rfid_number" class="form-control" placeholder="Optional">
          </div>
          
          <div class="mb-3">
            <label for="first_name" class="form-label"><span class="text-danger">*</span> First Name:</label>
            <input type="text" name="first_name" id="first_name" class="form-control" required>
          </div>
          
          <div class="mb-3">
            <label for="last_name" class="form-label"><span class="text-danger">*</span> Last Name:</label>
            <input type="text" name="last_name" id="last_name" class="form-control" required>
          </div>
          
          <div class="mb-3">
            <label for="year_id" class="form-label"><span class="text-danger">*</span> Year Level:</label>
            <select name="year_id" id="year_id" class="form-select" required>
              <option value="">-- Select Year --</option>
              <?php 
                $yq = "SELECT year_id, year_name FROM year_levels ORDER BY year_id ASC"; 
                $yr = @mysqli_query($conn, $yq); 
                if($yr){ 
                  while($y = @mysqli_fetch_assoc($yr)){ 
                    echo '<option value="'.intval($y['year_id']).'">'.htmlspecialchars($y['year_name']).'</option>'; 
                  } 
                } 
              ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label for="section_id" class="form-label"><span class="text-danger">*</span> Section:</label>
            <input type="text" name="section_id" id="section_id" class="form-control" required placeholder="e.g. A, B, C, or D">
          </div>

          <div class="mb-3">
            <label for="course_input" class="form-label">Course (College):</label>
            <input type="text" name="course" id="course_input" class="form-control" placeholder="e.g. BSIT, BSN">
          </div>
          
          <div class="mb-3">
            <label for="grade" class="form-label">Grade (High School):</label>
            <select name="grade" id="grade" class="form-select">
              <option value="">-- Select Grade --</option>
              <option value="7">7</option>
              <option value="8">8</option>
              <option value="9">9</option>
              <option value="10">10</option>
              <option value="11">11</option>
              <option value="12">12</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label for="strand" class="form-label">Strand (High School):</label>
            <select name="strand" id="strand" class="form-select">
              <option value="">-- Select Strand --</option>
              <option value="ABM">ABM (Accountancy, Business, Management)</option>
              <option value="TVL">TVL (Technical-Vocational Livelihood)</option>
              <option value="STEM">STEM (Science, Technology, Engineering, Mathematics)</option>
              <option value="HUMS">HUMS (Humanities and Social Sciences)</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label for="email" class="form-label"><span class="text-danger">*</span> Email:</label>
            <input type="email" name="email" id="email" class="form-control" required>
          </div>
          
          <div class="mb-3">
            <label for="address" class="form-label">Address:</label>
            <textarea name="address" id="address" class="form-control" rows="2" placeholder="Optional"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-danger"><strong>⚠️ Warning:</strong></p>
        <p>Are you sure you want to delete this user? This action <strong>cannot be undone</strong> and will:</p>
        <ul>
          <li>Remove all user data</li>
          <li>Release the RFID card (if assigned)</li>
          <li>Delete all associated records</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a id="deleteLink" href="#" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete User</a>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Open Edit Modal with data
  function openEditModal(data) {
    try {
      if (!data || !data.id) {
        alert('Error: Unable to load user data');
        return;
      }
      
      // Populate form fields
      document.getElementById('edit_id').value = data.id || '';
      document.getElementById('edit_type').value = data.type || 'student'; // Set type
      document.getElementById('rfid_number').value = data.rfid || '';
      document.getElementById('first_name').value = data.firstname || '';
      document.getElementById('last_name').value = data.lastname || '';
      document.getElementById('email').value = data.email || '';
      document.getElementById('address').value = data.address || '';
      document.getElementById('course_input').value = data.course || '';
      
      // Handle visibility based on type
      const studentFields = ['year_id', 'section_id', 'grade', 'strand'];
      if (data.type === 'employee') {
          // Hide student-only fields for employees
          studentFields.forEach(id => {
              const el = document.getElementById(id);
              if (el && el.closest('.mb-3')) el.closest('.mb-3').style.display = 'none';
              if (el) el.required = false;
          });
          document.getElementById('editModalLabel').innerHTML = '<i class="fas fa-edit"></i> Edit Staff member';
      } else {
          // Show student fields
          studentFields.forEach(id => {
              const el = document.getElementById(id);
              if (el && el.closest('.mb-3')) el.closest('.mb-3').style.display = 'block';
              if (el && (id === 'year_id' || id === 'section_id')) el.required = true;
          });
          document.getElementById('editModalLabel').innerHTML = '<i class="fas fa-edit"></i> Edit Student';
      }

      // Set dropdown values
      if (data.section) {
        document.getElementById('section_id').value = data.section;
      }
      if (data.grade) {
        document.getElementById('grade').value = data.grade;
      }
      if (data.strand) {
        document.getElementById('strand').value = data.strand;
      }
      
      // Show modal
      const editModal = new bootstrap.Modal(document.getElementById('editModal'));
      editModal.show();
    } catch (error) {
      console.error('Error in openEditModal:', error);
      alert('Error opening edit form: ' + error.message);
    }
  }


  // Confirm Delete Action
  function confirmDelete(userId, type) {
    try {
      if (!userId) {
        alert('Error: Invalid user ID');
        return;
      }
      
      const deleteLink = document.getElementById('deleteLink');
      deleteLink.href = '?delete_id=' + encodeURIComponent(userId) + '&type=' + encodeURIComponent(type);
      
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
      deleteModal.show();
    } catch (error) {
      console.error('Error in confirmDelete:', error);
      alert('Error opening delete confirmation: ' + error.message);
    }
  }
  
  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add click handlers to edit buttons
    document.querySelectorAll('.edit-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const data = {
          id: this.getAttribute('data-id'),
          type: this.getAttribute('data-type'), // Added type
          rfid: this.getAttribute('data-rfid'),
          firstname: this.getAttribute('data-firstname'),
          lastname: this.getAttribute('data-lastname'),
          year: this.getAttribute('data-year'),
          section: this.getAttribute('data-section'),
          grade: this.getAttribute('data-grade'),
          strand: this.getAttribute('data-strand'),
          email: this.getAttribute('data-email'),
          address: this.getAttribute('data-address'),
          course: this.getAttribute('data-course')
        };
        openEditModal(data);
      });
    });
    
    // Add click handlers to delete buttons
    document.querySelectorAll('.delete-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const userId = this.getAttribute('data-id');
        const userType = this.getAttribute('data-type');
        confirmDelete(userId, userType);
      });
    });
    
    // Auto-dismiss success/error messages after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
      if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
        setTimeout(function() {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        }, 5000);
      }
    });
  });
</script>

</body>
</html>
