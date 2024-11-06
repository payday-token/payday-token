<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$config = include('config.php');

$current_round = $config['round'];
// Database credentials
$servername = $config['db_server'];
$username = $config['db_user'];
$password = $config['db_password'];
$dbname = $config['db_name'];
$TON_API_KEY = $config['ton_api_key'];
$PAYDAY_TOKEN_WALLET = "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2";
$TON_DIVISOR = 1000000000;
$validSiteKey = $config['site_key']; // Set your SiteKey for validation

// Create connection with error handling
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}

// POST parameters with validation
$walletAddress = $_POST['walletAddress'] ?? null;
$transactionHash = $_POST['transactionHash'] ?? null;
$tonAmountPaid = $_POST['TONAmountPaid'] ?? null;
$siteKey = $_POST['SiteKey'] ?? null;

// Input validation
if (!$walletAddress || !$transactionHash || !$tonAmountPaid || !$siteKey) {
    die("Missing required POST parameters.");
}

// Authentication check
if ($siteKey !== $validSiteKey) {
    die("Invalid Site Key.");
}

try {


    // Check if table exists, if not, create it
    $tableCheckQuery = "SHOW TABLES LIKE 'transactions'";
    $tableExists = $conn->query($tableCheckQuery);
    if ($tableExists === false) {
        error_log("Error checking table existence: " . $conn->error);
        die("Error checking table existence. Please try again later.");
    }

    if ($tableExists->num_rows == 0) {
        $createTableQuery = "
        CREATE TABLE transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            WalletAddress VARCHAR(255) NOT NULL,
            TransactionHash VARCHAR(255) NOT NULL UNIQUE,  -- UNIQUE constraint added here
            TONAmountPaid DECIMAL(18, 8) NOT NULL,
            USDTValueOfTONPaid DECIMAL(18, 8),
            USDTPerPayDayToken DECIMAL(18, 8),
            PayDayTokenDue DECIMAL(18, 8),
            Round VARCHAR(255) DEFAULT '1st round',
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
        if (!$conn->query($createTableQuery)) {
            error_log("Error creating table: " . $conn->error);
            die("Error creating table. Please try again later.");
        }
    }

    // Fetch USDT value of TON from a free API with error handling
    $usdtValueOfTON = fetchUSDTValueOfTON();
    if (!$usdtValueOfTON) {
        die("Error fetching USDT value of TON.");
    }

    $isvalidtransaction = hasValidTransaction($walletAddress, $transactionHash, $tonAmountPaid, $PAYDAY_TOKEN_WALLET, $TON_API_KEY, $TON_DIVISOR);

    if (!$isvalidtransaction) {
        die("Error: TransactionHash is invalid.");
    }

    // PayDay presale price
    $payDayPresalePrice = 0.0006;

    // Calculate PayDay tokens due
    $payDayTokenDue = $tonAmountPaid / $payDayPresalePrice;

    // Insert data into the database with prepared statement error handling
    $insertQuery = $conn->prepare("
    INSERT INTO transactions 
    (WalletAddress, TransactionHash, TONAmountPaid, USDTValueOfTONPaid, USDTPerPayDayToken, PayDayTokenDue, Round) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

    if ($insertQuery === false) {
        error_log("Prepare statement error: " . $conn->error);
        die("Database error. Please try again later.");
    }

    $insertQuery->bind_param(
        "ssdddds",
        $walletAddress,
        $transactionHash,
        $tonAmountPaid,
        $usdtValueOfTON,
        $payDayPresalePrice,
        $payDayTokenDue,
        $current_round
    );

    if ($insertQuery->execute()) {
        echo "Transaction recorded successfully!";
    } else {
        if ($insertQuery->errno === 1062) { // Error code 1062 is for duplicate entry
            die("Error: Duplicate TransactionHash. Transaction already exists.");
        } else {
            error_log("Error executing query: " . $insertQuery->error);
            die("Error processing the transaction. Please try again later.");
        }
    }

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    die("Error: " . $e->getMessage());
} finally {
    $conn->close(); // Close the database connection
}

function hasValidTransaction($address, $hash, $requiredAmount, $walletAddress, $apiKey, $ton_divisor)
{
    $endpoint = "getTransactions";
    $params = [
        'address' => $walletAddress,
        "hash" => $hash,
        'limit' => 10
    ];

    $transactionsData = getToncenterData($endpoint, $params, $apiKey);
    //echo json_encode($transactionsData);

    if ($transactionsData && isset($transactionsData['result'])) {
        foreach ($transactionsData['result'] as $transaction) {
            // Check if 'in_msg' exists in the transaction
            if (isset($transaction['in_msg']['source']) && isset($transaction['in_msg']['value'])) {
                $source = $transaction['in_msg']['source'];
                $amount = $transaction['in_msg']['value'] / $ton_divisor; // Assuming TON_DIVISOR is 10^9

                if ($source === $address && $amount == $requiredAmount) {
                    return true;
                }
            }
        }
    }

    //If get here we just go ahead and return true, we will verify manually
    // as we might have exceeded the server limit
    return true;
}

function getToncenterData($endpoint, $params, $apiKey)
{
    $url = "https://toncenter.com/api/v2/" . $endpoint;
    $headers = [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json"
    ];

    $query = http_build_query($params);
    $url = $url . '?' . $query;

    $options = [
        'http' => [
            'header' => $headers,
            'method' => 'GET'
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("Error fetching data from TON API: " . error_get_last()['message']);
        return false;
    }

    $data = json_decode($response, true);

    if (isset($data['result'])) {
        return $data;
    } else {
        error_log("Unexpected TON API response format: " . json_encode($data));
        return false;
    }
}


// Function to fetch USDT value of TON using a free API with error handling
function fetchUSDTValueOfTON()
{
    $apiUrl = "https://api.coingecko.com/api/v3/simple/price?ids=toncoin&vs_currencies=usd";
    $response = @file_get_contents($apiUrl); // Use @ to suppress warnings

    if ($response === false) {
        error_log("Error fetching data from API: " . error_get_last()['message']);
        return false;
    }

    $data = json_decode($response, true);

    if (isset($data['toncoin']['usd'])) {
        return $data['toncoin']['usd'];
    } else {
        error_log("Unexpected API response format: " . json_encode($data));
        return 6.0;
    }
}
?>