import json
import os

import httpx

OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434")
MODEL = os.getenv("OLLAMA_MODEL", "deepseek-r1:7b")
# How long Ollama keeps the model in memory after a request ("-1" = forever).
KEEP_ALIVE = os.getenv("OLLAMA_KEEP_ALIVE", "30m")


async def generate_response(message: str) -> str:
    async with httpx.AsyncClient(timeout=60.0) as client:
        response = await client.post(
            f"{OLLAMA_URL}/api/generate",
            json={
                "model": MODEL,
                "prompt": message,
                "stream": False,
                "keep_alive": KEEP_ALIVE,
            },
        )
        data = response.json()
        return data.get("response", "")


async def stream_response(message: str):
    """Yield the model's output token-by-token as it is generated."""
    payload = {
        "model": MODEL,
        "prompt": message,
        "stream": True,
        "keep_alive": KEEP_ALIVE,
    }
    # timeout=None: don't cut off long generations mid-stream.
    async with httpx.AsyncClient(timeout=None) as client:
        async with client.stream(
            "POST", f"{OLLAMA_URL}/api/generate", json=payload
        ) as response:
            response.raise_for_status()
            async for line in response.aiter_lines():
                if not line:
                    continue
                chunk = json.loads(line)
                token = chunk.get("response", "")
                if token:
                    yield token
                if chunk.get("done"):
                    break


async def warm_up() -> None:
    """Preload the model into memory so the first real request is fast."""
    async with httpx.AsyncClient(timeout=120.0) as client:
        # Omitting "prompt" makes Ollama load the model without generating.
        await client.post(
            f"{OLLAMA_URL}/api/generate",
            json={"model": MODEL, "keep_alive": KEEP_ALIVE},
        )
