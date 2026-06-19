<h1>Chat</h1>

<input id="message">
<button onclick="send()">Send</button>

<pre id="output"></pre>

<script>
async function send() {
    const message = document.getElementById('message').value;

    const res = await fetch('/chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ message })
    });

    const data = await res.json();
    document.getElementById('output').textContent = data.response;
}
</script>
