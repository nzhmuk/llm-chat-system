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

if [ -z "$WEB_APP_IP" ]; then
  echo "ERROR: --web-app-ip is required"
  echo "Usage:"
  echo "  ./install.sh --web-app-ip=<IP> [--model=<MODEL>]"
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
    python3-venv \
    curl \
    ufw \
    fail2ban

# -------------------------------
# 3. Security (fail2ban + ufw)
# -------------------------------
echo "[3/9] Configuring Fail2Ban..."
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

echo "[4/9] Configuring firewall..."

sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH

# allow FastAPI only from web-app
sudo ufw allow from "$WEB_APP_IP" to any port 8000 proto tcp

sudo ufw --force enable

sudo ufw status verbose

# -------------------------------
# 5. Install Ollama (if needed)
# -------------------------------
echo "[5/9] Checking Ollama..."

if ! command -v ollama &> /dev/null; then
  curl -fsSL https://ollama.com/install.sh | sh
  sudo systemctl enable ollama
  sudo systemctl start ollama
else
  echo "Ollama already installed ✅"
fi

# -------------------------------
# 6. Ensure model exists
# -------------------------------
echo "[6/9] Ensuring model is installed..."

if ollama show "$MODEL" > /dev/null 2>&1; then
  echo "Model $MODEL already exists ✅"
else
  echo "Pulling model $MODEL..."
  ollama pull "$MODEL"
fi

# -------------------------------
# 7. Deploy API code
# -------------------------------
echo "[7/9] Deploying API..."

sudo mkdir -p "$INSTALL_ROOT"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# use rsync (safe + clean)
sudo rsync -av --delete "$PROJECT_ROOT/llm-service/api/" "$API_DIR/"

sudo chown -R $USER:$USER "$INSTALL_ROOT"

# -------------------------------
# 8. Setup Python venv
# -------------------------------
echo "[8/9] Setting up virtual environment..."

cd "$API_DIR"

python3 -m venv venv

source venv/bin/activate

pip install --upgrade pip
pip install -r requirements.txt

chmod +x run.sh

# -------------------------------
# 9. Setup systemd
# -------------------------------
echo "[9/9] Setting up systemd..."

sudo tee /etc/systemd/system/llm-api.service > /dev/null <<EOF
[Unit]
Description=LLM FastAPI Service
After=network.target ollama.service

[Service]
Type=simple
User=$USER
WorkingDirectory=$API_DIR
ExecStart=/usr/bin/env bash $API_DIR/run.sh
Restart=always
RestartSec=3

Environment=PYTHONUNBUFFERED=1
Environment=OLLAMA_URL=http://127.0.0.1:11434
Environment=OLLAMA_MODEL=$MODEL

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable llm-api
sudo systemctl restart llm-api

echo "Service status:"
sudo systemctl status llm-api --no-pager || true

echo "===== INSTALL COMPLETE ====="
