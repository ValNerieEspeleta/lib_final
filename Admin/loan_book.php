<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

$borrower = null; 
$statusMsg = "";

// Handle RFID scan submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uid']) && !isset($_POST['borrow'])) {
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);

    // Check students table first
    $sql = "SELECT 
                s.student_id as id, 
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
                'Student' as user_type,
                'students' as source_table
            FROM students s
            LEFT JOIN year_levels y ON s.year_id = y.year_id
            LEFT JOIN sections sec ON (s.section_id = sec.section_id OR (s.section_id = sec.section_name AND s.year_id = sec.level))
            WHERE s.rfid_number = '$uid' OR s.student_id = '$uid'
            LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $borrower = mysqli_fetch_assoc($result);
    } else {
        // Check employees table
        $sqlEmployee = "SELECT e.employee_id as id, e.firstname, e.lastname, e.email, e.assigned_course as address, 
                               1 as eligible_status, e.profile_pic as image_path, 
                               COALESCE(r.role_name, e.role_id) as user_type, COALESCE(e.rfid_number, e.employee_id) as uid,
                               'employees' as source_table
                        FROM employees e
                        LEFT JOIN roles r ON e.role_id = r.role_id
                        WHERE e.rfid_number = '$uid' OR e.employee_id = '$uid'
                        LIMIT 1";
        $resultEmployee = mysqli_query($conn, $sqlEmployee);
        
        if ($resultEmployee && mysqli_num_rows($resultEmployee) > 0) {
            $borrower = mysqli_fetch_assoc($resultEmployee);
        } else {
            $statusMsg = '<div class="alert alert-warning">⚠️ No user found with RFID/ID: <strong>' . htmlspecialchars($uid) . '</strong></div>';
        }
    }
}

// Handle Borrow Books submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['borrow'])) {
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);
    $accession_ids = $_POST['book_ids'] ?? []; // Receiving accession_ids here

    // Find borrower in students
    $sql = "SELECT student_id as id, eligible_status, 'students' as source_table FROM students WHERE rfid_number = '$uid' OR student_id = '$uid' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    $borrower_data = null;

    if ($result && mysqli_num_rows($result) > 0) {
        $borrower_data = mysqli_fetch_assoc($result);
    } else {
        // Find in employees
        $sqlE = "SELECT employee_id as id, 1 as eligible_status, 'employees' as source_table FROM employees WHERE rfid_number = '$uid' OR employee_id = '$uid' LIMIT 1";
        $resultE = mysqli_query($conn, $sqlE);
        if ($resultE && mysqli_num_rows($resultE) > 0) {
            $borrower_data = mysqli_fetch_assoc($resultE);
        }
    }

    if ($borrower_data) {
        $borrower_id = $borrower_data['id'];
        $source = $borrower_data['source_table'];
        $eligible = $borrower_data['eligible_status'] == 1;

        // CHECK CURRENT BORROWED COUNT
        $loanCountQuery = "SELECT COUNT(*) as borrowed_count FROM lib_rfid_loan WHERE student_id = '$borrower_id' AND status = 'borrowed'";
        $loanCountResult = mysqli_query($conn, $loanCountQuery);
        $currentBorrowedData = mysqli_fetch_assoc($loanCountResult);
        $currentBorrowed = $currentBorrowedData['borrowed_count'] ?? 0;
        $maxLimit = 3;

        // Auto-fix: If they have less than 3 books, they should be eligible
        if ($currentBorrowed < $maxLimit) {
            $eligible = true;
        }

        if ($eligible) {
            if (!empty($accession_ids)) {
                $requestedCount = count($accession_ids);
                
                if (($currentBorrowed + $requestedCount) > $maxLimit) {
                    $remaining = $maxLimit - $currentBorrowed;
                    $statusMsg = '<div class="alert alert-danger">❌ Limit Exceeded! Borrower currently has '.$currentBorrowed.' book(s). They can only borrow '.$remaining.' more. (Max: '.$maxLimit.')</div>';
                } else {
                    $count = 0;
                    foreach ($accession_ids as $acc_id) {
                        $acc_id = intval($acc_id);
                        
                        // Fetch book_id for this accession
                        $getB = mysqli_query($conn, "SELECT book_id FROM lib_accession_numbers WHERE id = $acc_id LIMIT 1");
                        if ($rowB = mysqli_fetch_assoc($getB)) {
                            $book_id = $rowB['book_id'];
                            
                            // Insert with accession_id
                            $insert = "INSERT INTO lib_rfid_loan (uid, student_id, book_id, accession_id, status, borrow_date) 
                                       VALUES ('$uid', '$borrower_id', '$book_id', '$acc_id', 'borrowed', NOW())";
                            
                            if (mysqli_query($conn, $insert)) {
                                // Update accession_number status to unavailable
                                mysqli_query($conn, "UPDATE lib_accession_numbers SET status = 'unavailable' WHERE id = $acc_id");
                                $count++;
                            }
                        }
                    }

                    // Update eligibility: Only disable if they reach the max limit
                    $newTotal = $currentBorrowed + $count;
                    if ($newTotal >= $maxLimit) {
                        if ($source === 'students') {
                            mysqli_query($conn, "UPDATE students SET eligible_status = 0 WHERE student_id = '$borrower_id'");
                        } else {
                            mysqli_query($conn, "UPDATE employees SET eligible_status = 0 WHERE employee_id = '$borrower_id'");
                        }
                    } else {
                        // Ensure eligibility is active if below limit
                        if ($source === 'students') {
                            mysqli_query($conn, "UPDATE students SET eligible_status = 1 WHERE student_id = '$borrower_id'");
                        } else {
                            mysqli_query($conn, "UPDATE employees SET eligible_status = 1 WHERE employee_id = '$borrower_id'");
                        }
                    }
                    
                    $statusMsg = '<div class="alert alert-success">✅ ' . $count . ' book(s) borrowed successfully! Total active borrows: ' . $newTotal . '</div>';
                }
            } else {
                $statusMsg = '<div class="alert alert-warning">⚠️ No books selected.</div>';
            }
        } else {
            $statusMsg = '<div class="alert alert-warning">⚠️ Borrower is not eligible (Maximum limit reached or restricted).</div>';
        }
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ Borrower not found.</div>';
    }
}

