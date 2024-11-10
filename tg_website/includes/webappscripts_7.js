
const linkedInFollowButton = document.getElementById("linkedInFollowBtn");
const linkedInLikeButton = document.getElementById("linkedInLikeBtn");
const twitterFoollowButton = document.getElementById("twitterFoollowBtn");
const twitterRetweetButton = document.getElementById("twitterRetweetBtn");
const connectWalletButton = document.getElementById("connectWalletBtn");
const linkedInFollowBtntatus = document.getElementById("linkedInFollowBtnStatus");
const taskStatus = document.getElementById("taskStatus");
const linkedInPageUrl = "https://www.linkedin.com/company/payday-token/about/?viewAsMember=true";
const linkedInPostUrl = "https://t.me/tokenpaydaychan";
const tqitterProfileUrl = "https://x.com/PDAY_Token";
const twitterPostUrl = "https://x.com/PDAY_Token/status/1851276856391000459";

function checkTaskCompletion(taskName, currentButton, nextButton, taskStatus, url) {
    // taskStatus is now a normal DOM element
    var countdown = 14;

    if (taskName === "wallet_connected") {
        // set countdown to 300 seconds
        countdown = 300;
    }

    // Create a countdown display and append it to the taskStatus element
    var countdownDisplay = document.createElement('span');
    countdownDisplay.textContent = ' (' + countdown + ')';
    taskStatus.appendChild(countdownDisplay);

    var countdownInterval = setInterval(function () {
        countdown--;
        countdownDisplay.textContent = ' (' + countdown + ')';

        if (countdown <= 0) {
            clearInterval(countdownInterval);
            taskStatus.removeChild(countdownDisplay); // Remove countdown after it finishes

            // Create a "Checking..." message with a spinner and append to taskStatus
            var checkingDisplay = document.createElement('span');
            // Determine if the user's language is Chinese
            if (navigator.language.startsWith('zh') || navigator.languages.some(lang => lang.startsWith('zh'))) {
                checkingDisplay.innerHTML = '正在檢查... <div class="spinner"></div>'; // Chinese text
            } else {
                checkingDisplay.innerHTML = 'Checking... <div class="spinner"></div>';
            }
            taskStatus.appendChild(checkingDisplay);

            // Create an XMLHttpRequest object
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url + `?cmdtype=${taskName}`, true); // Use dynamic URL passed to the function

            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const tasks = JSON.parse(xhr.responseText);

                        // Update token count
                        var tokenCountElement = document.getElementById('token-count');
                        var numstr = tokenCountElement.textContent.replace(/,/g, "");
                        var tokens = parseInt(numstr, 10); // Ensure the token count is an integer
                        if (tasks[taskName]) {
                            tokens += 20000; // Add tokens if the task is completed
                            tokenCountElement.textContent = tokens.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");

                            // Update task completion display
                            if (nextButton) {
                                nextButton.disabled = false;
                            }
                            currentButton.disabled = true;
                        }
                    } catch (e) {
                        console.error("Failed to parse response: ", e);
                    } finally {
                        taskStatus.removeChild(checkingDisplay); // Remove checking display after completion
                    }
                } else {
                    console.error("Request failed with status:", xhr.status);
                }
            };

            xhr.onerror = function () {
                console.error("Request error");
            };

            xhr.send(); // Send the request
        }
    }, 1000);
}


// function checkTaskCompletion(taskName, currentButton, nextButton, taskStatus, url) {
//     // Ensure taskStatus is a jQuery object
//     var $taskStatus = $(taskStatus); // Convert the div element to a jQuery object

//     // Display countdown next to the task
//     var countdown = 14;

//     if(taskName == "wallet_connected"){
//         // set countdown to 300 seconds
//         countdown = 300;
//     }

//     var countdownDisplay = $('<span> (' + countdown + ')</span>').appendTo($taskStatus);

//     var countdownInterval = setInterval(function () {
//         countdown--;
//         countdownDisplay.text(' (' + countdown + ')');

//         if (countdown <= 0) {
//             clearInterval(countdownInterval);
//             countdownDisplay.remove(); // Remove countdown after it finishes
//             countdownDisplay = $('Checking... <div class="spinner"></div>').appendTo($currentTaskStatus);
//             // Check task completion after countdown
//             $.ajax({
//                 url: 'check_tasks.php', // Use dynamic URL passed to the function
//                 type: 'GET',
//                 success: function (response) {
//                     try {
//                         const tasks = JSON.parse(response);

//                         // Update token count
//                         let tokens = parseInt($('#token-count').text(), 10); // Ensure the token count is an integer
//                         if (tasks[taskName]) {
//                             tokens += 20000; // Add tokens if the task is completed
//                         }
//                         $('#token-count').text(tokens);

//                         // Update task completion display
//                         if(nextButton){
//                             nextButton.disabled = false;
//                         }
//                         currentButton.disabled = true;
//                     } catch (e) {
//                         console.error("Failed to parse response: ", e);
//                     }
//                     finally{
//                         countdownDisplay.remove(); // Remove countdown after it finishes
//                     }
//                 },
//                 error: function (xhr, status, error) {
//                     console.error("AJAX request failed: ", error);
//                 }
//             });
//         }
//     }, 1000);
// }


function followOnLinkedIn() {
    // Navigate the user to a new page
    window.open(linkedInPageUrl, "_blank");
    // invoke checkTaskCompletion function
    checkTaskCompletion("linkedin_followed", linkedInFollowButton, linkedInLikeButton, taskStatus, "check_tasks.php");
}

function likeAndRepostLinkedInPost() {
    // Navigate the user to a new page
    window.open(linkedInPostUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("linkedin_liked", linkedInLikeButton, twitterFoollowButton, taskStatus, "check_tasks.php");
}

function followOnTwitter() {
    // Navigate the user to a new page
    window.open(tqitterProfileUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("twitter_followed", twitterFoollowButton, twitterRetweetButton, taskStatus, "check_tasks.php");
}

function retweetTwitterPost() {
    // Navigate the user to a new page
    window.open(twitterPostUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("twitter_retweeted", twitterRetweetButton, connectWalletButton, taskStatus, "check_tasks.php");
}

function connectWallet() {
    // Navigate the user to a new page
    var url = `connect_wallet.php?id=${document.getElementById('tg_id').value}`;
    window.open(url, "_blank");
    checkTaskCompletion("wallet_connected", connectWalletButton, null, taskStatus, "check_tasks.php");
}

linkedInFollowButton.addEventListener("click", function () {
    followOnLinkedIn();
});

linkedInLikeButton.addEventListener("click", function () {
    likeAndRepostLinkedInPost();
});

twitterFoollowButton.addEventListener("click", function () {
    followOnTwitter();
});

twitterRetweetButton.addEventListener("click", function () {
    retweetTwitterPost();
});

connectWalletButton.addEventListener("click", function () {
    connectWallet();
});
