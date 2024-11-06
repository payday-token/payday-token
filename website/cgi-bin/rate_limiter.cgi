#!/bin/bash

LIMIT=5
IP=$REMOTE_ADDR
TIME_FRAME=60 # Seconds
DB_FILE="/var/db/rate_limit.db"

# Create the database table if it doesn't exist
sqlite3 "$DB_FILE" "CREATE TABLE IF NOT EXISTS requests (ip TEXT, timestamp INTEGER);"

# Get current timestamp
NOW=$(date +%s)

# Clean up old entries from the database
sqlite3 "$DB_FILE" "DELETE FROM requests WHERE timestamp < $NOW - $TIME_FRAME;"

# Count requests from this IP within the time frame
COUNT=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM requests WHERE ip='$IP' AND timestamp >= $NOW - $TIME_FRAME;")

if [ "$COUNT" -ge "$LIMIT" ]; then
  echo "Content-type: text/html"
  echo ""
  echo "<html><body><h1>Rate limit exceeded. Try again later.</h1></body></html>"
  exit
fi

# Log the request with timestamp
sqlite3 "$DB_FILE" "INSERT INTO requests (ip, timestamp) VALUES ('$IP', $NOW);"

# Allow access to the presale page
echo "Content-type: text/html"
echo ""
cat ../index.html