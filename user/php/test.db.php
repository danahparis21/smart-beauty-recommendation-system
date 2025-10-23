<?php
include 'config/db.php';


// Check if connection object exists and works
if ($conn && !$conn->connect_error) {
    echo "<h3 style='color:green;'>✅ Database connection successful!</h3>";

    // Optional: run a quick test query
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "<p>✅ Query executed. Found " . $result->num_rows . " tables in database.</p>";
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange;'>⚠️ Connected, but test query failed.</p>";
    }
} else {
    echo "<h3 style='color:red;'>❌ Database connection failed:</h3>";
    echo $conn->connect_error;
}
?>
