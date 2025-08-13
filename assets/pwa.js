// PWA Install Manager para FEBIC Controle
class PWAInstaller {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.init();
    }
    
    init() {
        // Verificar se já está instalado
        this.checkInstallStatus();
        
        // Registrar Service Worker
        this.registerServiceWorker();
        
        // Configurar eventos de instalação
        this.setupInstallEvents();
        
        // Mostrar prompt de instalação se aplicável
        this.setupInstallPrompt();
        
        // Configurar sincronização
        this.setupBackgroundSync();
    }
    
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/controleacesso/sw.js', {
                    scope: '/controleacesso/'
                });
                
                console.log('[PWA] Service Worker registrado:', registration.scope);
                
                // Configurar sync se suportado
                if ('sync' in window.ServiceWorkerRegistration.prototype) {
                    registration.sync.register('sync-offline-data');
                }
                
                // Verificar atualizações
                this.checkForUpdates(registration);
                
            } catch (error) {
                console.error('[PWA] Erro ao registrar Service Worker:', error);
            }
        }
    }
    
    checkForUpdates(registration) {
        registration.addEventListener('updatefound', () => {
            const newWorker = registration.installing;
            
            newWorker.addEventListener('statechange', () => {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                    this.showUpdateNotification();
                }
            });
        });
    }
    
    showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'update-notification';
        notification.innerHTML = `
            <div class="update-content">
                <span>🔄 Nova versão disponível!</span>
                <button onclick="window.location.reload()" class="btn-update">Atualizar</button>
            </div>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 9999;
            font-weight: bold;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove após 10 segundos
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 10000);
    }
    
    setupInstallEvents() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('[PWA] Evento beforeinstallprompt disparado');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallBanner();
        });
        
        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App instalado');
            this.isInstalled = true;
            this.hideInstallBanner();
            this.showInstalledMessage();
        });
    }
    
    showInstallBanner() {
        // Verificar se banner já existe
        if (document.getElementById('pwa-install-banner')) return;
        
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.innerHTML = `
            <div class="install-banner">
                <div class="install-content">
                    <div class="install-icon">📱</div>
                    <div class="install-text">
                        <strong>Instalar FEBIC Controle</strong>
                        <small>Acesso rápido e funcionamento offline</small>
                    </div>
                </div>
                <div class="install-actions">
                    <button id="install-button" class="btn-install">Instalar</button>
                    <button id="dismiss-button" class="btn-dismiss">×</button>
                </div>
            </div>
        `;
        
        banner.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 9999;
            animation: slideUp 0.3s ease;
            max-width: 400px;
            margin: 0 auto;
        `;
        
        document.body.appendChild(banner);
        
        // Event listeners
        document.getElementById('install-button').addEventListener('click', () => {
            this.installApp();
        });
        
        document.getElementById('dismiss-button').addEventListener('click', () => {
            this.hideInstallBanner();
        });
    }
    
    async installApp() {
        if (!this.deferredPrompt) return;
        
        try {
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            
            console.log('[PWA] Escolha do usuário:', outcome);
            
            if (outcome === 'accepted') {
                this.hideInstallBanner();
            }
            
            this.deferredPrompt = null;
            
        } catch (error) {
            console.error('[PWA] Erro na instalação:', error);
        }
    }
    
    hideInstallBanner() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) {
            banner.style.animation = 'slideDown 0.3s ease';
            setTimeout(() => banner.remove(), 300);
        }
    }
    
    showInstalledMessage() {
        const message = document.createElement('div');
        message.innerHTML = `
            <div class="success-message">
                ✅ FEBIC Controle instalado com sucesso!
            </div>
        `;
        
        message.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: bold;
            z-index: 9999;
            animation: fadeInOut 3s ease;
        `;
        
        document.body.appendChild(message);
        
        setTimeout(() => message.remove(), 3000);
    }
    
    checkInstallStatus() {
        // Verificar se está rodando como PWA
        if (window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone) {
            this.isInstalled = true;
            console.log('[PWA] Rodando como aplicativo instalado');
        }
    }
    
    setupInstallPrompt() {
        // Mostrar prompt apenas em certas condições
        const shouldShowPrompt = !this.isInstalled && 
                                 !localStorage.getItem('pwa-prompt-dismissed') &&
                                 this.isEligibleDevice();
        
        if (shouldShowPrompt) {
            // Aguardar um tempo antes de mostrar
            setTimeout(() => {
                if (this.deferredPrompt) {
                    this.showInstallBanner();
                }
            }, 5000);
        }
    }
    
    isEligibleDevice() {
        // Verificar se é dispositivo móvel ou tablet
        const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const hasTouch = 'ontouchstart' in window;
        return isMobile || hasTouch;
    }
    
    setupBackgroundSync() {
        // Configurar sincronização automática
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            navigator.serviceWorker.ready.then(registration => {
                // Registrar sync periódico
                registration.sync.register('sync-offline-data');
                
                console.log('[PWA] Background sync configurado');
            });
        }
        
        // Fallback para dispositivos que não suportam background sync
        this.setupFallbackSync();
    }
    
    setupFallbackSync() {
        // Sincronizar quando a página fica visível
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && navigator.onLine) {
                this.syncOfflineData();
            }
        });
        
        // Sincronizar quando volta online
        window.addEventListener('online', () => {
            this.syncOfflineData();
        });
        
        // Sincronização periódica
        setInterval(() => {
            if (navigator.onLine) {
                this.syncOfflineData();
            }
        }, 60000); // A cada 1 minuto
    }
    
    async syncOfflineData() {
        try {
            const response = await fetch('/controleacesso/api/sincronizar_offline.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'sync_check' })
            });
            
            if (response.ok) {
                console.log('[PWA] Sincronização executada');
            }
        } catch (error) {
            console.log('[PWA] Erro na sincronização:', error.message);
        }
    }
    
    // Método público para forçar instalação
    forceInstall() {
        if (this.deferredPrompt) {
            this.installApp();
        } else {
            alert('Instalação não disponível neste momento');
        }
    }
}

// CSS para animações
const pwaStyles = `
    @keyframes slideUp {
        from { transform: translateY(100%); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    @keyframes slideDown {
        from { transform: translateY(0); opacity: 1; }
        to { transform: translateY(100%); opacity: 0; }
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateX(-50%) scale(0.8); }
        20% { opacity: 1; transform: translateX(-50%) scale(1); }
        80% { opacity: 1; transform: translateX(-50%) scale(1); }
        100% { opacity: 0; transform: translateX(-50%) scale(0.8); }
    }
    
    .install-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 20px;
    }
    
    .install-content {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .install-icon {
        font-size: 32px;
    }
    
    .install-text strong {
        display: block;
        color: #333;
        font-size: 16px;
    }
    
    .install-text small {
        color: #666;
        font-size: 12px;
    }
    
    .install-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-install {
        background: #667eea;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-install:hover {
        background: #5a6fd8;
        transform: scale(1.05);
    }
    
    .btn-dismiss {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: #999;
        padding: 5px;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-dismiss:hover {
        background: #f0f0f0;
    }
    
    .btn-update {
        background: none;
        border: 1px solid white;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        margin-left: 10px;
        cursor: pointer;
    }
    
    .btn-update:hover {
        background: white;
        color: #28a745;
    }
`;

// Injetar estilos
const styleSheet = document.createElement('style');
styleSheet.textContent = pwaStyles;
document.head.appendChild(styleSheet);

// Inicializar PWA quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.pwaInstaller = new PWAInstaller();
    });
} else {
    window.pwaInstaller = new PWAInstaller();
}

// Exportar para uso global
window.PWAInstaller = PWAInstaller;