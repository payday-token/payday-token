const tonweb = new window.TonWeb();
const connectWalletButton = document.getElementById("connectWalletButton");
const payNowButton = document.getElementById("payNowButton");
let address;
let lastTxHash;

const tonconnectUI = new TON_CONNECT_UI.TonConnectUI({
    manifestUrl: 'https://tg.pday.online/tonconnect-manifest.json',
});

async function connectWallet() {
    connectWalletButton.disabled = true;
    try {
        if (!tonconnectUI) {
            showMessage('error', "TonConnectUI not initialized."); // This will be translated in showMessage
            return;
        }

        const currentIsConnectedStatus = tonconnectUI.connected;

        if (currentIsConnectedStatus) {
            await tonconnectUI.disconnect(); 
        }

        // Get user's preferred language
        const userLanguage = navigator.language || navigator.userLanguage; 

        if (connectWalletButton.innerHTML === ("Disconnect Wallet" || (userLanguage.startsWith('zh') && connectWalletButton.innerHTML === "断开钱包连接"))) { 
            connectWalletButton.innerHTML = userLanguage.startsWith('zh') ? "连接钱包" : "Connect Wallet";
            connectWalletButton.disabled = false;
            return;
        }

        const wallet = await tonconnectUI.connectWallet();
        const mechineaddress = new TonWeb.utils.Address(wallet.account.address);
        address = mechineaddress.toString(isUserFriendly = true);
        connectWalletButton.innerHTML = userLanguage.startsWith('zh') ? "断开钱包连接" : "Disconnect Wallet";
        payNowButton.disabled = false;

        const lastTx = (await tonweb.getTransactions(address, 1))[0];
        if (lastTx) {
            lastTxHash = lastTx.transaction_id.hash;
        }

        // Construct the success message based on language
        const successMessage = userLanguage.startsWith('zh') 
            ? `钱包已连接: ${maskString(address)}` 
            : `Wallet connected: ${maskString(address)}`;
        showMessage('success', successMessage); 

    } catch (error) {
        connectWalletButton.disabled = false;
        payNowButton.disabled = true;
        console.error("Failed to connect wallet:", error);
        showMessage('error', "Failed to connect wallet."); // This will be translated in showMessage
    } finally {
        checkLimit();
    }
}

function maskString(input) {
    // Check if the input is at least 8 characters long
    if (input.length < 8) {
        return input;
    }

    // Extract the first four and last four characters
    const firstFour = input.slice(0, 4);
    const lastFour = input.slice(-4);

    // Combine the first four characters, six asterisks, and the last four characters
    return `${firstFour}******${lastFour}`;
}

