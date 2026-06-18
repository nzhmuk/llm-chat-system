#!/bin/bash
set -euo pipefail

echo "===== LLM SERVICE INSTALL START ====="

# -------------------------------
# Parse arguments
# -------------------------------
WEB_APP_IP=""
MODEL="qwen2.5:3b"
KEEP_ALIVE="-1"
NUM_PREDICT="200"
# Empty = don't send a thinking flag (correct for non-thinking models like
# qwen2.5). Set to "false"/"true" only for hybrid models such as qwen3.
THINK=""

USAGE="Usage: ./install.sh --web-app-ip=<IP> [--model=<MODEL>] [--keep-alive=<DURATION>] [--num-predict=<N>] [--think=<true|false>]"

for arg in "$@"; do
  case $arg in
    --web-app-ip=*)   WEB_APP_IP="${arg#*=}" ;;
    --model=*)        MODEL="${arg#*=}" ;;
    --keep-alive=*)   KEEP_ALIVE="${arg#*=}" ;;
    --num-predict=*)  NUM_PREDICT="${arg#*=}" ;;
    --think=*)        THINK="${arg#*=}" ;;
    *)
      echo "Unknown argument: $arg"
      echo "$USAGE"
      exit 1
      ;;
  esac
done

if [ -z "$WEB_APP_IP" ]; then
  echo "ERROR: --web-app-ip is required"
  echo "$USAGE"
  exit 1
fi

if ! [[ "$WEB_APP_IP" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}(/[0-9]{1,2})?$ ]]; then
  echo "ERROR: --web-app-ip must be a valid IPv4 address or CIDR"
  exit 1
fi

echo "Web App IP:  $WEB_APP_IP"
echo "Model:       $MODEL"
echo "Keep alive:  $KEEP_ALIVE"
echo "Num predict: $NUM_PREDICT"
echo "Think:       $THINK"

# -------------------------------
# Variables
# -------------------------------
INSTALL_ROOT="/opt/llm-service"
SERVICE_USER="llmsvc"
ENV_FILE="/etc/llm-api/llm-api.env"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# Reuse the existing API key on re-install (so the web app keeps working);
# generate a fresh one only on first install.
API_KEY=""
if [ -f "$ENV_FILE" ]; then
  API_KEY="$(sudo grep -E '^LLM_API_KEY=' "$ENV_FILE" | cut -d= -f2- || true)"
fi
if [ -z "$API_KEY" ]; then
  API_KEY="$(openssl rand -hex 32)"
fi

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

# Concurrency tuning via a systemd drop-in (kept separate from Ollama's own unit
# so its updater won't overwrite it). On a CPU host, parallel generations share
# cores — keep NUM_PARALLEL modest. 2 active + a queue serves ~5 concurrent users.
sudo mkdir -p /etc/systemd/system/ollama.service.d
sudo tee /etc/systemd/system/ollama.service.d/concurrency.conf > /dev/null <<EOF
[Service]
Environment=OLLAMA_NUM_PARALLEL=2
Environment=OLLAMA_MAX_QUEUE=16
EOF
sudo systemctl daemon-reload
sudo systemctl restart ollama

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
echo "[7/7] Writing config and registering systemd service..."

# Service config (operator-tunable settings + secret), loaded by systemd as an
# EnvironmentFile. Kept out of the unit file and readable only by the service.
sudo mkdir -p "$(dirname "$ENV_FILE")"
sudo tee "$ENV_FILE" > /dev/null <<EOF
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=$MODEL
OLLAMA_KEEP_ALIVE=$KEEP_ALIVE
OLLAMA_NUM_PREDICT=$NUM_PREDICT
OLLAMA_THINK=$THINK
LLM_API_KEY=$API_KEY
EOF
sudo chown "$SERVICE_USER:$SERVICE_USER" "$ENV_FILE"
sudo chmod 600 "$ENV_FILE"

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
LogsDirectory=llm-api
EnvironmentFile=$ENV_FILE
Environment=LOG_DIR=/var/log/llm-api
Environment=PYTHONUNBUFFERED=1
Environment=PYTHONPATH=$INSTALL_ROOT

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable llm-api
sudo systemctl restart llm-api

echo "Service status:"
sudo systemctl status llm-api --no-pager || true

echo "===== INSTALL COMPLETE ====="
echo ""
echo "----------------------------------------------------------------"
echo "LLM API KEY — set this as LLM_API_KEY in the web app's .env:"
echo ""
echo "    $API_KEY"
echo ""
echo "(Stored in $ENV_FILE; reused automatically on re-install.)"
echo "----------------------------------------------------------------"
