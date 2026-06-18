from fastapi import APIRouter
from app.schemas.chat import ChatRequest, ChatResponse
from app.services.ollama_client import generate_response

router = APIRouter()

@router.post("/chat", response_model=ChatResponse)
async def chat(request: ChatRequest):
    response = await generate_response(request.message)
    return ChatResponse(response=response)
