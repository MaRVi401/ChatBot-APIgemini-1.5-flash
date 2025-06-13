<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My First ChatBot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .chat-container {
            width: 100%;
            max-width: 600px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .chat-header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 1.2em;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .chat-header .title {
            text-align: center;
        }
        .chat-messages {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            max-height: 400px;
            min-height: 200px;
        }
        .message-box {
            display: flex;
            margin-bottom: 10px;
        }
        .message-user {
            justify-content: flex-end;
        }
        .message-bot {
            justify-content: flex-start;
        }
        .message-content {
            padding: 10px 15px;
            border-radius: 18px;
            max-width: 75%;
            word-wrap: break-word;
        }
        .message-user .message-content {
            background-color: #007bff;
            color: white;
            border-bottom-right-radius: 2px;
        }
        .message-bot .message-content {
            background-color: #e2e6ea;
            color: #333;
            border-bottom-left-radius: 2px;
        }
        .chat-input {
            display: flex;
            padding: 15px;
            border-top: 1px solid #eee;
            align-items: center;
        }
        .chat-input input {
            flex-grow: 1;
            border-radius: 20px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            margin-left: 10px; /* Add margin to the left of the input */
        }
        .chat-input button {
            border-radius: 20px;
            padding: 10px 20px;
        }
        /* Style untuk tombol refresh di input area */
        .chat-input #clear-chat-button {
            background-color: #dc3545; /* Warna merah Bootstrap */
            border: 1px solid #dc3545;
            color: white;
            width: 40px; /* Ukuran tetap untuk ikon */
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2em; /* Ukuran ikon */
            padding: 0; /* Hapus padding default button */
            margin-right: 10px; /* Jarak dari input di sebelah kanannya */
            margin-left: 0; /* Pastikan tidak ada margin kiri tambahan */
        }
        .chat-input #clear-chat-button:hover {
            background-color: #c82333; /* Merah sedikit lebih gelap saat hover */
            border-color: #bd2130;
        }
        .chat-input #clear-chat-button i {
            font-size: 1.2em; /* Pastikan ikon tetap besar */
        }
        /* Style untuk tombol kirim */
        .chat-input #send-button {
            margin-left: 10px; /* Jarak dari tombol refresh */
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="title">Private School</div>
        </div>
        <div class="chat-messages" id="chat-messages">
            <div class="message-box message-bot">
                <div class="message-content">Halo! Saya Chatbot Yang dibuat oleh Ahmad Yassin dengan menggunakan Flask. Ada yang bisa saya bantu?</div>
            </div>
        </div>
        <div class="chat-input">
            <button id="clear-chat-button"><i class="bi bi-arrow-clockwise"></i></button>
            <input type="text" id="user-message" placeholder="Ketik pesan Anda..." class="form-control">
            <button id="send-button" class="btn btn-primary">Kirim</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chatMessages = document.getElementById('chat-messages');
            const userMessageInput = document.getElementById('user-message');
            const sendButton = document.getElementById('send-button');
            const clearChatButton = document.getElementById('clear-chat-button');

            function appendMessage(sender, message) {
                const messageBox = document.createElement('div');
                messageBox.classList.add('message-box');
                messageBox.classList.add(sender === 'user' ? 'message-user' : 'message-bot');

                const messageContent = document.createElement('div');
                messageContent.classList.add('message-content');
                messageContent.textContent = message;

                messageBox.appendChild(messageContent);
                chatMessages.appendChild(messageBox);

                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            async function sendMessage() {
                const message = userMessageInput.value.trim();
                if (message === '') return;

                appendMessage('user', message);
                userMessageInput.value = '';

                try {
                    const response = await fetch('/chatbot/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ message: message })
                    });

                    const data = await response.json();

                    if (response.ok) {
                        appendMessage('bot', data.reply);
                    } else {
                        appendMessage('bot', `Error: ${data.error || 'Terjadi kesalahan yang tidak diketahui.'}`);
                    }
                } catch (error) {
                    console.error('Error mengirim pesan:', error);
                    appendMessage('bot', 'Maaf, terjadi masalah koneksi atau server. Silakan coba lagi.');
                }
            }

            function clearChat() {
                chatMessages.innerHTML = `
                    <div class="message-box message-bot">
                        <div class="message-content">Halo! Saya Chatbot Yang dibuat oleh Ahmad Yassin dengan menggunakan flask. Ada yang bisa saya bantu?</div>
                    </div>
                `;
            }

            sendButton.addEventListener('click', sendMessage);

            userMessageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });

            clearChatButton.addEventListener('click', clearChat);
        });
    </script>
</body>
</html>