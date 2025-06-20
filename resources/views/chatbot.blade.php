<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Chatbot</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
        }

        #chat-box {
            border: 1px solid #eee;
            padding: 10px;
            height: 300px;
            overflow-y: scroll;
            margin-bottom: 10px;
        }

        .message {
            margin-bottom: 5px;
        }

        .user-message {
            text-align: right;
            color: blue;
        }

        .bot-message {
            text-align: left;
            color: green;
        }

        .error-message {
            color: red;
        }
    </style>
</head>

<body>
    <h1>My Secretary</h1>
    <div id="chat-box"></div>
    <input type="text" id="user-input" style="width: 80%; padding: 8px;">
    <button id="send-button" style="width: 18%; padding: 8px;">Kirim</button>

    <script>
        document.getElementById('send-button').addEventListener('click', sendMessage);
        document.getElementById('user-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        function sendMessage() {
            const userInput = document.getElementById('user-input');
            const message = userInput.value.trim();
            if (!message) return;

            appendMessage('Anda: ' + message, 'user-message');
            userInput.value = '';

            fetch('/chatbot/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.reply) {
                    appendMessage('Bot: ' + data.reply, 'bot-message');
                } else if (data.error) {
                    appendMessage('Error: ' + data.error, 'error-message');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                appendMessage('Terjadi kesalahan saat berkomunikasi dengan server.', 'error-message');
            });
        }

        function appendMessage(message, className) {
            const chatBox = document.getElementById('chat-box');
            const msgElement = document.createElement('div');
            msgElement.classList.add('message', className);
            msgElement.innerHTML = message; // Gunakan innerHTML agar link Meet bisa diklik
            chatBox.appendChild(msgElement);
            chatBox.scrollTop = chatBox.scrollHeight; // Auto-scroll ke bawah
        }
    </script>
</body>

</html>