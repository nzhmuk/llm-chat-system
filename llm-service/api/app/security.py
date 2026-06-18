import os
import secrets

from fastapi import Header, HTTPException, status

API_KEY = os.getenv("LLM_API_KEY")


async def require_api_key(authorization: str | None = Header(default=None)) -> None:
    """Require a valid 'Authorization: Bearer <key>' header on protected routes."""
    if not API_KEY:
        # The service must be configured with a key; refuse rather than run open.
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="API key not configured.",
        )

    scheme, _, token = (authorization or "").partition(" ")
    if scheme.lower() != "bearer" or not secrets.compare_digest(token, API_KEY):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing API key.",
            headers={"WWW-Authenticate": "Bearer"},
        )
