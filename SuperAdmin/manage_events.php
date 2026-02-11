<?php
include '../includes/session.php';
include '../includes/dbcon.php';

$message = "";

// ==================== AUTO SETUP TABLE & COLUMNS ====================
// Create table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS lib_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    location VARCHAR(255),
    event_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
@mysqli_query($conn, $createTable);

// Add event_image column if it doesn't exist (for existing tables)
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM lib_events LIKE 'event_image'");
if ($checkColumn && mysqli_num_rows($checkColumn) == 0) {
    @mysqli_query($conn, "ALTER TABLE lib_events ADD COLUMN event_image VARCHAR(255) AFTER location");
}

// Handle Add/Edit Event
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
        $event_time = mysqli_real_escape_string($conn, $_POST['event_time']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);

        // Image Handling
        $event_image = "";
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
            $target_dir = "../uploads/events/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = strtolower(pathinfo($_FILES["event_image"]["name"], PATHINFO_EXTENSION));
            $clean_title = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '_', $title));
            $file_name = $clean_title . "_" . substr(md5(time()), 0, 8) . "." . $file_extension;
            $target_file = $target_dir . $file_name;
            if (move_uploaded_file($_FILES["event_image"]["tmp_name"], $target_file)) {
                $event_image = "uploads/events/" . $file_name;
            }
        }

        if ($action === 'add') {
            $insertQuery = "INSERT INTO lib_events (title, description, event_date, event_time, location, event_image) VALUES ('$title', '$description', '$event_date', '$event_time', '$location', " . ($event_image ? "'$event_image'" : "NULL") . ")";
            if (mysqli_query($conn, $insertQuery)) {
                $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Event added successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-circle'></i> Error: " . mysqli_error($conn) . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        } elseif ($action === 'edit') {
            $id = mysqli_real_escape_string($conn, $_POST['event_id']);
            $updateQuery = "UPDATE lib_events SET title='$title', description='$description', event_date='$event_date', event_time='$event_time', location='$location'";
            if ($event_image != "") {
                $updateQuery .= ", event_image='$event_image'";
            }
            $updateQuery .= " WHERE id='$id'";
            if (mysqli_query($conn, $updateQuery)) {
                $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Event updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-circle'></i> Error: " . mysqli_error($conn) . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $deleteQuery = "DELETE FROM lib_events WHERE id='$id'";
    if (mysqli_query($conn, $deleteQuery)) {
        header("Location: manage_events.php?status=deleted");
        exit();
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
    $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Event deleted successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// Fetch events for FullCalendar
$events_data = [];
$fetchQuery = "SELECT * FROM lib_events";
$fetchResult = mysqli_query($conn, $fetchQuery);
if ($fetchResult) {
    while ($row = mysqli_fetch_assoc($fetchResult)) {
        $events_data[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'start' => $row['event_date'] . ($row['event_time'] ? 'T' . $row['event_time'] : ''),
            'description' => $row['description'],
            'location' => $row['location'],
            'time' => $row['event_time'],
            'image' => getEventImageAdmin($row['event_image'])
        ];
    }
}

// Helper to find event image
function getEventImageAdmin($filename) {
    if (empty($filename)) return '../img/movepic1.jpg'; // Default library event image
    
    if (strpos($filename, '/') !== false && file_exists('../' . $filename)) {
        return '../' . $filename;
    }

    $locations = ['', 'uploads/', 'uploads/events/', 'img/'];
    foreach ($locations as $loc) {
        $path = '../' . $loc . basename($filename);
        if (file_exists($path)) return $path;
    }
    return '../img/movepic1.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <!-- FullCalendar CSS -->
  <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
  <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
  <style>
    .fc-event { cursor: pointer; }
    .card { box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: none; border-radius: 12px; }
    .card-header { border-radius: 12px 12px 0 0 !important; }
    #calendar { background: #fff; padding: 20px; border-radius: 12px; }
  </style>
</head>
<body style="background-color: #f8f9fa;">
  <div class="container-scroller">
    <?php include "partials/navbar.php";?>
    <div class="container-fluid page-body-wrapper">
      <?php include "partials/settings-panel.php";?>
      <?php include "partials/sidebar.php";?>
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-12 mb-4">
              <h3 class="fw-bold">📅 Library Events Management</h3>
              <p class="text-muted">Manage and schedule library events for visitors.</p>
            </div>
          </div>

          <?php if (!empty($message)) echo $message; ?>

          <div class="row">
            <!-- Calendar Section -->
            <div class="col-lg-8 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <div id='calendar'></div>
                </div>
              </div>
            </div>

            <!-- Add/Edit Section -->
            <div class="col-lg-4 grid-margin stretch-card">
              <div class="card">
                <div class="card-header bg-primary text-white py-3">
                  <h5 class="mb-0" id="form-title"><i class="fas fa-plus-circle me-2"></i> Add New Event</h5>
                </div>
                <div class="card-body">
                  <form method="POST" action="" id="event-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="event_id" id="event_id" value="">
                    
                    <div class="mb-3">
                      <label class="form-label fw-bold">Event Title</label>
                      <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Book Fair 2024" required>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">Date</label>
                      <input type="date" name="event_date" id="event_date" class="form-control" required>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">Time</label>
                      <input type="time" name="event_time" id="event_time" class="form-control">
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">Location</label>
                      <input type="text" name="location" id="location" class="form-control" placeholder="e.g. Main Hall">
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">Description</label>
                      <textarea name="description" id="description" class="form-control" rows="4" placeholder="Event details..."></textarea>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">Event Image</label>
                      <div class="d-flex align-items-center">
                        <img id="event_image_preview" src="" style="width: 60px; height: 60px; object-fit: cover; margin-right: 15px; border-radius: 8px; display: none;">
                        <input type="file" name="event_image" id="event_image" class="form-control" accept="image/*">
                      </div>
                      <small class="text-muted">Upload a poster or photo for the event.</small>
                    </div>

                    <div class="mt-4">
                      <button type="submit" class="btn btn-primary w-100 mb-2" id="submit-btn text-white">Save Event</button>
                      <button type="button" class="btn btn-light w-100" id="reset-btn" onclick="resetForm()">Cancel</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- Events Table -->
          <div class="row">
            <div class="col-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-header bg-dark text-white py-3">
                  <h5 class="mb-0"><i class="fas fa-list me-2"></i> Upcoming Events List</h5>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Poster</th>
                          <th>Title</th>
                          <th>Date & Time</th>
                          <th>Location</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $listResult = mysqli_query($conn, "SELECT * FROM lib_events ORDER BY event_date ASC");
                        if (mysqli_num_rows($listResult) > 0) {
                            while ($row = mysqli_fetch_assoc($listResult)) {
                                $dateTime = date("M d, Y", strtotime($row['event_date'])) . ($row['event_time'] ? " at " . date("h:i A", strtotime($row['event_time'])) : "");
                                ?>
                                <tr>
                                  <td>
                                    <img src="<?php echo getEventImageAdmin($row['event_image']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;" alt="Poster">
                                  </td>
                                  <td class="fw-bold"><?php echo htmlspecialchars($row['title']); ?></td>
                                  <td><?php echo $dateTime; ?></td>
                                  <td><?php echo htmlspecialchars($row['location'] ?: 'N/A'); ?></td>
                                  <td>
                                    <button class="btn btn-sm btn-info text-white" onclick="editEvent(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                      <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this event?')">
                                      <i class="fas fa-trash"></i>
                                    </a>
                                  </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center text-muted'>No events scheduled.</td></tr>";
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
        <?php include 'partials/footer.php'; ?>
      </div>
    </div>
  </div>

  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <script src="static/js/off-canvas.js"></script>
  <script src="static/js/hoverable-collapse.js"></script>
  <script src="static/js/template.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        themeSystem: 'bootstrap',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: <?php echo json_encode($events_data); ?>,
        eventClick: function(info) {
          // Find original data
          const event = <?php echo json_encode($events_data); ?>.find(e => e.id == info.event.id);
          if (event) {
            editEvent({
                id: event.id,
                title: event.title,
                event_date: event.start.split('T')[0],
                event_time: event.time,
                location: event.location,
                description: event.description
            });
          }
        }
      });
      calendar.render();
    });

    function editEvent(data) {
      document.getElementById('form-title').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Event';
      document.getElementById('form-action').value = 'edit';
      document.getElementById('event_id').value = data.id;
      document.getElementById('title').value = data.title;
      document.getElementById('event_date').value = data.event_date;
      document.getElementById('event_time').value = data.event_time;
      document.getElementById('location').value = data.location;
      document.getElementById('description').value = data.description;
      
      const imgPreview = document.getElementById('event_image_preview');
      if (data.event_image) {
          imgPreview.src = getEventUrl(data.event_image);
          imgPreview.style.display = 'block';
      } else if (data.image) {
          imgPreview.src = data.image; // Already processed by helper
          imgPreview.style.display = 'block';
      } else {
          imgPreview.style.display = 'none';
      }
      
      document.getElementById('event-form').scrollIntoView({ behavior: 'smooth' });
    }

    function getEventUrl(path) {
        if (!path) return '';
        if (path.startsWith('../')) return path;
        return '../' + path;
    }

    function resetForm() {
      document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Add New Event';
      document.getElementById('form-action').value = 'add';
      document.getElementById('event-form').reset();
      document.getElementById('event_image_preview').style.display = 'none';
    }
  </script>
</body>
</html>
