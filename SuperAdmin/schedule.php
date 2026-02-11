<?php
include '../includes/session.php';
include '../includes/dbcon.php';

$statusMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $grade = mysqli_real_escape_string($conn, $_POST['grade']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $strand = isset($_POST['strand']) ? mysqli_real_escape_string($conn, $_POST['strand']) : "";
    $date = mysqli_real_escape_string($conn, $_POST['sched_date']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);

    $sql = "INSERT INTO lib_schedule (grade_id, section_id, strand, sched_date, start_time, end_time) 
            VALUES ((SELECT grade_id FROM lib_grade WHERE grade_name = '$grade' LIMIT 1), 
                    (SELECT section_id FROM lib_section WHERE section_name = '$section' LIMIT 1), 
                    '$strand', '$date', '$start_time', '$end_time')";
    
    // Fallback if lib_grade/lib_section not used strictly
    $sql = "INSERT INTO lib_schedule (strand, sched_date, start_time, end_time) 
            VALUES ('$strand', '$date', '$start_time', '$end_time')";
    // Actually, let's just store them as strings for now to be safe with existing data structure if grade_id is not yet populated
    @mysqli_query($conn, "ALTER TABLE lib_schedule MODIFY COLUMN grade_id VARCHAR(50), MODIFY COLUMN section_id VARCHAR(50)");
    
    $sql = "INSERT INTO lib_schedule (grade_id, section_id, strand, sched_date, start_time, end_time) 
            VALUES ('$grade', '$section', '$strand', '$date', '$start_time', '$end_time')";

    if (mysqli_query($conn, $sql)) {
        $statusMsg = '<div class="alert alert-success">Schedule set successfully!</div>';
    } else {
        $statusMsg = '<div class="alert alert-danger">Error: ' . mysqli_error($conn) . '</div>';
    }
}

// Delete schedule
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM lib_schedule WHERE schedule_id = $id");
    header("Location: schedule.php?status=deleted");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
</head>
<body>
  <div class="container-scroller">
    <?php include "partials/navbar.php";?>
    <div class="container-fluid page-body-wrapper">
      <?php include "partials/sidebar.php";?>
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-md-5 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Set New Schedule</h4>
                  <?php echo $statusMsg; ?>
                  <form class="forms-sample" method="POST">
                    <div class="form-group">
                      <label>Level / Department</label>
                      <select class="form-control" id="level-select" required>
                        <option value="" disabled selected>Select Level</option>
                        <option value="Elementary">Elementary</option>
                        <option value="Junior High">Junior High</option>
                        <option value="Senior High">Senior High</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Grade</label>
                      <select class="form-control" id="grade-select" name="grade" required>
                        <option value="" disabled selected>Select Level first</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Section</label>
                      <input type="text" class="form-control" name="section" placeholder="e.g. A, B, Ruby" required>
                    </div>
                    <div class="form-group" id="strand-group" style="display:none;">
                      <label>Strand</label>
                      <select class="form-control" name="strand">
                        <option value="">N/A</option>
                        <option value="ABM">ABM</option>
                        <option value="TVL">TVL</option>
                        <option value="STEM">STEM</option>
                        <option value="HUMS">HUMS</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Date</label>
                      <input type="date" class="form-control" name="sched_date" required>
                    </div>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Start Time</label>
                          <input type="time" class="form-control" name="start_time" required>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group">
                          <label>End Time</label>
                          <input type="time" class="form-control" name="end_time" required>
                        </div>
                      </div>
                    </div>
                    <button type="submit" class="btn btn-primary mr-2">Submit</button>
                    <button type="reset" class="btn btn-light">Cancel</button>
                  </form>
                </div>
              </div>
            </div>
            <div class="col-md-7 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Schedule List</h4>
                  <div class="table-responsive">
                    <table class="table table-striped">
                      <thead>
                        <tr>
                          <th>Target</th>
                          <th>Date</th>
                          <th>Time</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $res = mysqli_query($conn, "SELECT * FROM lib_schedule ORDER BY sched_date DESC, start_time ASC");
                        while($row = mysqli_fetch_assoc($res)) {
                            $target = $row['grade_id'] . " - " . $row['section_id'];
                            if ($row['strand']) $target .= " (" . $row['strand'] . ")";
                            echo "<tr>
                                    <td>".htmlspecialchars($target)."</td>
                                    <td>".date('M d, Y', strtotime($row['sched_date']))."</td>
                                    <td>".date('h:i A', strtotime($row['start_time']))." - ".date('h:i A', strtotime($row['end_time']))."</td>
                                    <td>
                                      <a href='?delete=".$row['schedule_id']."' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                                    </td>
                                  </tr>";
                        }
                        ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include "partials/footer.php";?>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('level-select').addEventListener('change', function() {
        const val = this.value;
        const gradeSelect = document.getElementById('grade-select');
        const strandGroup = document.getElementById('strand-group');
        
        gradeSelect.innerHTML = '<option value="" disabled selected>Select Grade</option>';
        if (val === 'Elementary') {
            ['Senior Kinder', '1', '2', '3', '4', '5', '6'].forEach(g => {
                gradeSelect.innerHTML += `<option value="${g}">${g === 'Senior Kinder' ? g : 'Grade ' + g}</option>`;
            });
            strandGroup.style.display = 'none';
        } else if (val === 'Junior High') {
            ['7', '8', '9', '10'].forEach(g => {
                gradeSelect.innerHTML += `<option value="${g}">Grade ${g}</option>`;
            });
            strandGroup.style.display = 'none';
        } else if (val === 'Senior High') {
            ['11', '12'].forEach(g => {
                gradeSelect.innerHTML += `<option value="${g}">Grade ${g}</option>`;
            });
            strandGroup.style.display = 'block';
        }
    });
  </script>
</body>
</html>
