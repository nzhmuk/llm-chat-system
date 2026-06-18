#!/bin/bash
set -e

echo "===== LLM SERVICE INSTALL START ====="

# -------------------------------
# Parse arguments
# -------------------------------
WEB_APP_IP=""
MODEL="deepseek-r1:7b"

for arg in "$@"; do
  case $arg in
    --web-app-ip=*)
      WEB_APP_IP="${arg#*=}"
      ;;
    --model=*)
      MODEL="${arg#*=}"
      ;;
    *)
      echo "Unknown argument: $arg"
      exit 1
      ;;
  esac
done

# Validate input
if [ -z "$WEB_APP_IP" ]; then
  echo "ERROR: --web-app-ip is required"
  echo "Usage:"
  echo "  ./install.sh --web-app-ip=<SERVER_B_IP> [--model=MODEL]"
  exit 1
fi

echo "Web App IP: $WEB_APP_IP"
echo "Model: $MODEL"

# -------------------------------
# Variables
# -------------------------------
INSTALL_ROOT="/opt/llm-service"
API_DIR="$INSTALL_ROOT/api"

# -------------------------------
# 1. System update
# -------------------------------
echo "[1/9] Updating system..."
sudo apt update
sudo apt upgrade -y

# -------------------------------
# 2. Install dependencies
# -------------------------------
echo "[2/9] Installing dependencies..."
sudo apt install -y \
    git \
    python3 \
    python3-pip \
    curl \
    ufw \
    fail2ban

# -------------------------------
# 3. Setup Fail2Ban
# -------------------------------
echo "[3/9] Configuring Fail2Ban..."
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# -------------------------------
# 4. Configure UFW
# -------------------------------
echo "[4/9] Configuring firewall..."

sudo ufw default deny incoming
sudo ufw default allow outgoing

sudo ufw allow OpenSSH

# Allow FastAPI only from web-app server
sudo ufw allow from "$WEB_APP_IP" to any port 8000 proto tcp

# Block direct access to Ollama
sudo ufw deny 11434/tcp

sudo ufw --force enable

echo "Firewall status:"
sudo ufw status verbose

# -------------------------------
# 5. Install Ollama (if needed)
# -------------------------------
echo "[5/9] Installing Ollama (if needed)..."

if ! command -v ollama &> /dev/null; then
  curl -fsSL https://ollama.com/install.sh | sh
  sudo systemctl enable ollama
  sudo systemctl start ollama
else
  echo "Ollama already installed ✅"
fi

# -------------------------------
# 6. Ensure model is installed
# -------------------------------
echo "[6/9] Ensuring model is installed..."

if ollama show "$MODEL" > /dev/null 2>&1; then
  echo "Model $MODEL already exists ✅"
else
  echo "Pulling model $MODEL..."
  ollama pull "$MODEL"
fi

# -------------------------------
# 7. Prepare directories
# -------------------------------
echo "[7/9] Preparing directories..."

sudo mkdir -p "$INSTALL_ROOT"
sudo mkdir -p "$API_DIR"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "Copying API from repo..."

sudo rm -rf "$API_DIR"
sudo cp -r "$PROJECT_ROOT/llm-service/api" "$API_DIR"

sudo chown -R $USER:$USER "$INSTALL_ROOT"

# -------------------------------
# 8. Install Python dependencies
# -------------------------------
echo "[8/9] Installing Python dependencies..."

cd "$API_DIR"

python3 -m pip install --upgrade pip
pip3 install -r requirements.txt

chmod +x run.sh

# -------------------------------
# 9. Setup systemd service
# -------------------------------
echo "[9/9] Setting up systemd..."

sudo tee /etc/systemd/system/llm-api.service > /dev/null <<EOF
[Unit]
Description=LLM FastAPI Service
After=network.target

[Service]
User=$USER
WorkingDirectory=$API_DIR
ExecStart=/usr/bin/env bash $API_DIR/run.sh
Restart=always

Environment=OLLAMA_URL=http://127.0.0.1:11434
Environment=OLLAMA_MODEL=$MODEL

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable llm-api
sudo systemctl restart llm-api

echo "Service status:"
sudo systemctl status llm-api --no-pager

echo "===== INSTALL COMPLETE ====="