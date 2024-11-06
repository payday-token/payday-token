import requests
import json
from utils import get_titenum_api_key

API_KEY = f'{get_titenum_api_key()}'
BASE_URL = 'https://ton-mainnet.gateway.tatum.io/getTransactions'
ADDRESS = 'UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2'
INITIAL_LT = '18446744073709551615'
OUTPUT_FILE = 'ton_transactions.json'

def fetch_transactions(lt):
    params = {
        'address': ADDRESS,
        'limit': 50,
        'lt': lt,
        'archival': 'true'
    }
    headers = {
        'x-api-key': API_KEY
    }
    response = requests.get(BASE_URL, headers=headers, params=params)
    
    if response.status_code == 200:
        return response.json()
    else:
        print(f"Error: {response.status_code} - {response.text}")
        return None

def save_transactions(transactions):
    with open(OUTPUT_FILE, 'a') as f:
        for transaction in transactions['result']:
            f.write(json.dumps(transaction) + '\n')

def paginate_transactions(lt):
    while True:
        print(f"Fetching transactions with lt={lt}...")
        transactions = fetch_transactions(lt)
        
        if not transactions or not transactions.get('result'):
            print("No more transactions available.")
            break

        save_transactions(transactions)
        
        # Ensure that the 'lt' is accessible and valid
        if 'transaction_id' in transactions['result'][-1]:
            lt = transactions['result'][-1]['transaction_id']['lt']
            lt = str(int(lt) - 1)
        else:
            print("Transaction ID not found in the last transaction.")
            break

with open(OUTPUT_FILE, 'w') as f:
    f.write('')
paginate_transactions(INITIAL_LT)
print(f"Transactions have been saved to {OUTPUT_FILE}.")