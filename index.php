<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot WhatsApp</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        #chat-box { border: 1px solid #ccc; height: 300px; padding: 10px; overflow-y: scroll; background-color: #fff; }
        #message-input { width: 80%; padding: 10px; margin-top: 10px; }
        #send-button { width: 18%; padding: 10px; margin-left: 2%; cursor: pointer; background-color: #25D366; color: white; border: none; }
        .user-message { text-align: right; color: blue; }
        .bot-message { text-align: left; color: green; }
    </style>
</head>
<body>

    <h2>🤖 Chatbot de Teste (Web para WhatsApp)</h2>
    <div id="chat-box">
        </div>

    <input type="text" id="message-input" placeholder="Digite sua mensagem...">
    <button id="send-button" onclick="sendMessage()">Enviar</button>

    <p style="margin-top: 15px;">
        ⚠️ **Nota:** A resposta do WhatsApp não aparecerá automaticamente aqui, pois a Meta envia a resposta para o Webhook (**`webhook.php`**), e não de volta para esta página. Para o chat em tempo real, você precisaria de **WebSockets** ou um loop de **polling**.
    </p>


    <script>
        // 🚨 IMPORTANTE: Você precisará de um endpoint PHP separado para ENVIAR mensagens DA WEB para o WhatsApp.
        // O seu webhook.php atual só lida com o RECEBIMENTO da Meta.
        const SEND_ENDPOINT = 'send_mensage.php';
        const CHAT_BOX = document.getElementById('chat-box');
        const MESSAGE_INPUT = document.getElementById('message-input');
        
        // Simples função para adicionar mensagens na caixa
        function addMessage(text, sender) {
            const msgDiv = document.createElement('div');
            msgDiv.className = sender === 'user' ? 'user-message' : 'bot-message';
            msgDiv.textContent = text;
            CHAT_BOX.appendChild(msgDiv);
            CHAT_BOX.scrollTop = CHAT_BOX.scrollHeight; // Scroll para o final
        }

        async function sendMessage() {
            const message = MESSAGE_INPUT.value.trim();
            if (!message) return;

            addMessage(message, 'user');
            MESSAGE_INPUT.value = '';

            try {
                // Envia a mensagem para o seu servidor PHP
                const response = await fetch(SEND_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    // 🚨 Você precisará definir o NÚMERO DE DESTINO do WhatsApp aqui.
                    body: JSON.stringify({ 
                        text: message,
                        to_number: '55XXYYYYYYYYY' // Substitua pelo número do destinatário
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    addMessage('Mensagem enviada (via Meta API).', 'bot');
                } else {
                    addMessage('Erro ao enviar: ' + (result.error || 'Erro desconhecido'), 'bot');
                }
            } catch (error) {
                addMessage('Erro de conexão com o servidor.', 'bot');
                console.error('Fetch error:', error);
            }
        }

        MESSAGE_INPUT.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    </script>

</body>
</html>
