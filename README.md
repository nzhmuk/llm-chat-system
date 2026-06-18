# llm-chat-system

Self-hosted LLM chat system built on **Ollama + Qwen3 + FastAPI + Laravel**.

The model runs entirely on your own infrastructure — no third-party LLM APIs. A
Laravel web app provides the chat UI and talks to a FastAPI service, which is the
only component allowed to reach the locally-running Ollama model.

The web app is themed as **MU-TH-UR/6000**, the mainframe of the USCSS Nostromo
(a nod to *Alien*), with a green-on-black terminal aesthetic.

---

## Architecture

```
   User → Web App (Laravel) → LLM Service (FastAPI) → Ollama + Qwen3
```

Typically deployed across two servers: a public web app server, and an LLM
server where FastAPI + Ollama run with the model exposed only to the web app
(and never to the public internet). Both can also be co-located for development.

Responses are streamed token-by-token from Ollama through FastAPI and the Laravel
app to the browser, so long generations appear progressively and survive reverse
proxy timeouts.

---

## Repository layout

```
llm-chat-system/
├── llm-service/     # FastAPI service + Ollama — implemented
└── web-app/         # Laravel web application — implemented
```

- **[llm-service/](llm-service/README.md)** — the LLM API and its installer.
  See its README for architecture, installation, configuration, and API usage.
- **[web-app/](web-app/README.md)** — the Laravel front end. Contains the chat
  UI, authentication, and the MU-TH-UR theme. See its README for configuration,
  local setup, deployment, and theming.

---

## Components

### LLM service (`llm-service/`)
FastAPI wrapper over a local Ollama model. Exposes `/chat` (full response) and
`/chat/stream` (token streaming), keeps the model warm, caps response length,
and applies the MU-TH-UR system prompt. Installable as a systemd service via
`llm-service/install/install.sh`. Defaults to `qwen3:4b` with reasoning disabled.

### Web app (`web-app/app/`)
Laravel application providing:
- Authenticated chat terminal that streams responses live.
- Login / register / dashboard, themed via CSS variables (easy to add new
  color themes).
- A configurable LLM endpoint and timeout, talking to the FastAPI service.

---

## Deployment

The two components deploy independently:

- **LLM server:** run `llm-service/install/install.sh --web-app-ip=<WEB_APP_IP>`
  (see the [llm-service README](llm-service/README.md)).
- **Web app server:** a standard Laravel deploy (e.g. via Plesk Git), running
  `web-app/deployment/deploy.sh`. Point `LLM_API_URL` at the LLM server.

---

## License

[MIT](LICENSE) © 2026 NickLaoZ
