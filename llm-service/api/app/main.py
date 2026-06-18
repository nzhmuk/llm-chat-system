import asyncio
import logging
from contextlib import asynccontextmanager

from fastapi import Depends, FastAPI

from app.routes.chat import router as chat_router
from app.routes.health import router as health_router
from app.security import require_api_key
from app.services.ollama_client import close, warm_up

logger = logging.getLogger(__name__)


async def _warm_up_background() -> None:
    try:
        await warm_up()
        logger.info("Model warm-up complete.")
    except Exception as exc:
        logger.warning("Model warm-up failed (will load on first request): %s", exc)


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Warm the model concurrently so the server binds its port immediately
    # instead of blocking startup until the model is loaded.
    warm_up_task = asyncio.create_task(_warm_up_background())
    yield
    warm_up_task.cancel()
    await close()


app = FastAPI(title="LLM Service API", lifespan=lifespan)

app.include_router(chat_router, dependencies=[Depends(require_api_key)])
app.include_router(health_router)
