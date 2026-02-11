<?php
include '../includes/session.php';
include '../includes/dbcon.php';

if (isset($_GET['book_id'])) {
    $bookId = mysqli_real_escape_string($conn, $_GET['book_id']);

    $query = "SELECT 
                bt.*, 
                COALESCE(CONCAT(s.first_name, ' ', s.last_name), CONCAT(e.firstname, ' ', e.lastname)) as borrower_name,
                CASE 
                    WHEN s.student_id IS NOT NULL THEN 'Student' 
                    WHEN e.employee_id IS NOT NULL THEN 'Staff' 
                    ELSE 'Unknown'
                END as borrower_type
              FROM lib_rfid_loan bt
              LEFT JOIN students s ON bt.student_id = s.student_id
              LEFT JOIN employees e ON bt.student_id = e.employee_id
              WHERE bt.book_id = '$bookId'
              ORDER BY bt.borrow_date DESC";
    
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-striped">';
        echo '<thead><tr><th>Borrower</th><th>Type</th><th>Date Borrowed</th><th>Date Returned</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        while ($row = mysqli_fetch_assoc($result)) {
            $statusBadge = ($row['status'] == 'returned') ? '<span class="badge bg-success">Returned</span>' : '<span class="badge bg-warning">Borrowed</span>';
            $returnDate = ($row['return_date']) ? date('M d, Y h:i A', strtotime($row['return_date'])) : '---';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['borrower_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['borrower_type']) . '</td>';
            echo '<td>' . date('M d, Y h:i A', strtotime($row['borrow_date'])) . '</td>';
            echo '<td>' . $returnDate . '</td>';
            echo '<td>' . $statusBadge . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-info">No borrowing history found for this book.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid Request.</div>';
}
?>
