# Generate JWT Keys for Symfony Application (PowerShell)
# This script creates RSA key pair for JWT authentication

Write-Host "Generating JWT RSA key pair..." -ForegroundColor Cyan

# Create jwt directory if it doesn't exist
$jwtDir = "config\jwt"
if (-not (Test-Path $jwtDir)) {
    New-Item -ItemType Directory -Path $jwtDir -Force | Out-Null
}

# Check if OpenSSL is available
try {
    $null = openssl version
} catch {
    Write-Host "ERROR: OpenSSL is not installed or not in PATH" -ForegroundColor Red
    Write-Host "Please install OpenSSL or use WSL to run the bash script" -ForegroundColor Yellow
    exit 1
}

# Generate private key
Write-Host "Generating private key..." -ForegroundColor Yellow
openssl genrsa -out "$jwtDir\private.pem" 4096

# Generate public key from private key
Write-Host "Generating public key..." -ForegroundColor Yellow
openssl rsa -pubout -in "$jwtDir\private.pem" -out "$jwtDir\public.pem"

Write-Host ""
Write-Host "✓ JWT keys generated successfully!" -ForegroundColor Green
Write-Host "  - Private key: $jwtDir\private.pem" -ForegroundColor Gray
Write-Host "  - Public key: $jwtDir\public.pem" -ForegroundColor Gray
Write-Host ""
Write-Host "Make sure to set JWT_PASSPHRASE in your .env file (can be empty for now)" -ForegroundColor Yellow
