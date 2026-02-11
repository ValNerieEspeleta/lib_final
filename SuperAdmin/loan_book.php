<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

$borrower = null; // unified variable for student/regular
$statusMsg = "";

// Handle RFID scan submission (fetch borrower info from either table)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uid']) && !isset($_POST['borrow'])) {
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);

    // Check students table first
    $sql = "SELECT 
                s.student_id as id, 
                s.rfid_number as uid, 
                s.rfid_number,
                s.first_name as firstname, 
                s.last_name as lastname, 
                s.email, 
                s.address, 
                s.eligible_status as eligible_status, 
                0 as late_timeout_warnings,
                s.year_id, 
                y.year_name as year, 
                s.section_id,
                sec.section_name as section,
                s.course,
                s.grade,
                s.strand,
                s.profile_picture as image_path,
                'Student' as user_type
            FROM students s
            LEFT JOIN year_levels y ON s.year_id = y.year_id
            LEFT JOIN sections sec ON (s.section_id = sec.section_id OR (s.section_id = sec.section_name AND s.year_id = sec.level))
            WHERE s.rfid_number = '$uid' OR s.student_id = '$uid' OR CAST(s.student_id AS CHAR) = '$uid'
            LIMIT 1";
            
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        $statusMsg = '<div class="alert alert-danger">Database error: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    } else if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $borrower = [
            'source_table' => 'students',
            'id' => $row['id'],
            'uid' => $row['uid'],
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'email' => $row['email'],
            'address' => $row['address'],
            'eligible_status' => $row['eligible_status'],
            'late_timeout_warnings' => $row['late_timeout_warnings'] ?? 0,
            'image_path' => $row['image_path'],
            'user_type' => 'Student',
            'year' => $row['year'],
            'section' => $row['section'],
            'course' => $row['course'],
            'grade' => $row['grade'],
            'strand' => $row['strand']
        ];
    } else {
        // Check employees table
        $sqlEmployee = "SELECT e.employee_id as id, e.firstname, e.lastname, e.email, e.assigned_course as address, 
                               1 as eligible_status, 0 as late_timeout_warnings, e.profile_pic as profile_picture, 
                               COALESCE(r.role_name, e.role_id) as user_type, COALESCE(e.rfid_number, e.employee_id) as uid
                        FROM employees e
                        LEFT JOIN roles r ON e.role_id = r.role_id
                        WHERE e.rfid_number = '$uid' OR e.employee_id = '$uid' OR CAST(e.employee_id AS CHAR) = '$uid'
                        LIMIT 1";
        $resultEmployee = mysqli_query($conn, $sqlEmployee);
        
        if ($resultEmployee && mysqli_num_rows($resultEmployee) > 0) {
            $row = mysqli_fetch_assoc($resultEmployee);
            $borrower = [
                'source_table' => 'employees',
                'id' => $row['id'],
                'uid' => $row['uid'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'address' => $row['address'],
                'eligible_status' => $row['eligible_status'],
                'late_timeout_warnings' => $row['late_timeout_warnings'] ?? 0,
                'image_path' => $row['profile_picture'],
                'user_type' => $row['user_type'] ?? 'Employee',
                'year' => NULL,
                'section' => NULL,
                'course' => NULL
            ];
        } else {
            // User not found - show message
            $statusMsg = '<div class="alert alert-warning">⚠️ No user found with RFID/ID: <strong>' . htmlspecialchars($uid) . '</strong>. Please check if the RFID is registered in the system.</div>';
        }
    }
}

