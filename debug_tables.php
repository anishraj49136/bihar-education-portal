<?php
include('config.php');

echo "<h2>Database Tables Structure</h2>";

// Check pf_submissions table structure
echo "<h3>pf_submissions Table Structure:</h3>";
try {
    $stmt = $conn->query("DESCRIBE pf_submissions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Check salary_submissions table structure
echo "<h3>salary_submissions Table Structure:</h3>";
try {
    $stmt = $conn->query("DESCRIBE salary_submissions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Check schools table structure
echo "<h3>schools Table Structure:</h3>";
try {
    $stmt = $conn->query("DESCRIBE schools");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Show some sample data from pf_submissions
echo "<h3>Sample Data from pf_submissions:</h3>";
try {
    $stmt = $conn->query("SELECT * FROM pf_submissions LIMIT 3");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($data)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>";
        foreach (array_keys($data[0]) as $key) {
            echo "<th>" . $key . "</th>";
        }
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . $value . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No data found in pf_submissions table.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>