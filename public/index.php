<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Pagamento Vinti4 - Vinti4Pay SDK</title>
    <meta name="author" content="Eril TS Carvalho">

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }

        .form-container,
        .instructions {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            flex: 1 1 400px;
            max-width: 600px;
            box-sizing: border-box;
        }

        h2 {
            color: #004aad;
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 15px;
        }

        input[type="submit"] {
            background-color: #004aad;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 20px;
            margin-top: 20px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #003a8c;
        }

        .instructions ul {
            padding-left: 20px;
            line-height: 1.6;
        }

        code {
            background-color: #f1f1f1;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            max-width: 90%;
            max-height: 80%;
            border-radius: 8px;
            display: block;
            margin: auto;
            object-fit: contain;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
        }

        .close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
        }

        .show-image-btn {
            display: inline-block;
            margin-top: 15px;
            background-color: #0078d4;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 15px;
        }

        .show-image-btn:hover {
            background-color: #005fa3;
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
                padding: 15px;
            }

            .form-container,
            .instructions {
                max-width: 100%;
                box-shadow: none;
                border: 1px solid #ddd;
            }

            input[type="submit"] {
                font-size: 15px;
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            padding-top: 80px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            border-radius: 8px;
            box-shadow: 0 0 10px #000;
        }

        .close {
            position: absolute;
            top: 30px;
            right: 40px;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
        }

        .show-image-btn {
            display: inline-block;
            margin-top: 15px;
            background-color: #0078d4;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 15px;
        }

        .show-image-btn:hover {
            background-color: #005fa3;
        }

        @media (max-width: 480px) {
            h2 {
                font-size: 20px;
            }

            label {
                font-size: 14px;
            }

            input[type="text"],
            input[type="number"] {
                font-size: 14px;
                padding: 8px;
            }
        }
    </style>
</head>

<body>

    <div class="form-container">
        <h2>Formulário de Teste - Pagamento Vinti4</h2>
        <form action="postback.php" method="POST">
            <label for="price">Preço (em ECV):</label>
            <input type="number" id="price" name="price" value="100" placeholder="Ex: 100">

            <label for="order_number">Número do Pedido:</label>
            <input type="text" id="order_number" name="pedido" placeholder="Ex: PED12345">

            <input type="submit" value="Enviar Pagamento">

            <div style="display:flex; gap: 8px;">
                
            <!-- <input type="text" name="posid" placeholder="PosID">
            <input type="password" name="posAuthCode" placeholder="PosAutCode"> -->
            </div>
        </form>
    </div>

    <div class="instructions">
        <h2>Instruções de Teste (Ambiente SISP)</h2>
        <p>Para realizar um teste com o gateway <strong>Vinti4 (SISP)</strong>, siga os passos abaixo:</p>
        <ul>
            <li>Configure o campo <code>posID</code> com o ID do seu POS fornecido pela SISP.</li>
            <li>Atualize o <code>posAutCode</code> com o código de autenticação de teste.</li>
            <li>Use o endpoint de teste da SISP no arquivo <code>post.php</code>, por exemplo:<br>
                <code>https://teste.vinti4net.cv/AutorizacaoPagamento</code>
            </li>
            <li>Envie os parâmetros exigidos:
                <ul>
                    <li><code>posID</code></li>
                    <li><code>posAutCode</code></li>
                    <li><code>amount</code> (valor total)</li>
                    <li><code>responseUrl</code></li>
                    <li><code>billing</code> para compra (purchaseRequest)</li>
                </ul>
            </li>
            <li>Os dados para teste são fornecidos diretamente pela <strong>SISP</strong>.</li>
        </ul>

        <p><em>⚠️ Este formulário apenas coleta dados para teste local e envia para <code>post.php</code>, onde deve ser feita a requisição real ao endpoint da SISP.</em></p>
        <small style="color: orangered;">vá e edite os arquivos <code>post.php</code> e <code>callback.php</code> com suas configs</small>
        <br>

        <button class="show-image-btn" onclick="openModal()">📷 Mostrar Snippet Code</button>
    </div>

    <!-- Modal -->
    <div id="snippetModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="snippetImg" src="code.png" alt="Snippet Code">
    </div>

    <script>
        function openModal() {
            document.getElementById('snippetModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('snippetModal').style.display = 'none';
        }

        // Fechar modal ao clicar fora da imagem
        window.onclick = function(event) {
            const modal = document.getElementById('snippetModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
    </script>

</body>

</html>