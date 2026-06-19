<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>MU-TH-UR 6000</title>

    @vite(['resources/css/app.css'])
</head>

<body>

<header>
    <h1>MU-TH-UR / 6000</h1>
</header>

<main id="chat"></main>

<footer>
    <input id="message" placeholder="Enter command..." autocomplete="off">
    <button id="sendBtn" onclick="send()">SEND</button>
</footer>

<script>
const input = document.getElementById('message');
const button = document.getElementById('sendBtn');

input.focus();

// ENTER to send
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

    try {
        const res = await fetch('/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ message: text })
        });

        const data = await res.json();

        // ✅ STOP ANIMATION
        clearInterval(thinkingEl.dataset.intervalId);

        // ✅ SHOW FINAL RESPONSE
        thinkingEl.textContent = data.response;
        thinkingEl.classList.remove("thinking");

    } catch (e) {
        clearInterval(thinkingEl.dataset.intervalId);
        thinkingEl.textContent = "ERROR: CONNECTION FAILED";
    }

    disableInput(false);
    input.focus();
}

// append user/system message
function appendMessage(role, text) {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = "message " + role;
    div.textContent = text;

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

// ✅ animated "accessing mainframe..."
function appendThinking() {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = "message assistant thinking";

    let dots = "";
    const baseText = "accessing mainframe";

    div.textContent = baseText;

    const interval = setInterval(() => {
        dots = dots.length < 3 ? dots + "." : "";
        div.textContent = baseText + dots;
    }, 400);

    // store interval id so we can stop it
    div.dataset.intervalId = interval;

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;

    return div;
}

// disable UI during processing
function disableInput(state) {
    input.disabled = state;
    button.disabled = state;
}
</script>

</body>
</html>