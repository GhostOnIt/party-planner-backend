#!/bin/bash

# 1. Get token
TOKEN_RESPONSE=$(curl -s -X POST "https://sandbox.momodeveloper.mtn.com/collection/token/" \
  -H "Ocp-Apim-Subscription-Key: 55da1cb1f97143428721867b98bccdab" \
  -u "7024c24f-bcca-4232-8f10-ef0a3a400176:62a34bed882c4c5d8dacb9eb59f3384c" \
  -d "")
TOKEN=$(echo "$TOKEN_RESPONSE" | sed 's/.*"access_token":"\([^"]*\)".*/\1/')
echo "Token OK"

# 2. Valid UUID v4 (generate random hex)
UUID="$(printf '%04x%04x-%04x-%04x-%04x-%04x%04x%04x' $RANDOM $RANDOM $RANDOM $((RANDOM & 0x0fff | 0x4000)) $((RANDOM & 0x3fff | 0x8000)) $RANDOM $RANDOM $RANDOM)"
echo "UUID: $UUID"

# 3. Request
EXTID="ext$(date +%s)"
echo "External ID: $EXTID"

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Reference-Id: $UUID" \
  -H "X-Target-Environment: sandbox" \
  -H "Ocp-Apim-Subscription-Key: 55da1cb1f97143428721867b98bccdab" \
  -H "Content-Type: application/json" \
  -d "{\"amount\":\"100\",\"currency\":\"EUR\",\"externalId\":\"$EXTID\",\"payer\":{\"partyIdType\":\"MSISDN\",\"partyId\":\"46733123450\"},\"payerMessage\":\"Test\",\"payeeNote\":\"Test\"}")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

echo "HTTP Status: $HTTP_CODE"
echo "Body: $BODY"

if [ "$HTTP_CODE" = "202" ]; then
  echo "SUCCESS! Payment request accepted."
fi
