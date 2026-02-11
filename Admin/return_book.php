<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

$student = null; 
$statusMsg = "";
$loans = [];

/**
 * Helper: find borrower by uid in both students and employees tables
 */
function findBorrowerByUid($conn, $uid) {
    $u = mysqli_real_escape_string($conn, $uid);
    
    // Search students
    $sqlS = "SELECT s.student_id as id, s.rfid_number as uid, s.first_name as firstname, s.last_name as lastname, 
                    s.email, s.address, s.eligible_status, s.profile_picture as image_path,
                    y.year_name as year, s.section_id as section, 'Student' as user_type, 'students' as source_table
             FROM students s
             LEFT JOIN year_levels y ON s.year_id = y.year_id
             WHERE s.rfid_number = '$u' OR s.student_id = '$u'
             LIMIT 1";
    $resS = mysqli_query($conn, $sqlS);
    if ($resS && mysqli_num_rows($resS) > 0) return mysqli_fetch_assoc($resS);
    
    // Search employees
    $sqlE = "SELECT e.employee_id as id, COALESCE(e.rfid_number, e.employee_id) as uid, e.firstname, e.lastname, 
                    e.email, e.assigned_course as address, 1 as eligible_status, e.profile_pic as image_path,
                    COALESCE(r.role_name, e.role_id) as year, e.assigned_course as section, COALESCE(r.role_name, e.role_id) as user_type, 'employees' as source_table
             FROM employees e
             LEFT JOIN roles r ON e.role_id = r.role_id
             WHERE e.rfid_number = '$u' OR e.employee_id = '$u'
             LIMIT 1";
    $resE = mysqli_query($conn, $sqlE);
    if ($resE && mysqli_num_rows($resE) > 0) return mysqli_fetch_assoc($resE);

    return null;
}

// Handle RFID scan submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uid']) && !isset($_POST['return'])) {
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);
    $student = findBorrowerByUid($conn, $uid);

    if ($student) {
        $borrower_id = $student['id'];
        $loanSql = "SELECT l.id AS loan_id, l.book_id, l.borrow_date, b.title
                    FROM lib_rfid_loan l
                    JOIN lib_books b ON l.book_id = b.book_id
                    WHERE l.student_id = '$borrower_id' AND l.status = 'borrowed'
                    ORDER BY l.borrow_date ASC";
        $loanResult = mysqli_query($conn, $loanSql);
        while ($row = mysqli_fetch_assoc($loanResult)) { $loans[] = $row; }
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ No borrower found.</div>';
    }
}

