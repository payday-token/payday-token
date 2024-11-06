//const TonWeb = window.TonWeb;
//const tonweb = new TonWeb(new TonWeb.HttpProvider('https://toncenter.com/api/v2/jsonRPC', {apiKey: decodeSiteKey()}));

const tonweb = window.TonWeb;

const PDAY_CONTRACT_ADDRESS = "EQBGGZp3hbLIzr3GmM37mT-7tjP1lCgrBEzSa7AYCxPAFPHW";
const MAX_LINES = 10;

let ownerPrivateKey = '';
let ownerWalletAddress = '';


async function authenticateUser() {
    const passphrase = document.getElementById('passWordInput').value;
    chrome.storage.local.get(["encryptedPrivateKey"], async (result) => {
        if (result.encryptedPrivateKey) {
            try {
                const decryptedKeys = await decryptPrivateKey(result.encryptedPrivateKey, passphrase);
                ownerPrivateKey = decryptedKeys.privateKey;
                ownerWalletAddress = decryptedKeys.walletAddress;
                console.log("Decrypted Private Key:", ownerPrivateKey);
                document.getElementById('authenticationOverlay').style.display = 'none';
                await loadBalances(); // Load balances after authentication
            } catch (error) {
                alert("Invalid passphrase. Please try again.");
            }
        } else {
            alert("No key pair found. Please import or create a new key pair.");
        }
    });
}

async function importKey() {
    const mnemonicPhrase = document.getElementById('passphraseInput').value;
    if (!mnemonicPhrase) {
        alert("Mnemonic phrase is required.");
        return;
    }

    document.getElementById('passwordOverlay').style.display = 'flex';
    const passwordInput = document.getElementById('passWordInput2'); // Changed ID
    passwordInput.focus();

    passwordInput.addEventListener('keydown', async (event) => {
        if (event.key === 'Enter') {
            const passphrase = passwordInput.value;
            if (!passphrase) {
                alert("Passphrase is required.");
                return;
            }

            try {
                const keyPair = await deriveKeyPairFromMnemonic(mnemonicPhrase);
                ownerPrivateKey = keyPair.privateKey;
                ownerWalletAddress = keyPair.walletAddress;

                const encryptedKey = await encryptPrivateKey(keyPair, passphrase);
                chrome.storage.local.set({ encryptedPrivateKey: encryptedKey }, () => {
                    alert("Key pair stored securely.");
                    document.getElementById('passwordOverlay').style.display = 'none';
                });
            } catch (error) {
                alert("Error deriving keys. Please check your mnemonic phrase.");
            }
        }
    });
}

// Function to derive private and public keys from the mnemonic phrase
async function deriveKeyPairFromMnemonic(mnemonic) {
    // Use a library like 'bip39' and 'bip32' for derivation (ensure these are included in your project)
    const seed = await bip39.mnemonicToSeed(mnemonic);
    const root = bip32.fromSeed(seed);

    // Here we derive keys using a specific path; adjust as needed (e.g., "m/44'/60'/0'/0/0" for Ethereum)
    const child = root.derivePath("m/44'/60'/0'/0/0");

    const privateKey = child.privateKey.toString('hex');
    const publicKey = child.publicKey.toString('hex');

    const walletAddress = TonWeb.utils.toHex(publicKey); // Modify if needed

    return { privateKey, walletAddress };
}

async function CreateKey() {
    // Step 1: Generate BIP-39 mnemonic (backup phrase)
    const mnemonic = bip39.generateMnemonic();
    alert("Your backup phrase: " + mnemonic + "\nPlease save it in a secure location.");

    // Step 2: Derive private key and wallet address
    const seed = await bip39.mnemonicToSeed(mnemonic);
    const ec = new elliptic.ec('secp256k1');
    const keyPair = ec.genKeyPair({ entropy: seed.slice(0, 32) });

    const privateKey = keyPair.getPrivate('hex');
    const walletAddress = keyPair.getPublic('hex');
    console.log("Derived Key Pair:", { privateKey, walletAddress });

    // Step 3: Display passwordOverlay to collect password
    document.getElementById('passwordOverlay').style.display = 'flex';
    document.getElementById('passwordOverlay').querySelector('button').onclick = async () => {
        const password = document.getElementById('passWordInput').value;
        if (!password) {
            alert("Password is required.");
            return;
        }

        // Step 4: Encrypt the private key using the password
        const encryptedKey = await encryptPrivateKey({ privateKey, walletAddress }, password);

        // Step 5: Store encrypted key in Chrome storage
        chrome.storage.local.set({ encryptedPrivateKey: encryptedKey }, () => {
            alert("Key pair stored securely.");
            document.getElementById('passwordOverlay').style.display = 'none'; // Hide the password overlay
        });

        // Step 6: Hide create/import overlay after saving
        document.getElementById('createOrImportKeyOverlay').style.display = 'none';
    };
}


