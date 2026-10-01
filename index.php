<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUSI | WhatsApp</title>
    <style>
        :root {
            --bg: #eef4ef;
            --card: #ffffff;
            --line: #cbd8ce;
            --ink: #163227;
            --muted: #527062;
            --brand: #1f8f58;
            --brand-dark: #166a41;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: linear-gradient(180deg, #edf6ef 0%, #dbeae2 100%); color: var(--ink); }
        .wrap { max-width: 920px; margin: 0 auto; padding: 24px; }
        .hero { margin-bottom: 20px; }
        .hero h1 { margin: 0 0 8px; font-size: 2rem; }
        .hero p { margin: 0; color: var(--muted); }
        .grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 18px; box-shadow: 0 12px 30px rgba(22, 50, 39, 0.08); }
        .card h2 { margin-top: 0; font-size: 1.1rem; }
        .field { display: grid; gap: 8px; margin-bottom: 12px; }
        label { font-weight: 600; }
        input, textarea { width: 100%; border: 1px solid var(--line); border-radius: 12px; padding: 12px; font: inherit; }
        textarea { min-height: 120px; resize: vertical; }
        button { width: 100%; border: 0; border-radius: 12px; padding: 13px; font: inherit; font-weight: 700; color: #fff; background: var(--brand); cursor: pointer; }
        button:hover { background: var(--brand-dark); }
        #chat-box { border: 1px solid var(--line); border-radius: 14px; min-height: 260px; max-height: 360px; overflow-y: auto; background: #fdfefd; padding: 14px; }
        .message { margin-bottom: 10px; padding: 10px 12px; border-radius: 12px; white-space: pre-wrap; }
        .user-message { background: #dff4e6; margin-left: 20px; }
        .bot-message { background: #f2f6f3; margin-right: 20px; }
        code { background: #eef4ef; padding: 2px 6px; border-radius: 6px; }
        .hint { color: var(--muted); font-size: 0.95rem; }
        ul { margin: 12px 0 0; padding-left: 18px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <h1>SUSI no WhatsApp</h1>
            <p>Esta instalação está preparada para rodar toda na raiz do servidor. Use <code>webhook.php</code> na Meta e <code>send_message.php</code> para testes manuais de envio.</p>
        </div>

        <div class="grid">
            <section class="card">
                <h2>Enviar teste</h2>
                <div class="field">
                    <label for="to-number">Número de destino</label>
                    <input type="text" id="to-number" placeholder="5511999999999">
                </div>
                <div class="field">
                    <label for="message-input">Mensagem</label>
                    <textarea id="message-input" placeholder="Digite a mensagem que deseja enviar pelo WhatsApp"></textarea>
                </div>
                <button id="send-button" onclick="sendMessage()">Enviar para o WhatsApp</button>
                <p class="hint">Use o formato com DDI e DDD. Exemplo: <code>5511999999999</code>.</p>
            </section>

            <section class="card">
                <h2>Rotas da raiz</h2>
                <div id="chat-box"></div>
                <ul class="hint">
                    <li><code>webhook.php</code>: URL pública cadastrada na Meta.</li>
                    <li><code>send_message.php</code>: envio manual de mensagens.</li>
                    <li><code>health.php</code>: diagnóstico rápido da instalação.</li>
                </ul>
            </section>
        </div>
    </div>

    <script>
        const SEND_ENDPOINT = 'send_message.php';
        const CHAT_BOX = document.getElementById('chat-box');
        const MESSAGE_INPUT = document.getElementById('message-input');
        const TO_NUMBER_INPUT = document.getElementById('to-number');
        
        function addMessage(text, sender) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `message ${sender === 'user' ? 'user-message' : 'bot-message'}`;
            msgDiv.textContent = text;
            CHAT_BOX.appendChild(msgDiv);
            CHAT_BOX.scrollTop = CHAT_BOX.scrollHeight;
        }

        async function sendMessage() {
            const message = MESSAGE_INPUT.value.trim();
            const toNumber = TO_NUMBER_INPUT.value.trim();
            if (!message || !toNumber) {
                addMessage('Preencha o número e a mensagem antes de enviar.', 'bot');
                return;
            }

            addMessage(message, 'user');
            MESSAGE_INPUT.value = '';

            try {
                const response = await fetch(SEND_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        text: message,
                        to_number: toNumber
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    addMessage('Mensagem enviada com sucesso pela Meta API.', 'bot');
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
                e.preventDefault();
                sendMessage();
            }
        });

        addMessage('Abra `health.php` para conferir credenciais, extensões PHP e permissões de escrita.', 'bot');
    </script>

</body>
</html>
