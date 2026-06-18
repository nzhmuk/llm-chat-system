import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI

from app.routes.chat import router as chat_router
from app.routes.health import router as health_router
from app.services.ollama_client import warm_up

logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Warm the model on startup; don't fail boot if Ollama isn't ready yet.
    try:
        await warm_up()
    except Exception as exc:
        logger.warning("Model warm-up failed (will load on first request): %s", exc)
    yield


app = FastAPI(title="LLM Service API", lifespan=lifespan)

app.include_router(chat_router)
app.include_router(health_router)
