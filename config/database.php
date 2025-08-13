<?php
// Configurações do banco de dados - CREDENCIAIS CORRETAS
define('DB_HOST', 'localhost');
define('DB_NAME', 'u906658109_controleacesso');
define('DB_USER', 'u906658109_admin123');  // ✅ Usuário correto
define('DB_PASS', 'OfuturoMerc@do123.');   // ⚠️ Substitua pela senha que você definiu
define('DB_CHARSET', 'utf8mb4');

// Configurações gerais
define('SITE_URL', 'https://ibicsc.com.br/controleacesso/');
define('ADMIN_EMAIL', 'admin@febic.com.br');

// Configurações de QR Code
define('QR_CODE_LENGTH', 11);
define('QR_CODE_START', 1);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>