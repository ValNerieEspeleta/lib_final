<?php
include '../includes/dbcon.php';
include '../includes/session.php';

// Handle AJAX request first
if (isset($_GET['uid'])) {
    header('Content-Type: application/json');
    $uid = mysqli_real_escape_string($conn, $_GET['uid']);

    // Look for user in both tables
    $tables = ['lib_regulars', 'tbl_students'];
    $userFound = false;
    $userData = [];

    foreach ($tables as $table) {
        $query = "SELECT id, firstname, lastname, image_path FROM $table WHERE uid='$uid' LIMIT 1";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $userData = [
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'image_path' => $row['image_path'] ?: 'default.png' // fallback image if null
            ];
            $userFound = true;
            break;
        }
    }

    // Proceed only if user is found
    if ($userFound) {
        $userId = $userData['id'];
        $today = date('Y-m-d');
        $currentStatus = 'In';

        $attQuery = "SELECT id, status FROM tbl_attendance WHERE user_id='$userId' AND DATE(time_in)='$today' ORDER BY time_in DESC LIMIT 1";
        $attResult = mysqli_query($conn, $attQuery);

        if ($attResult && mysqli_num_rows($attResult) > 0) {
            $attRow = mysqli_fetch_assoc($attResult);
            if ($attRow['status'] === 'In') {
                $updateQuery = "UPDATE tbl_attendance SET time_out=NOW(), status='Out' WHERE id={$attRow['id']}";
                mysqli_query($conn, $updateQuery);
                $currentStatus = 'Out';
            } else {
                $insertQuery = "INSERT INTO tbl_attendance (user_id, time_in, status) VALUES ('$userId', NOW(), 'In')";
                mysqli_query($conn, $insertQuery);
                $currentStatus = 'In';
            }
        } else {
            $insertQuery = "INSERT INTO tbl_attendance (user_id, time_in, status) VALUES ('$userId', NOW(), 'In')";
            mysqli_query($conn, $insertQuery);
            $currentStatus = 'In';
        }

        echo json_encode([
            'success' => true,
            'firstname' => $userData['firstname'],
            'lastname' => $userData['lastname'],
            'image_path' => $userData['image_path'],
            'status' => $currentStatus
        ]);
    } else {
        // User not found
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Library Attendance</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.4/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="attendance.css">

  <style>
    /* Hide input but keep it functional */
    #uidInput {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .student-img {
      width: 120px;
      height: 120px;
      object-fit: cover;
      border-radius: 50%;
      margin-bottom: 15px;
    }
  </style>
</head>

<body>
  <!-- Attendance Card -->
  <div class="attendance-card text-center" id="attendanceCard">
    <i class="bi bi-person-bounding-box attendance-icon" id="personIcon" style="font-size: 4rem;"></i>
    <h2 class="mt-4">Library Attendance</h2>
    <p class="status-message" id="statusMessage">Please tap your RFID card to register your attendance.</p>
    <div class="uid-input">
      <input type="text" id="uidInput" class="form-control text-center" placeholder="Scan RFID UID" autofocus>
    </div>
  </div>

  <!-- Student Info Modal -->
  <div class="modal fade" id="studentModal" tabindex="-1" aria-labelledby="studentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content text-center">
        <div class="modal-body">
          <img src="" id="studentImage" class="student-img" alt="Student Image">
          <h4 id="studentName"></h4>
          <p id="studentUID"></p>
          <p id="attendanceStatus" class="fw-bold"></p>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const uidInput = document.getElementById('uidInput');
    const personIcon = document.getElementById('personIcon');
    const card = document.getElementById('attendanceCard');
    const status = document.getElementById('statusMessage');
    const defaultMessage = "Please tap your RFID card to register your attendance.";

    const studentModalEl = document.getElementById('studentModal');
    const studentModal = new bootstrap.Modal(studentModalEl);
    const studentName = document.getElementById('studentName');
    const studentImage = document.getElementById('studentImage');
    const studentUID = document.getElementById('studentUID');
    const attendanceStatus = document.getElementById('attendanceStatus');

    function focusInput() {
      uidInput.value = '';
      uidInput.focus();
    }

    window.addEventListener('load', focusInput);
    document.addEventListener('click', () => uidInput.focus());
    document.addEventListener('focusin', () => uidInput.focus());
    studentModalEl.addEventListener('hidden.bs.modal', () => focusInput());

    uidInput.addEventListener('input', () => {
      const uid = uidInput.value.trim();
      if (!uid) return;

      status.textContent = '🔄 Scanning...';
      status.style.color = '#0d6efd';
      personIcon.classList.add('scanning');

      setTimeout(() => {
        personIcon.classList.remove('scanning');

        fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?uid=${encodeURIComponent(uid)}`)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              card.classList.remove('in-animation', 'out-animation');

              const lastDigit = parseInt(uid.slice(-1));
              let message = '';
              let color = '';

              if (!isNaN(lastDigit) && lastDigit % 2 === 0) {
                card.classList.add('in-animation');
                message = '✅ Success!';
                color = '#198754';
              } else {
                card.classList.add('out-animation');
                message = '❌ Try again!';
                color = '#dc3545';
              }

              status.textContent = message;
              status.style.color = color;

              // Update modal content
              studentName.textContent = `${data.firstname} ${data.lastname}`;
              studentImage.src = data.image_path;
              studentUID.textContent = `UID: ${uid}`;
              attendanceStatus.textContent = `Status: ${data.status}`;
              studentModal.show();

              setTimeout(() => studentModal.hide(), 4000);
            } else {
              status.textContent = '';
              status.style.color = '#dc3545';
            }

            setTimeout(() => {
              card.classList.remove('in-animation', 'out-animation');
              status.textContent = defaultMessage;
              status.style.color = '#000';
              focusInput();
            }, 3000);

          })
          .catch(err => {
            status.textContent = '❌ Error scanning UID';
            status.style.color = '#dc3545';
            setTimeout(() => {
              status.textContent = defaultMessage;
              status.style.color = '#000';
              focusInput();
            }, 3000);
          });

      }, 1000);
    });

    // ✅ Ctrl + Shift + L for Logout
    document.addEventListener('keydown', function(e) {
      if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'l') {
        window.location.href = '../logout.php';
      }
    });
  </script>
</body>
</html>
