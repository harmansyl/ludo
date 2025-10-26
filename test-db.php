<?php
require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    echo "✅ Database connection successful!<br>";

    // Test query
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables in database:<br>";
    foreach ($tables as $table) {
        echo " - " . $table . "<br>";
    }
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage();
}




