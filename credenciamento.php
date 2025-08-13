<?php
require_once 'config/database.php';
require_once 'config/auth.php';

redirecionarSeNaoLogado();

// Não precisa mais de curso_id - credenciamento é independente!

// Buscar estatísticas de credenciamento
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_usuarios,
        COUNT(c.codigo_qr) as total_credenciados,
        COUNT(*) - COUNT(c.codigo_qr) as nao_credenciados,
        ROUND((COUNT(c.codigo_qr) / COUNT(*)) * 100, 2) as percentual_credenciados
    FROM usuarios u
    LEFT JOIN credenciamentos c ON u.codigo_qr = c.codigo_qr
    WHERE u.ativo = 1
");
$stmt->execute();
$stats = $stmt->fetch();

// Credenciamentos hoje
$stmt = $pdo->prepare("
    SELECT COUNT(*) as credenciados_hoje
    FROM credenciamentos 
    WHERE DATE(data_credenciamento) = CURDATE()
");
$stmt->execute();
$credenciados_hoje = $stmt->fetchColumn();

// Últimos credenciamentos
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        u.nome_usuario,
        u.funcao,
        o.nome as operador_nome
    FROM credenciamentos c
    JOIN usuarios u ON c.codigo_qr = u.codigo_qr
    JOIN operadores o ON c.operador_id = o.id
    ORDER BY c.data_credenciamento DESC
    LIMIT 15
");
$stmt->execute();
$ultimos_credenciamentos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credenciamento - FEBIC</title>
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="FEBIC Credenciamento">
    <link rel="apple-touch-icon" href="assets/icons/android/android-launchericon-192-192.png">
    
    <link rel="stylesheet" href="assets/style.css">
    
    <!-- PWA Script -->
    <script src="assets/pwa.js" defer></script>
    
    <style>
        .credenciamento-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .header-credenciamento {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header-credenciamento::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255,255,255,0.1) 10px,
                rgba(255,255,255,0.1) 20px
            );
            animation: shimmer 3s linear infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .credenciamento-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        .stats-credenciamento {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            text-align: center;
        }
        
        .stat-item {
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .stat-percentage {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
        }
        
        .reading-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .reading-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .reading-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .camera-area {
            background: #f8f9fa;
            border: 3px dashed #dee2e6;
            border-radius: 15px;
            padding: 40px;
            margin: 20px 0;
            position: relative;
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        
        .camera-placeholder {
            font-size: 64px;
            margin-bottom: 20px;
            color: #6c757d;
        }
        
        .manual-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .manual-input-group input {
            padding: 12px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            width: 200px;
            text-align: center;
            font-family: monospace;
            letter-spacing: 1px;
        }
        
        .manual-input-group input:focus {
            border-color: #28a745;
            outline: none;
        }
        
        .confirmacao-area {
            display: none;
            background: #e7f3ff;
            border: 2px solid #28a745;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }
        
        .confirmacao-area.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .participante-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
            border-left: 5px solid #28a745;
            text-align: left;
        }
        
        .participante-nome {
            font-size: 22px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .participante-detalhes {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .kit-options {
            margin: 20px 0;
            text-align: center;
        }
        
        .kit-option {
            display: inline-block;
            margin: 5px;
            padding: 8px 16px;
            background: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .kit-option.selected {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        .observacoes-input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
        }
        
        .credenciamentos-lista {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .credenciamentos-scroll {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .credenciamentos-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .credenciamentos-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .credenciamentos-scroll::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        .credenciamentos-scroll::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .credenciamento-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            animation: slideInFromRight 0.3s ease;
        }
        
        .credenciamento-item:last-child {
            border-bottom: none;
        }
        
        .credenciamento-item.novo {
            background: linear-gradient(90deg, transparent, #d4edda, transparent);
            border-radius: 8px;
            padding: 15px 10px;
        }
        
        @keyframes slideInFromRight {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .credenciamento-info {
            flex: 1;
        }
        
        .credenciamento-nome {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .credenciamento-detalhes {
            font-size: 13px;
            color: #666;
            margin-top: 2px;
        }
        
        .credenciamento-hora {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        
        .credenciamento-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            min-width: 80px;
        }
        
        .badge-participante {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-palestrante {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-organizador {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-visitante {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-credenciar {
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px 5px;
        }
        
        .btn-credenciar:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-credenciar:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-cancelar {
            background: #6c757d;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px 5px;
        }
        
        .btn-cancelar:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .controls-credenciamento {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-large {
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-camera {
            background: #28a745;
            color: white;
        }
        
        .btn-camera:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-stop {
            background: #dc3545;
            color: white;
        }
        
        .btn-stop:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .feedback-overlay {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 30px 40px;
            border-radius: 15px;
            font-size: 20px;
            font-weight: bold;
            z-index: 10000;
            text-align: center;
            min-width: 300px;
            max-width: 500px;
        }
        
        .feedback-success {
            border-left: 5px solid #28a745;
        }
        
        .feedback-error {
            border-left: 5px solid #dc3545;
        }
        
        .feedback-warning {
            border-left: 5px solid #ffc107;
        }
        
        #video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            background: #000;
        }
        
        @media (max-width: 768px) {
            .credenciamento-container {
                margin: 10px;
                padding: 15px;
            }
            
            .reading-section {
                grid-template-columns: 1fr;
            }
            
            .stats-credenciamento {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .controls-credenciamento {
                flex-direction: column;
            }
            
            .manual-input-group {
                flex-direction: column;
            }
            
            .manual-input-group input {
                width: 100%;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</head>
<body>
    <div class="credenciamento-container">
        <div class="header-credenciamento">
            <span class="credenciamento-icon">🎫</span>
            <h1>Sistema de Credenciamento</h1>
            <h2>FEBIC 2025 - Entrega de Kit e Crachá</h2>
        </div>

        <div class="stats-credenciamento">
            <div class="stat-item">
                <div class="stat-number"><?= number_format($stats['total_credenciados']) ?></div>
                <div class="stat-label">Credenciados</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($credenciados_hoje) ?></div>
                <div class="stat-label">Hoje</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= number_format($stats['nao_credenciados']) ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
            <div class="stat-item">
                <div class="stat-percentage"><?= number_format($stats['percentual_credenciados'], 1) ?>%</div>
                <div class="stat-label">Completude</div>
            </div>
        </div>

        <div class="reading-section">
            <!-- Câmera -->
            <div class="reading-card">
                <div class="reading-title">📱 Leitura por Câmera</div>
                
                <div class="camera-area">
                    <div id="camera-placeholder">
                        <div class="camera-placeholder">📷</div>
                        <p>Clique em "Iniciar Câmera" para escanear QR do crachá</p>
                    </div>
                    <video id="video" style="display: none;" autoplay playsinline></video>
                    <canvas id="canvas" style="display: none;"></canvas>
                </div>
                
                <div class="controls-credenciamento">
                    <button id="start-camera" class="btn-large btn-camera">📷 Iniciar Câmera</button>
                    <button id="stop-camera" class="btn-large btn-stop" style="display: none;">⏹️ Parar</button>
                </div>
            </div>

            <!-- Scanner Manual -->
            <div class="reading-card">
                <div class="reading-title">🔫 Scanner USB / Manual</div>
                
                <div style="background: #e7f3ff; padding: 15px; border-radius: 10px; margin: 15px 0;">
                    <strong>📡 Scanner USB:</strong><br>
                    Conecte o leitor QR e escaneie diretamente - funciona automaticamente!
                    <div style="font-size: 12px; margin-top: 8px; color: #666;">
                        Status: <span id="scanner-status" style="color: #28a745; font-weight: bold;">🟢 Pronto</span>
                    </div>
                </div>
                
                <div>
                    <h4>✍️ Ou digite manualmente:</h4>
                    <div class="manual-input-group">
                        <input type="text" 
                               id="manual-qr" 
                               placeholder="00000000000"
                               maxlength="11" 
                               pattern="\d{11}"
                               autocomplete="off">
                        <button onclick="processarQRManual()" class="btn-large btn-camera">✓ Verificar</button>
                    </div>
                    <p style="font-size: 12px; color: #666; text-align: center; margin-top: 10px;">
                        💡 Digite exatamente 11 números do QR do crachá
                    </p>
                </div>
            </div>
        </div>

        <!-- Área de Confirmação -->
        <div id="confirmacao-area" class="confirmacao-area">
            <h3>⚠️ Confirmar Credenciamento</h3>
            <div id="participante-dados" class="participante-info">
                <!-- Dados do participante aparecerão aqui -->
            </div>
            
            <div class="kit-options">
                <h4>Tipo de Kit:</h4>
                <div class="kit-option selected" data-tipo="participante">👤 Participante</div>
                <div class="kit-option" data-tipo="palestrante">🎤 Palestrante</div>
                <div class="kit-option" data-tipo="organizador">👔 Organizador</div>
                <div class="kit-option" data-tipo="visitante">👥 Visitante</div>
            </div>
            
            <textarea id="observacoes" class="observacoes-input" placeholder="Observações (opcional)..."></textarea>
            
            <div>
                <button id="btn-confirmar" class="btn-credenciar">✅ ENTREGAR KIT</button>
                <button id="btn-cancelar" class="btn-cancelar">❌ Cancelar</button>
            </div>
        </div>

        <div class="credenciamentos-lista">
            <h3>📋 Últimos Credenciamentos Realizados</h3>
            <div class="credenciamentos-scroll">
                <div id="credenciamentos-lista">
                    <?php if (count($ultimos_credenciamentos) === 0): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">Nenhum credenciamento realizado ainda</p>
                    <?php else: ?>
                        <?php foreach ($ultimos_credenciamentos as $cred): ?>
                            <div class="credenciamento-item">
                                <div class="credenciamento-info">
                                    <div class="credenciamento-nome"><?= htmlspecialchars($cred['nome_usuario']) ?></div>
                                    <div class="credenciamento-detalhes">
                                        <?= htmlspecialchars($cred['funcao']) ?>
                                        <?php if ($cred['observacoes']): ?>
                                            • <?= htmlspecialchars($cred['observacoes']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="credenciamento-hora">
                                        <?= date('d/m H:i:s', strtotime($cred['data_credenciamento'])) ?> 
                                        - Por: <?= htmlspecialchars($cred['operador_nome']) ?>
                                    </div>
                                </div>
                                <div class="credenciamento-badge badge-<?= $cred['tipo_kit'] ?>">
                                    <?= ucfirst($cred['tipo_kit']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="controls-credenciamento">
            <a href="admin/relatorio_credenciamento.php" class="btn-large" style="background: #17a2b8; color: white; text-decoration: none;">📊 Relatório Completo</a>
            <a href="index.php" class="btn-large" style="background: #6c757d; color: white; text-decoration: none;">← Voltar ao Início</a>
        </div>
    </div>

    <script>
        // Configurações globais
        const OPERADOR_ID = <?= $_SESSION['operador_id'] ?>;
        const OPERADOR_NOME = "<?= $_SESSION['operador_nome'] ?>";
        
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        let ctx = canvas.getContext('2d');
        let isScanning = false;
        let stream = null;
        let participanteAtual = null;
        
        // Elementos da interface
        const startBtn = document.getElementById('start-camera');
        const stopBtn = document.getElementById('stop-camera');
        const placeholder = document.getElementById('camera-placeholder');
        const manualInput = document.getElementById('manual-qr');
        const confirmacaoArea = document.getElementById('confirmacao-area');
        const participanteDados = document.getElementById('participante-dados');
        const btnConfirmar = document.getElementById('btn-confirmar');
        const btnCancelar = document.getElementById('btn-cancelar');
        const credenciamentosLista = document.getElementById('credenciamentos-lista');
        const observacoesInput = document.getElementById('observacoes');
        const scannerStatus = document.getElementById('scanner-status');
        
        // Variables para scanner externo
        let qrInputBuffer = '';
        let qrInputTimeout = null;
        let lastProcessedTime = 0;
        let tipoKitSelecionado = 'participante';
        
        // Event listeners
        startBtn.addEventListener('click', startCamera);
        stopBtn.addEventListener('click', stopCamera);
        btnConfirmar.addEventListener('click', confirmarCredenciamento);
        btnCancelar.addEventListener('click', cancelarCredenciamento);
        
        // Kit options
        document.querySelectorAll('.kit-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.kit-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                tipoKitSelecionado = this.dataset.tipo;
            });
        });
        
        // Input manual
        manualInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                processarQRManual();
            }
        });
        
        // Scanner externo
        document.addEventListener('keypress', function(e) {
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
                return;
            }
            
            qrInputBuffer += e.key;
            
            // Update status visual
            scannerStatus.innerHTML = '⏳ Lendo...';
            scannerStatus.style.color = '#ffc107';
            
            if (qrInputTimeout) {
                clearTimeout(qrInputTimeout);
            }
            
            qrInputTimeout = setTimeout(() => {
                if (qrInputBuffer.trim().length >= 10) {
                    processarScannerInput(qrInputBuffer.trim());
                }
                qrInputBuffer = '';
                scannerStatus.innerHTML = '🟢 Pronto';
                scannerStatus.style.color = '#28a745';
            }, 150);
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && qrInputBuffer.length > 0 && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                if (qrInputTimeout) {
                    clearTimeout(qrInputTimeout);
                }
                processarScannerInput(qrInputBuffer.trim());
                qrInputBuffer = '';
                scannerStatus.innerHTML = '🟢 Pronto';
                scannerStatus.style.color = '#28a745';
            }
        });
        
        // === FUNÇÕES DA CÂMERA ===
        async function startCamera() {
            try {
                const constraints = {
                    video: { 
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };
                
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                
                video.onloadedmetadata = () => {
                    placeholder.style.display = 'none';
                    video.style.display = 'block';
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'inline-block';
                    
                    isScanning = true;
                    requestAnimationFrame(scanQRCode);
                };
                
            } catch (err) {
                console.error('Erro ao acessar câmera:', err);
                mostrarFeedback('❌ Erro ao acessar a câmera. Verifique as permissões.', 'error');
            }
        }
        
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            
            isScanning = false;
            video.style.display = 'none';
            placeholder.style.display = 'block';
            stopBtn.style.display = 'none';
            startBtn.style.display = 'inline-block';
        }
        
        function scanQRCode() {
            if (!isScanning || video.readyState !== video.HAVE_ENOUGH_DATA) {
                if (isScanning) {
                    requestAnimationFrame(scanQRCode);
                }
                return;
            }
            
            canvas.height = video.videoHeight;
            canvas.width = video.videoWidth;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            
            if (code && code.data) {
                console.log('[Camera] QR detectado:', code.data);
                processarQRCode(code.data);
                return;
            }
            
            requestAnimationFrame(scanQRCode);
        }
        
        // === FUNÇÕES DO SCANNER ===
        function processarScannerInput(input) {
            const now = Date.now();
            if (now - lastProcessedTime < 1000) {
                return;
            }
            
            const qrMatch = input.match(/\d{11}/);
            if (qrMatch) {
                lastProcessedTime = now;
                processarQRCode(qrMatch[0]);
            } else {
                mostrarFeedback('❌ Código inválido do scanner', 'error');
            }
        }
        
        function processarQRManual() {
            const codigo = manualInput.value.trim();
            
            if (!/^\d{11}$/.test(codigo)) {
                mostrarFeedback('❌ Digite exatamente 11 dígitos', 'error');
                manualInput.focus();
                return;
            }
            
            processarQRCode(codigo);
            manualInput.value = '';
        }
        
        // === PROCESSAMENTO PRINCIPAL ===
        function processarQRCode(qrData) {
            if (!/^\d{11}$/.test(qrData)) {
                mostrarFeedback('❌ Formato de QR Code inválido!', 'error');
                return;
            }
            
            // Parar scanning temporariamente
            const wasScanning = isScanning;
            isScanning = false;
            
            verificarParticipante(qrData).finally(() => {
                setTimeout(() => {
                    if (wasScanning && stream) {
                        isScanning = true;
                        requestAnimationFrame(scanQRCode);
                    }
                }, 2000);
            });
        }
        
        async function verificarParticipante(codigoQr) {
            try {
                const response = await fetch('api/credenciamento.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        acao: 'verificar',
                        codigo_qr: codigoQr
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Pode credenciar
                    participanteAtual = {
                        codigo_qr: codigoQr,
                        ...result.participante
                    };
                    mostrarConfirmacao(participanteAtual);
                } else {
                    if (result.ja_credenciado) {
                        // Já credenciado
                        const dados = result.dados_credenciamento;
                        mostrarFeedback(
                            `❌ ${dados.participante}\n\n` +
                            `✅ Já credenciado em ${dados.data_credenciamento}\n` +
                            `📋 Kit: ${dados.tipo_kit}\n` +
                            `👤 Por: ${dados.operador}` +
                            (dados.observacoes ? `\n📝 Obs: ${dados.observacoes}` : ''),
                            'warning'
                        );
                    } else {
                        mostrarFeedback('❌ ' + result.message, 'error');
                    }
                }
                
            } catch (error) {
                console.error('[API] Erro:', error);
                mostrarFeedback('❌ Erro de conexão!', 'error');
            }
        }
        
        function mostrarConfirmacao(participante) {
            participanteDados.innerHTML = `
                <div class="participante-nome">${participante.nome_usuario}</div>
                <div class="participante-detalhes">
                    <strong>Função:</strong> ${participante.funcao || 'Não informado'}<br>
                    <strong>Email:</strong> ${participante.email_usuario || 'Não informado'}<br>
                    <strong>Telefone:</strong> ${participante.telefone_usuario || 'Não informado'}<br>
                    <strong>Cidade:</strong> ${participante.cidade_usuario || 'N/I'} - ${participante.estado_usuario || 'N/I'}<br>
                    <strong>QR:</strong> ${participante.codigo_qr}
                </div>
            `;
            
            // Auto-selecionar tipo de kit baseado na função
            if (participante.funcao) {
                const funcao = participante.funcao.toLowerCase();
                if (funcao.includes('palestrante') || funcao.includes('instrutor')) {
                    selecionarTipoKit('palestrante');
                } else if (funcao.includes('organizador') || funcao.includes('coordenador')) {
                    selecionarTipoKit('organizador');
                }
            }
            
            confirmacaoArea.classList.add('show');
            btnConfirmar.focus();
        }
        
        function selecionarTipoKit(tipo) {
            document.querySelectorAll('.kit-option').forEach(opt => opt.classList.remove('selected'));
            document.querySelector(`[data-tipo="${tipo}"]`).classList.add('selected');
            tipoKitSelecionado = tipo;
        }
        
        async function confirmarCredenciamento() {
            if (!participanteAtual) return;
            
            btnConfirmar.disabled = true;
            btnConfirmar.textContent = '⏳ Processando...';
            
            try {
                const response = await fetch('api/credenciamento.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        acao: 'credenciar',
                        codigo_qr: participanteAtual.codigo_qr,
                        tipo_kit: tipoKitSelecionado,
                        observacoes: observacoesInput.value.trim(),
                        device_id: navigator.userAgent
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    mostrarFeedback(
                        `✅ ${result.participante.nome_usuario}\n\n` +
                        `🎁 Kit ${result.participante.tipo_kit} entregue!\n` +
                        `⏰ ${result.data_credenciamento}\n\n` +
                        `📋 CREDENCIAMENTO CONCLUÍDO`,
                        'success'
                    );
                    adicionarCredenciamentoLista(result);
                    atualizarEstatisticas();
                    cancelarCredenciamento();
                } else {
                    mostrarFeedback('❌ ' + result.message, 'error');
                }
                
            } catch (error) {
                console.error('[Credenciar] Erro:', error);
                mostrarFeedback('❌ Erro ao processar credenciamento!', 'error');
            }
            
            btnConfirmar.disabled = false;
            btnConfirmar.textContent = '✅ ENTREGAR KIT';
        }
        
        function cancelarCredenciamento() {
            confirmacaoArea.classList.remove('show');
            participanteAtual = null;
            observacoesInput.value = '';
            selecionarTipoKit('participante');
        }
        
        function adicionarCredenciamentoLista(result) {
            // Remover mensagem "nenhum credenciamento"
            const emptyMessage = credenciamentosLista.querySelector('p');
            if (emptyMessage) {
                emptyMessage.remove();
            }
            
            const item = document.createElement('div');
            item.className = 'credenciamento-item novo';
            item.innerHTML = `
                <div class="credenciamento-info">
                    <div class="credenciamento-nome">${result.participante.nome_usuario}</div>
                    <div class="credenciamento-detalhes">
                        ${result.participante.funcao}
                        ${observacoesInput.value.trim() ? '• ' + observacoesInput.value.trim() : ''}
                    </div>
                    <div class="credenciamento-hora">
                        ${result.data_credenciamento} - Por: ${OPERADOR_NOME}
                    </div>
                </div>
                <div class="credenciamento-badge badge-${result.participante.tipo_kit}">
                    ${result.participante.tipo_kit.charAt(0).toUpperCase() + result.participante.tipo_kit.slice(1)}
                </div>
            `;
            
            credenciamentosLista.insertBefore(item, credenciamentosLista.firstChild);
            
            setTimeout(() => {
                item.classList.remove('novo');
            }, 1000);
            
            // Limitar a 15 itens
            const items = credenciamentosLista.querySelectorAll('.credenciamento-item');
            if (items.length > 15) {
                items[items.length - 1].remove();
            }
        }
        
        async function atualizarEstatisticas() {
            try {
                const response = await fetch('api/credenciamento.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'estatisticas' })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const stats = result.estatisticas.geral;
                    document.querySelector('.stats-credenciamento .stat-item:nth-child(1) .stat-number').textContent = 
                        new Intl.NumberFormat().format(stats.total_credenciados);
                    document.querySelector('.stats-credenciamento .stat-item:nth-child(2) .stat-number').textContent = 
                        new Intl.NumberFormat().format(result.estatisticas.credenciados_hoje);
                    document.querySelector('.stats-credenciamento .stat-item:nth-child(3) .stat-number').textContent = 
                        new Intl.NumberFormat().format(stats.nao_credenciados);
                    document.querySelector('.stats-credenciamento .stat-item:nth-child(4) .stat-percentage').textContent = 
                        Number(stats.percentual_credenciados).toFixed(1) + '%';
                }
            } catch (error) {
                console.log('Erro ao atualizar estatísticas:', error);
            }
        }
        
        function mostrarFeedback(mensagem, tipo) {
            const feedbackAnterior = document.querySelector('.feedback-overlay');
            if (feedbackAnterior) {
                feedbackAnterior.remove();
            }
            
            const feedback = document.createElement('div');
            feedback.className = `feedback-overlay feedback-${tipo}`;
            feedback.style.whiteSpace = 'pre-line';
            feedback.textContent = mensagem;
            
            document.body.appendChild(feedback);
            
            const tempo = tipo === 'success' ? 5000 : 4000;
            setTimeout(() => {
                if (feedback.parentNode) {
                    feedback.style.opacity = '0';
                    feedback.style.transform = 'translate(-50%, -50%) scale(0.8)';
                    setTimeout(() => feedback.remove(), 300);
                }
            }, tempo);
        }
        
        // Atualizar estatísticas periodicamente
        setInterval(atualizarEstatisticas, 30000);
        
        console.log('[Credenciamento] Sistema inicializado - Tabela específica');
    </script>
</body>
</html>