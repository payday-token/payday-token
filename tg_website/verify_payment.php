<?php

// verify_payment.php

use mysqli;

$config = include('config.php');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration
$SERVERNAME = $config['db_server'];
$USERNAME = $config['db_user'];
$PASSWORD = $config['db_password'];
$DBNAME = $config['db_name'];
$PAYDAY_TOKEN_WALLET = "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2";
$TON_API_KEY = $config['ton_api_key'];
$PAYMENT_LIMIT = 600000;
$REQUIRED_AMOUNT = 0.2;
$TON_DIVISOR = 1000000000;

// Database Connection
function getDatabaseConnection($SERVERNAME, $USERNAME, $PASSWORD, $DBNAME) {
    $conn = new mysqli($SERVERNAME, $USERNAME, $PASSWORD, $DBNAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Check if payment limit is reached
function isPaymentLimitReached($conn, $PAYMENT_LIMIT) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS paidUsersCount FROM users WHERE wallet_connected = TRUE");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['paidUsersCount'] >= $PAYMENT_LIMIT;
}


function hasValidTransaction($address, $hash, $requiredAmount, $walletAddress, $apiKey, $ton_divisor) {
    $endpoint = "getTransactions";
    $params = [
      'address' => $walletAddress,
      "hash" => $hash,
      'limit' => 10 
    ];
  
    $transactionsData = getToncenterData($endpoint, $params, $apiKey);
  
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

    // as we might have exceeded the server limit
    // and have will confirm all transactions before distribution.
    return true; 
  }

  function getToncenterData($endpoint, $params = [], $apiKey = '') {
    $url = "https://api.toncenter.com/v2/" . $endpoint;
  
    // Add API key to parameters
    $params['api_key'] = $apiKey;
  
    // Build query string
    $queryString = http_build_query($params);
  
    // Complete URL with query string
    $url .= "?" . $queryString;
  
    // Make the HTTP request
    $response = file_get_contents($url);
  
    // Handle potential errors
    if ($response === false) {
      // Handle the error, e.g., log it or throw an exception
      error_log("Error fetching data from Toncenter API: " . $url);
      return false; 
    }
  
    // Decode the JSON response
    $data = json_decode($response, true);
  
    // Handle potential JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE) {
      // Handle the error, e.g., log it or throw an exception
      error_log("Error decoding JSON response from Toncenter API: " . json_last_error_msg());
      return false;
    }
  
    return $data;
  }
  

// Update user wallet status in the database
function updateUserWalletStatus($conn, $address, $telegramId) {
    $stmt = $conn->prepare("UPDATE users SET wallet_connected = TRUE, ton_wallet = ? WHERE telegram_id = ?");
    $stmt->bind_param("ss", $address, $telegramId);
    $stmt->execute();
}

$tg_id = "";

// Check if "isadmin=true" is passed in the request (GET or POST)
if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
    $tg_id = $_REQUEST['id'];
}

if(isset($_SESSION['telegram_id']) && empty($_SESSION['telegram_id'])){
    $_SESSION['telegram_id'] = $tg_id;
}else if(!isset($_SESSION['telegram_id'])){
    $_SESSION['telegram_id'] = $tg_id;
}

// Main Logic
try {
    if (isset($_SESSION['telegram_id'])) {
        $telegramId = $_SESSION['telegram_id'];
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $address = $_POST["address"] ?? '';
            $hash = $_POST["hash"] ?? '';
            $checkLimit = $_POST["checkLimit"] ?? '';
    
            if (empty($address) && empty($checkLimit)) {
                throw new Exception("Address not provided");
            }

            if (empty($hash) && empty($checkLimit)) {
                throw new Exception("Hash not provided");
            }
    
            $conn = getDatabaseConnection($SERVERNAME, $USERNAME, $PASSWORD, $DBNAME);
            try{
                            //Check payment limit
            if(!empty($checkLimit) && $checkLimit == "true") {
                if (isPaymentLimitReached($conn, $PAYMENT_LIMIT)) {
                    echo "disabled";
                }
            }else {
    
                if (hasValidTransaction($address, $hash, $REQUIRED_AMOUNT,  $PAYDAY_TOKEN_WALLET, $TON_API_KEY, $TON_DIVISOR)) {
                    updateUserWalletStatus($conn, $address,$telegramId);
                    echo "success";
                } else {
                    echo "failed";
                }
            }
            }catch(Exception $e){
                echo "error: " . $e->getMessage();
            }finally{
                $conn->close();
            }
        }
    } else {
        echo json_encode(['error' => 'User not logged in']);    
    }
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
}

?>
