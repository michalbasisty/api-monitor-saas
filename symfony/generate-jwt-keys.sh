#!/bin/bash

# Generate JWT Keys for Symfony Application
# This script creates RSA key pair for JWT authentication

set -e

# Create jwt directory if it doesn't exist
mkdir -p config/jwt

echo "Generating JWT RSA key pair..."

# Generate private key
openssl genrsa -out config/jwt/private.pem 4096

# Generate public key from private key
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem

# Set appropriate permissions
chmod 600 config/jwt/private.pem
chmod 644 config/jwt/public.pem

echo "✓ JWT keys generated successfully!"
echo "  - Private key: config/jwt/private.pem"
echo "  - Public key: config/jwt/public.pem"
echo ""
echo "Make sure to set JWT_PASSPHRASE in your .env file (can be empty for now)"
