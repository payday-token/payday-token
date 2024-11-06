from ed_crypto import decrypt_string

def get_api_key():
    password = input("Enter the password: ")
    encrypted_api_key = "pSzmh83iqVayQ1WzxRlITWdBQUFBQUJuQW9aUWduUFdyVkJzOUhpbC1XcWQ4c1dXOXYwVHNtSW5lV1BVYTBOcWwxWkIyalIxZ3dYWjJjSzJNWUFtVktuWmlVR3hwYUlaUlY1S0dDVWdfQkZMS0VkRUtHdUtIeHpSdlRkVVBVQVNYWWpQSE8yU0JhQXR4OWRmdnl1djlmbmRoSkVFWGg0Sk5xLU9vcmtXenI0Mm1vd0JUVkQ5OEIzTEJjTlZieEYyd2ZfME5YWT0="
    api_key = decrypt_string(encrypted_api_key, password)
    return api_key

def get_titenum_api_key():
    password = input("Enter the password: ")
    encrypted_api_key = "HlqBwdJgIBonGQv2Swqtb2dBQUFBQUJuQW85VGJ1d3luaC1sdy1Oc1BhdzVlQlFGVWVGNkhCOHpzSFMteDdrMDU3b1d3S0d5WXBVTEZ6d0ZCR1Q2T0dBdjV2b003dWtack5CM1dVT1d1RXJoZEhmTXpvelN2aEU2aVdRVHBDdjRFVE8tcXdUZ19wWkZhX3lLZkk1djIzb2w5T0lNc3BrX2NYWXdhUE1HV3F4X2cxUFktdz09"
    api_key = decrypt_string(encrypted_api_key, password)
    return api_key