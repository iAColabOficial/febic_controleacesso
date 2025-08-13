// Service Worker para FEBIC Controle de Acesso
const CACHE_NAME = 'febic-controle-v1.2';
const OFFLINE_URL = '/controleacesso/offline.html';

// Arquivos essenciais para funcionamento offline
const ESSENTIAL_FILES = [
    '/controleacesso/',
    '/controleacesso/index.php',
    '/controleacesso/login.php',
    '/controleacesso/assets/style.css',
    '/controleacesso/manifest.json',
    '/controleacesso/offline.html'
];

// Arquivos da interface de leitura
const READING_FILES = [
    '/controleacesso/controle.php',
    '/controleacesso/leitura.php',
    'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js'
];

// APIs que devem ser executadas offline
const API_ENDPOINTS = [
    '/controleacesso/api/verificar_participante.php',
    '/controleacesso/api/registrar_presenca.php',
    '/controleacesso/api/sincronizar_offline.php'
];

// Instalação do Service Worker
self.addEventListener('install', event => {
    console.log('[SW] Instalando Service Worker...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[SW] Cache criado:', CACHE_NAME);
                
                // Cache dos arquivos essenciais
                return cache.addAll(ESSENTIAL_FILES.concat(READING_FILES))
                    .then(() => {
                        console.log('[SW] Arquivos essenciais em cache');
                        return cache.add(OFFLINE_URL);
                    })
                    .catch(error => {
                        console.warn('[SW] Erro ao cachear alguns arquivos:', error);
                        // Continuar mesmo com erros para não quebrar a instalação
                    });
            })
            .then(() => {
                console.log('[SW] Instalação concluída');
                return self.skipWaiting(); // Ativar imediatamente
            })
    );
});

// Ativação do Service Worker
self.addEventListener('activate', event => {
    console.log('[SW] Ativando Service Worker...');
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Removendo cache antigo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('[SW] Ativação concluída');
            return self.clients.claim(); // Controlar imediatamente
        })
    );
});

// Interceptação de requisições
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Ignorar requisições não HTTP
    if (!url.protocol.startsWith('http')) {
        return;
    }
    
    // Estratégia para diferentes tipos de requisição
    if (request.method === 'GET') {
        event.respondWith(handleGetRequest(request));
    } else if (request.method === 'POST' && isAPIRequest(request.url)) {
        event.respondWith(handleAPIRequest(request));
    }
});

// Gerenciar requisições GET
async function handleGetRequest(request) {
    const url = new URL(request.url);
    
    try {
        // Tentar buscar da rede primeiro (network-first para páginas)
        if (isPageRequest(request.url)) {
            const networkResponse = await fetch(request);
            
            if (networkResponse.ok) {
                // Atualizar cache com versão mais recente
                const cache = await caches.open(CACHE_NAME);
                cache.put(request, networkResponse.clone());
                return networkResponse;
            }
        }
        
        // Para recursos estáticos, tentar cache primeiro
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Se não estiver em cache, buscar da rede
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cachear recursos estáticos
            if (isCacheable(request.url)) {
                const cache = await caches.open(CACHE_NAME);
                cache.put(request, networkResponse.clone());
            }
        }
        
        return networkResponse;
        
    } catch (error) {
        console.log('[SW] Erro na rede:', error.message);
        
        // Tentar cache como fallback
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Se for página e não tiver cache, mostrar página offline
        if (isPageRequest(request.url)) {
            const offlineResponse = await caches.match(OFFLINE_URL);
            return offlineResponse || new Response('Página não disponível offline');
        }
        
        // Para outros recursos, retornar erro
        return new Response('Recurso não disponível offline', { status: 503 });
    }
}

