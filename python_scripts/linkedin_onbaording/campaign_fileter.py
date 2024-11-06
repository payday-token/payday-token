import requests
import re
from bs4 import BeautifulSoup
import json

# LinkedIn API credentials
CLIENT_ID = 'PayDay_CLIENT_ID'
CLIENT_SECRET = 'PayDay_CLIENT_SECRET'
REDIRECT_URI = 'PayDay_REDIRECT_URL'
POST_URN = 'urn:li:share:7248352321326247936'  # PayDay LinkedIn post URN
ACCESS_TOKEN = ''  # Fill this after you get the token manually for first-time use

# TON Wallet Address regex
ton_wallet_pattern = r"\b[A-Za-z0-9_-]{48}\b"

# Step 1: Function to get the access token via LinkedIn OAuth 2.0
def get_access_token(auth_code):
    token_url = 'https://www.linkedin.com/oauth/v2/accessToken'
    data = {
        'grant_type': 'authorization_code',
        'code': auth_code,
        'redirect_uri': REDIRECT_URI,
        'client_id': CLIENT_ID,
        'client_secret': CLIENT_SECRET
    }
    response = requests.post(token_url, data=data)
    
    if response.status_code == 200:
        access_token = response.json().get('access_token')
        print("Access Token:", access_token)
        return access_token
    else:
        print(f"Error getting access token: {response.status_code} - {response.text}")
        return None

# Step 2: Function to fetch comments on the post
def fetch_comments(post_urn, access_token):
    url = f'https://api.linkedin.com/v2/socialActions/{post_urn}/comments'
    headers = {'Authorization': f'Bearer {access_token}', 'X-Restli-Protocol-Version': '2.0.0'}
    response = requests.get(url, headers=headers)
    
    if response.status_code == 200:
        return response.json()['elements']  # Returns the comments
    else:
        print(f"Error fetching comments: {response.status_code}")
        return []

# Step 3: Function to fetch likes on the post
def fetch_likes(post_urn, access_token):
    url = f'https://api.linkedin.com/v2/socialActions/{post_urn}/likes'
    headers = {'Authorization': f'Bearer {access_token}', 'X-Restli-Protocol-Version': '2.0.0'}
    response = requests.get(url, headers=headers)
    
    if response.status_code == 200:
        return [like['actor']['name'] for like in response.json()['elements']]  # Returns usernames of people who liked
    else:
        print(f"Error fetching likes: {response.status_code}")
        return []

# Step 4: Function to fetch reposts on the post
def fetch_reposts(post_urn, access_token):
    url = f'https://api.linkedin.com/v2/socialActions/{post_urn}/shares'
    headers = {'Authorization': f'Bearer {access_token}', 'X-Restli-Protocol-Version': '2.0.0'}
    response = requests.get(url, headers=headers)
    
    if response.status_code == 200:
        return [repost['actor']['name'] for repost in response.json()['elements']]  # Returns usernames of people who reposted
    else:
        print(f"Error fetching reposts: {response.status_code}")
        return []

# Step 5: Function to extract TON wallet addresses from comments
def extract_wallet_addresses(comments, likes, reposts):
    valid_wallets = set()  # Use a set to avoid duplicates
    
    for comment in comments:
        actor = comment['actor']['name']  # Username from comment
        comment_text = comment['text']['text']  # Actual comment content
        
        # Check if the user has both liked and reposted
        if actor in likes and actor in reposts:
            # Extract TON wallet addresses from the comment
            found_wallets = re.findall(ton_wallet_pattern, comment_text)
            valid_wallets.update(found_wallets)
    
    return valid_wallets

# Function to get valid wallets (to be called externally)
def get_valid_wallets():
        # Fetch LinkedIn post data
    comments = fetch_comments(POST_URN, ACCESS_TOKEN)
    likes = fetch_likes(POST_URN, ACCESS_TOKEN)
    reposts = fetch_reposts(POST_URN, ACCESS_TOKEN)

    # Extract valid TON wallet addresses
    valid_wallets = extract_wallet_addresses(comments, likes, reposts)

    return valid_wallets

# Step 6: Main function to bring it all together
def main():
    # Fetch LinkedIn post data
    comments = fetch_comments(POST_URN, ACCESS_TOKEN)
    likes = fetch_likes(POST_URN, ACCESS_TOKEN)
    reposts = fetch_reposts(POST_URN, ACCESS_TOKEN)

    # Extract valid TON wallet addresses
    valid_wallets = extract_wallet_addresses(comments, likes, reposts)
    
    # Output the results
    if valid_wallets:
        print("Valid TON Wallet Addresses from users who liked and reposted:")
        for wallet in valid_wallets:
            print(wallet)
    else:
        print("No valid TON wallet addresses found.")

if __name__ == "__main__":
    main()
