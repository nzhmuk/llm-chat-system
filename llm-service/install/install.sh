#!/bin/bash
set -euo pipefail

echo "===== LLM SERVICE INSTALL START ====="

# -------------------------------
# Parse arguments
# -------------------------------
WEB_APP_IP=""
MODEL="deepseek-r1:7b"

for arg in "$@"; do
  case $arg in
    --web-app-ip=*) WEB_APP_IP="${arg#*=}" ;;
    --model=*)      MODEL="${arg#*=}" ;;
    *)
      echo "Unknown argument: $arg"
      echo "Usage: ./install.sh --web-app-ip=<IP> [--model=<MODEL>]"
      exit 1
      ;;
  esac
done

if [ -z "$WEB_APP_IP" ]; then
  echo "ERROR: --web-app-ip is required"
  echo "Usage: ./install.sh --web-app-ip=<IP> [--model=<MODEL>]"
  exit 1
fi

if ! [[ "$WEB_APP_IP" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}(/[0-9]{1,2})?$ ]]; then
  echo "ERROR: --web-app-ip must be a valid IPv4 address or CIDR"
  exit 1
fi

echo "Web App IP: $WEB_APP_IP"
echo "Model:      $MODEL"

# -------------------------------
# Variables
# -------------------------------
INSTALL_ROOT="/opt/llm-service"
SERVICE_USER="llmsvc"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# -------------------------------
# 1. System update + dependencies
# -------------------------------
echo "[1/7] Updating system and installing dependencies..."
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a            # Ubuntu 24.04: don't prompt for service restarts
sudo -E apt-get update
sudo -E apt-get upgrade -y
sudo -E apt-get install -y \
    git \
    python3 \
    python3-venv \
    curl \
    ufw \
    fail2ban

# -------------------------------
# 2. Security (fail2ban + firewall)
# -------------------------------
echo "[2/7] Configuring fail2ban and firewall..."
# fail2ban ships an enabled sshd jail on Ubuntu by default
sudo systemctl enable --now fail2ban

sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
# allow FastAPI (port 8000) only from the web app
sudo ufw allow from "$WEB_APP_IP" to any port 8000 proto tcp
sudo ufw --force enable
sudo ufw status verbose

# -------------------------------
# 3. Install Ollama
# -------------------------------
echo "[3/7] Installing Ollama..."
if ! command -v ollama &> /dev/null; then
  curl -fsSL https://ollama.com/install.sh | sh
fi
sudo systemctl enable --now ollama

echo "Waiting for Ollama to become ready..."
for _ in $(seq 1 30); do
  if curl -fsS http://127.0.0.1:11434/api/tags > /dev/null 2>&1; then
    break
  fi
  sleep 1
done

# -------------------------------
# 4. Ensure model is available
# -------------------------------
echo "[4/7] Ensuring model '$MODEL' is installed..."
if ollama show "$MODEL" > /dev/null 2>&1; then
  echo "Model already present."
else
  ollama pull "$MODEL"
fi

# -------------------------------
# 5. Deploy API code
# -------------------------------
echo "[5/7] Deploying API to $INSTALL_ROOT..."
sudo mkdir -p "$INSTALL_ROOT"
sudo rsync -a --delete \
  --exclude 'venv/' \
  "$PROJECT_ROOT/llm-service/api/" "$INSTALL_ROOT/"

# -------------------------------
# 6. Python virtual environment
# -------------------------------
echo "[6/7] Setting up Python virtual environment..."
sudo python3 -m venv "$INSTALL_ROOT/venv"
sudo "$INSTALL_ROOT/venv/bin/pip" install --upgrade pip
sudo "$INSTALL_ROOT/venv/bin/pip" install -r "$INSTALL_ROOT/requirements.txt"

# dedicated unprivileged user to run the service
if ! id "$SERVICE_USER" &> /dev/null; then
  sudo useradd --system --no-create-home --shell /usr/sbin/nologin "$SERVICE_USER"
fi
sudo chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_ROOT"

# -------------------------------
# 7. systemd service
# -------------------------------
echo "[7/7] Registering systemd service..."
sudo tee /etc/systemd/system/llm-api.service > /dev/null <<EOF
[Unit]
Description=LLM FastAPI Service
After=network.target ollama.service

[Service]
Type=simple
User=$SERVICE_USER
WorkingDirectory=$INSTALL_ROOT
ExecStart=$INSTALL_ROOT/venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000
Restart=always
RestartSec=3
Environment=PYTHONUNBUFFERED=1
Environment=PYTHONPATH=$INSTALL_ROOT
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