// Gerenciar requisições de API
async function handleAPIRequest(request) {
    try {
        // Tentar executar na rede primeiro
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            return networkResponse;
        }
        
        throw new Error('Resposta da rede não OK');
        
    } catch (error) {
        console.log('[SW] API offline:', request.url);
        
        // Se estiver offline, armazenar para sincronização posterior
        const requestData = await request.clone().json().catch(() => ({}));
        
        // Armazenar requisição offline
        await storeOfflineRequest(request.url, requestData);
        
        // Retornar resposta simulada para manter UX
        return new Response(JSON.stringify({
            success: true,
            offline: true,
            message: 'Dados armazenados para sincronização'
        }), {
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Armazenar requisições offline no IndexedDB
async function storeOfflineRequest(url, data) {
    try {
        const db = await openDB();
        const transaction = db.transaction(['offline_requests'], 'readwrite');
        const store = transaction.objectStore('offline_requests');
        
        await store.add({
            url: url,
            data: data,
            timestamp: new Date().toISOString(),
            id: generateUniqueId()
        });
        
        console.log('[SW] Requisição armazenada para sincronização offline');
    } catch (error) {
        console.error('[SW] Erro ao armazenar requisição offline:', error);
    }
}

// Sincronização em background
self.addEventListener('sync', event => {
    console.log('[SW] Evento de sincronização:', event.tag);
    
    if (event.tag === 'sync-offline-data') {
        event.waitUntil(syncOfflineData());
    }
});

// Sincronizar dados offline
async function syncOfflineData() {
    console.log('[SW] Iniciando sincronização de dados offline...');
    
    try {
        const db = await openDB();
        const transaction = db.transaction(['offline_requests'], 'readonly');
        const store = transaction.objectStore('offline_requests');
        const requests = await store.getAll();
        
        for (const offlineRequest of requests) {
            try {
                const response = await fetch(offlineRequest.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(offlineRequest.data)
                });
                
                if (response.ok) {
                    // Remover da fila offline após sucesso
                    const deleteTransaction = db.transaction(['offline_requests'], 'readwrite');
                    const deleteStore = deleteTransaction.objectStore('offline_requests');
                    await deleteStore.delete(offlineRequest.id);
                    
                    console.log('[SW] Requisição sincronizada:', offlineRequest.id);
                } else {
                    console.warn('[SW] Falha na sincronização:', response.status);
                }
                
            } catch (error) {
                console.warn('[SW] Erro ao sincronizar requisição:', error);
            }
        }
        
        console.log('[SW] Sincronização concluída');
        
    } catch (error) {
        console.error('[SW] Erro na sincronização geral:', error);
    }
}

// Helpers
function isPageRequest(url) {
    return url.includes('.php') || url.endsWith('/controleacesso/');
}

function isAPIRequest(url) {
    return url.includes('/api/');
}

function isCacheable(url) {
    const uncacheable = ['/admin/', '/api/', '?'];
    return !uncacheable.some(pattern => url.includes(pattern));
}

function generateUniqueId() {
    return Date.now() + '-' + Math.random().toString(36).substr(2, 9);
}

// IndexedDB para armazenar dados offline
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('FebicOfflineDB', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = event => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('offline_requests')) {
                const store = db.createObjectStore('offline_requests', { keyPath: 'id' });
                store.createIndex('timestamp', 'timestamp', { unique: false });
                store.createIndex('url', 'url', { unique: false });
            }
            
            if (!db.objectStoreNames.contains('offline_data')) {
                const store = db.createObjectStore('offline_data', { keyPath: 'key' });
                store.createIndex('timestamp', 'timestamp', { unique: false });
            }
        };
    });
}

// Push notifications (futuro)
self.addEventListener('push', event => {
    if (event.data) {
        const data = event.data.json();
        
        const options = {
            body: data.body || 'Nova notificação FEBIC',
            icon: '/controleacesso/assets/icons/icon-192x192.png',
            badge: '/controleacesso/assets/icons/icon-72x72.png',
            tag: 'febic-notification',
            requireInteraction: true,
            actions: [
                {
                    action: 'open',
                    title: 'Abrir',
                    icon: '/controleacesso/assets/icons/icon-72x72.png'
                },
                {
                    action: 'close',
                    title: 'Fechar'
                }
            ]
        };
        
        event.waitUntil(
            self.registration.showNotification(data.title || 'FEBIC', options)
        );
    }
});

// Clique em notificação
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'open' || !event.action) {
        event.waitUntil(
            clients.openWindow('/controleacesso/')
        );
    }
});

console.log('[SW] Service Worker carregado');