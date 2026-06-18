from fastapi import APIRouter
from fastapi.responses import StreamingResponse

from app.schemas.chat import ChatRequest, ChatResponse
from app.services.ollama_client import generate_response, stream_response

router = APIRouter()


def _history(request: ChatRequest) -> list[dict] | None:
    if request.history is None:
        return None
    return [m.model_dump() for m in request.history]


@router.post("/chat", response_model=ChatResponse)
async def chat(request: ChatRequest):
    response = await generate_response(
        request.message, history=_history(request), model=request.model
    )
    return ChatResponse(response=response)


@router.post("/chat/stream")
async def chat_stream(request: ChatRequest):
    return StreamingResponse(
        stream_response(request.message, history=_history(request), model=request.model),
        media_type="text/plain",
    )