function addTransactionLine() {
    const transactionInputs = document.getElementById("transactionInputs");
    const currentLines = transactionInputs.getElementsByClassName("transaction").length;

    if (currentLines < MAX_LINES) {
        const transactionDiv = document.createElement("div");
        transactionDiv.classList.add("transaction");

        const addressInput = document.createElement("input");
        addressInput.type = "text";
        addressInput.placeholder = "Employee Address";
        addressInput.classList.add("address-input");

        const amountInput = document.createElement("input");
        amountInput.type = "number";
        amountInput.placeholder = "Amount in PDAY";
        amountInput.classList.add("amount-input");

        transactionDiv.appendChild(addressInput);
        transactionDiv.appendChild(amountInput);
        transactionInputs.appendChild(transactionDiv);
    } else {
        alert("You can only add up to 10 lines.");
    }
}

async function sendPDAYPayment() {
    const sendButton = document.querySelector('.send-button');
    sendButton.classList.add('loading');
    sendButton.disabled = true;

    try {
        // Initialize jettonMaster with the PDAY Jetton contract address
        const jettonMaster = new TonWeb.jetton.JettonMaster(new TonWeb.utils.Address(PDAY_CONTRACT_ADDRESS));

        if (!ownerPrivateKey || !ownerWalletAddress) {
            await authenticateUser();
            if (!ownerPrivateKey || !ownerWalletAddress) {
                alert("Please authenticate first.");
                showAuthenticationOverlay();
                return;
            }
        }

        // Check the available Toncoin in the owner's wallet
        const ownerWallet = await TonWeb.wallet.getWalletForAddress(new TonWeb.utils.Address(ownerWalletAddress));
        const walletBalance = await ownerWallet.getBalance(); // Get the wallet balance
        const gasAndNetworkFees = await estimateGasAndNetworkFees(); // Function to estimate fees

        // Alert if the available balance is insufficient
        if (walletBalance < gasAndNetworkFees) {
            //log walletbalance and gasAndNetworFees
            console.log(`Ton balance: ${walletBalance} \nGas fees ${gasAndNetworkFees}`);
            alert("Insufficient Toncoin for gas and network fees.");
            return; // Abort the function
        }

        const addresses = document.querySelectorAll('.address-input');
        const amounts = document.querySelectorAll('.amount-input');
        const transactions = Array.from(addresses).map((input, i) => {
            const amount = parseFloat(amounts[i].value);
            const address = input.value;
            return { address, amount };
        }).filter(tx => tx.amount > 0 && TonWeb.utils.isAddress(tx.address));

        for (const tx of transactions) {
            const jettonWallet = await jettonMaster.getWalletForAddress(new TonWeb.utils.Address(ownerWalletAddress));
            await jettonWallet.methods.transfer({
                to: new TonWeb.utils.Address(tx.address),
                jettonAmount: tx.amount * 1e9, // Adjusting for PDAY decimals
                forwardAmount: 0,
                forwardPayload: new TonWeb.utils.Cell(),
            }).send();
        }
        await loadBalances(); // Load balances after payments.
    } catch (error) {
        console.error("PDAY Transaction failed:", error);
    } finally {
        sendButton.classList.remove('loading');
        sendButton.disabled = false;
        loadTransactionHistory();
    }
}

// Function to estimate gas and network fees
async function estimateGasAndNetworkFees() {
    try {
        // Fetch the current gas price (in Toncoin)
        const gasPrice = await TonWeb.provider.getGasPrice(); // Ensure this returns gas price in Toncoin

        // Define a rough estimate for the gas limit required for a transaction
        const gasLimit = 21000; // Standard gas limit for a simple transfer

        // Calculate estimated fees (in Toncoin)
        const estimatedFees = gasPrice * gasLimit;

        return estimatedFees;
    } catch (error) {
        console.error("Error estimating gas and network fees:", error);
        return 0.01; // Fallback to a default fee in case of an error
    }
}

// Function to show the authentication overlay
function showAuthenticationOverlay() {
    const overlay = document.getElementById('authenticationOverlay');
    if (overlay) {
        overlay.style.display = 'block'; // Show the overlay
    }
}


async function logTransaction(address, amount, tx) {
    chrome.storage.local.get({ transactions: [] }, (result) => {
        const newTransaction = { address, amount, tx, timestamp: new Date().toISOString() };
        const updatedTransactions = [...result.transactions, newTransaction];
        chrome.storage.local.set({ transactions: updatedTransactions });
    });
}

async function loadTransactionHistory() {
    if (!ownerWalletAddress) return;

    try {
        // Fetch the transaction history for the owner's wallet address
        const transactions = await tonweb.getTransactions(ownerWalletAddress); // You'll need to implement this method or adjust based on the available methods

        const historyList = document.getElementById("transactionHistory");
        historyList.innerHTML = ""; // Clear previous history

        // Loop through transactions and display them
        transactions.forEach((transaction) => {
            const listItem = document.createElement("li");
            // Format the transaction details as needed
            const formattedTime = new Date(transaction.time).toLocaleString(); // Adjust according to the timestamp format returned
            listItem.textContent = `To: ${transaction.to}, Amount: ${transaction.amount}, Time: ${formattedTime}`;
            historyList.appendChild(listItem);
        });
    } catch (error) {
        console.error("Error loading transaction history:", error);
    }
}


