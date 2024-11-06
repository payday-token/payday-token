<?php
// credit_tokens.php

$config = include('config.php');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

$tg_id = ""; // Set to false after the site has gone live

// Check if "isadmin=true" is passed in the request (GET or POST)
if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
    $tg_id = $_REQUEST['id'];
}

if(isset($_SESSION['telegram_id']) && empty($_SESSION['telegram_id'])){
    $_SESSION['telegram_id'] = $tg_id;
}else if(!isset($_SESSION['telegram_id'])){
    $_SESSION['telegram_id'] = $tg_id;
}

if (isset($_SESSION['telegram_id'])) {
    $telegramId = $_SESSION['telegram_id'];

    // Create a database connection using MySQLi with error handling
    $conn = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
        exit();
    }

    try {
        // Process only POST requests
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Validate and sanitize user input
            if (isset($_POST["address"]) && !empty($_POST["address"]) && isset($_POST["amount"]) && !empty($_POST["amount"])) {
                $address = $conn->real_escape_string($_POST["address"]);
                $amount = intval($conn->real_escape_string($_POST["amount"]));

                // Check if the address exists first (optional)
                $sql_check = "SELECT ton_wallet FROM users WHERE ton_wallet = ? AND telegram_id = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("ss", $address, $telegramId);
                $stmt_check->execute();
                $result = $stmt_check->get_result();

                if ($result->num_rows > 0) {
                    // Close the check statement
                    $stmt_check->close();

                    // Update tokens for an existing wallet address
                    $sql_update = "UPDATE users SET tokens = ? WHERE ton_wallet = ? AND telegram_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("iss", $amount, $address, $telegramId);
                    $stmt_update->execute();

                    echo json_encode(['message' => "Credited with $amount PDAY, address is valid."]);
                } else {
                    // Address does not exist, add it and credit tokens
                    $sql_update = "UPDATE users SET tokens = ?, ton_wallet = ? WHERE telegram_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("iss", $amount, $address, $telegramId);
                    $stmt_update->execute();

                    echo json_encode(['message' => "Address added and $amount PDAY credited."]);
                }

                $stmt_update->close();
            } else {
                echo json_encode(['error' => 'Invalid address or amount']);
            }
        } else {
            echo json_encode(['error' => 'Invalid request method']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    } finally {
        $conn->close();
    }
} else {
    http_response_code(403); // Forbidden response code for unauthorized access
    echo json_encode(['error' => 'User not logged in']);
}

?>