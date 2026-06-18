#!/bin/bash

source /opt/llm-service/api/venv/bin/activate

export PYTHONPATH=.

uvicorn app.main:app \
  --host 0.0.0.0 \
  --port 8000
