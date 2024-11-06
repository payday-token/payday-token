<?php
// Load configuration file
$config = include('config.php');

// Fetch current round and database credentials
$current_round = $config['round'];
$servername = $config['db_server'];
$username = $config['db_user'];
$password = $config['db_password'];
$dbname = $config['db_name'];
$validSiteKey = $config['site_key']; // Set your SiteKey for validation

// Establish a secure PDO connection with error handling
try {
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Enable exceptions for errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch associative arrays by default
        PDO::ATTR_EMULATE_PREPARES => false, // Disable emulated prepares
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die(json_encode(["error" => "Database connection failed. Please try again later."]));
}

// Get the POST parameter with fallback
$siteKey = $_POST['sitekey'] ?? null;

// Input validation for siteKey
if (empty($siteKey)) {
    die(json_encode(["error" => "Missing sitekey parameter."]));
}

// Authentication check
if ($siteKey !== $validSiteKey) {
    die(json_encode(["error" => "Invalid Site Key."]));
}

// Use prepared statements to prevent SQL injection
$query = "SELECT SUM(PayDayTokenDue) AS totalPayDayBought FROM transactions WHERE Round = :round";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute(['round' => $current_round]);
    
    // Fetch the result
    $row = $stmt->fetch();
    $totalPayDayBought = $row['totalPayDayBought'] ?? 0; // Default to 0 if no records
    
    echo json_encode(["totalPayDayBought" => $totalPayDayBought]);

} catch (PDOException $e) {
    error_log("Error executing query: " . $e->getMessage());
    die(json_encode(["error" => "Error fetching total PayDayTokenDue."]));
}

// No need to explicitly close the connection; PDO will handle it automatically
?>
