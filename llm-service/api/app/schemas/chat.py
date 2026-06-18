from pydantic import BaseModel, Field


class HistoryMessage(BaseModel):
    role: str
    content: str


class ChatRequest(BaseModel):
    message: str = Field(min_length=1, max_length=8000)
    # Optional model override; falls back to the service default when omitted.
    model: str | None = Field(default=None, max_length=100)
    # Prior conversation turns, oldest first.
    history: list[HistoryMessage] | None = Field(default=None)


class ChatResponse(BaseModel):
    response: str
