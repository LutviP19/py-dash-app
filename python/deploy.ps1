$ErrorActionPreference = "Stop"
$PORT = 8050

Write-Host "🚀 Starting Python Dash Automated Production Deployment..." -ForegroundColor Indigo
Write-Host "📂 Project Directory: $PSScriptRoot"

# 1. Initialize Virtual Environment if missing
if (-not (Test-Path -Path ".\.venv")) {
    Write-Host "📦 Creating isolated Python virtual environment (.venv)..." -ForegroundColor Cyan
    python -m venv .venv
} else {
    Write-Host "✅ Existing virtual environment detected." -ForegroundColor Green
}

# 2. Activate isolated context
Write-Host "🔌 Activating virtual environment..." -ForegroundColor Cyan
. .\.venv\Scripts\Activate.ps1

# 3. Handle dependency resolution matrix
Write-Host "📥 Upgrading pip and sync-installing dependencies..." -ForegroundColor Cyan
python -m pip install --upgrade pip
if (Test-Path -Path ".\requirements.txt") {
    pip install -r requirements.txt
} else {
    Write-Warning "requirements.txt not found! Installing base modules manually..."
    pip install dash pandas plotly psycopg2-binary sqlalchemy
}

# 4. Safely verify port 8050 state before binding app engine
$portCheck = Get-NetTCPConnection -LocalPort $PORT -State Listen -ErrorAction SilentlyContinue
if ($portCheck) {
    Write-Error "🛑 Error: Port $PORT is already in use by another service."
    exit 1
}

# 5. Launch Service Lifecycle
Write-Host "🎉 Setup complete! Launching Python Dash microservice..." -ForegroundColor Green
Write-Host "--------------------------------------------------------"
python app.py