// Function to update the message div
function showMessage(type, text) {
    const messageDiv = document.getElementById('message');
    messageDiv.className = 'message ' + type;

    // Get user's preferred language
    const userLanguage = navigator.language || navigator.userLanguage;

    // Check if the user's language is Chinese
    if (userLanguage.startsWith('zh')) {
        // Replace with your Chinese translations
        switch (text) {
            case "TonConnectUI not initialized.":
                text = "TonConnectUI 未初始化。";
                break;
            case "Failed to connect wallet.":
                text = "无法连接钱包。";
                break;
            case "Connect your wallet first.":
                text = "请先连接您的钱包。";
                break;
            case "Checking... <div class=\"spinner\"></div>":
                text = "正在检查... <div class=\"spinner\"></div>";
                break;
            case "0.2 TON collected successfully. Verifying payment...":
                text = "已成功收集 0.2 TON。 正在验证付款...";
                break;
            case "Wallet connected and 0.2 TON payment credited successfully!\nDistribution will be announced soon!!":
                text = "钱包已连接，0.2 TON 付款已成功 credited！\n分配即将公布！！";
                break;
            case "Error crediting tokens. Please contact support.":
                text = "crediting 代币时出错。 请联系支持人员。";
                break;
            case "The PayDay Token Distribution has reached its maximum capacity. Payment is currently disabled.":
                text = "PayDay Token 分配已达到最大容量。 付款目前已禁用。";
                break;
            case "Error verifying payment. Please contact support.":
                text = "验证付款时出错。 请联系支持人员。";
                break;
            case "Failed to collect payment. Please try again.":
                text = "无法收集付款。 请再试一次。";
                break;
            case "An error occurred during the payment process. Please try again.":
                text = "付款过程中发生错误。 请再试一次。";
                break;
            // Add more translations as needed
            default:
                break;
        }
    }

    messageDiv.innerHTML = text;
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function transferTON() {
    if (!address) {
        showMessage('error', "Connect your wallet first.");
        transferStatus.innerHTML = "";
        return;
    }

    // collect 0.2 TON
    try {
        // Initiating the payment collection
        const tonAmount = 0.2; // TON amount to collect

        // Simulate TON transfer request (this will need a TON payment SDK in production)
        const paymentResponse = await tonconnectUI.sendTransaction({
            validUntil: Date.now() + 60 * 20000, // valid for 20 minutes
            messages: [{
                address: "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2",
                amount: tonAmount * 1e9 // TON amount converted to nanoTON
            }]
        });

        //log response for debugging
        console.log('Payment Response:', paymentResponse);

        showMessage('success', 'Checking... <div class="spinner"></div>');

        //Get the transaction
        const bocCellBytes = await TonWeb.boc.Cell.oneFromBoc(TonWeb.utils.base64ToBytes(paymentResponse.boc)).hash();

        const hashBase64 = TonWeb.utils.bytesToBase64(bocCellBytes);

        // We try to confirm transaction here
        // Run a loop until user's last tx hash changes
        var txHash = lastTxHash
        while (txHash == lastTxHash) {
            await sleep(1500) // some delay between API calls
            let tx = (await tonweb.getTransactions(address, 1))[0]
            txHash = tx.transaction_id.hash
        }

        var tgId = document.getElementById('tg_id').value;

        // Check if the payment was successful (this depends on the TON SDK response structure)
        if (paymentResponse) {
            showMessage('success', "0.2 TON collected successfully. Verifying payment...");

            // After payment is successful, verify payment via XMLHttpRequest
            var xhrVerify = new XMLHttpRequest();
            xhrVerify.open('POST', `verify_payment.php?id=${tgId}`, true);
            xhrVerify.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhrVerify.onreadystatechange = function () {
                if (xhrVerify.readyState === 4) {
                    if (xhrVerify.status === 200) {
                        var response = xhrVerify.responseText;
                        if (response === 'success') {
                            var xhrCredit = new XMLHttpRequest();
                            xhrCredit.open('POST', `credit_tokens.php?id=${tgId}`, true);
                            xhrCredit.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhrCredit.onreadystatechange = function () {
                                if (xhrCredit.readyState === 4) {
                                    if (xhrCredit.status === 200) {
                                        showMessage('success', "Wallet connected and 0.2 TON payment credited successfully!\nDistribution will be announced soon!!");
                                    } else {
                                        showMessage('error', "Error crediting tokens. Please contact support.");
                                    }
                                }
                            };
                            xhrCredit.send("address=" + encodeURIComponent(address) + "&amount=100000");
                        } else if (response === 'disabled') {
                            showMessage('error', "The PayDay Token Distribution has reached its maximum capacity. Payment is currently disabled.");
                        } else if (response === 'failed'){
                            showMessage('error', "Error crediting tokens. Please contact support.");
                        }else {
                            showMessage('error', `Payment verification failed. Please ensure you have sent 0.2 TON. \n${response}`);
                        }
                    } else {
                        showMessage('error', "Error verifying payment. Please contact support.");
                    }
                }
            };
            xhrVerify.send("address=" + encodeURIComponent(address) + "&hash=" + encodeURIComponent(hashBase64) + "&checkLimit=");
        } else {
            showMessage('error', "Failed to collect payment. Please try again.");
        }

    } catch (error) {
        connectWalletButton.disabled = false;
        payNowButton.disabled = true;
        console.error(error);
        showMessage('error', "An error occurred during the payment process. Please try again.");
    } finally {
        payNowButton.disabled = true;
    }
}

async function checkLimit() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'verify_payment.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                var response = xhr.responseText;
                if (response === 'disabled') {
                    var msg = "<p>We have reached the maximum number of participants for the PayDay Token Distribution.</p>";
                    msg += "<p>Thank you for your interest!</p>";
                    // Disable payment as limit has been reached.
                    connectWalletButton.disabled = true;
                    payNowButton.disabled = true;
                    showMessage('error', msg);
                }
            } else {
                console.error("Error verifying limit:", xhr.statusText);
                console.error("Server response:", xhr.responseText);
            }
        }
    };

    xhr.onerror = function () {
        console.error("Request failed");
    };

    xhr.send("address=&hash=&checkLimit=true");
}


// tonconnectUI.on('connect', async (wallet) => {

// });

connectWalletButton.addEventListener('click', connectWallet);
payNowButton.addEventListener('click', transferTON);
// Check limit
checkLimit();