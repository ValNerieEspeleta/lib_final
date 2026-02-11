<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set custom session save path
$sess_path = __DIR__ . '/sessions';
if (!is_dir($sess_path)) {
    mkdir($sess_path, 0777, true);
}
session_save_path($sess_path);
session_start();
ob_start();
include 'includes/dbcon.php';

// Variable to hold error messages
$error_message = "";

// Login logic moved to login.php

// Fetch only 4 books
$books = [];
$sql = "SELECT b.book_id, b.title, b.description, b.status, b.genre_id, g.name as genre_name,
               (SELECT GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') 
                FROM lib_book_authors ba 
                JOIN lib_authors a ON ba.author_id = a.id 
                WHERE ba.book_id = b.book_id) as author_name
        FROM lib_books b
        LEFT JOIN lib_genres g ON b.genre_id = g.id
        ORDER BY b.book_id DESC
        LIMIT 4";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $desc = strip_tags($row['description']); 
        $words = explode(" ", $desc);
        if (count($words) > 20) {
            $desc = implode(" ", array_slice($words, 0, 20)) . "...";
        }
        $row['short_description'] = $desc;
        $books[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>SRC Library</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Roboto:wght@700;800&display=swap"
        rel="stylesheet">

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
    <link rel="stylesheet" href="static/css/dark-mode.css">

    <style>
        :root {
            --login-primary: #00378aff;
            --login-primary-hover: #002d70;
            --login-text-main: #1e293b;
            --login-text-muted: #64748b;
        }

        .login-section {
            background: linear-gradient(rgba(0, 55, 138, 0.05), rgba(0, 55, 138, 0.1)), url('img/library_bg.png');
            background-size: cover;
            background-position: center;
            padding: 80px 0;
            position: relative;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 40px;
            max-width: 450px;
            margin: 0 auto;
        }

        .login-title {
            color: var(--login-primary);
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 10px;
            text-align: center;
        }

        .login-subtitle {
            color: var(--login-text-muted);
            font-size: 0.95rem;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-label {
            font-weight: 600;
            color: var(--login-text-main);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .input-group-text {
            background-color: #f8fafc;
            border-right: none;
            color: var(--login-text-muted);
        }

        .login-card .form-control {
            border-left: none;
            padding: 12px;
            background-color: #f8fafc;
        }

        .login-card .form-control:focus {
            box-shadow: none;
            border-color: #dee2e6;
            background-color: #fff;
        }

        .btn-login {
            background-color: var(--login-primary);
            color: white;
            padding: 12px;
            font-weight: 700;
            border-radius: 10px;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s;
            border: none;
        }

        .btn-login:hover {
            background-color: var(--login-primary-hover);
            transform: translateY(-2px);
            color: white;
        }

        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            color: var(--login-text-muted);
        }

        .dark-mode .login-card {
            background: rgba(30, 41, 59, 0.9);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .dark-mode .login-title, .dark-mode .form-label {
            color: #f1f5f9;
        }

        .dark-mode .login-subtitle {
            color: #94a3b8;
        }

        .dark-mode .login-card .form-control, .dark-mode .input-group-text {
            background-color: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        /* --- Features Section Styles --- */
        .feature-item {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 30px;
            height: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
            background: white;
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .rule-item {
            display: flex;
            align-items: start;
            gap: 15px;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .rule-item:hover {
            background: #e9ecef;
        }

        .dark-mode .feature-item {
            background: #1e293b;
            border-color: #334155;
            color: #f1f5f9;
        }

        .dark-mode .rule-item {
            background: #0f172a;
            color: #f1f5f9;
        }
    </style>

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


    <!-- Carousel Start -->
    <div class="container-fluid p-0 mb-6 wow fadeIn" data-wow-delay="0.1s">
        <div id="header-carousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#header-carousel" data-bs-slide-to="0" class="active"
                    aria-current="true" aria-label="Slide 1">
                    <img class="img-fluid" src="img/movepic1.jpg" alt="Image">
                </button>
                <button type="button" data-bs-target="#header-carousel" data-bs-slide-to="1" aria-label="Slide 2">
                    <img class="img-fluid" src="img/movepic2.jpg" alt="Image">
                </button>
                <button type="button" data-bs-target="#header-carousel" data-bs-slide-to="2" aria-label="Slide 3">
                    <img class="img-fluid" src="img/movepic3.jpg" alt="Image">
                </button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img class="w-100" src="img/movepic1.jpg" alt="Image">
                    <div class="carousel-caption">
                        <h1 class="display-1 text-uppercase text-white mb-4 animated zoomIn">Welcome to Santa Rita College Library
                        </h1>
                        <a href="#" class="btn btn-primary py-3 px-4">Explore More</a>
                    </div>
                </div>
                <div class="carousel-item">
                    <img class="w-100" src="img/movepic2.jpg" alt="Image">
                    <div class="carousel-caption">
                        <h1 class="display-1 text-uppercase text-white mb-4 animated zoomIn">Welcome to Santa Rita College Library
                        </h1>
                        <a href="#" class="btn btn-primary py-3 px-4">Explore More</a>
                    </div>
                </div>
                <div class="carousel-item">
                    <img class="w-100" src="img/movepic3.jpg" alt="Image">
                    <div class="carousel-caption">
                        <h1 class="display-1 text-uppercase text-white mb-4 animated zoomIn">Welcome to Santa Rita College Library
                        </h1>
                        <a href="#" class="btn btn-primary py-3 px-4">Explore More</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Carousel End -->

    <!-- Login Section Moved to login.php -->


    <!-- About Start -->
    
    <!-- About End -->


    <!-- Features Start -->


    <!-- Main Content -->
    <div class="container my-5">
        <h1 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">
            Santa Rita College of Pampanga Library
        </h1>

        <div class="row g-4">
            <!-- VISION -->
            <div class="col-md-6 col-lg-6">
                <div class="feature-item">
                    <div class="feature-icon bg-primary">
                        <i class="fa fa-eye fa-3x text-white"></i>
                    </div>
                    <h5 class="text-uppercase">VISION</h5>
                    <p style="text-align: justify;">
                        The Santa Rita College of Pampanga Library envisions a transformative learning environment where students, teachers, administrators, and staff are empowered through knowledge, guided by Divine Providence, and inspired to cultivate personal character, civic responsibility, and a profound love for God.
                    </p>
                    <p style="text-align: justify;">
                        We strive to be a beacon of academic excellence and moral growth, nurturing individuals who are not only scholarly proficient but also devoted and responsible citizens of God and country.
                    </p>
                    <a href="#" class="read-more-btn" data-bs-toggle="modal" data-bs-target="#visionModal">
                        READ MORE <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- MISSION -->
            <div class="col-md-6 col-lg-6">
                <div class="feature-item">
                    <div class="feature-icon bg-primary">
                        <i class="fa fa-bullseye fa-3x text-white"></i>
                    </div>
                    <h5 class="text-uppercase">MISSION</h5>
                    <p style="text-align: justify;">
                        Guided by the principles of service to youth, country, and God, the Library of Santa Rita College of Pampanga is committed to supporting the educational, research, and public service missions of our institution.
                    </p>
                    <p style="text-align: justify;">
                        We dedicate ourselves to fostering a nurturing and inclusive environment that promotes academic freedom, personal discipline, and the development of a strong civic conscience.
                    </p>
                    <a href="#" class="read-more-btn" data-bs-toggle="modal" data-bs-target="#missionModal">
                        READ MORE <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- MANDATE -->
            <div class="col-md-6 col-lg-6">
                <div class="feature-item">
                    <div class="feature-icon bg-primary">
                        <i class="fa fa-file-invoice fa-3x text-white"></i>
                    </div>
                    <h5 class="text-uppercase">MANDATE</h5>
                    <p style="text-align: justify;">
                        The Library of Santa Rita College of Pampanga, guided by the principles of service to youth, country, and God, is mandated to provide access to quality resources, information services, and learning spaces that uphold the institution's educational, research, and public service missions.
                    </p>
                    <a href="#" class="read-more-btn" data-bs-toggle="modal" data-bs-target="#mandateModal">
                        READ MORE <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- RULES & REGULATIONS -->
            <div class="col-md-6 col-lg-6">
                <div class="feature-item">
                    <div class="feature-icon bg-primary">
                        <i class="fa fa-bookmark fa-3x text-white"></i>
                    </div>
                    <h5 class="text-uppercase">RULES & REGULATIONS</h5>
                    <p><strong style="font-size: 1.2rem;">Library Hours:</strong></p>
                    <ul style="list-style: none; padding-left: 0; font-size: 1.1rem;">
                        <li style="margin-bottom: 12px;">📅 <strong>Monday - Friday:</strong> 7:30 AM - 5:00 PM</li>
                        <li style="margin-bottom: 12px;">🍽️ <strong>Lunch Break:</strong> 12:00 NN - 1:00 PM</li>
                        <li style="margin-bottom: 12px;">📅 <strong>Saturday:</strong> 9:00 AM - 5:00 PM</li>
                    </ul>
                    <a href="#" class="read-more-btn" data-bs-toggle="modal" data-bs-target="#rulesModal">
                        READ MORE <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Vision Modal -->
    <div class="modal fade" id="visionModal" tabindex="-1" aria-labelledby="visionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="visionModalLabel"><i class="fa fa-eye me-2"></i>OUR VISION</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="text-align: justify;">
                    <p class="lead" style="font-size: 1.3rem; font-weight: 500;">
                        The Santa Rita College of Pampanga Library envisions a transformative learning environment where students, teachers, administrators, and staff are empowered through knowledge, guided by Divine Providence, and inspired to cultivate personal character, civic responsibility, and a profound love for God.
                    </p>
                    <p style="font-size: 1.2rem;">
                        We strive to be a beacon of academic excellence and moral growth, nurturing individuals who are not only scholarly proficient but also devoted and responsible citizens of God and country.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mission Modal -->
    <div class="modal fade" id="missionModal" tabindex="-1" aria-labelledby="missionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="missionModalLabel"><i class="fa fa-bullseye me-2"></i>OUR MISSION</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="text-align: justify;">
                    <p class="lead" style="font-size: 1.3rem; font-weight: 500;">
                        Guided by the principles of service to youth, country, and God, the Library of Santa Rita College of Pampanga is committed to supporting the educational, research, and public service missions of our institution.
                    </p>
                    <p style="font-size: 1.2rem;">
                        We dedicate ourselves to fostering a nurturing and inclusive environment that promotes academic freedom, personal discipline, and the development of a strong civic conscience.
                    </p>
                    <p style="font-size: 1.2rem;">
                        Through our resources and services, we aim to contribute to the holistic formation of each student, equipping them with the tools and values necessary to become exemplary individuals and leaders in service of their community and faith.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mandate Modal -->
    <div class="modal fade" id="mandateModal" tabindex="-1" aria-labelledby="mandateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="mandateModalLabel"><i class="fa fa-file-invoice me-2"></i>OUR MANDATE</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="text-align: justify;">
                    <p class="lead" style="font-size: 1.3rem; font-weight: 500;">
                        The Library of Santa Rita College of Pampanga, guided by the principles of service to youth, country, and God, is mandated to provide access to quality resources, information services, and learning spaces that uphold the institution's educational, research, and public service missions.
                    </p>
                    <p style="font-size: 1.2rem;">
                        It shall foster an inclusive environment that promotes academic freedom, discipline, and civic responsibility, while supporting the holistic formation of students into knowledgeable, value-driven, and community-oriented leaders rooted in faith and service.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rules & Regulations Modal -->
    <div class="modal fade" id="rulesModal" tabindex="-1" aria-labelledby="rulesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="rulesModalLabel"><i class="fa fa-bookmark me-2"></i>LIBRARY RULES & REGULATIONS</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Library Hours -->
                    <div class="mb-5">
                        <h5 class="text-primary mb-4" style="font-size: 1.5rem;">
                            <i class="bi bi-clock me-2"></i>Library Hours
                        </h5>
                        <div class="ps-3" style="font-size: 1.2rem;">
                            <p class="mb-3">📅 <strong>Monday to Friday:</strong> 7:30 AM - 5:00 PM</p>
                            <p class="mb-3">🍽️ <strong>Lunch Break:</strong> 12:00 NN - 1:00 PM</p>
                            <p class="mb-0">📅 <strong>Saturday:</strong> 9:00 AM - 5:00 PM</p>
                        </div>
                    </div>

                    <hr style="margin: 30px 0;">

                    <!-- Discipline and Conduct -->
                    <div class="mb-4">
                        <h5 class="text-primary mb-4" style="font-size: 1.5rem;">
                            <i class="bi bi-shield-check me-2"></i>Discipline and Conduct
                        </h5>
                        <div class="alert alert-info" style="font-size: 1.2rem; padding: 20px;">
                            <strong>1. SILENCE must be observed at all times.</strong>
                        </div>
                        <p class="mb-3" style="font-size: 1.2rem;"><strong>2. The following are NOT ALLOWED in the library and its premises:</strong></p>
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">A</span>
                                    <span style="font-size: 1.1rem;">🚭 <strong>NO SMOKING & NO VAPING</strong></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">B</span>
                                    <span style="font-size: 1.1rem;">🍔 <strong>NO EATING OR DRINKING</strong></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">C</span>
                                    <span style="font-size: 1.1rem;">🚶 <strong>NO LOITERING</strong></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">D</span>
                                    <span style="font-size: 1.1rem;">🗑️ <strong>NO LITTERING</strong></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">E</span>
                                    <span style="font-size: 1.1rem;">😴 <strong>NO SLEEPING</strong></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">F</span>
                                    <span style="font-size: 1.1rem;">✏️ <strong>NO VANDALISM</strong></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">G</span>
                                    <span style="font-size: 1.1rem;">👥 <strong>NO GROUP DISCUSSION</strong></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">H</span>
                                    <span style="font-size: 1.1rem;">💺 <strong>NO SEAT RESERVATION</strong></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="rule-item">
                                    <span class="badge bg-danger">I</span>
                                    <span style="font-size: 1.1rem;">🪑 <strong>KEEP THE TABLES AND CHAIRS WHERE THEY ARE</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>



                <!-- Student Attendance Violations -->
                <div class="mb-4">
                    <h5 class="text-primary mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Student Attendance Violations</h5>
                    <div class="ps-3">
                        <div class="alert alert-warning mb-2">
                            <strong>1st Violation:</strong> Warning
                        </div>
                        <div class="alert alert-warning mb-2">
                            <strong>2nd Violation:</strong> Guidance or Parents Required
                        </div>
                        <div class="alert alert-danger mb-0">
                            <strong>3rd Violation:</strong> Banned from Library
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Book Violations -->
                <div class="mb-4">
                    <h5 class="text-primary mb-3"><i class="bi bi-book me-2"></i>Book Violations</h5>
                    <div class="ps-3">
                        <div class="alert alert-warning">
                            <p class="mb-2"><strong>📕 Damaged Book:</strong></p>
                            <p class="mb-0">The student must REPLACE or REPAIR the damaged book.</p>
                        </div>
                        <div class="alert alert-danger">
                            <p class="mb-2"><strong>📗 Lost Book:</strong></p>
                            <p class="mb-0">The student must PURCHASE a new book similar to the lost one.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- Features End -->
    <!-- restore soon -->

    <!-- Features Start -->
    
    <!-- Features End -->


    <!-- Service Start -->
    <div class="container-fluid service pt-6 pb-6">
        <div class="container">
            <div class="text-center mx-auto wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
                <h1 class="display-6 text-uppercase mb-5">Library Books Collection</h1>
            </div>

            <!-- Books Grid (Only 4 Books) -->
            <div class="row g-4" id="booksContainer">
                <?php if (!empty($books)): ?>
                    <?php foreach ($books as $index => $book): ?>
                        <div class="col-lg-3 col-md-6 wow fadeInUp"
                             data-wow-delay="<?php echo (0.1 * ($index % 4 + 1)); ?>s">
                            <div class="service-item h-100">
                                <div class="service-inner pb-5">
                                    <div class="service-text px-5 pt-4">
                                        <h5 class="text-uppercase"><?php echo htmlspecialchars($book['title']); ?></h5>
                                        <p><?php echo htmlspecialchars($book['short_description']); ?></p>
                                        <p>
                                            <strong>Status: </strong>
                                            <?php if ($book['status'] === 'available'): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Borrowed</span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($book['genre_name'])): ?>
                                            <p><strong>Genre:</strong> <?php echo htmlspecialchars($book['genre_name']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <button 
                                        class="btn btn-light px-3 read-more-btn align-self-start" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#bookModal"
                                        data-description="<?php echo htmlspecialchars($book['description']); ?>"
                                        data-status="<?php echo htmlspecialchars($book['status']); ?>"
                                        data-genre="<?php echo htmlspecialchars($book['genre_name']); ?>"
                                        data-author="<?php echo htmlspecialchars($book['author_name'] ?? 'N/A'); ?>">
                                        Read More<i class="bi bi-chevron-double-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No books available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Book Modal -->
    <div class="modal fade" id="bookModal" tabindex="-1" aria-labelledby="bookModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="bookModalLabel">Book Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <h3 id="modalTitle"></h3>
            <p id="modalDescription"></p>
            <p><strong>Status: </strong><span id="modalStatus"></span></p>
            <p><strong>Genre: </strong><span id="modalGenre"></span></p>
            <p><strong>Author(s): </strong><span id="modalAuthor"></span></p>
            <div class="library-note">
                ðŸ“š Note: To read the full content of this book, please visit the <strong>Santa Rita College of Pampanga Library</strong>.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Service End -->


    <!-- Appoinment Start -->

    <!-- Appoinment End -->


    <!-- Team Start -->
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Staff</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .team-item img {
            width: 100%;
            height: 300px; /* same height for all */
            object-fit: cover; /* crop nicely */
            border-radius: 10px; /* optional rounded corners */
        }
        .team-social {
            background: transparent !important;
        }
        .team-item:hover .team-social {
            background: transparent !important; /* Fully transparent on hover */
        }
    </style>
</head>
<body>
<div class="container-fluid team pt-6 pb-6">
    <div class="container">
        <div class="text-center mx-auto wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
            <h1 class="display-6 text-uppercase mb-5">Library Staff</h1>
        </div>
        <div class="row g-4">
            <?php
            // Helper function to find image in multiple locations
            function getStaffImage($filename) {
                if (empty($filename)) return 'img/defaulticon.png';
                
                $locations = ['', 'uploads/', 'img/', 'SuperAdmin/', 'Admin/'];
                foreach ($locations as $loc) {
                    $path = $loc . $filename;
                    if (file_exists($path)) return $path;
                    
                    // Case-insensitive check for Linux servers
                    $dir = empty($loc) ? '.' : $loc;
                    if (is_dir($dir)) {
                        $files = scandir($dir);
                        foreach ($files as $file) {
                            if (strtolower($file) === strtolower($filename)) {
                                return ($loc === '.' ? '' : $loc) . $file;
                            }
                        }
                    }
                }
                return 'img/defaulticon.png'; // Fallback
            }

            $staffResult = mysqli_query($conn, "SELECT * FROM lib_library_staff ORDER BY id ASC");
            $delay = 0.3;
            while ($staff = mysqli_fetch_assoc($staffResult)):
                $profilePic = getStaffImage($staff['profile_image']);
            ?>
            <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                <div class="team-item">
                    <div class="position-relative overflow-hidden">
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="<?php echo htmlspecialchars($staff['name']); ?>">
                        <div class="team-social">
                            <?php if ($staff['facebook']): ?><a class="btn btn-square btn-dark mx-1" href="<?php echo $staff['facebook']; ?>"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
                            <?php if ($staff['twitter']): ?><a class="btn btn-square btn-dark mx-1" href="<?php echo $staff['twitter']; ?>"><i class="fab fa-twitter"></i></a><?php endif; ?>
                            <?php if ($staff['linkedin']): ?><a class="btn btn-square btn-dark mx-1" href="<?php echo $staff['linkedin']; ?>"><i class="fab fa-linkedin-in"></i></a><?php endif; ?>
                            <?php if ($staff['youtube']): ?><a class="btn btn-square btn-dark mx-1" href="<?php echo $staff['youtube']; ?>"><i class="fab fa-youtube"></i></a><?php endif; ?>
                        </div>
                    </div>
                    <div class="text-center p-4">
                        <h5 class="mb-1"><?php echo htmlspecialchars($staff['name']); ?></h5>
                        <span><?php echo htmlspecialchars($staff['designation']); ?></span>
                    </div>
                </div>
            </div>
            <?php 
            $delay += 0.1;
            endwhile; 
            ?>
        </div>
    </div>
</div>
</body>
</html>
    <!-- Team End -->


    <!-- Testimonial Start -->
    
    <!-- Testimonial End -->


    <!-- Newsletter Start -->
    
    <!-- Newsletter Start -->


    <!-- Footer Start -->
   <?php include 'includes/footer.php'; ?>   
    <!-- Footer End -->


    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i
            class="bi bi-arrow-up"></i></a>


    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>

    <!-- Template Javascript -->
    <script src="js/main.js"></script>

    <script>
    function togglePassword() {
        var passwordInput = document.getElementById("password");
        var toggleIcon = document.getElementById("toggleIcon");
        
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            toggleIcon.classList.remove("fa-eye");
            toggleIcon.classList.add("fa-eye-slash");
        } else {
            passwordInput.type = "password";
            toggleIcon.classList.remove("fa-eye-slash");
            toggleIcon.classList.add("fa-eye");
        }
    }

    document.addEventListener("keydown", function(event) {
        // Example: Ctrl + L will focus login form
        if (event.ctrlKey && event.key.toLowerCase() === "l") {
            event.preventDefault();
            document.getElementById("login").scrollIntoView({ behavior: 'smooth' });
            document.getElementsByName("username")[0].focus();
        }
    });

    // Handle session auto-redirect if already logged in and tries to login again
    <?php if (isset($_POST['login']) && isset($_SESSION['role_id'])): ?>
        window.location.href = "<?php echo ($_SESSION['role_id'] == 6) ? 'SuperAdmin/index.php' : 'Admin/index.php'; ?>";
    <?php endif; ?>
    </script>


</script>

</body>

</html>