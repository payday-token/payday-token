<?php
$config = include('config.php');
// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];


// Rate Limiting Configuration
$LIMIT = 5; // Max requests allowed
$TIME_FRAME = 60; // Time frame in seconds

// Get client's IP address
$IP = $_SERVER['REMOTE_ADDR'];

// Establish database connection and handle potential errors
try {
    // Create the DSN (Data Source Name) string
    $dsn = "mysql:host=$dbServer;dbname=$dbName;charset=utf8mb4";
    $db = new PDO($dsn, $dbUser, $dbPassword);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure the requests table exists
    createRequestsTable($db);

    // Current timestamp
    $NOW = time();

    // Clean up old entries and count current requests
    cleanUpOldRequests($db, $NOW - $TIME_FRAME);
    $COUNT = countRequests($db, $IP, $NOW - $TIME_FRAME);

    // Check request limit
    if ($COUNT >= $LIMIT) {
        sendRateLimitExceededResponse();
    }

    // Log the request
    logRequest($db, $IP, $NOW);

    // Serve the presale page
    servePresalePage();

} catch (PDOException $e) {
    echo "Connection failed: dbname=$dbname " . $e->getMessage();
}

/**
 * Create the requests table if it doesn't exist.
 *
 * @param PDO $db
 */
function createRequestsTable(PDO $db) {
    // $db->exec("CREATE TABLE IF NOT EXISTS requests (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     ip VARCHAR(45) NOT NULL,
    //     timestamp INT NOT NULL
    // )");
}

/**
 * Clean up old request entries from the database.
 *
 * @param PDO $db
 * @param int $timestamp
 */
function cleanUpOldRequests(PDO $db, int $timestamp) {
    $stmt_cleanup = $db->prepare("DELETE FROM requests WHERE timestamp < :timestamp");
    $stmt_cleanup->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
    $stmt_cleanup->execute();
}

/**
 * Count the number of requests from a specific IP within the time frame.
 *
 * @param PDO $db
 * @param string $ip
 * @param int $timestamp
 * @return int
 */
function countRequests(PDO $db, string $ip, int $timestamp): int {
    $stmt_count = $db->prepare("SELECT COUNT(*) FROM requests WHERE ip = :ip AND timestamp >= :timestamp");
    $stmt_count->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt_count->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
    $stmt_count->execute();
    return (int)$stmt_count->fetchColumn();
}

/**
 * Log a request in the database.
 *
 * @param PDO $db
 * @param string $ip
 * @param int $timestamp
 */
function logRequest(PDO $db, string $ip, int $timestamp) {
    $stmt_insert = $db->prepare("INSERT INTO requests (ip, timestamp) VALUES (:ip, :timestamp)");
    $stmt_insert->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt_insert->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
    $stmt_insert->execute();
}

/**
 * Send a response indicating that the rate limit has been exceeded.
 */
function sendRateLimitExceededResponse() {
    header('Content-type: text/html');
    echo "<html><body><h1>Rate limit exceeded. Try again later.</h1></body></html>";
    exit;
}

/**
 * Serve the presale page.
 */
function servePresalePage() {
    header('Content-type: text/html');
    readfile("presale.html");
}
?>
