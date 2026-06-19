<!DOCTYPE html>
<html>
<head>
    <title>MU-TH-UR 6000</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
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

// ✅ ENTER TO SEND
input.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        send();
    }
});

async function send() {
    const text = input.value.trim();
    if (!text) return;

    appendMessage("user", text);

    input.value = "";
    disableInput(true);

    // ✅ SHOW THINKING MESSAGE
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

        // ✅ REPLACE WITH REAL RESPONSE
        thinkingEl.textContent = data.response;
        thinkingEl.classList.remove("thinking");

    } catch (e) {
        thinkingEl.textContent = "ERROR: CONNECTION FAILED";
    }

    disableInput(false);
    input.focus();
}

// ✅ ADD NORMAL MESSAGE
function appendMessage(role, text) {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = "message " + role;
    div.textContent = text;

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

// ✅ ADD "ACCESSING MAINFRAME..."
function appendThinking() {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = "message assistant thinking";
    div.textContent = "accessing mainframe...";

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;

    return div;
}

// ✅ DISABLE INPUT WHILE WAITING
function disableInput(state) {
    input.disabled = state;
    button.disabled = state;
}
</script>

</body>
</html>