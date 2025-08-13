<?php
require_once 'config/database.php';
require_once 'config/auth.php';

redirecionarSeNaoLogado();

$curso_id = (int)($_GET['curso'] ?? 0);
$modo = $_GET['modo'] ?? '';

if ($curso_id === 0 || !in_array($modo, ['entrada', 'saida'])) {
    header('Location: index.php');
    exit;
}

// Buscar dados do curso
$stmt = $pdo->prepare("
    SELECT c.*, tc.nome as tipo_nome
    FROM cursos c 
    JOIN tipos_controle tc ON c.tipo_controle_id = tc.id 
    WHERE c.id = ? AND c.status = 'ativo'
");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: index.php');
    exit;
}

// Buscar registros recentes para o feedback
$stmt = $pdo->prepare("
    SELECT r.*, u.nome_usuario, u.funcao
    FROM registros r 
    JOIN usuarios u ON r.codigo_qr = u.codigo_qr 
    WHERE r.curso_id = ? 
    ORDER BY r.timestamp_registro DESC 
    LIMIT 10
");
$stmt->execute([$curso_id]);
$registros_recentes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leitura QR - <?= htmlspecialchars($curso['nome']) ?></title>
    
    <!-- PWA Configuration -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="FEBIC Controle">
    <link rel="apple-touch-icon" href="assets/icons/android/android-launchericon-192-192.png">
    
    <link rel="stylesheet" href="assets/style.css">
    
    <!-- PWA Script -->
    <script src="assets/pwa.js" defer></script>
    
    <style>
        .leitura-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .header-controle {
            background: <?= $modo === 'entrada' ? 'linear-gradient(135deg, #28a745, #20c997)' : 'linear-gradient(135deg, #dc3545, #fd7e14)' ?>;
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header-controle::before {
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
        
        .modo-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        .curso-info {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .reading-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .option-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .option-title {
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
            min-height: 300px;
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
        
        .input-manual {
            margin-top: 20px;
        }
        
        .input-manual h4 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .manual-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
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
            border-color: #667eea;
            outline: none;
        }
        
        .scanner-info {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            font-size: 14px;
            color: #004085;
        }
        
        .scanner-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .scanner-ready {
            background: #d4edda;
            color: #155724;
        }
        
        .scanner-waiting {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-display {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .registro-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            animation: slideInFromRight 0.3s ease;
        }
        
        .registro-item:last-child {
            border-bottom: none;
        }
        
        .registro-item.novo {
            background: linear-gradient(90deg, transparent, #d4edda, transparent);
        }
        
        @keyframes slideInFromRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .registro-info {
            flex: 1;
        }
        
        .registro-nome {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .registro-funcao {
            font-size: 13px;
            color: #666;
            margin-top: 2px;
        }
        
        .registro-time {
            font-size: 12px;
            color: #999;
            margin-top: 2px;
        }
        
        .registro-tipo {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .tipo-entrada {
            background: #d4edda;
            color: #155724;
        }
        
        .tipo-saida {
            background: #f8d7da;
            color: #721c24;
        }
        
        .controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
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
        
        .offline-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 15px;
            background: #ffc107;
            color: #856404;
            border-radius: 25px;
            font-size: 12px;
            font-weight: bold;
            display: none;
            z-index: 9999;
        }
        
        .offline-indicator.show {
            display: block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        #video {
            max-width: 100%;
            max-height: 400px;
            border-radius: 10px;
            background: #000;
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
        
        @media (max-width: 768px) {
            .leitura-container {
                margin: 10px;
                padding: 15px;
            }
            
            .reading-options {
                grid-template-columns: 1fr;
            }
            
            .controls {
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
    <div class="leitura-container">
        <div class="header-controle">
            <span class="modo-icon"><?= $modo === 'entrada' ? '🚪➡️' : '➡️🚪' ?></span>
            <h1>Controle de <?= ucfirst($modo) ?></h1>
            <h2><?= htmlspecialchars($curso['nome']) ?></h2>
        </div>

        <div class="curso-info">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; text-align: center;">
                <div>
                    <strong>📅 Data</strong><br>
                    <?= date('d/m/Y', strtotime($curso['data'])) ?>
                </div>
                <?php if ($curso['periodo']): ?>
                <div>
                    <strong>🕐 Período</strong><br>
                    <?= htmlspecialchars($curso['periodo']) ?>
                </div>
                <?php endif; ?>
                <?php if ($curso['horario_inicio'] && $curso['horario_fim']): ?>
                <div>
                    <strong>⏰ Horário</strong><br>
                    <?= date('H:i', strtotime($curso['horario_inicio'])) ?> - <?= date('H:i', strtotime($curso['horario_fim'])) ?>
                </div>
                <?php endif; ?>
                <?php if ($curso['nome_docente']): ?>
                <div>
                    <strong>👨‍🏫 Docente</strong><br>
                    <?= htmlspecialchars($curso['nome_docente']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="reading-options">
            <!-- Opção 1: Câmera -->
            <div class="option-card">
                <div class="option-title">📱 Câmera</div>
                
                <div class="camera-area">
                    <div id="camera-placeholder">
                        <div class="camera-placeholder">📷</div>
                        <p>Clique em "Iniciar Câmera" para escanear</p>
                    </div>
                    <video id="video" style="display: none;" autoplay playsinline></video>
                    <canvas id="canvas" style="display: none;"></canvas>
                </div>
                
                <div class="controls">
                    <button id="start-camera" class="btn-large btn-camera">📷 Iniciar Câmera</button>
                    <button id="stop-camera" class="btn-large btn-stop" style="display: none;">⏹️ Parar Câmera</button>
                </div>
            </div>

            <!-- Opção 2: Scanner Externo + Input Manual -->
            <div class="option-card">
                <div class="option-title">🔫 Scanner / Manual</div>
                
                <div class="scanner-info">
                    <strong>📡 Scanner Externo:</strong><br>
                    Conecte um leitor QR via USB e escaneie diretamente - funciona automaticamente!
                    
                    <div id="scanner-status" class="scanner-status scanner-ready">
                        🟢 Sistema pronto para receber códigos
                    </div>
                </div>
                
                <div class="input-manual">
                    <h4>✍️ Ou digite manualmente:</h4>
                    <div class="manual-input-group">
                        <input type="text" 
                               id="manual-qr" 
                               placeholder="00000000000"
                               maxlength="11" 
                               pattern="\d{11}"
                               autocomplete="off">
                        <button onclick="processarQRManual()" class="btn btn-large">✓ Confirmar</button>
                    </div>
                    <p style="font-size: 12px; color: #666; margin-top: 10px;">
                        💡 Digite exatamente 11 números ou use scanner externo
                    </p>
                </div>
            </div>
        </div>

        <div class="status-display">
            <h3>📋 Últimas Leituras - <?= ucfirst($modo) ?></h3>
            <div id="registros-lista">
                <?php if (count($registros_recentes) === 0): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">Nenhum registro ainda</p>
                <?php else: ?>
                    <?php foreach ($registros_recentes as $registro): ?>
                        <div class="registro-item">
                            <div class="registro-info">
                                <div class="registro-nome"><?= htmlspecialchars($registro['nome_usuario']) ?></div>
                                <div class="registro-funcao"><?= htmlspecialchars($registro['funcao']) ?></div>
                                <div class="registro-time">
                                    <?= date('d/m H:i:s', strtotime($registro['timestamp_registro'])) ?>
                                </div>
                            </div>
                            <div class="registro-tipo tipo-<?= $registro['tipo_registro'] ?>">
                                <?= $registro['tipo_registro'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="controls">
            <a href="controle.php?tipo=<?= $curso['tipo_controle_id'] ?>" class="btn-large" style="background: #6c757d; color: white; text-decoration: none;">← Voltar aos Cursos</a>
        </div>
    </div>

    <div id="offline-indicator" class="offline-indicator">
        📶 Modo Offline - Dados serão sincronizados
    </div>

    <script>
        // Configurações globais
        const CURSO_ID = <?= $curso_id ?>;
        const MODO = '<?= $modo ?>';
        const OPERADOR_ID = <?= $_SESSION['operador_id'] ?>;
        
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        let ctx = canvas.getContext('2d');
        let isScanning = false;
        let stream = null;
        
        // Elementos da interface
        const startBtn = document.getElementById('start-camera');
        const stopBtn = document.getElementById('stop-camera');
        const placeholder = document.getElementById('camera-placeholder');
        const offlineIndicator = document.getElementById('offline-indicator');
        const registrosLista = document.getElementById('registros-lista');
        const manualInput = document.getElementById('manual-qr');
        const scannerStatus = document.getElementById('scanner-status');
        
        // Variables para scanner externo
        let qrInputBuffer = '';
        let qrInputTimeout = null;
        let lastProcessedTime = 0;
        
        // Event listeners principais
        startBtn.addEventListener('click', startCamera);
        stopBtn.addEventListener('click', stopCamera);
        
        // Input manual
        manualInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                processarQRManual();
            }
        });
        
        // Scanner externo - capturar keypresses globais
        document.addEventListener('keypress', function(e) {
            // Não processar se estiver em um input
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
                return;
            }
            
            // Adicionar caractere ao buffer
            qrInputBuffer += e.key;
            
            // Update status visual
            updateScannerStatus('waiting');
            
            // Limpar timeout anterior
            if (qrInputTimeout) {
                clearTimeout(qrInputTimeout);
            }
            
            // Processar após 150ms de pausa (scanner terminou)
            qrInputTimeout = setTimeout(() => {
                if (qrInputBuffer.trim().length >= 10) {
                    processarScannerInput(qrInputBuffer.trim());
                }
                qrInputBuffer = '';
                updateScannerStatus('ready');
            }, 150);
        });
        
        // Capturar Enter (alguns scanners enviam Enter)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && qrInputBuffer.length > 0 && document.activeElement.tagName !== 'INPUT') {
                if (qrInputTimeout) {
                    clearTimeout(qrInputTimeout);
                }
                processarScannerInput(qrInputBuffer.trim());
                qrInputBuffer = '';
                updateScannerStatus('ready');
            }
        });
        
        // Verificar se está online/offline
        function updateOnlineStatus() {
            if (navigator.onLine) {
                offlineIndicator.classList.remove('show');
                sincronizarDados();
            } else {
                offlineIndicator.classList.add('show');
            }
        }
        
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();
        
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
        
        // === FUNÇÕES DO SCANNER EXTERNO ===
        function processarScannerInput(input) {
            console.log('[Scanner] Input recebido:', input);
            
            // Evitar processar o mesmo código muito rapidamente
            const now = Date.now();
            if (now - lastProcessedTime < 1000) {
                console.log('[Scanner] Ignorando - muito rápido');
                return;
            }
            
            // Tentar extrair código de 11 dígitos
            const qrMatch = input.match(/\d{11}/);
            if (qrMatch) {
                lastProcessedTime = now;
                processarQRCode(qrMatch[0]);
            } else {
                console.log('[Scanner] Formato inválido:', input);
                mostrarFeedback('❌ Código inválido do scanner', 'error');
            }
        }
        
        function updateScannerStatus(status) {
            if (status === 'ready') {
                scannerStatus.className = 'scanner-status scanner-ready';
                scannerStatus.innerHTML = '🟢 Sistema pronto para receber códigos';
            } else if (status === 'waiting') {
                scannerStatus.className = 'scanner-status scanner-waiting';
                scannerStatus.innerHTML = '⏳ Recebendo código...';
            }
        }
        
        // === FUNÇÃO MANUAL ===
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
        
        // === PROCESSAMENTO PRINCIPAL DO QR ===
        function processarQRCode(qrData) {
            console.log('[QR] Processando:', qrData);
            
            // Validar formato
            if (!/^\d{11}$/.test(qrData)) {
                mostrarFeedback('❌ Formato de QR Code inválido!', 'error');
                setTimeout(() => {
                    if (isScanning) requestAnimationFrame(scanQRCode);
                }, 2000);
                return;
            }
            
            // Parar scanning temporariamente
            const wasScanning = isScanning;
            isScanning = false;
            
            // Verificar participante
            verificarParticipante(qrData).finally(() => {
                // Retomar scanning após 3 segundos
                setTimeout(() => {
                    if (wasScanning && stream) {
                        isScanning = true;
                        requestAnimationFrame(scanQRCode);
                    }
                }, 3000);
            });
        }
        
        async function verificarParticipante(codigoQr) {
            try {
                const response = await fetch('api/verificar_participante.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        codigo_qr: codigoQr,
                        curso_id: CURSO_ID,
                        modo: MODO
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await registrarPresenca(codigoQr, result.participante);
                } else {
                    mostrarFeedback('❌ ' + result.message, 'error');
                    tocarSom('error');
                }
                
            } catch (error) {
                console.log('[API] Erro na verificação:', error);
                if (!navigator.onLine) {
                    // Modo offline - criar registro local
                    const participanteFake = {
                        nome_usuario: 'Participante (Offline)',
                        funcao: 'A confirmar',
                        codigo_qr: codigoQr
                    };
                    await registrarPresenca(codigoQr, participanteFake, true);
                } else {
                    mostrarFeedback('❌ Erro de conexão!', 'error');
                    tocarSom('error');
                }
            }
        }
        
        async function registrarPresenca(codigoQr, participante, offline = false) {
            const registro = {
                codigo_qr: codigoQr,
                curso_id: CURSO_ID,
                tipo_registro: MODO,
                operador_id: OPERADOR_ID,
                timestamp: new Date().toISOString(),
                participante: participante,
                offline: offline
            };
            
            try {
                if (navigator.onLine && !offline) {
                    const response = await fetch('api/registrar_presenca.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(registro)
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        mostrarFeedback(`✅ ${participante.nome_usuario}\n${MODO.toUpperCase()} registrada!`, 'success');
                        adicionarRegistroLista(registro);
                        tocarSom('success');
                    } else {
                        mostrarFeedback('❌ ' + result.message, 'error');
                        tocarSom('error');
                    }
                } else {
                    // Modo offline
                    armazenarOffline(registro);
                    mostrarFeedback(`📶 ${participante.nome_usuario}\nRegistrado offline!`, 'warning');
                    adicionarRegistroLista(registro);
                    tocarSom('success');
                }
                
            } catch (error) {
                console.log('[Registro] Erro:', error);
                armazenarOffline(registro);
                mostrarFeedback(`📶 Registrado offline\nSerá sincronizado`, 'warning');
                adicionarRegistroLista(registro);
                tocarSom('success');
            }
        }
        
        function armazenarOffline(registro) {
            let registrosOffline = JSON.parse(localStorage.getItem('registros_offline') || '[]');
            registro.id_local = Date.now() + Math.random();
            registrosOffline.push(registro);
            localStorage.setItem('registros_offline', JSON.stringify(registrosOffline));
            updateOnlineStatus();
        }
        
        async function sincronizarDados() {
            let registrosOffline = JSON.parse(localStorage.getItem('registros_offline') || '[]');
            
            if (registrosOffline.length === 0) return;
            
            try {
                const response = await fetch('api/sincronizar_offline.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ registros: registrosOffline })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    localStorage.removeItem('registros_offline');
                    console.log('[Sync] Concluída:', result.sincronizados, 'registros');
                }
                
            } catch (error) {
                console.log('[Sync] Erro:', error.message);
            }
        }
        
        function mostrarFeedback(mensagem, tipo) {
            // Remover feedback anterior se existir
            const feedbackAnterior = document.querySelector('.feedback-overlay');
            if (feedbackAnterior) {
                feedbackAnterior.remove();
            }
            
            const feedback = document.createElement('div');
            feedback.className = `feedback-overlay feedback-${tipo}`;
            feedback.style.whiteSpace = 'pre-line'; // Permitir quebras de linha
            feedback.textContent = mensagem;
            
            document.body.appendChild(feedback);
            
            setTimeout(() => {
                if (feedback.parentNode) {
                    feedback.style.opacity = '0';
                    feedback.style.transform = 'translate(-50%, -50%) scale(0.8)';
                    setTimeout(() => feedback.remove(), 300);
                }
            }, 3000);
        }
        
        function adicionarRegistroLista(registro) {
            // Remover mensagem "nenhum registro"
            const emptyMessage = registrosLista.querySelector('p');
            if (emptyMessage) {
                emptyMessage.remove();
            }
            
            const item = document.createElement('div');
            item.className = 'registro-item novo';
            item.innerHTML = `
                <div class="registro-info">
                    <div class="registro-nome">${registro.participante.nome_usuario}</div>
                    <div class="registro-funcao">${registro.participante.funcao}</div>
                    <div class="registro-time">
                        ${new Date().toLocaleString('pt-BR', {
                            day: '2-digit', 
                            month: '2-digit', 
                            hour: '2-digit', 
                            minute: '2-digit',
                            second: '2-digit'
                        })}
                    </div>
                </div>
                <div class="registro-tipo tipo-${registro.tipo_registro}">
                    ${registro.tipo_registro} ${registro.offline ? '(offline)' : ''}
                </div>
            `;
            
            // Adicionar no topo
            registrosLista.insertBefore(item, registrosLista.firstChild);
            
            // Remover classe 'novo' após animação
            setTimeout(() => {
                item.classList.remove('novo');
            }, 1000);
            
            // Limitar a 10 itens visíveis
            const items = registrosLista.querySelectorAll('.registro-item');
            if (items.length > 10) {
                items[items.length - 1].remove();
            }
        }
        
        function tocarSom(tipo) {
            if (!window.AudioContext && !window.webkitAudioContext) return;
            
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = tipo === 'success' ? 800 : 400;
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            } catch (e) {
                console.log('[Audio] Erro ao tocar som:', e);
            }
        }
        
        // Auto-sincronizar periodicamente
        setInterval(() => {
            if (navigator.onLine) {
                sincronizarDados();
            }
        }, 30000);
        
        // Sincronizar ao fechar página
        window.addEventListener('beforeunload', () => {
            if (navigator.onLine) {
                sincronizarDados();
            }
        });
        
        // Inicialização
        console.log('[Sistema] Leitura QR inicializada');
        console.log('[Sistema] Curso:', CURSO_ID, 'Modo:', MODO);
        
        // Status inicial
        updateScannerStatus('ready');
    </script>
</body>
</html>