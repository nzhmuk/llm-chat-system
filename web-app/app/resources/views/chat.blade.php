<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MU-TH-UR 6000</title>

    @vite(['resources/css/app.css'])
</head>

<body class="theme-muthur">

<header class="mu-appbar">
    <h1>MU-TH-UR / 6000</h1>
    <nav class="mu-nav">
        <button type="button" id="muteBtn" onclick="toggleMute()">AUDIO: ON</button>
        <form method="POST" action="{{ route('chat.new') }}">
            @csrf
            <button type="submit">NEW INQUIRY</button>
        </form>
        <a href="{{ route('dashboard') }}">DASHBOARD</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">LOGOUT</button>
        </form>
    </nav>
</header>

<main id="chat">
@foreach (($messages ?? []) as $m)
    <div class="message {{ $m->role }}">{{ $m->content }}</div>
@endforeach
</main>

<footer>
    <input id="message" placeholder="Enter command..." autocomplete="off">
    <button id="sendBtn" onclick="send()">SEND</button>
</footer>

<script>
const input = document.getElementById('message');
const button = document.getElementById('sendBtn');

input.focus();

// Scroll to the most recent message on load (persisted history).
const chatEl = document.getElementById('chat');
chatEl.scrollTop = chatEl.scrollHeight;

// ✅ MU-TH-UR sound effects
const sounds = {
    thinking: new Audio("{{ asset('audio/thinking.ogg') }}"),
    teletype: new Audio("{{ asset('audio/teletype.ogg') }}"),
    background: new Audio("{{ asset('audio/background.ogg') }}"),
};
sounds.thinking.loop = true;
sounds.teletype.loop = true;
sounds.background.loop = true;
const BG_VOLUME = 0.7;   // target ambient level; faded in/out rather than hard cut

let muted = localStorage.getItem('mu_muted') === '1';
document.getElementById('muteBtn').textContent = 'AUDIO: ' + (muted ? 'OFF' : 'ON');

function playLoop(name) {
    if (muted) return;
    const a = sounds[name];
    a.currentTime = 0;
    a.play().catch(() => {});   // ignore autoplay rejections
}

function stopLoop(name) {
    const a = sounds[name];
    a.pause();
    a.currentTime = 0;
}

// Only stops the per-response sounds — the ambient background keeps playing.
function stopAllSounds() {
    stopLoop('thinking');
    stopLoop('teletype');
}

let bgFade = null;

// Ramp the background volume to `target` over `duration` ms.
function fadeBackground(target, duration = 800, onDone) {
    if (bgFade) cancelAnimationFrame(bgFade);
    const start = sounds.background.volume;
    const t0 = performance.now();
    (function step(now) {
        const p = Math.min(1, (now - t0) / duration);
        sounds.background.volume = start + (target - start) * p;
        if (p < 1) {
            bgFade = requestAnimationFrame(step);
        } else {
            bgFade = null;
            if (onDone) onDone();
        }
    })(performance.now());
}

function startBackground() {
    if (muted) return;
    if (sounds.background.paused) {
        sounds.background.volume = 0;
        sounds.background.play()
            .then(() => fadeBackground(BG_VOLUME))   // fade in
            .catch(() => {});                        // ignore autoplay rejections
    }
}

// Browsers block audio until a user gesture, so start the ambient bed on the
// first interaction (and it no-ops once already playing).
document.addEventListener('click', startBackground);
document.addEventListener('keydown', startBackground);

function toggleMute() {
    muted = !muted;
    localStorage.setItem('mu_muted', muted ? '1' : '0');
    document.getElementById('muteBtn').textContent = 'AUDIO: ' + (muted ? 'OFF' : 'ON');
    if (muted) {
        stopAllSounds();
        fadeBackground(0, 600, () => sounds.background.pause());   // fade out, then pause
    } else {
        startBackground();
    }
}

// ✅ ENTER to send
input.addEventListener("keydown", function(e) {
    if (e.key === "Enter") send();
});

async function send() {
    const text = input.value.trim();
    if (!text) return;

    appendMessage("user", text);

    input.value = "";
    disableInput(true);

    const thinkingEl = appendThinking();
    playLoop('thinking');

    // Buffered "teletype" reveal: incoming chunks queue in `pending` and are
    // revealed at a steady pace, so the answer types out even when the network
    // delivers it all at once. The reveal rate scales up if a backlog builds,
    // so it never lags far behind generation.
    let pending = "";
    let revealed = "";
    let started = false;       // has the first character been revealed?
    let gotAny = false;        // did any content arrive?
    let streamDone = false;    // has the network stream finished?

    function finish() {
        stopAllSounds();
        if (!gotAny) {
            clearInterval(thinkingEl._intervalId);
            thinkingEl.classList.remove("thinking");
            thinkingEl.textContent = "NO DATA RECEIVED.";
        }
        disableInput(false);
        input.focus();
    }

    const drain = setInterval(() => {
        if (pending.length > 0) {
            // First revealed character: drop the thinking animation/sound and
            // start the teletype print sound.
            if (!started) {
                started = true;
                clearInterval(thinkingEl._intervalId);
                thinkingEl.classList.remove("thinking");
                thinkingEl.textContent = "";
                stopLoop('thinking');
                playLoop('teletype');
            }

            const n = Math.max(1, Math.floor(pending.length / 50));
            revealed += pending.slice(0, n);
            pending = pending.slice(n);
            thinkingEl.textContent = revealed;
            chatEl.scrollTop = chatEl.scrollHeight;
        } else if (streamDone) {
            // Caught up and the stream is finished.
            clearInterval(drain);
            finish();
        }
    }, 16);

    try {
        const res = await fetch('/chat/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/plain',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: text })
        });

        // Surface the real failure instead of a generic "connection failure".
        if (!res.ok) {
            if (res.status === 419) throw new Error("SESSION EXPIRED. RELOAD TERMINAL.");
            if (res.status === 401 || res.status === 302) throw new Error("AUTHENTICATION REQUIRED.");
            throw new Error("MAINFRAME ERROR " + res.status + ".");
        }

        // Read the network stream into the reveal buffer.
        const reader = res.body.getReader();
        const decoder = new TextDecoder();

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const piece = decoder.decode(value, { stream: true });
            if (piece) {
                pending += piece;
                gotAny = true;
            }
        }

        streamDone = true;   // the drain reveals what's left, then calls finish()

    } catch (e) {
        clearInterval(drain);
        clearInterval(thinkingEl._intervalId);
        thinkingEl.classList.remove("thinking");
        thinkingEl.textContent = e.message || "CONNECTION FAILURE.";
        stopAllSounds();
        disableInput(false);
        input.focus();
    }
}

// ✅ append user/system message
function appendMessage(role, text) {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = "message " + role;
    div.textContent = text;

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

// ✅ cinematic animated thinking
function appendThinking() {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = "message assistant thinking";

    let dots = "";

    // ✅ rotating system phrases
    const messages = [
        "accessing mainframe",
        "establishing uplink",
        "querying system core",
        "evaluating request"
    ];

    const baseText = messages[Math.floor(Math.random() * messages.length)];

    div.textContent = baseText;

    // ✅ smooth dot animation
    const interval = setInterval(() => {
        dots = (dots + ".").slice(0, 3);
        div.textContent = baseText + dots;
    }, 400);

    // ✅ FIXED: proper interval storage (not dataset)
    div._intervalId = interval;

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;

    return div;
}

// ✅ disable UI during processing
function disableInput(state) {
    input.disabled = state;
    button.disabled = state;
}
</script>

</body>
</html>
