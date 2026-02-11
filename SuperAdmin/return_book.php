<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

$student = null; // will hold borrower info whether from students or regulars
$statusMsg = "";
$loans = [];

/**
 * Helper: find borrower by uid in both students and employees tables
 * Returns associative array with all borrower info or null if not found.
 */
function findBorrowerByUid($conn, $uid) {
    $u = mysqli_real_escape_string($conn, $uid);
    
    // Search students table first
    $sqlS = "SELECT s.student_id as id, 
                    s.rfid_number as uid, 
                    s.first_name as firstname, 
                    s.last_name as lastname, 
                    s.email, 
                    s.address, 
                    s.eligible_status, 
                    s.profile_picture as image_path,
                    s.year_id,
                    y.year_name as year,
                    s.section_id,
                    sec.section_name as section,
                    s.grade,
                    s.strand,
                    s.course,
                    'Student' as user_type,
                    'students' as source_table
             FROM students s
             LEFT JOIN year_levels y ON s.year_id = y.year_id
             LEFT JOIN sections sec ON (s.section_id = sec.section_id OR (s.section_id = sec.section_name AND s.year_id = sec.level))
             WHERE s.rfid_number = '$u' OR s.student_id = '$u' OR CAST(s.student_id AS CHAR) = '$u'
             LIMIT 1";
    $resS = mysqli_query($conn, $sqlS);
    if ($resS && mysqli_num_rows($resS) > 0) {
        return mysqli_fetch_assoc($resS);
    }
    
    // Search employees table if not found in students
    $sqlE = "SELECT e.employee_id as id, 
                    COALESCE(e.rfid_number, e.employee_id) as uid, 
                    e.firstname, 
                    e.lastname, 
                    e.email, 
                    e.assigned_course as address, 
                    e.eligible_status, 
                    e.profile_pic as image_path,
                    NULL as year_id,
                    COALESCE(r.role_name, e.role_id) as year,
                    e.assigned_course as section,
                    COALESCE(r.role_name, e.role_id) as user_type,
                    'employees' as source_table
             FROM employees e
             LEFT JOIN roles r ON e.role_id = r.role_id
             WHERE e.rfid_number = '$u' OR e.employee_id = '$u' OR CAST(e.employee_id AS CHAR) = '$u'
             LIMIT 1";
    $resE = mysqli_query($conn, $sqlE);
    if ($resE && mysqli_num_rows($resE) > 0) {
        return mysqli_fetch_assoc($resE);
    }

    return null;
}

// Handle RFID scan submission (fetch borrower info from either table)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uid']) && !isset($_POST['return'])) {
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);

    $borrower = findBorrowerByUid($conn, $uid);

    if ($borrower) {
        $student = $borrower;

        // Fetch all active loans (status borrowed) for that borrower id (works for students or regulars)
        $borrower_id = $student['id'];
        $loanSql = "SELECT l.id AS loan_id, l.book_id, l.borrow_date, b.title
                    FROM lib_rfid_loan l
                    JOIN lib_books b ON l.book_id = b.book_id
                    WHERE l.student_id = '$borrower_id' AND l.status = 'borrowed'
                    ORDER BY l.borrow_date ASC";
        $loanResult = mysqli_query($conn, $loanSql);
        if ($loanResult && mysqli_num_rows($loanResult) > 0) {
            while ($row = mysqli_fetch_assoc($loanResult)) {
                $loans[] = $row;
            }
        }
    } else {
        $student = null;
        $statusMsg = '<div class="alert alert-danger">❌ No borrower found with this UID. Please check if the RFID is registered in the system.</div>';
    }
}

