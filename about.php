<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>About</title>
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
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="bg-white p-5">
                        <h1 class="display-6 text-uppercase mb-3 animated slideInDown">About</h1>
                        <nav aria-label="breadcrumb animated slideInDown">
                            <ol class="breadcrumb justify-content-center mb-0">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item"><a href="#">Pages</a></li>
                                <li class="breadcrumb-item" aria-current="page">About</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Page Header End -->


    <!-- About Start -->
    
    <!-- About End -->


    <!-- Features Start -->
    
    <!-- Features End -->


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
            background: transparent !important;
        }
    </style>
</head>
<body>
<?php
include 'includes/dbcon.php';

// Helper function to find image in multiple locations
function getStaffImage($filename) {
    if (empty($filename)) return 'img/defaulticon.png';
    
    // If it already contains a directory separator, check it directly first
    if (strpos($filename, '/') !== false && file_exists($filename)) {
        return $filename;
    }

    $locations = ['', 'uploads/', 'uploads/staff/', 'img/', 'SuperAdmin/', 'Admin/'];
    foreach ($locations as $loc) {
        $path = $loc . basename($filename);
        if (file_exists($path)) return $path;
        
        // Case-insensitive check for Linux servers
        $dir = empty($loc) ? '.' : $loc;
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if (strtolower($file) === strtolower(basename($filename))) {
                    return ($loc === '.' ? '' : $loc) . $file;
                }
            }
        }
    }
    return 'img/defaulticon.png'; // Fallback
}
?>
<div class="container-fluid team pt-6 pb-6">
    <div class="container">
        <div class="text-center mx-auto wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
            <h1 class="display-6 text-uppercase mb-5">Library Staff</h1>
        </div>
        <div class="row g-4">
            <?php
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

    
    <!-- Newsletter Start -->
    <!-- Newsletter End -->


    <!-- Footer Start -->
    <?php include 'includes/footer.php'; ?>
    <!-- Copyright End -->


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
</body>

</html>