<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

$statusMsg = ""; // For status messages

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uid       = mysqli_real_escape_string($conn, $_POST['uid']);
    $studentId = mysqli_real_escape_string($conn, $_POST['student_id'] ?? '');
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $year      = isset($_POST['year']) ? mysqli_real_escape_string($conn, $_POST['year']) : "";
    $section   = isset($_POST['section']) ? mysqli_real_escape_string($conn, $_POST['section']) : "";
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $regType   = mysqli_real_escape_string($conn, $_POST['registration_type']);
    $course    = isset($_POST['course']) ? mysqli_real_escape_string($conn, $_POST['course']) : "";
    $date_created = date("Y-m-d H:i:s");

    $imagePath = "";
    if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] == 0) {
        $targetDir = "../uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = time() . "_" . basename($_FILES["profile_image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = array("jpg","jpeg","png","gif");
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFilePath)) {
                $imagePath = $targetFilePath;
            }
        }
    }

    if ($regType === "Student") {
      // Server-side validation: student ID is required for students
      if (empty($studentId)) {
        $error = urlencode('Student ID is required for Student registration.');
        header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
        exit();
      }
      
      // Get grade and strand (for high school students)
      $grade = isset($_POST['grade']) && !empty($_POST['grade']) ? intval($_POST['grade']) : NULL;
      $strand = isset($_POST['strand']) && !empty($_POST['strand']) ? mysqli_real_escape_string($conn, $_POST['strand']) : NULL;
      
      // Validation: Either (Grade AND Strand) OR (Year AND Section) must be filled
      $hasHighSchoolFields = !empty($grade) && !empty($strand);
      $hasCollegeFields = !empty($year) && !empty($section);
      
      if (!$hasHighSchoolFields && !$hasCollegeFields) {
        $error = urlencode('Please fill either (Grade & Strand for High School) OR (Year & Section for College).');
        header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
        exit();
      }
        
        // Generate a default password (you might want to make this configurable)
        $defaultPassword = 'student123'; // Default password, consider making this more secure
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        // Get eligible status (default is 1 for eligible, can be unchecked to set to 0)
        $eligibleStatus = isset($_POST['eligible_status']) ? 1 : 1;  // Default: eligible
        
        // Prepare NULL-safe values for optional fields
        $yearVal = (!empty($year) && intval($year) > 0) ? intval($year) : 'NULL';
        
        // Handle section as a string name and map/create section entry
        $sectionVal = 'NULL';
        if (!empty($section)) {
            $sName = mysqli_real_escape_string($conn, $section);
            $yId = intval($year);
            // Check if section exists for this level
            $checkSection = mysqli_query($conn, "SELECT section_id FROM sections WHERE section_name = '$sName' AND level = '$yId' LIMIT 1");
            if ($rowS = mysqli_fetch_assoc($checkSection)) {
                $sectionVal = $rowS['section_id'];
            } else {
                // Auto-create section if it doesn't exist for this level (as a safety fallback)
                mysqli_query($conn, "INSERT INTO sections (section_name, level) VALUES ('$sName', '$yId')");
                $sectionVal = mysqli_insert_id($conn);
            }
        }
        
        $gradeVal = ($grade !== NULL) ? $grade : 'NULL';
        $strandVal = ($strand !== NULL) ? "'" . mysqli_real_escape_string($conn, $strand) . "'" : 'NULL';
        $courseVal = !empty($course) ? "'$course'" : 'NULL';
        
        $sql = "INSERT INTO students 
          (student_id, rfid_number, first_name, last_name, year_id, section_id, email, address, 
           profile_picture, status, eligible_status, created_at, password, grade, strand, course) 
          VALUES 
          ('$studentId', '$uid', '$firstname', '$lastname', $yearVal, '$sectionVal', '$email', '$address', 
           '$imagePath', 'active', $eligibleStatus, '$date_created', '$hashedPassword', $gradeVal, $strandVal, $courseVal)";
    } elseif ($regType === "Teaching" || $regType === "Nonteaching") {
        // Insert into employees table for teaching/non-teaching staff
        $defaultPassword = 'employee123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        $assignedCourse = mysqli_real_escape_string($conn, $_POST['assigned_course'] ?? '');
        $role = $regType === "Teaching" ? "Teaching Staff" : "Non-Teaching Staff";
        
        $sql = "INSERT INTO employees 
                (firstname, lastname, email, password, role_id, profile_pic, assigned_course, rfid_number) 
                VALUES 
                ('$firstname', '$lastname', '$email', '$hashedPassword', '$role', '$imagePath', '$assignedCourse', '$uid')";
    }

    if (mysqli_query($conn, $sql)) {
        $updateRFID = "UPDATE lib_rfid_auth SET inuse = 1 WHERE uid = '$uid'";
        mysqli_query($conn, $updateRFID);
        header("Location: ".$_SERVER['PHP_SELF']."?status=success");
        exit();
    } else {
        $error = urlencode(mysqli_error($conn));
        header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
        exit();
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] == "success") {
        $statusMsg = '<div class="alert alert-success">✅ Registered successfully!</div>';
    } elseif ($_GET['status'] == "error" && isset($_GET['msg'])) {
        $statusMsg = '<div class="alert alert-danger">❌ Error: '.htmlspecialchars($_GET['msg']).'</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <style>
  #loading-spinner {
    display: none;
    text-align: center;
    margin-top: 15px;
  }
  .spinner-border {
    width: 2rem;
    height: 2rem;
    border: 0.25em solid #ccc;
    border-top: 0.25em solid #007bff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 10px;
  }
  @keyframes spin {
    100% { transform: rotate(360deg); }
  }
  .card-header-custom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
  }
  .dropdown-select {
    width: 200px;
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

          <?php if ($statusMsg) echo $statusMsg; ?>

          <form class="forms-sample w-100" method="POST" action="" enctype="multipart/form-data">
            <div class="row">

              <div class="col-md-4 grid-margin stretch-card">
                <div class="card" id="rfid-card">
                  <div class="card-body text-center">
                    <h4 class="card-title">Scan RFID Card</h4>
                    <p class="card-description">Tap an RFID card to generate UID</p>
                    <div id="rfid-animation" class="mt-3">
                      <div id="rfid-circle" class="rfid-circle green"></div>
                      <div class="rfid-check">✔</div>
                      <p id="rfid-status" class="text-success font-weight-bold mt-2">Card Scanned!</p>
                    </div>
                    <?php include 'partials/spinner.php'; ?>
                    <div class="form-group">
                      <label for="uid">UID</label>
                      <p id="uid-display" class="form-control text-center font-weight-bold" 
                         style="background:#f8f9fa;">Waiting for scan...</p>
                      <input type="hidden" id="uid" name="uid" required>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-8 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <div class="card-header-custom">
                      <h4 class="card-title">Registration Form</h4>
                      <select class="form-control dropdown-select" id="registration-type" name="registration_type" required>
                          <option value="" disabled selected>Choose first</option>
                          <option value="Student">Student</option>
                          <option value="Teaching">Teaching</option>
                          <option value="Nonteaching">Nonteaching</option>
                        </select>

                    </div>
                    <p class="card-description">Fill out the form to register</p>

                    <div class="alert alert-info" role="alert" style="font-size: 0.9rem;">
                      <strong>📝 Note:</strong> For <strong>High School</strong> students, fill Grade & Strand. For <strong>College</strong> students, fill Year & Section.
                    </div>

                    <div class="form-group" id="student-id-group" style="display:none;">
                      <label for="student_id">Student ID</label>
                      <input type="text" class="form-control" id="student_id" name="student_id" placeholder="Enter Student ID" disabled>
                    </div>
                    <div class="form-group">
                      <label for="firstname">First Name</label>
                      <input type="text" class="form-control" id="firstname" name="firstname" placeholder="First Name" required disabled>
                    </div>
                    <div class="form-group">
                        <label for="lastname">Last Name</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Last Name" required disabled>
                    </div>

                    <div class="row" id="year-section-group">
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="course">Course <span id="course-required-badge" class="badge badge-danger" style="display:none;">Required if College</span></label>
                          <input type="text" class="form-control" id="course" name="course" placeholder="e.g. BSIT, BSN" disabled>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="year">Year <span id="year-required-badge" class="badge badge-danger" style="display:none;">Required if College</span></label>
                          <select class="form-control" id="year" name="year" disabled>
                            <option value="" disabled selected>Select Year</option>
                            <?php 
                              $yq = "SELECT year_id, year_name FROM year_levels ORDER BY year_id ASC";
                              $yr = mysqli_query($conn, $yq);
                              while($y = mysqli_fetch_assoc($yr)) {
                                echo '<option value="'.$y['year_id'].'">'.htmlspecialchars($y['year_name']).'</option>';
                              }
                            ?>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="section">Section <span id="section-required-badge" class="badge badge-danger" style="display:none;">Required if College</span></label>
                          <input type="text" class="form-control" id="section" name="section" placeholder="e.g. A, B, C, or D" disabled>
                        </div>
                      </div>
                    </div>

                    <div class="row" id="grade-strand-group" style="display:none;">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="grade">Grade <span id="grade-required-badge" class="badge badge-danger">Required (HighSchool)</span></label>
                          <select class="form-control" id="grade" name="grade" disabled>
                            <option value="" disabled selected>Select Grade</option>
                            <option value="7">Grade 7</option>
                            <option value="8">Grade 8</option>
                            <option value="9">Grade 9</option>
                            <option value="10">Grade 10</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="strand">Strand <span id="strand-required-badge" class="badge badge-danger">Required (HighSchool)</span></label>
                          <select class="form-control" id="strand" name="strand" disabled>
                            <option value="" disabled selected>Select Strand</option>
                            <option value="ABM">ABM (Accounting, Business & Management)</option>
                            <option value="TVL">TVL (Technical-Vocational Livelihood)</option>
                            <option value="STEM">STEM (Science, Technology, Engineering & Math)</option>
                            <option value="HUMS">HUMS (Humanities & Social Sciences)</option>
                          </select>
                        </div>
                      </div>
                    </div>

                    <div class="form-group" id="assigned-course-group" style="display:none;">
                        <label for="assigned_course">Department</label>
                        <input type="text" class="form-control" id="assigned_course" name="assigned_course" placeholder="e.g. Computer Science, Nursing" disabled>
                    </div>

                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required disabled>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" class="form-control" id="address" name="address" placeholder="Address" required disabled>
                    </div>

                    <div class="form-group">
                        <label for="profile_image">Profile Picture</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" disabled>
                    </div>

                    <div class="form-group" id="eligible-status-group">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="eligible_status" name="eligible_status" value="1" checked>
                            <label class="form-check-label" for="eligible_status">Eligible for Borrowing</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mr-2" disabled id="submit-btn">Submit</button>
                    <button type="reset" class="btn btn-light">Cancel</button>
                  </div>
                </div>
              </div>

            </div>
          </form>

        </div>
        <?php include 'partials/footer.php'; ?>
      </div>
    </div>   
  </div>

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

  <script>
  const uidInput = document.getElementById("uid");
  const uidDisplay = document.getElementById("uid-display");
  const rfidAnimation = document.getElementById("rfid-animation");
  const rfidCircle = document.getElementById("rfid-circle");
  const rfidStatus = document.getElementById("rfid-status");
  const loadingSpinner = document.getElementById("loading-spinner");

  const firstname = document.getElementById("firstname");
  const lastname = document.getElementById("lastname");
  const studentId = document.getElementById("student_id");
  const studentIdGroup = document.getElementById("student-id-group");
  const year = document.getElementById("year");
  const section = document.getElementById("section");
  const grade = document.getElementById("grade");
  const strand = document.getElementById("strand");
  const email = document.getElementById("email");
  const address = document.getElementById("address");
  const assignedCourse = document.getElementById("assigned_course");
  const profileImage = document.getElementById("profile_image");
  const submitBtn = document.getElementById("submit-btn");
  const regType = document.getElementById("registration-type");
  const yearSectionGroup = document.getElementById("year-section-group");
  const gradStrandGroup = document.getElementById("grade-strand-group");
  const assignedCourseGroup = document.getElementById("assigned-course-group");
  const eligibleStatusGroup = document.getElementById("eligible-status-group");
  const eligibleStatus = document.getElementById("eligible_status");

  let scanning = false;
  let buffer = "";

  // Toggle fields based on registration type
  regType.addEventListener("change", () => {
    const isStudent = regType.value === 'Student';
    const isEmployee = regType.value === 'Teaching' || regType.value === 'Nonteaching';
    const inputs = document.querySelectorAll('#student_id, #firstname, #lastname, #email, #address, #profile_image, #course');
    const submitBtn = document.getElementById('submit-btn');

    // Enable/disable all inputs
    inputs.forEach(input => input.disabled = false);
    
    // Show both year-section and grade-strand groups for students
    if (yearSectionGroup) {
      yearSectionGroup.style.display = isStudent ? 'flex' : 'none';
      document.getElementById('year').disabled = !isStudent;
      document.getElementById('section').disabled = !isStudent;
      // Don't set required here - will be handled by mutual exclusivity logic
    }

    // Show grade and strand for students
    if (gradStrandGroup) {
      gradStrandGroup.style.display = isStudent ? 'flex' : 'none';
      document.getElementById('grade').disabled = !isStudent;
      document.getElementById('strand').disabled = !isStudent;
      // Don't set required here - will be handled by mutual exclusivity logic
    }

    // Student ID required and visible only for Students
    if (studentId) {
      studentId.disabled = !isStudent;
      studentId.required = isStudent;
    }
    if (studentIdGroup) {
      studentIdGroup.style.display = isStudent ? 'block' : 'none';
    }
    
    // Show/hide assigned course for employees
    if (assignedCourseGroup) {
      assignedCourseGroup.style.display = isEmployee ? 'block' : 'none';
      assignedCourse.disabled = !isEmployee;
      assignedCourse.required = isEmployee;
    }
    
    // Show/hide eligible status for students only
    if (eligibleStatusGroup) {
      eligibleStatusGroup.style.display = isStudent ? 'block' : 'none';
    }
    
    // Enable submit button
    if (submitBtn) submitBtn.disabled = false;
  });

  // Handle mutual exclusivity: Grade/Strand for HighSchool vs Year/Section for College
  document.getElementById('grade').addEventListener('change', function() {
    const hasGrade = this.value !== '';
    const hasStrand = document.getElementById('strand').value !== '';
    
    if (hasGrade || hasStrand) {
      // High school mode: disable Year/Section/Course, require Grade/Strand
      document.getElementById('course').required = false;
      document.getElementById('year').required = false;
      document.getElementById('section').required = false;
      document.getElementById('grade').required = true;
      document.getElementById('strand').required = true;
    } else {
      // Reset to college mode
      document.getElementById('course').required = true;
      document.getElementById('year').required = true;
      document.getElementById('section').required = true;
      document.getElementById('grade').required = false;
      document.getElementById('strand').required = false;
    }
  });

  document.getElementById('strand').addEventListener('change', function() {
    const hasGrade = document.getElementById('grade').value !== '';
    const hasStrand = this.value !== '';
    
    if (hasGrade || hasStrand) {
      // High school mode: disable Year/Section/Course, require Grade/Strand
      document.getElementById('course').required = false;
      document.getElementById('year').required = false;
      document.getElementById('section').required = false;
      document.getElementById('grade').required = true;
      document.getElementById('strand').required = true;
    } else {
      // Reset to college mode
      document.getElementById('course').required = true;
      document.getElementById('year').required = true;
      document.getElementById('section').required = true;
      document.getElementById('grade').required = false;
      document.getElementById('strand').required = false;
    }
  });

  document.getElementById('year').addEventListener('change', function() {
    const hasYear = this.value !== '';
    const hasSection = document.getElementById('section').value !== '';
    const hasCourse = document.getElementById('course').value !== '';
    
    if (hasYear || hasSection || hasCourse) {
      // College mode: set Grade/Strand as not required
      document.getElementById('course').required = true;
      document.getElementById('year').required = true;
      document.getElementById('section').required = true;
      document.getElementById('grade').required = false;
      document.getElementById('strand').required = false;
      document.getElementById('grade').value = '';
      document.getElementById('strand').value = '';
    } else {
      // Reset to high school mode
      document.getElementById('course').required = false;
      document.getElementById('year').required = false;
      document.getElementById('section').required = false;
      document.getElementById('grade').required = true;
      document.getElementById('strand').required = true;
    }
  });

  document.getElementById('section').addEventListener('change', function() {
    const hasYear = document.getElementById('year').value !== '';
    const hasSection = this.value !== '';
    const hasCourse = document.getElementById('course').value !== '';
    
    if (hasYear || hasSection || hasCourse) {
      // College mode: set Grade/Strand as not required
      document.getElementById('course').required = true;
      document.getElementById('year').required = true;
      document.getElementById('section').required = true;
      document.getElementById('grade').required = false;
      document.getElementById('strand').required = false;
      document.getElementById('grade').value = '';
      document.getElementById('strand').value = '';
    } else {
      // Reset to high school mode
      document.getElementById('course').required = false;
      document.getElementById('year').required = false;
      document.getElementById('section').required = false;
      document.getElementById('grade').required = true;
      document.getElementById('strand').required = true;
    }
  });

  document.getElementById('course').addEventListener('change', function() {
    const hasYear = document.getElementById('year').value !== '';
    const hasSection = document.getElementById('section').value !== '';
    const hasCourse = this.value !== '';
    
    if (hasYear || hasSection || hasCourse) {
      // College mode: set Grade/Strand as not required
      document.getElementById('course').required = true;
      document.getElementById('year').required = true;
      document.getElementById('section').required = true;
      document.getElementById('grade').required = false;
      document.getElementById('strand').required = false;
      document.getElementById('grade').value = '';
      document.getElementById('strand').value = '';
    } else {
      // Reset to high school mode
      document.getElementById('course').required = false;
      document.getElementById('year').required = false;
      document.getElementById('section').required = false;
      document.getElementById('grade').required = true;
      document.getElementById('strand').required = true;
    }
  });
  document.querySelector('form').addEventListener('submit', function(e) {
    const grade = document.getElementById('grade').value;
    const strand = document.getElementById('strand').value;
    const year = document.getElementById('year').value;
    const section = document.getElementById('section').value;
    const course = document.getElementById('course').value;
    const regType = document.getElementById('registration-type').value;

    // Only validate for students
    if (regType === 'Student') {
      const hasHighSchool = grade !== '' && strand !== '';
      const hasCollege = year !== '' && section !== '' && course !== '';

      if (!hasHighSchool && !hasCollege) {
        e.preventDefault();
        alert('⚠️ Please fill either:\n\n✓ Grade & Strand (for High School students), OR\n✓ Course, Year & Section (for College students)');
        return false;
      }
    }
  });

  document.addEventListener("keydown", function(e) {
    if (!scanning) {
      buffer = "";
      scanning = true;
    }

    if (e.key === "Enter") {
      e.preventDefault();
      if (buffer.trim() !== "") {
        const uid = buffer.trim();
        uidInput.value = uid;
        uidDisplay.textContent = uid;

        loadingSpinner.style.display = "block";
        rfidAnimation.style.display = "none";

        setTimeout(() => {
          fetch("validate_uid.php?uid=" + uid)
            .then(res => res.json())
            .then(data => {
              loadingSpinner.style.display = "none";
              rfidAnimation.style.display = "block";

              if (data.status === "valid") {
                rfidCircle.classList.remove("red");
                rfidCircle.classList.add("green");
                rfidStatus.textContent = "✅ Valid Card!";
                rfidStatus.classList.remove("text-danger");
                rfidStatus.classList.add("text-success");

                firstname.disabled = false;
                lastname.disabled = false;
                year.disabled = false;
                section.disabled = false;
                document.getElementById('course').disabled = false;
                grade.disabled = false;
                strand.disabled = false;
                email.disabled = false;
                address.disabled = false;
                if (studentId) studentId.disabled = (regType.value !== 'Student');
                profileImage.disabled = false;
                submitBtn.disabled = false;
              } else {
                rfidCircle.classList.remove("green");
                rfidCircle.classList.add("red");
                rfidStatus.textContent = "❌ Invalid Card!";
                rfidStatus.classList.remove("text-success");
                rfidStatus.classList.add("text-danger");

                firstname.disabled = true;
                lastname.disabled = true;
                year.disabled = true;
                section.disabled = true;
                document.getElementById('course').disabled = true;
                grade.disabled = true;
                strand.disabled = true;
                email.disabled = true;
                address.disabled = true;
                if (studentId) studentId.disabled = true;
                profileImage.disabled = true;
                submitBtn.disabled = true;
              }
            });
        }, 2000);
      }
      scanning = false;
      buffer = "";
    } else {
      if (e.key.length === 1) {
        buffer += e.key;
      }
    }
  });
  </script>

</body>
</html>