// Handle Return Book submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['return'])) {
    $loan_ids = $_POST['loan_ids'] ?? [];
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);

    if (!empty($loan_ids)) {
        foreach ($loan_ids as $loan_id) {
            $loan_id = mysqli_real_escape_string($conn, $loan_id);
            $loanRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM lib_rfid_loan WHERE id = '$loan_id' LIMIT 1"));
            if ($loanRow) {
                $book_id = $loanRow['book_id'];
                if (mysqli_query($conn, "UPDATE lib_rfid_loan SET status = 'returned', return_date = NOW() WHERE id = '$loan_id'")) {
                    mysqli_query($conn, "UPDATE lib_books SET status = 'available' WHERE book_id = '$book_id'");
                }
            }
        }
        // Update eligibility if no more loans
        $borrower = findBorrowerByUid($conn, $uid);
        if ($borrower) {
            $id = $borrower['id'];
            $source = $borrower['source_table'];
            $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM lib_rfid_loan WHERE student_id = '$id' AND status = 'borrowed'"));
            if ($check['cnt'] < 3) {
                if ($source === 'students') mysqli_query($conn, "UPDATE students SET eligible_status = 1 WHERE student_id = '$id'");
                else mysqli_query($conn, "UPDATE employees SET eligible_status = 1 WHERE employee_id = '$id'");
            }
        }
        $statusMsg = '<div class="alert alert-success">✅ Books returned successfully!</div>';
    } else {
        $statusMsg = '<div class="alert alert-warning">⚠️ Please select books.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <style>
    .profile-img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; }
    .borrowed-book { border: 1px solid #ddd; padding: 12px; border-radius: 8px; margin-bottom: 12px; background: #f9f9f9; }
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
            <div class="col-md-4">
              <div class="card text-center">
                <div class="card-body">
                  <h4 class="card-title">Scan RFID Card</h4>
                  <form method="POST" action="">
                    <div class="form-group">
                      <p id="uid-display" class="form-control text-center font-weight-bold" style="background:#f8f9fa;">
                        <?php echo htmlspecialchars($student['uid'] ?? 'Waiting for scan...'); ?>
                      </p>
                      <input type="hidden" id="uid" name="uid" value="<?php echo htmlspecialchars($student['uid'] ?? ''); ?>">
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-md-8">
              <div class="card shadow-sm">
                <div class="card-body">
                  <h4 class="card-title">🎓 Borrower's Profile</h4>
                  <?php if ($student): ?>
                    <div class="d-flex align-items-center mb-4 text-start">
                      <?php
                        $imgSrc = '../img/defaulticon.png';
                        if (!empty($student['image_path'])) {
                          if (file_exists('../uploads/' . $student['image_path'])) $imgSrc = '../uploads/' . $student['image_path'];
                          elseif (file_exists($student['image_path'])) $imgSrc = $student['image_path'];
                        }
                      ?>
                      <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="profile-img me-4 border border-3 border-primary shadow-sm">
                      <div>
                        <h3 class="fw-bold"><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></h3>
                        <div class="mt-2 text-start">
                          <span class="badge bg-info px-3 py-2"><?php echo htmlspecialchars($student['user_type']); ?></span>
                          <span class="badge bg-success px-3 py-2">Active</span>
                        </div>
                      </div>
                    </div>

                    <div class="row text-start">
                      <div class="col-md-6 mb-3">
                        <p class="mb-1 text-muted">Email</p>
                        <p class="fs-6 fw-bold"><?php echo htmlspecialchars($student['email']); ?></p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <p class="mb-1 text-muted"><?php echo ($student['source_table'] === 'employees') ? 'Department' : 'Address'; ?></p>
                        <p class="fs-6 fw-bold"><?php echo htmlspecialchars($student['address']); ?></p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <p class="mb-1 text-muted">UID</p>
                        <p class="fs-6 fw-bold"><?php echo htmlspecialchars($student['uid']); ?></p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <p class="mb-1 text-muted"><?php echo ($student['source_table'] === 'employees') ? 'Role' : 'Grade/Year'; ?></p>
                        <p class="fs-6 fw-bold"><?php echo htmlspecialchars($student['year'] ?? 'N/A'); ?></p>
                      </div>
                    </div>

                    <hr>
                    <h5>📚 Borrowed Books</h5>
                    <?php if (!empty($loans)): ?>
                      <form method="POST" action="">
                        <input type="hidden" name="return" value="1">
                        <input type="hidden" name="uid" value="<?php echo htmlspecialchars($student['uid']); ?>">
                        <?php foreach ($loans as $loan): ?>
                          <div class="borrowed-book d-flex align-items-center text-start">
                            <input type="checkbox" name="loan_ids[]" value="<?php echo $loan['loan_id']; ?>" class="form-check-input me-3">
                            <div>
                              <p class="mb-0 fw-bold"><?php echo htmlspecialchars($loan['title']); ?></p>
                              <small class="text-muted">Borrowed: <?php echo htmlspecialchars($loan['borrow_date']); ?></small>
                            </div>
                          </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-danger btn-block mt-2">Return Selected</button>
                      </form>
                    <?php else: ?>
                      <p class="text-muted">No active loans.</p>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="text-center py-5 text-muted"> <p class="fs-5">Scan a student or staff card.</p> </div>
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
  <script>
    let uidInput = "";
    document.addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        const form = document.createElement("form");
        form.method = "POST";
        const input = document.createElement("input");
        input.type = "hidden"; input.name = "uid"; input.value = uidInput;
        form.appendChild(input); document.body.appendChild(form); form.submit();
      } else if (e.key.length === 1) uidInput += e.key;
    });
  </script>
</body>
</html>
