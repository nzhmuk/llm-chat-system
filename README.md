# llm-chat-system

Self-hosted LLM chat system built on **Ollama + DeepSeek + FastAPI + Laravel**.

The model runs entirely on your own infrastructure — no third-party LLM APIs. A
Laravel web app provides the chat UI and talks to a FastAPI service, which is the
only component allowed to reach the locally-running Ollama model.

---

## Architecture

```
   User → Web App (Laravel) → LLM Service (FastAPI) → Ollama + DeepSeek
```

Typically deployed across two servers: a public web app server, and an LLM
server where FastAPI + Ollama run with the model exposed only to the web app
(and never to the public internet). Both can also be co-located for development.

---

## Repository layout

```
llm-chat-system/
├── llm-service/     # FastAPI service + Ollama — implemented
└── web-app/         # Laravel web application — placeholder, not yet implemented
```

- **[llm-service/](llm-service/README.md)** — the LLM API and its installer.
  See its README for architecture, installation, configuration, and API usage.
- **web-app/** — the Laravel front-end. Not yet implemented.

---

## License

[MIT](LICENSE) © 2026 NickLaoZ