// Fetch available books (specific copies)
$books = [];
// Join accession_numbers to list individual copies
$bookSql = "SELECT 
                acc.id as accession_id, 
                acc.accession_number, 
                acc.acquisition_type,
                b.book_id, 
                b.title, 
                b.description, 
                b.status, 
                g.name as genre_name 
            FROM lib_accession_numbers acc
            JOIN lib_books b ON acc.book_id = b.book_id
            LEFT JOIN lib_genres g ON b.genre_id = g.id
            WHERE acc.status = 'available' 
            ORDER BY b.title ASC, acc.accession_number ASC";
$bookResult = mysqli_query($conn, $bookSql);
while ($row = mysqli_fetch_assoc($bookResult)) { $books[] = $row; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    .profile-img { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; }
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
              <div class="card">
                <div class="card-body text-center">
                  <h4 class="card-title">Scan RFID Card</h4>
                  <form method="POST" action="">
                    <div class="form-group">
                      <input type="text" id="uid" name="uid" class="form-control text-center font-weight-bold" placeholder="Scan RFID..." autocomplete="off" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Fetch Profile</button>
                  </form>
                  <hr>
                  <h4 class="card-title">Borrow Books</h4>
                  <form method="POST" action="">
                    <input type="hidden" name="borrow" value="1">
                    <input type="hidden" name="uid" value="<?php echo htmlspecialchars($borrower['uid'] ?? ''); ?>">
                    <div class="form-group text-left">
                      <label>Select Books (Search by Title or ID)</label>
                      <select name="book_ids[]" id="book_ids" class="form-control" multiple="multiple" required>
                        <?php foreach ($books as $book): ?>
                          <option value="<?php echo $book['accession_id']; ?>">
                             <?php echo htmlspecialchars($book['title']); ?> 
                             (ID: <?php echo htmlspecialchars($book['book_id']); ?> | Copy: <?php echo htmlspecialchars($book['accession_number']); ?><?php echo $book['acquisition_type'] == 'Donated' ? ' | Donated' : ' | Purchased'; ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Borrow</button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-md-8">
              <div class="card shadow-sm">
                <div class="card-body">
                  <h4 class="card-title">
                    <?php 
                      if ($borrower && $borrower['source_table'] === 'employees') {
                        echo '👤 Borrower\'s Profile (Staff)';
                      } else {
                        echo '🎓 Borrower\'s Profile';
                      }
                    ?>
                  </h4>
                  <div id="profile-content">
                    <?php if ($borrower): ?>
                      <div class="d-flex align-items-center mb-4">
                        <?php
                          $imgSrc = '../img/defaulticon.png';
                          if (!empty($borrower['image_path'])) {
                             if (file_exists('../uploads/' . $borrower['image_path'])) $imgSrc = '../uploads/' . $borrower['image_path'];
                             elseif (file_exists($borrower['image_path'])) $imgSrc = $borrower['image_path'];
                          }
                        ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="profile-img me-4 border border-3 border-primary">
                        <div class="text-start">
                          <h3 class="fw-bold"><?php echo htmlspecialchars($borrower['firstname'] . ' ' . $borrower['lastname']); ?></h3>
                          <div class="mt-2 text-start">
                            <span class="badge bg-info px-3 py-2"><?php echo htmlspecialchars($borrower['user_type']); ?></span>
                            <?php if ($borrower['eligible_status'] == 1): ?>
                              <span class="badge bg-success px-3 py-2">Eligible to Borrow</span>
                            <?php else: ?>
                              <span class="badge bg-danger px-3 py-2">Not Eligible</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>

                      <div class="row text-start">
                        <div class="col-md-6 mb-3">
                          <p class="mb-1 text-muted">Email</p>
                          <p class="fs-6 fw-bold"><?php echo htmlspecialchars($borrower['email']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                          <p class="mb-1 text-muted"><?php echo ($borrower['source_table'] === 'employees') ? 'Department' : 'Address'; ?></p>
                          <p class="fs-6 fw-bold"><?php echo htmlspecialchars($borrower['address'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                          <p class="mb-1 text-muted">UID</p>
                          <p class="fs-6 fw-bold"><?php echo htmlspecialchars($borrower['uid']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                          <p class="mb-1 text-muted">Status</p>
                          <p class="fs-6 fw-bold <?php echo ($borrower['eligible_status'] == 1) ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ($borrower['eligible_status'] == 1) ? 'Eligible to Borrow' : 'Not Eligible'; ?>
                          </p>
                        </div>
                        <?php if ($borrower['source_table'] === 'employees'): ?>
                          <div class="col-md-6 mb-3">
                            <p class="mb-1 text-muted">Role</p>
                            <p class="fs-6 fw-bold"><?php echo htmlspecialchars($borrower['user_type'] ?? 'N/A'); ?></p>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <div class="text-center py-5">
                        <p class="fs-5 text-muted">No borrower data. Please scan a card.</p>
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
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    $(document).ready(function() {
      $('#book_ids').select2({ placeholder: "Search books...", allowClear: true, width: '100%' });
      document.getElementById("uid").focus();
    });
  </script>
</body>
</html>
