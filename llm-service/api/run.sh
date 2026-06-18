#!/bin/bash
set -euo pipefail

# Launch the API from its own directory using the local venv.
# Intended for manual / local development (foreground; no systemd).
cd "$(dirname "$0")"

source venv/bin/activate

export PYTHONPATH=.

uvicorn app.main:app \
  --host 0.0.0.0 \
  --port 8000
