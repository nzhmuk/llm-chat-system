# LLM Service (FastAPI + Ollama)

This service exposes a simple HTTP API for interacting with a local LLM using Ollama (DeepSeek by default).

It acts as a backend service for the main web application and provides a clean abstraction layer over LLM execution.

---

## Architecture

Web App (Laravel)
        ↓
LLM Service (FastAPI)
        ↓
Ollama (local only)

- FastAPI handles HTTP requests  
- Ollama executes the model locally  
- Ollama is NOT exposed externally  

---

## Features

- REST API for chat
- Async request handling
- Local LLM execution
- Secure setup (LLM not publicly exposed)
- systemd service support

---

## Requirements

- Ubuntu / Debian-based system
- Python 3.10+
- Ollama installed
- Model pulled (e.g. deepseek-r1:7b)

---

## Manual Setup

### 1. Navigate to API directory

```bash
cd llm-service/api
```

### 2. Create virtual environment

```bash
python3 -m venv venv
source venv/bin/activate
```

### 3. Install dependencies

```bash
pip install -r requirements.txt
```

### 4. Run the service

```bash
./run.sh
```

---

## Testing

### Health check

```bash
curl http://127.0.0.1:8000/health
```

### Chat request

```bash
curl -X POST http://127.0.0.1:8000/chat \
  -H "Content-Type: application/json" \
  -d '{"message":"Hello"}'
```

---

## Automated Installation

```bash
cd llm-service/install

./install.sh \
  --web-app-ip=<SERVER_B_IP> \
  --model=deepseek-r1:7b
```

---

## Configuration

Environment variables:

```
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=deepseek-r1:7b
```

---

## API Endpoints

### GET /health

```json
{"status":"ok"}
```

### POST /chat

Request:

```json
{"message":"Hello"}
```

Response:

```json
{"response":"Hello! How can I assist you today?"}
```

---

## Service Management

Restart:

```bash
sudo systemctl restart llm-api
```

Status:

```bash
systemctl status llm-api
```

Logs:

```bash
journalctl -u llm-api -f
```

---

## Notes

- FastAPI runs on port 8000
- Ollama runs on port 11434 (local only)
- Firewall should allow access to port 8000 only from the web app server

---

## Summary

This service provides a secure and simple API layer for interacting with a local LLM and is intended to be used as a backend component of a chat system.