async function generateKeyPair() {
    const ec = new elliptic.ec('secp256k1'); // Initialize secp256k1 curve
    const key = ec.genKeyPair();             // Generate a key pair

    // Get private and public keys in hexadecimal format
    const privateKey = key.getPrivate('hex');
    const publicKey = key.getPublic('hex');

    // Generate wallet address using public key
    const walletAddress = TonWeb.utils.toHex(publicKey);  // Modify if another format is required

    return { privateKey, walletAddress };
}


// Placeholder for the encryptPrivateKey function
async function encryptPrivateKey(keyPair, passphrase) {
    const enc = new TextEncoder();
    const privateKey = keyPair.privateKey;

    // Derive a key from the passphrase
    const keyMaterial = await window.crypto.subtle.importKey(
        "raw",
        enc.encode(passphrase),
        { name: "PBKDF2" },
        false,
        ["deriveBits", "deriveKey"]
    );

    // Derive an encryption key
    const salt = window.crypto.getRandomValues(new Uint8Array(16));
    const key = await window.crypto.subtle.deriveKey(
        {
            name: "PBKDF2",
            salt: salt,
            iterations: 100000,
            hash: "SHA-256",
        },
        keyMaterial,
        { name: "AES-GCM", length: 256 },
        false,
        ["encrypt"]
    );

    // Encrypt the private key
    const iv = window.crypto.getRandomValues(new Uint8Array(12)); // Initialization vector
    const encryptedPrivateKey = await window.crypto.subtle.encrypt(
        {
            name: "AES-GCM",
            iv: iv,
        },
        key,
        enc.encode(privateKey)
    );

    // Return the salt, iv, and encrypted data as a single object
    return {
        salt: Array.from(salt),
        iv: Array.from(iv),
        encryptedPrivateKey: Array.from(new Uint8Array(encryptedPrivateKey)),
    };
}

// Placeholder for the decryptPrivateKey function
async function decryptPrivateKey(encryptedData, passphrase) {
    const enc = new TextEncoder();
    const { salt, iv, encryptedPrivateKey } = encryptedData;

    // Derive a key from the passphrase
    const keyMaterial = await window.crypto.subtle.importKey(
        "raw",
        enc.encode(passphrase),
        { name: "PBKDF2" },
        false,
        ["deriveBits", "deriveKey"]
    );

    // Derive the same encryption key used for encryption
    const key = await window.crypto.subtle.deriveKey(
        {
            name: "PBKDF2",
            salt: new Uint8Array(salt),
            iterations: 100000,
            hash: "SHA-256",
        },
        keyMaterial,
        { name: "AES-GCM", length: 256 },
        false,
        ["decrypt"]
    );

    // Decrypt the private key
    const decryptedKey = await window.crypto.subtle.decrypt(
        {
            name: "AES-GCM",
            iv: new Uint8Array(iv),
        },
        key,
        new Uint8Array(encryptedPrivateKey)
    );

    // Convert decrypted data back to a string
    const privateKey = new TextDecoder().decode(decryptedKey);
    return {
        privateKey: privateKey,
        // Add any other data you may want to return, like walletAddress
    };
}

async function loadBalances() {
    if (!ownerWalletAddress) return;

    try {
        // Fetch TON balance
        const tonBalance = await tonweb.getBalance(new TonWeb.utils.Address(ownerWalletAddress));
        const formattedTonBalance = (tonBalance / 1e9).toFixed(2); // Adjust decimal points as needed

        // Fetch PDAY balance
        const jettonMaster = new TonWeb.jetton.JettonMaster(new TonWeb.utils.Address(PDAY_CONTRACT_ADDRESS));
        const jettonWallet = await jettonMaster.getWalletForAddress(new TonWeb.utils.Address(ownerWalletAddress));
        const pdayBalance = await jettonWallet.methods.getBalance().call();
        const formattedPDAYBalance = (pdayBalance[0] / 1e9).toFixed(2); // Adjust for PDAY decimals

        // Display balances
        document.getElementById("balanceDisplay").innerText =
            `Total TON: ${formattedTonBalance} | Total PDAY: ${formattedPDAYBalance}`;
    } catch (error) {
        console.error("Error fetching balances:", error);
        document.getElementById("balanceDisplay").innerText = "Failed to load balances";
    }
}


function showCreateOrImportKeyOverlay() {
    document.getElementById('createOrImportKeyOverlay').style.display = 'flex';
    document.getElementById('authenticationOverlay').style.display = 'none'; // Hide authentication overlay
}

function showAuthenticationOverlay() {
    document.getElementById('createOrImportKeyOverlay').style.display = 'none'; // Hide create key overlay
    document.getElementById('authenticationOverlay').style.display = 'flex';
}

// Display the authentication overlay on page load
window.onload = function () {
    showAuthenticationOverlay();
    // Call loadTransactionHistory on page load to populate history tab
    loadTransactionHistory();
};