// Handle Borrow Books submission (works for students, teaching staff, and non-teaching staff)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['borrow'])) {
    $uid = mysqli_real_escape_string($conn, $_POST['uid']);
    $accession_ids = isset($_POST['book_ids']) ? $_POST['book_ids'] : []; // These are accession_ids

    // Find borrower in students table first
    $sql = "SELECT student_id as id, eligible_status, 0 as late_timeout_warnings, 'students' as source_table, 'Student' as user_type
            FROM students 
            WHERE rfid_number = '$uid' OR student_id = '$uid' OR CAST(student_id AS CHAR) = '$uid'
            LIMIT 1";
    $result = mysqli_query($conn, $sql);

    $borrower = null;
    $source_table = '';

    if ($result && mysqli_num_rows($result) > 0) {
        $borrower = mysqli_fetch_assoc($result);
        $source_table = 'students';
    } else {
        // Check employees table
        $sqlEmployee = "SELECT employee_id as id, 1 as eligible_status, 0 as late_timeout_warnings, 'employees' as source_table, role_id as user_type
                        FROM employees
                        WHERE rfid_number = '$uid' OR employee_id = '$uid' OR CAST(employee_id AS CHAR) = '$uid'
                        LIMIT 1";
        $resultEmployee = mysqli_query($conn, $sqlEmployee);
        
        if ($resultEmployee && mysqli_num_rows($resultEmployee) > 0) {
            $borrower = mysqli_fetch_assoc($resultEmployee);
            $source_table = 'employees';
        }
    }

    if (!$borrower) {
        $statusMsg = '<div class="alert alert-danger">❌ User (student/staff) not found with RFID/ID: <strong>' . htmlspecialchars($uid) . '</strong>.</div>';
        $_SESSION['statusMsg'] = $statusMsg;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $borrower_id = $borrower['id'];
    
    // Check warnings first - 3 or more warnings = cannot borrow
    $warnings = isset($borrower['late_timeout_warnings']) ? intval($borrower['late_timeout_warnings']) : 0;
    if ($warnings >= 3) {
        $statusMsg = '<div class="alert alert-danger">❌ <strong>Access Denied:</strong> This user has ' . $warnings . '/3 warnings for not timing out. They are blocked from borrowing books until warnings are cleared by the SuperAdmin.</div>';
    } else {
        // Check eligibility: if eligible_status is NULL or not explicitly set to 0, allow borrowing
        $isEligible = is_null($borrower['eligible_status']) || $borrower['eligible_status'] == 1;
        if ($isEligible) {
            if (!empty($accession_ids)) {
                $successCount = 0;
                foreach ($accession_ids as $acc_id) {
                    $acc_id = intval($acc_id);

                    // Fetch book_id for this accession
                    $getB = mysqli_query($conn, "SELECT book_id FROM lib_accession_numbers WHERE id = $acc_id LIMIT 1");
                    if ($rowB = mysqli_fetch_assoc($getB)) {
                        $book_id = $rowB['book_id'];

                        // Insert into lib_rfid_loan
                        $insert = "INSERT INTO lib_rfid_loan (uid, student_id, book_id, accession_id, status, borrow_date) 
                                   VALUES ('$uid', '$borrower_id', '$book_id', '$acc_id', 'borrowed', NOW())"; // Insert accession_id

                        if (mysqli_query($conn, $insert)) {
                            // Update accession_number status to unavailable
                            mysqli_query($conn, "UPDATE lib_accession_numbers SET status = 'unavailable' WHERE id = $acc_id");
                            $successCount++;
                        }
                    }
                }

                // Update borrower eligibility to 0 (Not Eligible) - update based on source table
                if ($source_table === 'students') {
                    $updateBorrower = "UPDATE students SET eligible_status = 0 WHERE student_id = '$borrower_id'";
                } else {
                    $updateBorrower = "UPDATE employees SET eligible_status = 0 WHERE employee_id = '$borrower_id'";
                }
                mysqli_query($conn, $updateBorrower);

                if ($successCount > 0) {
                    $statusMsg = '<div class="alert alert-success">✅ ' . $successCount . ' book(s) borrowed successfully! Borrower is now Not Eligible.</div>';
                } else {
                    $statusMsg = '<div class="alert alert-danger">❌ Failed to borrow books.</div>';
                }
            } else {
                $statusMsg = '<div class="alert alert-warning">⚠️ No books selected.</div>';
            }
        } else {
            $statusMsg = '<div class="alert alert-warning">⚠️ Borrower is not eligible to borrow books. Contact admin to enable borrowing.</div>';
        }
    }
}

