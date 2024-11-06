<?php
$config = include('config.php');
// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

// Rate Limiting
$under_construction = false; // Set to false after the site has gone live

// Check if "isadmin=true" is passed in the request (GET or POST)
if (isset($_REQUEST['isadmin']) && $_REQUEST['isadmin'] === 'true') {
    $under_construction = false;
}

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

    // Serve the presale page if under construction
    if ($under_construction) {
        servePresalePage();
        exit;
    }

} catch (PDOException $e) {
    echo "Connection failed: dbname=$dbname " . $e->getMessage();
}

/**
 * Create the requests table if it doesn't exist.
 *
 * @param PDO $db
 */
function createRequestsTable(PDO $db)
{
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
function cleanUpOldRequests(PDO $db, int $timestamp)
{
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
function countRequests(PDO $db, string $ip, int $timestamp): int
{
    $stmt_count = $db->prepare("SELECT COUNT(*) FROM requests WHERE ip = :ip AND timestamp >= :timestamp");
    $stmt_count->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt_count->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
    $stmt_count->execute();
    return (int) $stmt_count->fetchColumn();
}

/**
 * Log a request in the database.
 *
 * @param PDO $db
 * @param string $ip
 * @param int $timestamp
 */
function logRequest(PDO $db, string $ip, int $timestamp)
{
    $stmt_insert = $db->prepare("INSERT INTO requests (ip, timestamp) VALUES (:ip, :timestamp)");
    $stmt_insert->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt_insert->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
    $stmt_insert->execute();
}

/**
 * Send a response indicating that the rate limit has been exceeded.
 */
function sendRateLimitExceededResponse()
{
    header('Content-type: text/html');
    echo "<html><body><h1>Rate limit exceeded. Try again later.</h1></body></html>";
    exit;
}

/**
 * Serve the presale page.
 */
function servePresalePage()
{
    header('Content-type: text/html');
    readfile("tg_app.html");
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayDay Token</title>
    <link rel="icon" href="https://tg.pday.online/imgs/paydayicon.png" type="image/png">
    <!-- Google Tag Manager -->
    <script>(function (w, d, s, l, i) {
            w[l] = w[l] || []; w[l].push({
                'gtm.start':
                    new Date().getTime(), event: 'gtm.js'
            }); var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-T77NH9G2');</script>
    <!-- End Google Tag Manager -->
    <style>
        /* Style for the DIV with background and new spinner */
        #loadingDiv {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            /* Center horizontally */
            min-width: 439px;
            /* Minimum width */
            width: auto;
            /* Auto width */
            height: calc(100vh - 20px);
            /* Full height minus top and bottom margins */
            background-image: url('imgs/payday_poster.png');
            /* Replace with the correct path */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: flex-end;
            padding: 20px;
            /* Optional: padding around the content */
        }

        /* Style for the new red spinner */
        .loading-spinner {
            border: 6px solid #f3f3f3;
            border-top: 6px solid red;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1.5s linear infinite;
            margin-bottom: 20px;
        }

        /* Keyframes for the spinner animation */
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Hide the loading div after content loads */
        .loaded #loadingDiv {
            display: none;
        }

        /* Mobile-specific styles */
        @media (max-width: 768px) {
            #loadingDiv {
                background-size: contain;
                align-items: center;
                /* Center the spinner vertically for smaller screens */
            }

            .loading-spinner {
                width: 30px;
                height: 30px;
                border-width: 4px;
            }
        }
    </style>
</head>

<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-T77NH9G2" height="0" width="0"
            style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <div id="loadingDiv">
        <div class="loading-spinner"></div>
    </div>
    <script>
        // Delay execution by 10 seconds
        setTimeout(function () {
            // Get the full URL including the fragment (hash)
            var fullUrl = window.location.href;

            // Replace 'index.php' with 'webapp.php' in the full URL
            fullUrl = fullUrl.replace('index.php', 'webapp.php');

            // Send the updated full URL to the server using an AJAX request
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "webapp.php", true);  // PHP script path
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            // Define what happens when the server responds
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // Insert the content into the body
                    document.body.innerHTML = xhr.responseText;

                    // Load external script files
                    loadExternalScripts();

                    // Find and execute any <script> tags
                    var scripts = document.body.getElementsByTagName("script");
                    for (var i = 0; i < scripts.length; i++) {
                        eval(scripts[i].innerText);  // Execute the script content
                    }

                    document.body.classList.add('loaded');
                }
            };

            // Send the request with the decode fullUrl
            xhr.send("full_url=" + fullUrl);

            // Optionally, log the updated full URL to the console for debugging
            console.log("Updated Full URL: " + fullUrl);

            // Function to load external scripts
            function loadExternalScripts() {
                var scriptUrls = [
                    'https://telegram.org/js/telegram-web-app.js',  // Add your script URLs here
                    'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js',
                    'https://tg.pday.online/includes/webappscripts_6.js'
                ];

                // Dynamically load each script
                scriptUrls.forEach(function (url) {
                    var script = document.createElement('script');
                    script.src = url;
                    script.type = 'text/javascript';
                    document.body.appendChild(script);
                });
            }
        }, 10000); // 10000 milliseconds = 10 seconds
    </script>
    <script type="module">
        // Import the functions you need from the SDKs you need
        import { initializeApp } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-app.js";
        import { getAnalytics } from "https://www.gstatic.com/firebasejs/11.0.1/firebase-analytics.js";
        // TODO: Add SDKs for Firebase products that you want to use
        // https://firebase.google.com/docs/web/setup#available-libraries

        // Your web app's Firebase configuration
        // For Firebase JS SDK v7.20.0 and later, measurementId is optional
        const firebaseConfig = {
            apiKey: "AIzaSyD8O5RdqYjMZIEwWmkxiA-2iXJz_fJdaxI",
            authDomain: "tg-web-app---payday.firebaseapp.com",
            projectId: "tg-web-app---payday",
            storageBucket: "tg-web-app---payday.firebasestorage.app",
            messagingSenderId: "859490855411",
            appId: "1:859490855411:web:3b045f7a168b6092cdca86",
            measurementId: "G-EK8TXKC2TR"
        };

        // Initialize Firebase
        const app = initializeApp(firebaseConfig);
        const analytics = getAnalytics(app);
    </script>
</body>

</html>