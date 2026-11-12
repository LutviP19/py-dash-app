#!/bin/bash

# Exit instantly if any sequential command returns a non-zero exit status code
set -e

PROJECT_DIR="$(pwd)"
PORT=8050

echo "🚀 Starting Python Dash Automated Production Deployment..."
echo "📂 Project Directory: $PROJECT_DIR"

# 1. Initialize Virtual Environment if missing
if [ ! -d ".venv" ]; then
    echo "📦 Creating isolated Python virtual environment (.venv)..."
    python3 -m venv .venv
else
    echo "✅ Existing virtual environment detected."
fi

# 2. Activate isolated context
echo "🔌 Activating virtual environment..."
source .venv/bin/activate

# 3. Handle dependency resolution matrix
echo "📥 Upgrading pip and sync-installing dependencies..."
pip install --upgrade pip
if [ -f "requirements.txt" ]; then
    pip install -r requirements.txt
else
    echo "⚠️ Warning: requirements.txt not found! Installing base modules manually..."
    pip install dash pandas plotly psycopg2-binary sqlalchemy
fi

# 4. Safely verify port 8050 state before binding app engine
if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null ; then
    echo "🛑 Error: Port $PORT is already in use by another service."
    echo "   Please clear the listener port or adjust app.py configuration parameters."
    exit 1
fi

# 5. Launch Service Lifecycle
echo "🎉 Setup complete! Launching Python Dash microservice..."
echo "--------------------------------------------------------"
python app.py