// Handle Return Book submission (multiple books)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['return'])) {
    $loan_ids = $_POST['loan_ids'] ?? [];
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);

    if (!empty($loan_ids)) {
        $processedBorrowerId = null;
        foreach ($loan_ids as $loan_id) {
            $loan_id = mysqli_real_escape_string($conn, $loan_id);

            // Fetch loan (ensure it belongs to the provided UID and is borrowed)
            $loanSql = "SELECT * FROM lib_rfid_loan WHERE id = '$loan_id' AND uid = '$uid' AND status = 'borrowed' LIMIT 1";
            $loanResult = mysqli_query($conn, $loanSql);

            if ($loanResult && mysqli_num_rows($loanResult) > 0) {
                $loanRow = mysqli_fetch_assoc($loanResult);
                $book_id = $loanRow['book_id'];
                $student_id = $loanRow['student_id'];

                // Save borrower id (they should all be same borrower)
                $processedBorrowerId = $student_id;

                // Update loan status to returned
                $updateLoan = "UPDATE lib_rfid_loan SET status = 'returned', return_date = NOW() WHERE id = '$loan_id'";
                if (mysqli_query($conn, $updateLoan)) {
                    // Make book available again
                    $updateBook = "UPDATE lib_books SET status = 'available' WHERE book_id = '$book_id'";
                    mysqli_query($conn, $updateBook);
                }
            }
        }

        if ($processedBorrowerId !== null) {
            // Determine whether this borrower id belongs to students or employees
            $borrowerAfter = findBorrowerByUid($conn, $uid);

            if ($borrowerAfter) {
                $source = $borrowerAfter['source_table'];
                $idToUpdate = $borrowerAfter['id'];

                // Check if borrower still has active loans
                $checkSql = "SELECT COUNT(*) AS cnt FROM lib_rfid_loan WHERE student_id = '$idToUpdate' AND status = 'borrowed'";
                $checkResult = mysqli_query($conn, $checkSql);
                $countRow = mysqli_fetch_assoc($checkResult);

                if ($countRow['cnt'] == 0) {
                    // Update eligible_status to 1 (re-enable borrowing)
                    if ($source === 'students') {
                        mysqli_query($conn, "UPDATE students SET eligible_status = 1 WHERE student_id = '$idToUpdate'");
                    } elseif ($source === 'employees') {
                        mysqli_query($conn, "UPDATE employees SET eligible_status = 1 WHERE employee_id = '$idToUpdate'");
                    }
                }
            }
        }

        $statusMsg = '<div class="alert alert-success">✅ Selected books returned successfully!</div>';
    } else {
        $statusMsg = '<div class="alert alert-warning">⚠️ Please select at least one book to return.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <style>
    .profile-img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
    }
    .borrowed-book {
      border: 1px solid #ddd;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 12px;
      background: #f9f9f9;
    }
    /* Spinner Loader */
    .spinner-border {
      width: 3rem;
      height: 3rem;
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

          <!-- Status Message -->
          <?php if ($statusMsg) echo $statusMsg; ?>

          <div class="row">
            <div class="col-md-4 grid-margin stretch-card">
              <div class="card">
                <div class="card-body text-center">
                  <h4 class="card-title">Scan RFID Card</h4>
                  <p class="card-description">Scan to return books - Students & Staff</p>

                  <form method="POST" action="">
                    <div class="form-group">
                      <label for="uid">UID</label>
                      <p id="uid-display" class="form-control text-center font-weight-bold" style="background:#f8f9fa;">
                        <?php echo isset($student['uid']) ? htmlspecialchars($student['uid']) : 'Waiting for scan...'; ?>
                      </p>
                      <input type="hidden" id="uid" name="uid" value="<?php echo isset($student['uid']) ? htmlspecialchars($student['uid']) : ''; ?>" required>
                    </div>
                    <button type="submit" id="scan-submit" style="display:none;">Submit</button>
                  </form>

                </div>
              </div>
            </div>

            <!-- Borrower Profile Card -->
            <div class="col-md-8 grid-margin stretch-card">
              <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body">
                  <h4 class="card-title mb-4">
                    <?php 
                      if ($student && $student['user_type'] !== 'Student') {
                        echo '👤 Borrower\'s Profile (Staff)';
                      } else {
                        echo '🎓 Borrower\'s Profile';
                      }
                    ?>
                  </h4>

                  <!-- Loader -->
                  <div id="profile-loader" class="text-center py-5" style="display:none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 fw-bold text-muted">Wait for the validation...</p>
                  </div>

                  <div id="profile-content" style="display:<?php echo $student ? 'block' : 'none'; ?>;">
                 <?php if ($student): ?>
  <div class="d-flex align-items-center mb-4">
    <?php
    // ✅ Fixed image handling
    $imgSrc = '../img/defaulticon.png'; // Default image path
    
    if (!empty($student['image_path'])) {
        // Check if image_path is just a filename (no directory)
        if (strpos($student['image_path'], '/') === false) {
            // Just filename - check in uploads directory
            if (file_exists('../uploads/' . $student['image_path'])) {
                $imgSrc = '../uploads/' . $student['image_path'];
            }
        } else {
            // Full or relative path provided
            if (file_exists($student['image_path'])) {
                $imgSrc = $student['image_path'];
            } elseif (file_exists('../uploads/' . basename($student['image_path']))) {
                $imgSrc = '../uploads/' . basename($student['image_path']);
            }
        }
    }
    ?>
    
    <!-- Fixed: Use $imgSrc instead of undefined $photoPath -->
    <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
         alt="Profile Photo" 
         class="profile-img me-4 border border-3 border-primary shadow-sm"
         onerror="this.src='../img/defaulticon.png'">

    <div class="text-start">
      <h3 class="mb-1 fw-bold text-dark">
        <?php echo htmlspecialchars($student['firstname']) . " " . htmlspecialchars($student['lastname']); ?>
      </h3>

                        <div class="mt-2">
                          <!-- Show user type badge -->
                          &nbsp;&nbsp;&nbsp;<?php if (isset($student['user_type'])): ?>
                            <span class="badge bg-info px-3 py-2">
                              <?php echo htmlspecialchars($student['user_type']); ?>
                            </span>
                          <?php else: ?>
                            <span class="badge bg-secondary px-3 py-2">Student</span>
                          <?php endif; ?>

                          &nbsp;&nbsp;
                          <?php if (!empty($student['eligible_status']) && $student['eligible_status'] != 0): ?>
                            <span class="badge bg-success px-3 py-2">Eligible to Borrow</span>
                          <?php else: ?>
                            <span class="badge bg-warning px-3 py-2">Can Return Books</span>
                          <?php endif; ?>
                        </div>

                      </div>
                    </div>

                    <div class="row text-start">
                      <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted">Email</p>
                        <p class="fs-6 text-dark"><?php echo htmlspecialchars($student['email']); ?></p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted"><?php echo ($student['source_table'] === 'employees') ? 'Department' : 'Address'; ?></p>
                        <p class="fs-6 text-dark"><?php echo htmlspecialchars($student['address']); ?></p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted">UID</p>
                        <p class="fs-6 text-dark"><?php echo htmlspecialchars($student['uid']); ?></p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted">Status</p>
                        <?php if (!empty($student['eligible_status']) && $student['eligible_status'] != 0): ?>
                          <p class="fs-6 text-success fw-bold">Eligible to Borrow</p>
                        <?php else: ?>
                          <p class="fs-6 text-warning fw-bold">Can Return Books</p>
                        <?php endif; ?>
                      </div>

                      <?php if ($student['user_type'] === 'Student'): ?>
                        <?php 
                        // Check if high school student (has grade and strand) or college student (has year and section)
                        $isHighSchool = !empty($student['grade']) && !empty($student['strand']);
                        ?>
                        
                        <?php if ($isHighSchool): ?>
                            <!-- High School Student Info -->
                            <div class="col-md-6 mb-3">
                                <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-school me-2"></i>Grade</p>
                                <p class="fs-6 text-dark">
                                    <span class="badge bg-success"><?php echo htmlspecialchars($student['grade']); ?></span>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-book-outline me-2"></i>Strand</p>
                                <p class="fs-6 text-dark">
                                    <span class="badge bg-warning"><?php echo htmlspecialchars($student['strand']); ?></span>
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- College Student Info -->
                            <div class="col-md-6 mb-3">
                                <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-school me-2"></i>Year Level</p>
                                <p class="fs-6 text-dark">
                                    <?php 
                                    if (!empty($student['year'])) {
                                        echo htmlspecialchars($student['year']);
                                    } elseif (!empty($student['year_id'])) {
                                        // Fallback to year_id if year_name from JOIN is NULL
                                        $yearMap = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'];
                                        echo isset($yearMap[$student['year_id']]) ? $yearMap[$student['year_id']] : 'Year ' . htmlspecialchars($student['year_id']);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-account-group-outline me-2"></i>Section</p>
                                <p class="fs-6 text-dark">
                                    <?php 
                                    $section = isset($student['section']) && !empty($student['section']) ? htmlspecialchars($student['section']) : (isset($student['section_id']) && !empty($student['section_id']) ? 'Section ' . htmlspecialchars($student['section_id']) : 'N/A');
                                    echo $section;
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>
                      <?php else: ?>
                        <!-- Employee Info -->
                        <div class="col-md-6 mb-3">
                          <p class="mb-1 fw-semibold text-muted">Role</p>
                          <p class="fs-6 text-dark"><?php echo htmlspecialchars($student['year'] ?? 'N/A'); ?></p>
                        </div>
                      <?php endif; ?>
                    </div>

                    <!-- Borrowed Books Info -->
                    <hr>
                    <h5 class="mb-3">📚 Borrowed Books</h5>
                    <?php if (!empty($loans)): ?>
                      <form method="POST" action="">
                        <input type="hidden" name="return" value="1">
                        <input type="hidden" name="uid" value="<?php echo htmlspecialchars($student['uid']); ?>">

                        <div style="max-height: 250px; overflow-y: auto; padding-right: 10px;">
                          <?php foreach ($loans as $loan): ?>
                            <div class="borrowed-book d-flex align-items-center">
                              <input type="checkbox" name="loan_ids[]" value="<?php echo $loan['loan_id']; ?>" class="form-check-input me-2">
                              <div>
                                <p class="mb-1"><strong>Title:</strong> <?php echo htmlspecialchars($loan['title']); ?></p>
                                <p class="mb-0"><strong>Borrowed On:</strong> <?php echo htmlspecialchars($loan['borrow_date']); ?></p>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-danger mt-3">Return Selected Books</button>
                      </form>
                    <?php else: ?>
                      <p class="text-muted mt-3">No borrowed books.</p>
                    <?php endif; ?>

                  <?php else: ?>
                    <div class="text-center text-muted py-5">
                      <i class="mdi mdi-account-circle-outline display-1 d-block mb-3"></i>
                      <p class="fs-5">No borrower data yet. Please scan a card (Student or Staff).</p>
                    </div>
                  <?php endif; ?>
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

  <!-- JS -->
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <script>
  const uidInput = document.getElementById("uid");
  const uidDisplay = document.getElementById("uid-display");
  const scanSubmit = document.getElementById("scan-submit");
  const profileLoader = document.getElementById("profile-loader");
  const profileContent = document.getElementById("profile-content");

  let scanning = false;

  // Simulate RFID scan
  document.addEventListener("keydown", function(e) {
    if (!scanning) {
      // New scan → reset old UID
      uidInput.value = "";
      uidDisplay.textContent = "Scanning...";
      scanning = true;
    }

    if (e.key === "Enter") {
      e.preventDefault();
      if (uidInput.value.trim() !== "") {
        uidDisplay.textContent = uidInput.value;

        // Show loader in profile card
        profileLoader.style.display = "block";
        profileContent.style.display = "none";

        // After 2 seconds, submit form
        setTimeout(() => {
          scanSubmit.click();
          scanning = false; // ready for next scan
        }, 2000);
      }
    } else {
      if (e.key.length === 1) {
        uidInput.value += e.key;
        uidDisplay.textContent = uidInput.value; // live update display
      }
    }
  });
  </script>
</body>
</html>
