<?php
// Check and migrate existing ISBN data
include 'includes/dbcon.php';

echo "<h2>ISBN Migration Status</h2>";

// Check current status
$check = mysqli_query($conn, "SELECT COUNT(*) as total_isbn, COUNT(accession_id) as linked_isbn FROM lib_isbn");
$status = mysqli_fetch_assoc($check);

echo "<p><strong>Total ISBNs:</strong> {$status['total_isbn']}</p>";
echo "<p><strong>Linked ISBNs:</strong> {$status['linked_isbn']}</p>";
echo "<p><strong>Unlinked ISBNs:</strong> " . ($status['total_isbn'] - $status['linked_isbn']) . "</p>";

// Migrate unlinked ISBNs
if ($status['total_isbn'] > $status['linked_isbn']) {
    echo "<hr><h3>Migrating unlinked ISBNs...</h3>";
    
    $migrate = "UPDATE lib_isbn i
                INNER JOIN (
                    SELECT 
                        a.book_id,
                        MIN(a.accession_id) as first_accession_id
                    FROM lib_accession_numbers a
                    GROUP BY a.book_id
                ) AS first_acc ON i.book_id = first_acc.book_id
                SET i.accession_id = first_acc.first_accession_id
                WHERE i.accession_id IS NULL";
    
    if (mysqli_query($conn, $migrate)) {
        $affected = mysqli_affected_rows($conn);
        echo "<p style='color: green;'>✅ Successfully linked {$affected} ISBNs to accession numbers!</p>";
    } else {
        echo "<p style='color: red;'>❌ Error: " . mysqli_error($conn) . "</p>";
    }
    
    // Check again
    $check2 = mysqli_query($conn, "SELECT COUNT(*) as total_isbn, COUNT(accession_id) as linked_isbn FROM lib_isbn");
    $status2 = mysqli_fetch_assoc($check2);
    
    echo "<hr><h3>After Migration:</h3>";
    echo "<p><strong>Total ISBNs:</strong> {$status2['total_isbn']}</p>";
    echo "<p><strong>Linked ISBNs:</strong> {$status2['linked_isbn']}</p>";
    echo "<p><strong>Unlinked ISBNs:</strong> " . ($status2['total_isbn'] - $status2['linked_isbn']) . "</p>";
} else {
    echo "<p style='color: green;'>✅ All ISBNs are already linked!</p>";
}

// Show sample data
echo "<hr><h3>Sample ISBN Data (First 10):</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>ISBN ID</th><th>Book ID</th><th>ISBN Number</th><th>Accession ID</th><th>Accession Number</th></tr>";

$sample = mysqli_query($conn, "SELECT i.isbn_id, i.book_id, i.isbn_number, i.accession_id, a.accession_number 
                                FROM lib_isbn i 
                                LEFT JOIN lib_accession_numbers a ON i.accession_id = a.accession_id 
                                ORDER BY i.book_id LIMIT 10");

while ($row = mysqli_fetch_assoc($sample)) {
    echo "<tr>";
    echo "<td>{$row['isbn_id']}</td>";
    echo "<td>{$row['book_id']}</td>";
    echo "<td>{$row['isbn_number']}</td>";
    echo "<td>" . ($row['accession_id'] ?: 'NULL') . "</td>";
    echo "<td>" . ($row['accession_number'] ?: 'N/A') . "</td>";
    echo "</tr>";
}

echo "</table>";

mysqli_close($conn);
?>
