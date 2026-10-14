<?php

require_once "config.php";

echo "PHP is working.<br>";
echo "Database connection successful.<br>";

$result = mysqli_query($conn, "SELECT DATABASE() AS db");

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$row = mysqli_fetch_assoc($result);

echo "Current database: " . htmlspecialchars($row["db"]);