import json
import os
import re
from pathlib import Path

import httpx

# CONFIG
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434")
MODEL = os.getenv("OLLAMA_MODEL", "qwen2.5:3b")


def _parse_bool(value: str | None) -> bool | None:
    """Parse a tri-state env flag: "true"/"false" -> bool, unset/empty -> None."""
    if value is None or value.strip() == "":
        return None
    return value.strip().lower() in ("1", "true", "yes", "on")


def _parse_keep_alive(value: str):
    """Ollama expects keep_alive as an int (seconds, -1 = forever) or a
    duration string ("30m"). A numeric env value must be sent as an int —
    the string "-1" is not a valid duration and Ollama rejects it (400)."""
    try:
        return int(value)
    except (TypeError, ValueError):
        return value


# "-1" keeps the model resident indefinitely, avoiding cold-start latency.
KEEP_ALIVE = _parse_keep_alive(os.getenv("OLLAMA_KEEP_ALIVE", "-1"))

# Max tokens to generate. Caps response length and bounds worst-case latency
# (set "-1" for unlimited).
NUM_PREDICT = int(os.getenv("OLLAMA_NUM_PREDICT", "200"))

# Reasoning toggle for hybrid models (e.g. qwen3). "false" disables the slow
# <think> chain-of-thought. Sent only when set, so non-thinking models are
# unaffected.
THINK = _parse_bool(os.getenv("OLLAMA_THINK"))

# SYSTEM PROMPT (MU-TH-UR personality). Loaded from a text file so the persona
# can be edited without touching code. Override the path with SYSTEM_PROMPT_FILE.
_DEFAULT_PROMPT_FILE = Path(__file__).resolve().parent.parent / "prompts" / "muthur_system.txt"
SYSTEM_PROMPT = Path(os.getenv("SYSTEM_PROMPT_FILE", _DEFAULT_PROMPT_FILE)).read_text(
    encoding="utf-8"
)

# Shared client with an idle-read cap but no total cap (long generations are fine).
_TIMEOUT = httpx.Timeout(None, connect=10.0, read=300.0)
_client = httpx.AsyncClient(timeout=_TIMEOUT)


async def close() -> None:
    """Close the shared client. Call on application shutdown."""
    await _client.aclose()


def _build_messages(message: str, history: list[dict] | None = None) -> list[dict]:
    messages = [{"role": "system", "content": SYSTEM_PROMPT}]
    if history:
        messages.extend(history)
    messages.append({"role": "user", "content": message})
    return messages


def _build_payload(
    message: str, history: list[dict] | None, *, stream: bool, model: str | None = None
) -> dict:
    payload = {
        "model": model or MODEL,
        "messages": _build_messages(message, history),
        "stream": stream,
        "keep_alive": KEEP_ALIVE,
        "options": {"num_predict": NUM_PREDICT},
    }
    if THINK is not None:
        payload["think"] = THINK
    return payload


_THINK_RE = re.compile(r"<think>.*?</think>\s*", re.DOTALL)


def _strip_think(text: str) -> str:
    """Remove <think>...</think> reasoning blocks emitted by reasoning models."""
    return _THINK_RE.sub("", text).strip()


END_MARKER = "END OF LINE."


def _ensure_end_marker(text: str) -> str:
    """Guarantee the response ends with the signature line (the model is not
    fully reliable about including it). Detection ignores the trailing period so
    a model-produced "END OF LINE" isn't duplicated."""
    if "END OF LINE" in text.upper():
        return text
    return f"{text.rstrip()}\n\n{END_MARKER}"


# NON-STREAMING RESPONSE
async def generate_response(
    message: str, history: list[dict] | None = None, model: str | None = None
) -> str:
    response = await _client.post(
        f"{OLLAMA_URL}/api/chat",
        json=_build_payload(message, history, stream=False, model=model),
    )
    response.raise_for_status()
    data = response.json()
    return _ensure_end_marker(_strip_think(data.get("message", {}).get("content", "")))


# STREAMING RESPONSE (token-by-token, with think blocks filtered out)
async def stream_response(
    message: str, history: list[dict] | None = None, model: str | None = None
):
    payload = _build_payload(message, history, stream=True, model=model)

    in_think = False
    emitted = ""
    async with _client.stream(
        "POST", f"{OLLAMA_URL}/api/chat", json=payload
    ) as response:
        response.raise_for_status()

        async for line in response.aiter_lines():
            if not line:
                continue

            try:
                chunk = json.loads(line)
            except json.JSONDecodeError:
                continue

            if "error" in chunk:
                raise RuntimeError(chunk["error"])

            token = chunk.get("message", {}).get("content", "")
            if token:
                # Suppress reasoning tokens between <think> and </think>.
                if "<think>" in token:
                    in_think = True
                if not in_think:
                    emitted += token
                    yield token
                if "</think>" in token:
                    in_think = False

            if chunk.get("done"):
                break

    # Guarantee the signature line if the model didn't produce it.
    if "END OF LINE" not in emitted.upper():
        yield f"\n\n{END_MARKER}"


# WARM-UP FUNCTION (preload model)
async def warm_up() -> None:
    """Preload the model into memory to avoid cold start delay."""
    response = await _client.post(
        f"{OLLAMA_URL}/api/chat",
        json={"model": MODEL, "messages": [], "keep_alive": KEEP_ALIVE},
        timeout=120.0,
    )
    response.raise_for_status()
