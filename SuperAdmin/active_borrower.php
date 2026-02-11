<?php
error_reporting(0);
include '../includes/session.php';
include '../includes/dbcon.php';

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';

// Get search parameter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query based on filter
$filterWhere = "WHERE bt.status != 'returned'";
$filterWhereCount = "WHERE status != 'returned'";
$filterTitle = "Active Borrowers";

if (!empty($search)) {
    $searchCond = " (CONCAT(s.first_name, ' ', s.last_name) LIKE '%$search%' OR CONCAT(e.firstname, ' ', e.lastname) LIKE '%$search%' OR s.student_id LIKE '%$search%' OR e.employee_id LIKE '%$search%' OR bt.uid LIKE '%$search%')";
    $filterWhere .= " AND " . $searchCond;
    $filterWhereCount .= " AND " . $searchCond;
}

switch ($filter) {
    case 'all':
        $filterWhere = !empty($search) ? "WHERE $searchCond" : "";
        $filterWhereCount = !empty($search) ? "WHERE $searchCond" : "";
        $filterTitle = "All Borrowing Transactions";
        break;
    case 'returned':
        $filterWhere = "WHERE bt.status = 'returned'";
        if (!empty($search)) $filterWhere .= " AND " . $searchCond;
        $filterWhereCount = "WHERE status = 'returned'";
        if (!empty($search)) $filterWhereCount .= " AND " . $searchCond;
        $filterTitle = "Returned Books";
        break;
    case 'active':
        $filterWhere = "WHERE bt.status != 'returned'";
        if (!empty($search)) $filterWhere .= " AND " . $searchCond;
        $filterWhereCount = "WHERE status != 'returned'";
        if (!empty($search)) $filterWhereCount .= " AND " . $searchCond;
        $filterTitle = "Active Borrowers";
        break;
}

// Pagination
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch all borrowers with their book count
// We join with both students and employees tables to handle both types of users
$borrowersSQL = "SELECT 
    bt.student_id as borrower_id,
    bt.uid,
    COALESCE(CONCAT(s.first_name, ' ', s.last_name), CONCAT(e.firstname, ' ', e.lastname)) as borrower_name,
    CASE 
        WHEN s.student_id IS NOT NULL THEN 'Student' 
        WHEN e.employee_id IS NOT NULL THEN 'Staff' 
        ELSE 'Unknown'
    END as borrower_type,
    bt.student_id,
    COUNT(*) as total_books_borrowed,
    MAX(bt.borrow_date) as last_borrow_date,
    GROUP_CONCAT(DISTINCT b.title SEPARATOR ', ') as books_borrowed
FROM lib_rfid_loan bt
LEFT JOIN lib_books b ON bt.book_id = b.book_id
LEFT JOIN students s ON bt.student_id = s.student_id
LEFT JOIN employees e ON bt.student_id = e.employee_id
$filterWhere
GROUP BY bt.student_id, bt.uid, borrower_name, borrower_type
ORDER BY borrower_name ASC
LIMIT $offset, $limit";

$borrowersResult = mysqli_query($conn, $borrowersSQL);

if (!$borrowersResult) {
    die("Error fetching borrowers: " . mysqli_error($conn));
}

$borrowers = mysqli_fetch_all($borrowersResult, MYSQLI_ASSOC);

// Get total count for pagination
// Get total count for pagination w/ search support
$countSQL = "SELECT COUNT(DISTINCT bt.student_id) as total 
             FROM lib_rfid_loan bt 
             LEFT JOIN students s ON bt.student_id = s.student_id
             LEFT JOIN employees e ON bt.student_id = e.employee_id
             $filterWhere";
$countResult = mysqli_query($conn, $countSQL);
if (!$countResult) { die("Count Error: " . mysqli_error($conn)); }
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $limit);

// Get summary statistics
$summarySQL = "SELECT 
    COUNT(DISTINCT student_id) as total_borrowers,
    COUNT(*) as total_transactions,
    SUM(CASE WHEN status != 'returned' THEN 1 ELSE 0 END) as active_loans,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_loans
FROM lib_rfid_loan";

$summaryResult = mysqli_query($conn, $summarySQL);
$summary = mysqli_fetch_assoc($summaryResult);

// Fetch unique names for the search datalist (Autocomplete)
$namesForRecomm = [];
$nameQ = "SELECT DISTINCT first_name, last_name FROM students UNION SELECT DISTINCT firstname, lastname FROM employees";
$nameRes = mysqli_query($conn, $nameQ);
if ($nameRes) {
    while($nr = mysqli_fetch_assoc($nameRes)) {
        $fullName = $nr['first_name'] . ' ' . $nr['last_name'];
        $namesForRecomm[] = $fullName;
    }
} else {
    // Fail silently or log error
    error_log("Search recommendation query failed: " . mysqli_error($conn));
}

