<?php
include '../includes/session.php';
include '../includes/dbcon.php';

$message = "";

// Handle Update Penalty Rate
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update') {
    $penalty_rate = mysqli_real_escape_string($conn, $_POST['penalty_rate']);
    
    if (!is_numeric($penalty_rate)) {
        $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-circle'></i> Please enter a valid number!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $updateQuery = "UPDATE lib_penalty_settings SET penalty_rate = '$penalty_rate' WHERE id = 1";
        if (mysqli_query($conn, $updateQuery)) {
            $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Penalty rate updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-circle'></i> Error: " . mysqli_error($conn) . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}

// Fetch current penalty rate
$penalty_result = mysqli_query($conn, "SELECT penalty_rate FROM lib_penalty_settings WHERE id = 1");
$penalty_data = mysqli_fetch_assoc($penalty_result);
$current_rate = $penalty_data ? $penalty_data['penalty_rate'] : 0.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .card { box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: none; border-radius: 12px; }
    .card-header { border-radius: 12px 12px 0 0 !important; }
    .penalty-info { background: #f0f7ff; padding: 20px; border-radius: 8px; border-left: 5px solid #007bff; }
    .math-formula { font-family: 'Courier New', Courier, monospace; background: #eee; padding: 5px 10px; border-radius: 4px; }
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
            <div class="col-md-6 grid-margin stretch-card">
              <div class="card">
                <div class="card-header bg-primary text-white py-3">
                  <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2"></i> Manage Penalty Settings</h5>
                </div>
                <div class="card-body">
                  <?php if (!empty($message)) echo $message; ?>
                  
                  <div class="penalty-info mb-4">
                    <h6><i class="fas fa-calculator me-2"></i> Penalty Calculation Logic:</h6>
                    <p class="mb-2">The total penalty is calculated exponentially based on the number of books and days overdue:</p>
                    <div class="math-formula mb-3">
                      Total Penalty = (No. of Books × Penalty Rate) × Days Overdue
                    </div>
                    <strong>Example:</strong>
                    <ul class="mb-0">
                      <li>Books Borrowed: 3</li>
                      <li>Penalty Rate: ₱<?php echo number_format($current_rate, 2); ?></li>
                      <li>Days Overdue: 5 days</li>
                      <li>Computation: (3 × <?php echo $current_rate; ?>) × 5 = <strong>₱<?php echo number_format($current_rate * 3 * 5, 2); ?></strong></li>
                    </ul>
                  </div>

                  <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <div class="form-group mb-4">
                      <label for="penalty_rate" class="form-label fw-bold">Daily Penalty Rate (per book) <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" min="0" class="form-control form-control-lg" id="penalty_rate" name="penalty_rate" value="<?php echo htmlspecialchars($current_rate); ?>" placeholder="0.00" required>
                      </div>
                      <small class="text-muted">Enter the cost per book for each day it remains overdue.</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm">
                      <i class="fas fa-sync-alt me-2"></i> Update Rate
                    </button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-md-6 grid-margin stretch-card">
              <div class="card bg-info text-white shadow-sm overflow-hidden" style="border-radius: 12px;">
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-5">
                   <div class="rounded-circle bg-white p-4 mb-3" style="width: 100px; height: 100px; display: flex; align-items: center; justify-content: center;">
                       <i class="fas fa-file-invoice-dollar text-info fs-1"></i>
                   </div>
                   <h2 class="display-4 fw-bold">₱<?php echo number_format($current_rate, 2); ?></h2>
                   <p class="fs-5 opacity-75">Current Active Penalty Rate</p>
                   <hr class="w-25 bg-white opacity-25">
                   <p class="px-4">This rate is automatically applied to all overdue books currently managed in the system.</p>
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
</body>
</html>
