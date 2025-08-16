<?php
// Decide if we should load remote/production overrides
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
$forceRemote = getenv('ABICO_USE_REMOTE') === '1';

// Attempt to load remote/production overrides first (only if not local or forced)
$remoteConfig = __DIR__ . '/database.remote.php';
if (($forceRemote || !$isLocal) && is_file($remoteConfig)) {
    require_once $remoteConfig;
}

// Local development defaults (only if not already defined by remote)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USERNAME')) define('DB_USERNAME', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
if (!defined('DB_NAME')) define('DB_NAME', 'abico');

/**
 * Create database connection
 * @return mysqli
 */
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // Ensure utf8mb4 across the app
    if (method_exists($conn, 'set_charset')) {
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
?>
