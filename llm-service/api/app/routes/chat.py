from fastapi import APIRouter, HTTPException

from app.schemas.chat import ChatRequest, ChatResponse
from app.services.ollama_client import OllamaError, generate_response

router = APIRouter(tags=["chat"])


@router.post("/chat", response_model=ChatResponse)
async def chat(request: ChatRequest):
    try:
        response = await generate_response(request.message)
    except OllamaError:
        raise HTTPException(status_code=502, detail="LLM backend unavailable")
    return ChatResponse(response=response)
