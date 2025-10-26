<?php
// ✅ Database connection file for Quantum Ludo

$host = "sql100.infinityfree.com";   // InfinityFree MySQL Host
$user = "if0_39750606";              // InfinityFree MySQL Username
$pass = "HSgaming18";                // Your vPanel password
$db   = "if0_39750606_ludo";         // InfinityFree Database Name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>
