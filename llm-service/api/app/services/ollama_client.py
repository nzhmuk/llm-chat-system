import logging
import os

import httpx

logger = logging.getLogger(__name__)

OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434")
MODEL = os.getenv("OLLAMA_MODEL", "deepseek-r1:7b")


class OllamaError(Exception):
    """Raised when the Ollama backend cannot be reached or returns an error."""


async def generate_response(message: str) -> str:
    try:
        async with httpx.AsyncClient(timeout=60.0) as client:
            response = await client.post(
                f"{OLLAMA_URL}/api/generate",
                json={"model": MODEL, "prompt": message, "stream": False},
            )
            response.raise_for_status()
    except httpx.HTTPError as exc:
        logger.exception("Ollama request failed: %s", exc)
        raise OllamaError(str(exc)) from exc

    return response.json().get("response", "")
