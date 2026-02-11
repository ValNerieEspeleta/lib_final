<?php
session_start();
include 'includes/dbcon.php';

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
            'image' => getEventImage($row['event_image'])
        ];
    }
}

// Helper to find event image
function getEventImage($filename) {
    if (empty($filename)) return 'img/movepic1.jpg'; // Default library event image
    
    if (strpos($filename, '/') !== false && file_exists($filename)) {
        return $filename;
    }

    $locations = ['', 'uploads/', 'uploads/events/', 'img/'];
    foreach ($locations as $loc) {
        $path = $loc . basename($filename);
        if (file_exists($path)) return $path;
    }
    return 'img/movepic1.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Library Events - SRC Library</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    
    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Roboto:wght@700;800&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.4/font/bootstrap-icons.css">

    <!-- Libraries Stylesheet -->
    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="css/style.css" rel="stylesheet">

    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    
    <style>
        .page-header {
            background: linear-gradient(rgba(0, 55, 138, 0.7), rgba(0, 55, 138, 0.7)), url(img/movepic1.jpg) center center no-repeat;
            background-size: cover;
        }
        #calendar {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 45px rgba(0,0,0,.08);
            margin-bottom: 30px;
        }
        .event-card {
            transition: .5s;
            border: 1px solid #dee2e6;
            border-radius: 10px;
        }
        .event-card:hover {
            box-shadow: 0 0 15px rgba(0,0,0,.1);
            transform: translateY(-5px);
        }
    </style>
</head>

<body>
    <!-- Spinner Start -->
    <?php include 'includes/spinner.php'; ?>
    <!-- Spinner End -->

    <!-- Topbar Start -->
    <?php include 'includes/topbar.php'; ?>
    <!-- Topbar End -->

    <!-- Navbar Start -->
    <?php include 'includes/navbar.php'; ?>
    <!-- Navbar End -->

    <!-- Page Header Start -->
    <div class="container-fluid page-header pt-5 mb-6 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center pt-5">
            <h1 class="display-4 text-white text-uppercase mb-3 animated slideInDown">Library Events</h1>
            <nav aria-label="breadcrumb animated slideInDown">
                <ol class="breadcrumb justify-content-center mb-0 text-white">
                    <li class="breadcrumb-item text-white"><a class="text-white" href="index.php">Home</a></li>
                    <li class="breadcrumb-item text-white active" aria-current="page">Events</li>
                </ol>
            </nav>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Events Start -->
    <div class="container-xxl py-6">
        <div class="container">
            <div class="text-center mx-auto mb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
                <h6 class="text-primary text-uppercase mb-2">Calendar</h6>
                <h1 class="display-6 mb-4">Upcoming Library Events & Activities</h1>
            </div>

            <div class="row g-5">
                <div class="col-lg-12 wow fadeInUp" data-wow-delay="0.1s">
                    <div id='calendar'></div>
                </div>
            </div>

            <div class="row g-4 mt-5">
                <div class="col-12 wow fadeInUp" data-wow-delay="0.1s">
                    <h2 class="mb-4 text-uppercase">Events List</h2>
                </div>
                <?php
                $listResult = mysqli_query($conn, "SELECT * FROM lib_events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
                if (mysqli_num_rows($listResult) > 0) {
                    while ($row = mysqli_fetch_assoc($listResult)) {
                        ?>
                        <div class="col-lg-6 col-md-12 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="event-card p-5 h-100 bg-white shadow-sm">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="bg-primary text-white text-center p-3 rounded-3 me-4" style="min-width: 80px; height: 80px;">
                                        <h2 class="text-white mb-0"><?php echo date("d", strtotime($row['event_date'])); ?></h2>
                                        <h6 class="text-white text-uppercase mb-0"><?php echo date("M", strtotime($row['event_date'])); ?></h6>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($row['title']); ?></h3>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <img src="<?php echo getEventImage($row['event_image']); ?>" class="img-fluid rounded-3 w-100" style="height: 250px; object-fit: cover;" alt="Event Poster">
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-sm-6">
                                        <p class="text-muted fs-5 mb-0"><i class="fa fa-clock text-primary me-2"></i><?php echo $row['event_time'] ? date("h:i A", strtotime($row['event_time'])) : 'N/A'; ?></p>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="text-muted fs-5 mb-0"><i class="fa fa-map-marker-alt text-primary me-2"></i><?php echo htmlspecialchars($row['location'] ?: 'Library Hall'); ?></p>
                                    </div>
                                </div>
                                <hr class="my-4">
                                <p class="fs-5 text-dark"><?php echo htmlspecialchars($row['description']); ?></p>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='col-12 text-center py-5'><p class='text-muted fs-5'>No upcoming events scheduled at this time. Check back later!</p></div>";
                }
                ?>
            </div>
        </div>
    </div>
    <!-- Events End -->

    <!-- Event Detail Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalEventTitle">Event Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4 text-center">
                        <img id="modalEventImage" src="" class="img-fluid rounded-3 shadow-sm" style="max-height: 300px; width: 100%; object-fit: cover;" alt="Event Poster">
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold text-primary">Location:</label>
                        <p id="modalEventLocation"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold text-primary">Time:</label>
                        <p id="modalEventTime"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold text-primary">Description:</label>
                        <p id="modalEventDescription"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Start -->
    <?php include 'includes/footer.php'; ?>
    <!-- Footer End -->

    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>

    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

    <!-- Template Javascript -->
    <script src="js/main.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                themeSystem: 'bootstrap5',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: <?php echo json_encode($events_data); ?>,
                eventClick: function(info) {
                    const event = <?php echo json_encode($events_data); ?>.find(e => e.id == info.event.id);
                    if (event) {
                        document.getElementById('modalEventTitle').innerText = event.title;
                        document.getElementById('modalEventLocation').innerText = event.location || 'Library Hall';
                        document.getElementById('modalEventTime').innerText = event.time ? new Date('1970-01-01T' + event.time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'N/A';
                        document.getElementById('modalEventDescription').innerText = event.description || 'No description provided.';
                        document.getElementById('modalEventImage').src = event.image;
                        
                        var myModal = new bootstrap.Modal(document.getElementById('eventModal'));
                        myModal.show();
                    }
                }
            });
            calendar.render();
        });
    </script>
</body>

</html>