// Fetch current penalty rate for calculations
$penalty_rate_q = mysqli_query($conn, "SELECT penalty_rate FROM lib_penalty_settings WHERE id = 1");
$penalty_rate_row = mysqli_fetch_assoc($penalty_rate_q);
$global_penalty_rate = $penalty_rate_row ? $penalty_rate_row['penalty_rate'] : 10.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "partials/head.php";?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }

        .stat-card.borrowers { border-left-color: #667eea; }
        .stat-card.transactions { border-left-color: #3182ce; }
        .stat-card.active { border-left-color: #48bb78; }
        .stat-card.returned { border-left-color: #f6ad55; }

        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            margin: 10px 0;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .stat-icon {
            font-size: 48px;
            color: rgba(102, 126, 234, 0.2);
            margin-bottom: 10px;
        }

        .filter-section {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-badge {
            background: white;
            border: 2px solid #e2e8f0;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #2d3748;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-badge:hover,
        .filter-badge.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .search-container {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border-radius: 25px;
            border: 2px solid #e2e8f0;
            background: white;
            transition: all 0.3s;
            font-size: 15px;
            outline: none;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .borrower-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
            transition: all 0.3s;
        }

        .borrower-card:hover {
            transform: translateX(5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .borrower-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .borrower-name {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .borrower-info {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            flex-wrap: wrap;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f7fafc;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #4a5568;
        }

        .info-badge i {
            color: #667eea;
        }

        .books-list {
            background: #f7fafc;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .books-list-title {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }

        .book-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        .book-item:last-child {
            border-bottom: none;
        }

        .book-title {
            color: #2d3748;
            font-weight: 600;
        }

        .book-count {
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .type-badge.student {
            background: #bee3f8;
            color: #2c5282;
        }

        .type-badge.staff {
            background: #feebc8;
            color: #7c2d12;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
            background: white;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #2d3748;
            margin-bottom: 10px;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .pagination {
            display: flex;
            gap: 10px;
            list-style: none;
            padding: 0;
        }

        .page-link {
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            color: #2d3748;
            text-decoration: none;
            transition: all 0.3s;
        }

        .page-link:hover, .page-link.active {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
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
            
            <div class="page-header" style="background: transparent; box-shadow: none; padding: 0; margin-bottom: 20px;">
                <h3 class="page-title" style="color: #343a40; font-size: 1.8rem;">
                    <span class="page-title-icon bg-gradient-primary text-white me-2">
                        <i class="fas fa-book-reader"></i>
                    </span> 
                    <?php echo $filterTitle; ?>
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Borrower Report</li>
                    </ol>
                </nav>
            </div>

            <div class="stats-container">
                <div class="stat-card borrowers">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-label">Total Borrowers</div>
                    <div class="stat-number"><?php echo $summary['total_borrowers'] ?? 0; ?></div>
                </div>

                <div class="stat-card transactions">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-label">Total Transactions</div>
                    <div class="stat-number"><?php echo $summary['total_transactions'] ?? 0; ?></div>
                </div>

                <div class="stat-card active">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-label">Active Loans</div>
                    <div class="stat-number"><?php echo $summary['active_loans'] ?? 0; ?></div>
                </div>

                <div class="stat-card returned">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-label">Returned Books</div>
                    <div class="stat-number"><?php echo $summary['returned_loans'] ?? 0; ?></div>
                </div>
            </div>

            <div class="filter-section align-items-center">
                <div class="d-flex gap-2 flex-wrap flex-grow-1">
                    <a href="active_borrower.php?filter=active&search=<?php echo urlencode($search); ?>" class="filter-badge <?php echo $filter === 'active' ? 'active' : ''; ?>">
                        <i class="fas fa-hourglass-half"></i> Active Borrowers
                    </a>
                    <a href="active_borrower.php?filter=returned&search=<?php echo urlencode($search); ?>" class="filter-badge <?php echo $filter === 'returned' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i> Returned Books
                    </a>
                    <a href="active_borrower.php?filter=all&search=<?php echo urlencode($search); ?>" class="filter-badge <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All Transactions
                    </a>
                </div>
                
                <div class="search-container">
                    <form action="" method="GET">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <button type="submit" class="search-icon" style="background: none; border: none; padding: 0; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <input type="text" name="search" class="search-input" list="searchRecommendations" placeholder="Search by name, ID or RFID..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                        <datalist id="searchRecommendations">
                            <?php foreach($namesForRecomm as $name): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </form>
                </div>
            </div>

            <div class="borrowers-container">
                <?php if ($borrowers && count($borrowers) > 0): ?>
                    <?php foreach ($borrowers as $borrower): ?>
                        <?php
                            // Calculate Overdue Status
                            $isOverdue = false;
                            $daysElapsed = 0;
                            $statusBadge = '';
                            $statusColor = '';

                            if ($filter === 'returned') {
                                // If specifically filtering for returned books, always show as returned
                                $statusBadge = '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Returned</span>';
                                $statusColor = 'border-left-color: #48bb78;';
                            } else {
                                $borrowDate = new DateTime($borrower['last_borrow_date']);
                                $currentDate = new DateTime(); // Now
                                
                                // Calculate the difference in days
                                $interval = $borrowDate->diff($currentDate);
                                $daysElapsed = $interval->days; // Total days since borrow
                                $isOverdue = $daysElapsed >= 1; // Assuming overdue starts after 24 hours
                                
                                if (!$isOverdue) {
                                    $statusBadge = '<span class="badge badge-success"><i class="fas fa-check-circle"></i> On Time</span>';
                                    $statusColor = 'border-left-color: #48bb78;';
                                } elseif ($daysElapsed == 1) {
                                    $statusBadge = '<span class="badge badge-warning"><i class="fas fa-exclamation-circle"></i> 1st Warning (1 Day Overdue)</span>';
                                    $statusColor = 'border-left-color: #ecc94b;'; 
                                } elseif ($daysElapsed == 2) {
                                    $statusBadge = '<span class="badge badge-warning" style="background-color: #d69e2e;"><i class="fas fa-exclamation-triangle"></i> 2nd Warning (2 Days Overdue)</span>';
                                    $statusColor = 'border-left-color: #d69e2e;';
                                 } else {
                                     // 3 days or more
                                     $totalPenalty = ($borrower['total_books_borrowed'] * $global_penalty_rate) * $daysElapsed;
                                     $statusBadge = '<span class="badge badge-danger"><i class="fas fa-money-bill-wave"></i> Penalty Applied: ₱' . number_format($totalPenalty, 2) . ' (' . $daysElapsed . ' Days)</span>';
                                     $statusColor = 'border-left-color: #e53e3e;';
                                 }
                            }
                        ?>
                        <div class="borrower-card" style="<?php echo $statusColor; ?>">
                            <div class="borrower-header">
                                <div>
                                    <h2 class="borrower-name">
                                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($borrower['borrower_name']); ?>
                                    </h2>
                                    <div class="borrower-info">
                                        <span class="info-badge">
                                            <i class="fas fa-id-card"></i> ID: <?php echo htmlspecialchars($borrower['student_id'] ?? $borrower['borrower_id']); ?>
                                        </span>
                                        <span class="type-badge <?php echo strtolower($borrower['borrower_type']) === 'student' ? 'student' : 'staff'; ?>">
                                            <?php echo htmlspecialchars($borrower['borrower_type']); ?>
                                        </span>
                                        <div style="margin-top: 5px;">
                                            <?php echo $statusBadge; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 32px; font-weight: 700; color: #667eea;">
                                        <?php echo $borrower['total_books_borrowed']; ?>
                                    </div>
                                    <div style="font-size: 13px; color: #718096; text-transform: uppercase;">
                                        Books Borrowed
                                    </div>
                                </div>
                            </div>

                            <?php if ($borrower['books_borrowed']): ?>
                                <div class="books-list">
                                    <div class="books-list-title">
                                        <i class="fas fa-book"></i> Books Borrowed
                                    </div>
                                    <?php 
                                    $books = explode(', ', $borrower['books_borrowed']);
                                    foreach ($books as $index => $book): 
                                    ?>
                                        <div class="book-item">
                                            <div class="book-title">
                                                <i class="fas fa-bookmark"></i> <?php echo htmlspecialchars($book); ?>
                                            </div>
                                            <div class="book-count"><?php echo ($index + 1); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="borrower-info" style="margin-top: 15px;">
                                <span class="info-badge" style="background: #e6fffa;">
                                    <i class="fas fa-calendar"></i> Borrowed: <?php echo date('M d, Y h:i A', strtotime($borrower['last_borrow_date'] ?? 'now')); ?>
                                </span>
                                <?php if($isOverdue && $filter !== 'returned'): ?>
                                    <span class="info-badge" style="background: #fff5f5; color: #c53030;">
                                        <i class="fas fa-clock" style="color: #c53030;"></i> Overdue by: <?php echo $daysElapsed; ?> day(s)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li><a href="?filter=<?php echo $filter; ?>&page=<?php echo $page-1; ?>" class="page-link">&laquo; Prev</a></li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li>
                                    <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li><a href="?filter=<?php echo $filter; ?>&page=<?php echo $page+1; ?>" class="page-link">Next &raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Borrowers Found</h3>
                        <p>There are no borrowing records matching your filter.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        <!-- content-wrapper ends -->
        <?php include 'partials/footer.php'; ?>
      </div>
      <!-- main-panel ends -->
    </div>   
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <!-- plugins:js -->
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="static/vendors/chart.js/Chart.min.js"></script>
  <script src="static/vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="static/vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="static/js/dataTables.select.min.js"></script>

  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="static/js/off-canvas.js"></script>
  <script src="static/js/hoverable-collapse.js"></script>
  <script src="static/js/template.js"></script>
  <script src="static/js/settings.js"></script>
  <script src="static/js/todolist.js"></script>
  <!-- endinject -->
  <script>
    // Auto-submit search when a datalist option is selected
    document.querySelector('.search-input').addEventListener('input', function(e) {
        var val = this.value;
        var list = document.getElementById('searchRecommendations').options;
        for (var i = 0; i < list.length; i++) {
            if (list[i].value === val) {
                this.form.submit();
                break;
            }
        }
    });
  </script>
</body>
</html>
