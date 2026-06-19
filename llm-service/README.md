# LLM Service (FastAPI + Ollama)

This service exposes a simple HTTP API for interacting with a local LLM using Ollama (DeepSeek by default).

It acts as a backend service for the main web application and provides a clean abstraction layer over LLM execution.

---

## Architecture

```
Web App (Laravel)
        ↓
LLM Service (FastAPI)
        ↓
Ollama (local only)
```

- FastAPI handles HTTP requests
- Ollama executes the model locally
- Ollama is NOT exposed externally

---

## Features

- REST API for chat (standard JSON and token streaming)
- Async request handling
- Request validation
- Model kept warm in memory (preloaded on startup, configurable keep-alive)
- Local LLM execution
- Secure setup (LLM not publicly exposed)
- systemd service support (automated install only)

---

## Requirements

- **Ubuntu 24.04** (only supported platform for now)
- Internet access to download packages and the model

Python, Ollama, and the model are **not** assumed to be pre-installed. The
automated installer provisions all of them. For manual setup you install the
prerequisites yourself (see below).

---

## Installation

### Automated installation (recommended)

The installer updates the system, installs all required components
(Python, Ollama, the model, firewall), deploys the code to `/opt/llm-service`,
and registers + starts the `llm-api` systemd service.

```bash
# 1. Clone the repository
git clone <REPO_URL>
cd <REPO>/llm-service/install

# 2. Run the installer
./install.sh \
  --web-app-ip=<WEB_APP_SERVER_IP> \
  --model=deepseek-r1:7b
```

Arguments:

| Argument       | Required | Default          | Description                                          |
| -------------- | -------- | ---------------- | ---------------------------------------------------- |
| `--web-app-ip` | yes      | —                | IP/CIDR of the web app allowed to reach port 8000    |
| `--model`      | no       | `deepseek-r1:7b` | Ollama model to pull and serve                       |

After it finishes, the service runs under systemd and starts automatically on boot.

---

### Manual setup

Use this for local development or quick testing.

> **Note:** Manual setup does **not** install or register the systemd service.
> It only launches the API in the foreground — the process stops when you close
> the terminal. Use the automated installation for a managed, persistent service.

First install the prerequisites yourself:

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull the model
ollama pull deepseek-r1:7b
```

Then set up and run the API:

```bash
# 1. Navigate to the API directory
cd llm-service/api

# 2. Create a virtual environment
python3 -m venv venv
source venv/bin/activate

# 3. Install dependencies
pip install -r requirements.txt

# 4. Run the service
./run.sh
```

> `run.sh` expects the `venv/` created in step 2 — run the setup steps first, or it will exit with an error.

---

## Configuration

Environment variables:

| Variable             | Default                  | Description                                                        |
| -------------------- | ------------------------ | ------------------------------------------------------------------ |
| `OLLAMA_URL`         | `http://127.0.0.1:11434` | URL of the local Ollama daemon                                     |
| `OLLAMA_MODEL`       | `deepseek-r1:7b`         | Model used for generation                                          |
| `OLLAMA_KEEP_ALIVE`  | `30m`                    | How long Ollama keeps the model loaded in memory (`-1` = forever)  |

The automated installer sets these in the systemd unit. For manual setup, copy
`api/.env.example` to `api/.env` and adjust as needed.

On startup the service sends a warm-up request so the model is preloaded and the
first chat request doesn't pay the model-load delay.

---

## API Endpoints

### GET /health

```json
{ "status": "ok" }
```

### POST /chat

Returns the full response once generation completes.

Request:

```json
{ "message": "Hello" }
```

Response:

```json
{ "response": "Hello! How can I assist you today?" }
```

`message` is required and must be 1–8000 characters; otherwise the API responds
with `422 Unprocessable Entity`.

### POST /chat/stream

Same request body as `/chat`, but streams the response token-by-token as
`text/plain` (lower time-to-first-token, better for chat UIs). The response is
not JSON — it is the raw generated text sent incrementally.

Request:

```json
{ "message": "Hello" }
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

### Streaming chat request

`-N` disables curl buffering so tokens appear as they are generated:

```bash
curl -N -X POST http://127.0.0.1:8000/chat/stream \
  -H "Content-Type: application/json" \
  -d '{"message":"Tell me a short story."}'
```

---

## Service Management (automated install only)

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
- The firewall allows access to port 8000 only from the web app server

---

## Summary

This service provides a secure and simple API layer for interacting with a local
LLM and is intended to be used as a backend component of a chat system.
