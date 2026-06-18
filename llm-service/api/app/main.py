from fastapi import FastAPI

from app.logging_config import configure_logging
from app.routes.chat import router as chat_router
from app.routes.health import router as health_router

configure_logging()

app = FastAPI(title="LLM Service API")

app.include_router(chat_router)
app.include_router(health_router)
