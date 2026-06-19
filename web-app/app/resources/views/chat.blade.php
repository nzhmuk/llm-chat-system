<!DOCTYPE html>
<html>
<head>
    <title>MU-TH-UR 6000</title>

    <style>
        body {
            margin: 0;
            background: black;
            color: #00FF41;
            font-family: "Courier New", monospace;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        header {
            border-bottom: 1px solid #00FF41;
            padding: 10px 20px;
            display: flex;
            align-items: center;
        }

        header h1 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 2px;
        }

        main {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
        }

        .message {
            margin-bottom: 10px;
            white-space: pre-wrap;
        }

        .user::before {
            content: "YOU > ";
        }

        .assistant::before {
            content: "MU-TH-UR > ";
        }

        footer {
            border-top: 1px solid #00FF41;
            padding: 10px;
            display: flex;
        }

        input {
            flex: 1;
            background: black;
            color: #00FF41;
            border: none;
            outline: none;
            font-family: inherit;
        }

        button {
            background: black;
            color: #00FF41;
            border: 1px solid #00FF41;
            padding: 5px 10px;
            margin-left: 10px;
            cursor: pointer;
        }

        button:hover {
            background: #003300;
        }

        input::placeholder {
            color: #006600;
        }
    </style>
</head>
<body>

<header>
    <h1>MU-TH-UR / 6000</h1>
</header>

<main id="chat"></main>

<footer>
    <input id="message" placeholder="Enter command...">
    <button onclick="send()">SEND</button>
</footer>

<script>
const input = document.getElementById('message');

// ✅ Auto focus on load
input.focus();

// ✅ Send on Enter
input.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        send();
    }
});

async function send() {
    const text = input.value;

    if (!text.trim()) return;

    appendMessage("user", text);
    input.value = "";

    const res = await fetch('/chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ message: text })
    });

    const data = await res.json();

    appendMessage("assistant", data.response);
}

// ✅ Append message to chat
function appendMessage(role, text) {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = "message " + role;
    div.textContent = text;

    chat.appendChild(div);

    // auto scroll
    chat.scrollTop = chat.scrollHeight;
}
</script>

</body>
</html>
