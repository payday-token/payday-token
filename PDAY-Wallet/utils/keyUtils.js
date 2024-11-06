// utils/keyUtils.js
const TonWeb = require('tonweb');
const { toNano } = TonWeb.utils;

const tonweb = new TonWeb(new TonWeb.HttpProvider('https://toncenter.com/api/v2/jsonRPC', { apiKey: process.env.TON_API_KEY }));

async function sendPDAYPayment(ownerPrivateKey, ownerWalletAddress, transactions) {
    const wallet = tonweb.wallet.create({ publicKey: ownerPrivateKey });
    const walletAddress = wallet.getAddress();

    const sendAmount = toNano(transactions.amount); // Convert to nano (smallest unit)
    
    const message = await wallet.createTransfer({
        to: transactions.toAddress,
        value: sendAmount,
        seqno: await wallet.getSeqno(), // Get the current sequence number
    });
    
    const signedMessage = await tonweb.provider.signMessage(message, ownerPrivateKey);
    
    const result = await tonweb.provider.sendMessage(signedMessage);
    return result; // Return the transaction result
}

async function getPDAYBalance(address) {
    // Replace with the actual contract address for PDAY and the ABI
    const pdayContractAddress = 'EQBGGZp3hbLIzr3GmM37mT-7tjP1lCgrBEzSa7AYCxPAFPHW';
    const pdayContract = tonweb.contract(pdayContractAddress);

    try {
        const balance = await pdayContract.getBalance(address); // Implement this method based on your contract's ABI
        return balance; // Return balance directly or convert it as necessary
    } catch (error) {
        console.error('Error fetching PDAY balance:', error);
        throw error; // Rethrow error for handling in the route
    }
}

async function loadTransactionHistory(address) {
    const transactions = await tonweb.provider.getTransactions(address);
    return transactions; // Return the list of transactions for the provided address
}

module.exports = { sendPDAYPayment, getPDAYBalance, loadTransactionHistory };
