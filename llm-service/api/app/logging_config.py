import logging
import os
from logging.handlers import RotatingFileHandler

LOG_DIR = os.getenv("LOG_DIR", "/var/log/llm-api")
LOG_FILE = os.path.join(LOG_DIR, "error.log")


def configure_logging() -> None:
    handlers: list[logging.Handler] = [logging.StreamHandler()]  # -> journald

    try:
        os.makedirs(LOG_DIR, exist_ok=True)
        file_handler = RotatingFileHandler(
            LOG_FILE, maxBytes=10_000_000, backupCount=5
        )
        file_handler.setLevel(logging.ERROR)  # only errors go to the file
        handlers.append(file_handler)
    except OSError as exc:
        logging.getLogger(__name__).warning(
            "File logging disabled (%s not writable): %s", LOG_FILE, exc
        )

    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
        handlers=handlers,
    )