// Fetch books for dropdown (only available copies)
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
if ($bookResult && mysqli_num_rows($bookResult) > 0) {
    while ($row = mysqli_fetch_assoc($bookResult)) {
        $books[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
 <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
 <style>
   .profile-img {
     width: 120px;
     height: 120px;
     object-fit: cover;
     border-radius: 50%;
   }
   .book-counter {
     margin-top: 8px;
     margin-bottom: 8px;
     font-weight: 600;
   }
   .selected-books-list ul {
     list-style: none;
     padding-left: 0;
     margin: 0 0 10px 0;
   }
   .selected-books-list li {
     margin-bottom: 6px;
   }
   .book-details-panel {
     margin-top: 12px;
     padding: 15px;
     border-radius: 8px;
     background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
     border-left: 4px solid #667eea;
     max-height: 300px;
     overflow-y: auto;
     display: none;
   }
   .book-details-panel.active {
     display: block;
     animation: slideDown 0.3s ease-out;
   }
   @keyframes slideDown {
     from {
       opacity: 0;
       transform: translateY(-10px);
     }
     to {
       opacity: 1;
       transform: translateY(0);
     }
   }
   .book-detail-item {
     margin-bottom: 10px;
     padding-bottom: 10px;
     border-bottom: 1px solid rgba(255,255,255,0.3);
   }
   .book-detail-item:last-child {
     border-bottom: none;
     margin-bottom: 0;
     padding-bottom: 0;
   }
   .detail-label {
     font-weight: 700;
     color: #667eea;
     font-size: 0.9rem;
     text-transform: uppercase;
     letter-spacing: 0.5px;
   }
   .detail-value {
     color: #333;
     margin-top: 4px;
     font-size: 0.95rem;
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
              <div class="card" id="rfid-card">
                <div class="card-body text-center">
                  <h4 class="card-title">Scan RFID Card</h4>
                  <p class="card-description">Tap an RFID card to fetch borrower info</p>

                  <!-- UID Display -->
                  <form method="POST" action="">
                    <div class="form-group">
                      <label for="uid">UID / RFID Number</label>
                      <input type="text" id="uid" name="uid" class="form-control text-center font-weight-bold" 
                        placeholder="Scan RFID or enter manually" 
                        value="<?php echo isset($borrower['uid']) ? htmlspecialchars($borrower['uid']) : ''; ?>" 
                        autocomplete="off" autofocus required>
                      <small class="text-muted mt-2 d-block">Tap card or type RFID number and press Enter</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Fetch Profile</button>
                  </form>

                  <!-- Borrow Books Section -->
                  <hr>
                  <h4 class="card-title">Borrow Books</h4>
                  <form method="POST" action="">
                    <input type="hidden" name="borrow" value="1">
                    <div class="form-group" style="display:none;">
                        <input type="hidden" name="uid" id="borrow-uid"
                            value="<?php echo isset($borrower['uid']) ? htmlspecialchars($borrower['uid']) : ''; ?>" 
                            required>
                        </div>
                        <div class="book-counter">Books Selected: <span id="book-count">0</span></div>
                        <div class="selected-books-list" id="selected-books-list">
                             <ul></ul>
                        </div>

                        <div class="form-group">
                        <label for="book_ids">Select Books (Search by Title or ID)</label>
                        <select name="book_ids[]" id="book_ids" class="form-control" multiple="multiple" required>
                            <?php foreach ($books as $book): ?>
                            <option value="<?php echo $book['accession_id']; ?>" 
                                data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                data-description="<?php echo htmlspecialchars($book['description'] ?? ''); ?>"
                                data-genre="<?php echo htmlspecialchars($book['genre_name'] ?? 'N/A'); ?>"
                                data-status="<?php echo htmlspecialchars($book['status']); ?>">
                                <?php echo htmlspecialchars($book['title']); ?> (ID: <?php echo $book['book_id']; ?> | Copy: <?php echo $book['accession_number']; ?><?php echo $book['acquisition_type'] == 'Donated' ? ' | Donated' : ' | Purchased'; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        </div>

                        <!-- Book Details Panel -->
                        <div id="book-details-panel" class="book-details-panel">
                            <div id="book-details-content"></div>
                        </div>

                    <button type="submit" class="btn btn-primary btn-block">Borrow</button>
                  </form>

                </div>
              </div>
            </div>

            <!-- Borrower Profile Card -->
            <div class="col-md-8 grid-margin stretch-card">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body profile-card" id="borrower-profile">
                <h4 class="card-title text-left mb-4">
                  <?php 
                    if ($borrower && $borrower['source_table'] === 'employees') {
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

                <div id="profile-content" style="display:<?php echo $borrower ? 'block' : 'none'; ?>;">
                <?php if ($borrower): ?>
                    <!-- Profile Header -->
                    <div class="d-flex align-items-center mb-4">
                     <?php
                      // ✅ Default image handling
                      $imgSrc = '../img/defaulticon.png';
                      if (!empty($borrower['image_path'])) {
                          if (strpos($borrower['image_path'], '/') === false && file_exists('../uploads/' . $borrower['image_path'])) {
                              $imgSrc = '../uploads/' . $borrower['image_path'];
                          } elseif (file_exists($borrower['image_path'])) {
                              $imgSrc = $borrower['image_path'];
                          } elseif (file_exists('../uploads/' . $borrower['image_path'])) {
                              $imgSrc = '../uploads/' . $borrower['image_path'];
                          }
                      }
                    ?>
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                        alt="Profile Photo" class="profile-img me-4 border border-3 border-primary shadow-sm">

                    <div class="text-start">
                        <h3 class="mb-1 fw-bold text-dark">
                        &nbsp;&nbsp;&nbsp;&nbsp;<?php 
                        $firstName = $borrower['firstname'] ?? 'Unknown';
                        $lastName = $borrower['lastname'] ?? '';
                        echo htmlspecialchars($firstName . ' ' . $lastName); 
                        ?>
                        </h3>

                        <div class="mt-2">
                          &nbsp;&nbsp;&nbsp;<?php if (isset($borrower['user_type'])): ?>
                            <span class="badge bg-info px-3 py-2">
                              <?php echo htmlspecialchars($borrower['user_type']); ?>
                            </span>
                          <?php else: ?>
                            <span class="badge bg-secondary px-3 py-2">Unknown</span>
                          <?php endif; ?>

                          &nbsp;&nbsp;
                          <?php 
                          // Show eligible if NULL or == 1
                          $showEligible = is_null($borrower['eligible_status']) || $borrower['eligible_status'] == 1;
                          if ($showEligible): 
                          ?>
                            <span class="badge bg-success px-3 py-2">Eligible to Borrow</span>
                          <?php else: ?>
                            <span class="badge bg-danger px-3 py-2">Not Eligible to Borrow</span>
                          <?php endif; ?>
                        </div>

                    </div>
                    </div>

                    <!-- Profile Details -->
                    <div class="row text-start">
                    <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-email-outline me-2"></i>Email</p>
                        <p class="fs-6 text-dark"><?php echo isset($borrower['email']) ? htmlspecialchars($borrower['email']) : 'N/A'; ?></p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-home-outline me-2"></i><?php echo ($borrower['source_table'] === 'employees') ? 'Department' : 'Address'; ?></p>
                        <p class="fs-6 text-dark"><?php echo isset($borrower['address']) ? htmlspecialchars($borrower['address']) : 'N/A'; ?></p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-card-account-details-outline me-2"></i>UID</p>
                        <p class="fs-6 text-dark"><?php echo isset($borrower['uid']) ? htmlspecialchars($borrower['uid']) : (isset($borrower['rfid_number']) ? htmlspecialchars($borrower['rfid_number']) : 'N/A'); ?></p>
                    </div>

                    <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-book-open-variant me-2"></i>Status</p>
                        <?php 
                        // Show eligible if NULL or == 1
                        $showEligible = is_null($borrower['eligible_status']) || $borrower['eligible_status'] == 1;
                        if ($showEligible): 
                        ?>
                          <p class="fs-6 text-success fw-bold">Eligible to Borrow</p>
                        <?php else: ?>
                          <p class="fs-6 text-danger fw-bold">Not Eligible to Borrow</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($borrower['user_type'] === 'Student'): ?>
                        <?php 
                        $isHighSchool = !empty($borrower['grade']) && !empty($borrower['strand']);
                        ?>
                        <?php if ($isHighSchool): ?>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-school me-2"></i>Grade</p>
                                <p class="fs-6 text-dark"><span class="badge bg-success"><?php echo htmlspecialchars($borrower['grade']); ?></span></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-book-outline me-2"></i>Strand</p>
                                <p class="fs-6 text-dark"><span class="badge bg-warning"><?php echo htmlspecialchars($borrower['strand']); ?></span></p>
                            </div>
                        <?php else: ?>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-school me-2"></i>Year Level</p>
                                <p class="fs-6 text-dark"><?php echo isset($borrower['year']) ? htmlspecialchars($borrower['year']) : 'N/A'; ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-account-group-outline me-2"></i>Section</p>
                                <p class="fs-6 text-dark"><?php echo isset($borrower['section']) ? htmlspecialchars($borrower['section']) : 'N/A'; ?></p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Employee Info -->
                        <div class="col-md-6 mb-3">
                            <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-account-star me-2"></i>Role</p>
                            <p class="fs-6 text-dark"><?php echo htmlspecialchars($borrower['user_type'] ?? 'N/A'); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-6 mb-3">
                        <p class="mb-1 fw-semibold text-muted"><i class="mdi mdi-alert-circle me-2"></i>Late Time-Out Warnings</p>
                        <p class="fs-6 text-dark">
                            <?php 
                            $warnings = isset($borrower['late_timeout_warnings']) ? intval($borrower['late_timeout_warnings']) : 0;
                            if ($warnings >= 3): 
                            ?>
                                <span class="badge bg-danger px-3 py-2">🚫 <?php echo $warnings; ?>/3 - BLOCKED</span>
                            <?php elseif ($warnings > 0): ?>
                                <span class="badge bg-warning px-3 py-2">⚠️ <?php echo $warnings; ?>/3 Warnings</span>
                            <?php else: ?>
                                <span class="badge bg-success px-3 py-2">✅ No Warnings</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                    <i class="mdi mdi-account-circle-outline display-1 d-block mb-3"></i>
                    <p class="fs-5">No borrower data yet. Please scan a card.</p>
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

  <!-- plugins:js -->
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
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

  <!-- Select2 -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <script>
  const uidInput = document.getElementById("uid");
  const borrowUid = document.getElementById("borrow-uid");
  const profileLoader = document.getElementById("profile-loader");
  const profileContent = document.getElementById("profile-content");

  // Initialize Select2
  $(document).ready(function() {
    // Focus on UID input for scanning
    uidInput.focus();

    // Update borrow-uid field when main uid changes
    uidInput.addEventListener('change', function() {
      if (borrowUid) {
        borrowUid.value = this.value;
      }
    });

    $('#book_ids').select2({
      placeholder: "Search by book title or ID...",
      allowClear: true,
      width: '100%',
      matcher: function(params, data) {
        if (!params.term || params.term.trim() === '') {
          return data;
        }
        var term = params.term.toLowerCase();
        // Search by displayed text (title)
        if (data.text.toLowerCase().indexOf(term) > -1) {
          return data;
        }
        // Search by option value (book_id)
        if (data.id && data.id.toLowerCase().indexOf(term) > -1) {
          return data;
        }
        return null;
      }
    });

    // Show details only when typing in search field
    $(document).on('input', '.select2-search__field', function() {
      updateBookDetailsPanel();
    });

    $('#book_ids').on('select2:closing', function() {
      // Hide panel when dropdown closes
      $('#book-details-panel').removeClass('active');
    });

    function updateBookDetailsPanel() {
      let search = $('.select2-search__field').val() || '';
      
      // Only show panel if user is typing
      if (!search) {
        $('#book-details-panel').removeClass('active');
        return;
      }

      let allOptions = $('#book_ids').find('option');
      let detailsHtml = '';

      allOptions.each(function() {
        let title = $(this).data('title').toLowerCase();
        let id = $(this).val().toLowerCase();
        let searchTerm = search.toLowerCase();

        // Show only books that match the search
        if (title.includes(searchTerm) || id.includes(searchTerm)) {
          let description = $(this).data('description');
          let genre = $(this).data('genre');
          let status = $(this).data('status');
          let bookId = $(this).val();
          let bookTitle = $(this).data('title');

          detailsHtml += `
            <div class="book-detail-item">
              <div class="detail-label"><i class="mdi mdi-book-open-variant"></i> ${bookTitle}</div>
              <div class="detail-value">
                <small><strong>📚 ID:</strong> ${bookId}</small><br/>
                <strong>📖 Genre:</strong> ${genre}<br/>
                <strong>✅ Status:</strong> <span class="badge" style="background-color: #28a745; color: white;">Available</span><br/>
                <strong>📄 Description:</strong> <small>${description ? description.substring(0, 100) + '...' : 'No description available'}</small>
              </div>
            </div>
          `;
        }
      });

      if (detailsHtml) {
        $('#book-details-panel').addClass('active');
        $('#book-details-content').html(detailsHtml);
      } else {
        $('#book-details-panel').html('<small class="text-muted">No books match your search.</small>').addClass('active');
      }
    }

    // Update selected books and counter
    $('#book_ids').on('change', function() {
      let selected = $(this).find("option:selected");
      let count = selected.length;
      $("#book-count").text(count);

      let list = $("#selected-books-list ul");
      list.empty();
      selected.each(function() {
        let title = $(this).data('title');
        let bookId = $(this).val();
        list.append("<li><i class='mdi mdi-book-open-variant'></i> " + title + " (Copy ID: " + bookId + ")</li>");
      });
    });
  });
  </script>
</body>
</html>
