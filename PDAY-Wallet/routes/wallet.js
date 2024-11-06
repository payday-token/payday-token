// routes/wallet.js
const express = require('express');
const router = express.Router();
const { sendPDAYPayment, loadTransactionHistory } = require('../utils/keyUtils');

// Route to send PDAY payment
router.post('/send', async (req, res) => {
    try {
        const { ownerPrivateKey, ownerWalletAddress, transactions } = req.body;
        const result = await sendPDAYPayment(ownerPrivateKey, ownerWalletAddress, transactions);
        res.json(result);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Route to load transaction history
router.get('/history/:address', async (req, res) => {
    try {
        const { address } = req.params;
        const history = await loadTransactionHistory(address);
        res.json(history);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Route to check wallet balance
app.get('/api/balance/:address', async (req, res) => {
    const { address } = req.params;

    try {
        // Check TON balance
        const tonBalance = await tonweb.getBalance(address);
        
        // Convert balance from nanoTON to TON
        const tonBalanceInTon = tonBalance / Math.pow(10, 9); // Convert from nanoTON to TON

        // Check PDAY balance (Assuming you have a function for this)
        const pdayBalance = await getPDAYBalance(address);

        // Return both balances
        res.json({
            address,
            tonBalance: tonBalanceInTon,
            pdayBalance
        });
    } catch (error) {
        console.error('Error fetching balance:', error);
        res.status(500).json({ error: 'Failed to fetch balance' });
    }
});

module.exports = router;
