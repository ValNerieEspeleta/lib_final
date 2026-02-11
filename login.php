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

if (isset($_POST['login'])) {
    // Get the submitted username and password
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Unified check in the employees table
    $query_employees = "SELECT e.*, r.role_name FROM employees e 
                       JOIN roles r ON e.role_id = r.role_id 
                       WHERE (e.email = '$username' OR e.firstname = '$username')";
    $rs_employees = $conn->query($query_employees);

    if ($rs_employees && $rs_employees->num_rows > 0) {
        $rows_employees = $rs_employees->fetch_assoc();
        
        // Verify password
        $password_match = false;
        if (password_verify($password, $rows_employees['password'])) {
            $password_match = true;
        } elseif ($rows_employees['password'] === $password) {
            $password_match = true;
        }
        
        if ($password_match) {
            $_SESSION['userId'] = $rows_employees['employee_id'];
            $_SESSION['firstname'] = $rows_employees['firstname'];
            $_SESSION['lastname'] = $rows_employees['lastname'];
            $_SESSION['fullname'] = trim($rows_employees['firstname'] . ' ' . $rows_employees['lastname']);
            $_SESSION['email'] = $rows_employees['email'];
            $_SESSION['role_id'] = $rows_employees['role_id'];
            $_SESSION['role_name'] = $rows_employees['role_name'];
            $_SESSION['user_type'] = ($rows_employees['role_id'] == 6) ? 'superadmin' : 'staff';
            $_SESSION['profile_pic'] = $rows_employees['profile_pic'];

            session_write_close();
            if ($rows_employees['role_name'] === 'Library Consultant') {
                header('Location: SuperAdmin/index.php');
            } else {
                header('Location: Admin/index.php');
            }
            exit();
        } else {
            $error_message = "Invalid Password!";
        }
    } else {
        $error_message = "Invalid Username or Email!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>SRC Library - Login</title>
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
    <link rel="stylesheet" href="static/css/dark-mode.css">

    <style>
        :root {
            --login-primary: #00378aff;
            --login-primary-hover: #002d70;
            --login-text-main: #1e293b;
            --login-text-muted: #64748b;
        }

        body {
            /* Full screen background for login page */
            background: linear-gradient(rgba(0, 55, 138, 0.05), rgba(0, 55, 138, 0.1)), url('img/library_bg.png');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 450px;
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

        /* Dark Mode overrides for login card */
        .dark-mode .login-card {
            background: rgba(30, 41, 59, 0.9);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .dark-mode .login-title, .dark-mode .form-label { color: #f1f5f9; }
        .dark-mode .login-subtitle { color: #94a3b8; }
        .dark-mode .login-card .form-control, .dark-mode .input-group-text {
            background-color: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }
    </style>
</head>

<body>
    <!-- Topbar Start -->
    <?php include 'includes/topbar.php'; ?>
    <!-- Topbar End -->

    <!-- Navbar Start -->
    <?php include 'includes/navbar.php'; ?>
    <!-- Navbar End -->

    <div class="login-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5">
                    <div class="login-card wow fadeInUp" data-wow-delay="0.1s">
                        <div class="text-center mb-4">
                            <img src="img/srclogo.png" alt="Logo" style="width: 80px; height: auto;">
                        </div>
                        <h2 class="login-title">Staff Portal</h2>
                        <p class="login-subtitle">Sign in to access the management system</p>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <div><?php echo $error_message; ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php">
                            <div class="mb-4">
                                <label class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="username" placeholder="Enter your credential" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <div class="input-group position-relative">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required style="border-right: none;">
                                    <span class="input-group-text" style="background: #f8fafc; border-left: none;">
                                        <i class="fas fa-eye password-toggle" id="toggleIcon" onclick="togglePassword()"></i>
                                    </span>
                                </div>
                            </div>

                            <button type="submit" name="login" class="btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i> SIGN IN
                            </button>
                        </form>
                        
                        <?php if (isset($_SESSION['role_id'])): ?>
                            <div class="mt-4 text-center">
                                <p class="text-success fw-bold">You are already logged in as <?php echo htmlspecialchars($_SESSION['role_name']); ?>.</p>
                                <a href="<?php echo ($_SESSION['role_id'] == 6) ? 'SuperAdmin/index.php' : 'Admin/index.php'; ?>" class="btn btn-outline-primary w-100">
                                    Go to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="js/main.js"></script>
    
    <script>
        function togglePassword() {
            var passwordInput = document.getElementById("password");
            var icon = document.getElementById("toggleIcon");
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                passwordInput.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>

</html>
