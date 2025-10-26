<?php
// Database config
define('DB_HOST', 'localhost');
define('DB_NAME', 'ludo');
define('DB_USER', 'root');
define('DB_PASS', '');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
