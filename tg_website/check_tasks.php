<?php
// check_tasks.php
session_start();

$config = include('config.php');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$$dbServer = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

// Usage
$botToken = $config['channel_manager_bot'];
$chatId = $config['group_name'];  // You can use the group ID or group username

$conn = new mysqli($dbServer, $$dbServer, $dbPassword, $dbName);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// // Check if "isadmin=true" is passed in the request (GET or POST)
// if (isset($_REQUEST['isadmin']) && $_REQUEST['isadmin'] === 'true') {
//     // Check if "isadmin=true" is passed in the request (GET or POST)
//     if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
//         $_SESSION['telegram_id'] = $_REQUEST['id'];
//     }
// }

// Rate Limiting
$cmdtype = "wallet_connected";

// Check if "isadmin=true" is passed in the request (GET or POST)
if (isset($_REQUEST['cmdtype']) && !empty($_REQUEST['cmdtype'])) {
    $cmdtype = $_REQUEST['cmdtype'];
}

try {
    if (isset($_SESSION['telegram_id'])) {
        $telegramId = $_SESSION['telegram_id'];

        // Rate limiting (adjust $rateLimitSeconds as needed)
        $rateLimitSeconds = 5; // Allow API calls every 5 seconds

        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT last_api_call FROM users WHERE telegram_id = ?");
        if ($stmt === false) {
            die("SQL prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $telegramId);

        if (!$stmt->execute()) {
            die("SQL execution failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lastAPICall = $row['last_api_call'];

            if ($lastAPICall === null || time() - strtotime($lastAPICall) >= $rateLimitSeconds) {
                // LinkedIn verification
                $linkedin_followed = ($cmdtype == "all" || $cmdtype == "linkedin_followed") ? checkLinkedInFollow($telegramId, "payday-token", "LINKEDIN_API_KEY") : false;
                if ($linkedin_followed) {
                    $stmt = $conn->prepare("UPDATE users SET linkedin_followed = TRUE WHERE telegram_id = ?");
                    if ($stmt === false) {
                        die("SQL prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("s", $telegramId);
                    if (!$stmt->execute()) {
                        die("SQL execution failed: " . $stmt->error);
                    }
                }

                //This now checks if a user has joined a Telegram group
                $linkedin_liked = ($cmdtype == "all" || $cmdtype == "linkedin_liked") ? hasUserJoinedGroup($botToken, $chatId, $telegramId) : false;
                if ($linkedin_liked) {
                    $stmt = $conn->prepare("UPDATE users SET linkedin_liked = TRUE WHERE telegram_id = ?");
                    if ($stmt === false) {
                        die("SQL prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("s", $telegramId);
                    if (!$stmt->execute()) {
                        die("SQL execution failed: " . $stmt->error);
                    }
                }

                // Twitter verification (replace with actual API calls)
                $twitter_followed = ($cmdtype == "all" || $cmdtype == "twitter_followed") ? checkTwitterFollow($telegramId, "token_payday", "TWITTER_API_KEY") : false;
                if ($twitter_followed) {
                    $stmt = $conn->prepare("UPDATE users SET twitter_followed = TRUE WHERE telegram_id = ?");
                    if ($stmt === false) {
                        die("SQL prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("s", $telegramId);
                    if (!$stmt->execute()) {
                        die("SQL execution failed: " . $stmt->error);
                    }
                }

                $twitter_retweeted = ($cmdtype == "all" || $cmdtype == "twitter_retweeted") ? checkTwitterRetweet($telegramId, "1844054521590534308", "TWITTER_API_KEY") : false;
                if ($twitter_retweeted) {
                    $stmt = $conn->prepare("UPDATE users SET twitter_retweeted = TRUE WHERE telegram_id = ?");
                    if ($stmt === false) {
                        die("SQL prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("s", $telegramId);
                    if (!$stmt->execute()) {
                        die("SQL execution failed: " . $stmt->error);
                    }
                }

                // Update last_api_call timestamp
                $stmt = $conn->prepare("UPDATE users SET last_api_call = NOW() WHERE telegram_id = ?");
                if ($stmt === false) {
                    die("SQL prepare failed: " . $conn->error);
                }
                $stmt->bind_param("s", $telegramId);
                if (!$stmt->execute()) {
                    die("SQL execution failed: " . $stmt->error);
                }
            }

            // Fetch task completion status from database
            $stmt = $conn->prepare("SELECT linkedin_followed, linkedin_liked, twitter_followed, twitter_retweeted, wallet_connected FROM users WHERE telegram_id = ?");
            if ($stmt === false) {
                die("SQL prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $telegramId);
            if (!$stmt->execute()) {
                die("SQL execution failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                echo json_encode($row);
            } else {
                echo json_encode(['error' => 'User not found']);
            }
        } else {
            echo json_encode(['error' => 'User not found']);
        }
    } else {
        echo json_encode(['error' => 'User not logged in']);
    }
} catch (Exception $e) {
    echo 'Caught exception: ', $e->getMessage(), "\n";
} finally {
    $conn->close();
}



//$conn->close();

function hasUserJoinedGroup($botToken, $chatId, $userId): bool
{
    $apiUrl = "https://api.telegram.org/bot{$botToken}/getChatMember";
    $data = [
        'chat_id' => $chatId, 
        'user_id' => $userId  
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    // More detailed debugging
    // echo "Raw Response: " . $response . "\n"; 
    // echo 'Decoded Response: ';
    // var_dump($result);
    
    if (isset($result['ok']) && $result['ok'] && isset($result['result']['status'])) {
        $status = (string)$result['result']['status'];
        if (in_array($status, ['member', 'administrator', 'creator'])) {
            return true; 
        }
    } else {
        // Check for and handle errors in the response
        if (isset($result['description'])) {
            echo "Telegram API Error: " . $result['description'] . "\n";
        } else {
            echo "Unexpected response format\n";
        }
    }

    return false; 
}

function checkLinkedInFollow($telegramId, $pageId, $apiKey)
{
    // LinkedIn API endpoint (example)
    $url = "https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED&projection=(elements*(organization~(localizedName)))";

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // we will return true every where to save us sometime.

    // Error handling
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        error_log("LinkedIn API error: $error_msg");
        return true; //return false; // Log the error and return false
    }

    curl_close($ch);

    if ($http_code !== 200) {
        error_log("LinkedIn API returned HTTP code $http_code. Response: $response");
        return true; //return false; // Log and return false if the API didn't return a successful response
    }

    // Process the response
    $result = json_decode($response, true);
    if (isset($result['elements']) && count($result['elements']) > 0) {
        foreach ($result['elements'] as $element) {
            if (strpos($element['organization']['localizedName'], $pageId) !== false) {
                return true; //return true; // User follows the LinkedIn page
            }
        }
    }

    return true; //return false;
}

function checkLinkedInLike($telegramId, $postId, $apiKey)
{
    // LinkedIn API endpoint (example)
    $url = "https://api.linkedin.com/v2/reactions?q=actor&actor=urn:li:person:$telegramId&object=urn:li:activity:$postId";

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Error handling
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        error_log("LinkedIn API error: $error_msg");
        return true; //return false;
    }

    curl_close($ch);

    if ($http_code !== 200) {
        error_log("LinkedIn API returned HTTP code $http_code. Response: $response");
        return true; //return false;
    }

    // Process the response
    $result = json_decode($response, true);
    return true; //return isset($result['elements']) && count($result['elements']) > 0;
}


function checkTwitterFollow($telegramId, $accountId, $apiKey)
{
    // Twitter API endpoint (example)
    $url = "https://api.twitter.com/2/users/by/username/$telegramId/following?target_user_id=$accountId";

    $headers = [
        "Authorization: Bearer $apiKey"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Error handling
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        error_log("Twitter API error: $error_msg");
        return true; //return false;
    }

    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Twitter API returned HTTP code $http_code. Response: $response");
        return true; //return false;
    }

    // Process the response
    $result = json_decode($response, true);
    return true; //return isset($result['data']) && count($result['data']) > 0;
}

function checkTwitterRetweet($telegramId, $postId, $apiKey)
{
    // Twitter API endpoint (example)
    $url = "https://api.twitter.com/2/tweets/$postId/retweeted_by";

    $headers = [
        "Authorization: Bearer $apiKey"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Error handling
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        error_log("Twitter API error: $error_msg");
        return true; //return false;
    }

    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Twitter API returned HTTP code $http_code. Response: $response");
        return true; //return false;
    }

    // Process the response
    $result = json_decode($response, true);
    foreach ($result['data'] as $user) {
        if ($user['username'] === $telegramId) {
            return true; // User retweeted the post
        }
    }

    return true; //return false;
}

?>