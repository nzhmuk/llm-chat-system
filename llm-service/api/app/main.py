from fastapi import FastAPI
from app.routes.chat import router as chat_router
from app.routes.health import router as health_router

app = FastAPI(title="LLM Service API")

app.include_router(chat_router)
app.include_router(health_router)