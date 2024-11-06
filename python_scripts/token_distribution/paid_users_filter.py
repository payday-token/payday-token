import json
from datetime import datetime, timezone

# Constants
WALLET_ADDRESS = 'EQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUs9z'
TARGET_AMOUNT = 0.2 * 1_000_000_000  # Convert to the appropriate units (nano-ton)
START_DATE = datetime(2024, 10, 5, tzinfo=timezone.utc)  # Make START_DATE timezone-aware

def filter_transactions(transactions):
    unique_addresses = set()
    filtered_transactions = []

    for tx in transactions:
        # Convert the Unix timestamp to a date using fromtimestamp with timezone
        tx_time = datetime.fromtimestamp(tx['utime'], tz=timezone.utc)

        # Extract necessary fields
        source_address = tx['in_msg'].get('source')
        destination_address = tx['in_msg'].get('destination')
        amount = int(tx['in_msg'].get('value', 0))

        # Check if the transaction meets the required criteria
        if (
            destination_address == WALLET_ADDRESS and
            tx_time >= START_DATE and
            amount == TARGET_AMOUNT
        ):
            # Add the transaction to the filtered list
            filtered_transactions.append(tx)
            unique_addresses.add(source_address)

    return filtered_transactions, unique_addresses

# Function to get wallets that has paid the 0.2 TON (to be called externally)
def get_paid_wallets_and_transactions():
    # Load transactions from the JSON file
    with open('ton_transactions.json', 'r') as file:
        # Load the data and split it into separate transactions
        transactions = [json.loads(line) for line in file if line.strip()]

    # Filter transactions
    return filter_transactions(transactions)

def main():
    # Load transactions from the JSON file
    with open('ton_transactions.json', 'r') as file:
        # Load the data and split it into separate transactions
        transactions = [json.loads(line) for line in file if line.strip()]

    # Filter transactions
    filtered_transactions, unique_addresses = filter_transactions(transactions)

    # Output results
    print(f"Filtered Transactions: {filtered_transactions}")
    print(f"Unique Addresses: {unique_addresses}")

if __name__ == "__main__":
    main()