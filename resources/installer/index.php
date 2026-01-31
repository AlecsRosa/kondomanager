<?php
/**
 * KondoManager Installer & Updater - v11.2 Deep Cache Cleaner
 * Fixes: Full Laravel Cache Clear (Config, Routes, Views) without shell_exec
 * Data: 30 Gennaio 2026
 */

// ============================================================================
// 1. ENVIRONMENT OPTIMIZATION
// ============================================================================
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { @ob_end_clean(); }
ob_implicit_flush(1);

// FIX 1: Generazione NONCE Globale
$nonce = bin2hex(random_bytes(16));

header('Content-Encoding: none');
header('X-Accel-Buffering: no');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// FIX 2: CSP Header Pulito
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

error_reporting(E_ALL);
ini_set('display_errors', 0);
@set_time_limit(900);
ini_set('memory_limit', '512M');

// ============================================================================
// 2. CONFIGURATION
// ============================================================================
define('PACKAGE_URL', 'https://kondomanager.com/packages/km_v1.8.0-beta.3.zip');
define('PACKAGE_HASH', '0542eea456e2112281d08526ea1aec76e0f92aa04c77a58d1aafe49254dea189');
define('MIN_PHP_VERSION', '8.2.0');
define('APP_VERSION', '1.8.0-beta.3');

define('LOG_FILE', __DIR__ . '/install.log');
define('ZIP_FILE', __DIR__ . '/update_temp.zip');
define('TEMP_DIR', __DIR__ . '/_update_temp_' . time());
define('BACKUP_DIR', __DIR__ . '/_km_safe_zone');
define('BRIDGE_FILE', __DIR__ . '/update_bridge.json'); 

$mode = file_exists(__DIR__ . '/.env') ? 'update' : 'install';

// FIX SESSIONE: Carichiamo il token dal bridge se esiste
$bridgeData = file_exists(BRIDGE_FILE) ? json_decode(file_get_contents(BRIDGE_FILE), true) : [];
$bridgeToken = $bridgeData['security']['token'] ?? null;

// ============================================================================
// 3. LOCALIZATION (IT/EN)
// ============================================================================
$langs = [
    'it' => [
        'title'         => $mode === 'update' ? 'Aggiornamento KondoManager' : 'Installazione KondoManager',
        'welcome'       => $mode === 'update' ? 'Aggiornamento Sistema' : 'Benvenuto in KondoManager',
        'tagline'       => $mode === 'update' ? 'Aggiornamento alla versione v%s' : 'Installazione nuova versione v%s',
        'dont_close'    => 'Non chiudere questa pagina fino al completamento.',
        'error_title'   => 'Impossibile Procedere',
        'ready_msg'     => 'Tutto pronto. Scegli l\'operazione da eseguire.',
        'btn_text'      => $mode === 'update' ? 'Aggiorna ora' : 'Installa ora',
        'progress'      => 'Avanzamento',
        'step_start'    => 'Inizializzazione...',
        'step_down'     => 'Download pacchetto...',
        'step_down_prog'=> 'Download: %s MB / %s MB',
        'step_hash'     => 'Verifica integrità (SHA256)...',
        'step_backup'   => 'Salvataggio dati (Storage & .env)...',
        'step_unzip'    => 'Estrazione sicura...',
        'step_install'  => 'Applicazione aggiornamento...',
        'step_opcache'  => 'Pulizia cache PHP (OPcache)...',
        'step_health'   => 'Verifica integrità post-installazione...',
        'step_htaccess' => 'Aggiornamento regole server (.htaccess)...',
        'step_clean'    => 'Pulizia cache e file temporanei...', // Aggiornato testo
        'step_done'     => 'Operazione completata con successo!',
        'step_rollback' => 'ERRORE RILEVATO! Ripristino backup in corso...',
        'chk_php'       => 'Versione PHP',
        'chk_ext'       => 'Estensioni Richieste',
        'chk_perm'      => 'Permessi di Scrittura',
        'chk_https'     => 'Connessione Sicura (HTTPS)',
        'chk_rewrite'   => 'Modulo Apache Rewrite',
        'chk_disk'      => 'Spazio Disco Disponibile',
        'chk_pkg'       => 'Pacchetto Remoto v%s',
        'chk_ok'        => 'OK',
        'chk_warn'      => 'Assente (Consigliato)',
        'chk_err'       => 'Errore',
        'chk_unknown'   => 'Non verificabile',
        'pkg_ok'        => 'Disponibile',
        'pkg_err'       => 'Non raggiungibile',
        'err_php'       => 'PHP %s+ richiesto (Rilevato: %s)',
        'err_ext'       => 'Estensione mancante: %s',
        'err_write'     => 'Cartella non scrivibile. Imposta permessi 755.',
        'err_disk'      => 'Spazio insufficiente (%s MB liberi, min 100 MB)',
        'err_pkg'       => 'Pacchetto non raggiungibile (HTTP %s)',
        'err_hash_format' => 'Hash configurato non valido (SHA256 richiesto)',
        'err_csrf'      => 'Sessione scaduta. Ricarica la pagina.',
        'err_ratelimit' => 'Troppi tentativi. Attendi 60 secondi.',
        'err_hash_mismatch' => 'ERRORE SICUREZZA: Hash non corrispondente!',
        'err_download'  => 'Errore durante il download: %s',
        'err_zip'       => 'Impossibile aprire l\'archivio ZIP.',
        'err_mkdir'     => 'Impossibile creare cartella: %s',
        'err_copy'      => 'Errore durante la copia del file: %s',
        'err_health'    => 'Health Check fallito: File critici mancanti (%s)',
        'msg_rollback_ok' => 'Backup ripristinato. Il sistema è tornato alla configurazione precedente.',
        'msg_rollback_ko' => 'ROLLBACK FALLITO. Controllare manualmente la cartella _km_safe_zone.',
        'pre_check_hint' => 'Verifica connessione internet, SSL o configurazione hash.',
        'warn_update'   => 'ATTENZIONE: Verranno sovrascritti i file di sistema. Il database e i file caricati verranno preservati.',
    ],
    'en' => [
        'title'         => $mode === 'update' ? 'KondoManager Update' : 'KondoManager Installation',
        'welcome'       => $mode === 'update' ? 'System Update' : 'Welcome to KondoManager',
        'tagline'       => $mode === 'update' ? 'Updating to version v%s' : 'Installing new version v%s',
        'dont_close'    => 'Do not close this page until completion.',
        'error_title'   => 'Cannot Proceed',
        'ready_msg'     => 'Ready. Please select an action.',
        'btn_text'      => $mode === 'update' ? 'Update Now' : 'Install Now',
        'progress'      => 'Progress',
        'step_start'    => 'Initializing...',
        'step_down'     => 'Downloading package...',
        'step_down_prog'=> 'Download: %s MB / %s MB',
        'step_hash'     => 'Integrity check (SHA256)...',
        'step_backup'   => 'Backing up data (Storage & .env)...',
        'step_unzip'    => 'Safe extraction...',
        'step_install'  => 'Applying update...',
        'step_opcache'  => 'Clearing PHP cache...',
        'step_health'   => 'Post-installation Health Check...',
        'step_htaccess' => 'Updating server rules (.htaccess)...',
        'step_clean'    => 'Cleaning cache & temp files...', // Updated text
        'step_done'     => 'Operation completed successfully!',
        'step_rollback' => 'ERROR DETECTED! Restoring backup...',
        'chk_php'       => 'PHP Version',
        'chk_ext'       => 'Required Extensions',
        'chk_perm'      => 'Write Permissions',
        'chk_https'     => 'Secure Connection (HTTPS)',
        'chk_rewrite'   => 'Apache Rewrite Module',
        'chk_disk'      => 'Available Disk Space',
        'chk_pkg'       => 'Remote Package v%s',
        'chk_ok'        => 'OK',
        'chk_warn'      => 'Missing (Recommended)',
        'chk_err'       => 'Error',
        'chk_unknown'   => 'Unknown',
        'pkg_ok'        => 'Available',
        'pkg_err'       => 'Not reachable',
        'err_php'       => 'PHP %s+ required (Detected: %s)',
        'err_ext'       => 'Missing extension: %s',
        'err_write'     => 'Folder not writable. Set permissions to 755.',
        'err_disk'      => 'Insufficient disk space (%s MB free, min 100 MB)',
        'err_pkg'       => 'Package not reachable (HTTP %s)',
        'err_hash_format' => 'Invalid hash configuration (SHA256 required)',
        'err_csrf'      => 'Session expired. Please reload.',
        'err_ratelimit' => 'Too many attempts. Wait 60 seconds.',
        'err_hash_mismatch' => 'SECURITY ERROR: Hash mismatch!',
        'err_download'  => 'Download error: %s',
        'err_zip'       => 'Cannot open ZIP archive.',
        'err_mkdir'     => 'Cannot create folder: %s',
        'err_copy'      => 'File copy error: %s',
        'err_health'    => 'Health Check failed: Critical files missing (%s)',
        'msg_rollback_ok' => 'Backup restored. System reverted to previous configuration.',
        'msg_rollback_ko' => 'ROLLBACK FAILED. Please check _km_safe_zone manually.',
        'pre_check_hint' => 'Check internet connection, SSL or hash configuration.',
        'warn_update'   => 'WARNING: System files will be overwritten. Database and uploads will be preserved.',
    ]
];

function getLang() {
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    return strpos(strtolower($lang), 'it') === 0 ? 'it' : 'en';
}

function t($key, ...$args) {
    global $langs;
    $text = $langs[getLang()][$key] ?? $key;
    if (empty($args) && strpos($text, '%s') !== false) {
        $args = [APP_VERSION];
    }
    return vsprintf($text, $args);
}

// ============================================================================
// 4. HELPER FUNCTIONS
// ============================================================================
function logTech($msg) {
    @file_put_contents(LOG_FILE, '[' . date('c') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

function safeRmdir($dir) {
    if (!is_dir($dir)) return false;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $op = $file->isDir() ? 'rmdir' : 'unlink';
        @$op($file->getRealPath());
    }
    return @rmdir($dir);
}

function performRollback() {
    logTech("ROLLBACK: Avvio procedura...");
    if (!is_dir(BACKUP_DIR)) return false;

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BACKUP_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        $relativePath = substr($item->getPathname(), strlen(BACKUP_DIR) + 1);
        $targetPath = __DIR__ . '/' . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($targetPath)) @mkdir($targetPath, 0755, true);
        } else {
            @copy($item->getPathname(), $targetPath);
        }
    }
    return true;
}

function formatBytes($bytes, $precision = 2) { 
    $units = ['B', 'KB', 'MB', 'GB', 'TB']; 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

// FIX 2: FUNZIONE GLOBALE CON FLUSH
function sendProgress($pct, $msg, $status='current', $replace=false) {
    global $nonce;
    $pct = round($pct);
    $msg = addslashes($msg);
    $repStr = $replace ? 'true' : 'false';
    echo "<script nonce='{$nonce}'>updateProgress({$pct}, '{$msg}', '{$status}', {$repStr});</script>";
    echo str_repeat(' ', 4096); 
    if (ob_get_level() > 0) ob_flush();
    flush();
}

session_start();

// ============================================================================
// 6. CSS STYLES
// ============================================================================

$css = <<<CSS
:root { --primary: #0f172a; --danger: #dc2626; --success: #16a34a; --warning: #f59e0b; --info: #3b82f6; --bg: #f8fafc; --card: #ffffff; --text: #334155; }
body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; margin: 0; }
.installer-card { background: var(--card); width: 100%; max-width: 600px; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
.header { text-align: center; margin-bottom: 2rem; }
.logo-svg { width: 3.5rem; height: 3.5rem; margin-bottom: 1rem; }
h1 { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 0.5rem 0; }
.tagline { color: #64748b; font-size: 0.95rem; line-height: 1.5; }
.req-box { margin: 1.5rem 0; border: 1px solid #f1f5f9; border-radius: 8px; overflow: hidden; }
.req-item { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; }
.req-item:last-child { border-bottom: none; }
.status-ok { color: var(--success); font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.status-warn { color: var(--warning); font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.status-err { color: var(--danger); font-weight: 600; }
.update-alert { background: #eff6ff; border-left: 3px solid var(--info); color: #1e40af; padding: 0.75rem; font-size: 0.85rem; margin-bottom: 1.5rem; }
.error-alert { background: #fef2f2; border-left: 3px solid var(--danger); color: #b91c1c; padding: 0.75rem; font-size: 0.85rem; margin-bottom: 1.5rem; }
.progress-container { margin: 2rem 0; }
.progress-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
.progress-track { background: #f1f5f9; height: 10px; border-radius: 10px; overflow: hidden; }
.progress-fill { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.4s ease; }
.progress-fill.error { background: linear-gradient(90deg, #dc2626 0%, #ef4444 100%); }
.log-window { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 0.85rem; height: 200px; overflow-y: auto; border: 1px solid #334155; }
.log-entry { margin-bottom: 4px; display: flex; gap: 8px; }
.icon-ok { color: #4ade80; } .icon-err { color: #f87171; }
.btn { display: flex; width: 100%; justify-content: center; padding: 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: opacity 0.2s; text-decoration: none; }
.btn:disabled { background: #cbd5e1 !important; color: #94a3b8 !important; cursor: not-allowed !important; opacity: 0.6; }
.btn:hover:not(:disabled) { opacity: 0.9; }
a { color: #3b82f6; text-decoration: none; font-weight: bold; }
CSS;

// ============================================================================
// PHASE 1: PRE-FLIGHT CHECKS (GET Request)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (empty($_SESSION['token'])) $_SESSION['token'] = bin2hex(random_bytes(32));

    $errors = [];
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    $hasRewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : null;
    $freeSpaceMB = disk_free_space(__DIR__);

    if (!version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=')) $errors[] = t('err_php', MIN_PHP_VERSION, PHP_VERSION);
    $requiredExt = ['zip', 'curl', 'openssl', 'mbstring', 'fileinfo'];
    foreach ($requiredExt as $ext) if (!extension_loaded($ext)) $errors[] = t('err_ext', $ext);
    if (!is_writable(__DIR__)) $errors[] = t('err_write');
    if ($freeSpaceMB < 100 * 1024 * 1024) $errors[] = t('err_disk', formatBytes($freeSpaceMB));

    $pkgStatus = 'unknown';
    $preCheckError = '';
    
    if (function_exists('curl_init')) {
        $ch = curl_init(PACKAGE_URL);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true 
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) {
            $pkgStatus = 'ok';
        } else {
            $errors[] = t('err_pkg', $httpCode);
            $preCheckError = t('err_pkg', $httpCode);
        }
    }

    if (!preg_match('/^[a-f0-9]{64}$/i', PACKAGE_HASH)) {
        $errors[] = t('err_hash_format');
        $preCheckError = t('err_hash_format');
    }

    $allOk = empty($errors);
?>
<!DOCTYPE html>
<html lang="<?= getLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('title') ?></title>
    <style><?= $css ?></style>
</head>
<body>
    <div class="installer-card">
        <div class="header">
            <svg class="logo-svg" viewBox="0 0 16 16">
                <circle cx="8" cy="8" r="8" fill="#0f172a"/>
                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="8" fill="white" font-weight="bold">Km</text>
            </svg>
            <h1><?= t('welcome') ?></h1>
            <p class="tagline"><?= sprintf(t('tagline'), APP_VERSION) ?></p>
        </div>
        
        <?php if (!$allOk): ?>
            <div class="req-box" style="border-color: var(--danger);">
                <div style="padding:1rem; background:#fef2f2; color:#b91c1c; font-weight:600; text-align:center;">
                    ⚠️ <?= t('error_title') ?>
                </div>
                <?php foreach ($errors as $e): ?>
                    <div class="req-item">
                        <span><?= htmlspecialchars($e) ?></span>
                        <span class="status-err">✕</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn" disabled><?= t('btn_text') ?></button>
        <?php else: ?>
            <div class="req-box">
                <div class="req-item">
                    <span><?= t('chk_php') ?></span>
                    <span class="status-ok">✓ <?= PHP_VERSION ?></span>
                </div>
                <div class="req-item">
                    <span><?= t('chk_ext') ?></span>
                    <span class="status-ok">✓ <?= t('chk_ok') ?></span>
                </div>
                <div class="req-item">
                    <span><?= t('chk_perm') ?></span>
                    <span class="status-ok">✓ <?= t('chk_ok') ?></span>
                </div>
                <div class="req-item">
                    <span><?= t('chk_disk') ?></span>
                    <span class="status-ok">✓ <?= formatBytes($freeSpaceMB) ?></span>
                </div>
                <div class="req-item">
                    <span><?= t('chk_https') ?></span>
                    <?php if($isHttps): ?>
                        <span class="status-ok">✓ <?= t('chk_ok') ?></span>
                    <?php else: ?>
                        <span class="status-warn">⚠️ <?= t('chk_warn') ?></span>
                    <?php endif; ?>
                </div>
                <div class="req-item">
                    <span><?= t('chk_rewrite') ?></span>
                    <?php if($hasRewrite === true): ?>
                        <span class="status-ok">✓ <?= t('chk_ok') ?></span>
                    <?php elseif($hasRewrite === null): ?>
                        <span class="status-warn">⚠️ <?= t('chk_unknown') ?></span>
                    <?php else: ?>
                        <span class="status-warn">⚠️ <?= t('chk_warn') ?></span>
                    <?php endif; ?>
                </div>
                <div class="req-item">
                    <span><?= t('chk_pkg') ?></span>
                    <?php if($pkgStatus === 'ok'): ?>
                        <span class="status-ok">✓ <?= t('pkg_ok') ?></span>
                    <?php else: ?>
                        <span class="status-err">✕ <?= t('pkg_err') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($mode === 'update'): ?>
                <div class="update-alert"><?= t('warn_update') ?></div>
            <?php else: ?>
                <p style="text-align:center; color:#64748b; margin-bottom:1.5rem; font-size:0.9rem;">
                    <?= t('ready_msg') ?>
                </p>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                <button type="submit" class="btn"><?= t('btn_text') ?></button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
<?php exit; }

// ============================================================================
// PHASE 2: INSTALLATION PROCESS (POST Request)
// ============================================================================
?>
<!DOCTYPE html>
<html lang="<?= getLang() ?>">
<head>
<meta charset="UTF-8">
<title><?= t('title') ?></title>
<style><?= $css ?></style>
</head>
<body>
    <div class="installer-card">
        <div class="header">
            <svg class="logo-svg" viewBox="0 0 16 16">
                <circle cx="8" cy="8" r="8" fill="#0f172a"/>
                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="8" fill="white" font-weight="bold">Km</text>
            </svg>
            <h1><?= t('installing') ?></h1>
            <p class="tagline"><?= t('dont_close') ?></p>
        </div>
        <div class="progress-container">
            <div class="progress-header">
                <span><?= t('progress') ?></span>
                <span id="pct-text">0%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="progress-bar"></div>
            </div>
        </div>
        <div class="log-window" id="log-box"></div>
        <div id="final-msg" style="display:none; text-align:center; margin-top:1.5rem;"></div>
    </div>

<script nonce="<?= $nonce ?>">
function updateProgress(p, m, s, replaceLast = false) {
    const bar = document.getElementById('progress-bar');
    const pctText = document.getElementById('pct-text');
    const log = document.getElementById('log-box');
    
    bar.style.width = p + '%';
    pctText.innerText = Math.round(p) + '%';
    
    if (replaceLast && log.lastElementChild) {
        log.lastElementChild.innerHTML = '• ' + m;
    } else {
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        
        let icon = '•';
        if (s === 'success') icon = '<span class="icon-ok">✓</span>';
        if (s === 'error') icon = '<span class="icon-err">✕</span>';
        
        entry.innerHTML = icon + ' ' + m;
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    }
    
    if (s === 'error') {
        bar.classList.add('error');
    }
}
function showFinal(url) {
    const el = document.getElementById('final-msg');
    el.style.display = 'block';
    el.innerHTML = '<p style="color:#16a34a; font-weight:bold;">Operazione completata.</p><p>Reindirizzamento in corso...<br>Se non vieni reindirizzato, <a href="'+url+'">clicca qui</a>.</p>';
}
</script>

<?php
flush();

// VARIABILE PER GESTIRE IL ROLLBACK
$backupCreated = false;

try {
    // FIX SESSIONE: CONTROLLO TOKEN IBRIDO (Sessione o Bridge)
    $postedToken = $_POST['token'] ?? '';
    $sessionToken = $_SESSION['token'] ?? '';
    $isValid = ($postedToken === $sessionToken);
    
    // Se non corrisponde alla sessione, controlliamo se è il token del bridge (Scenario Update)
    if (!$isValid && isset($bridgeToken) && $bridgeToken) {
        if ($postedToken === $bridgeToken) $isValid = true;
    }

    if (!$isValid) throw new Exception(t('err_csrf'));

    // 1. RATE LIMIT
    if (isset($_SESSION['last_run']) && time() - $_SESSION['last_run'] < 60) throw new Exception(t('err_ratelimit'));
    $_SESSION['last_run'] = time();

    sendProgress(5, t('step_start'));

    // 2. Download
    sendProgress(10, t('step_down'));
    $fp = fopen(ZIP_FILE, 'wb');
    $ch = curl_init(PACKAGE_URL);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 600,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function($r, $ds, $dld) {
            if ($ds > 0) {
                static $last_mb = 0;
                $current_mb = $dld / 1048576;
                if ($current_mb - $last_mb >= 0.5) {
                    $pct = 10 + ($dld / $ds) * 30;
                    $msg = sprintf("Download: %.1f MB / %.1f MB", $current_mb, $ds/1048576);
                    global $nonce; // Necessario per la closure
                    echo "<script nonce='$nonce'>updateProgress($pct, '$msg', 'current', true);</script>";
                    echo str_repeat(' ', 4096); flush();
                    $last_mb = $current_mb;
                }
            }
        }
    ]);
    curl_exec($ch);
    if (curl_errno($ch)) throw new Exception(t('err_download', curl_error($ch)));
    fclose($fp);
    curl_close($ch);

    // 3. Hash Check
    sendProgress(40, t('step_hash'));
    $calculated = hash_file('sha256', ZIP_FILE);
    if (!hash_equals(PACKAGE_HASH, $calculated)) {
        @unlink(ZIP_FILE);
        throw new Exception(t('err_hash_mismatch'));
    }

    // 4. Backup (Update Mode Only)
    if ($mode === 'update') {
        sendProgress(50, t('step_backup'));
        @mkdir(BACKUP_DIR, 0755, true);
        foreach (['.env', 'config/app.php', 'public/index.php', 'composer.json'] as $f) {
            $src = __DIR__ . '/' . $f;
            if (file_exists($src)) {
                $dest = BACKUP_DIR . '/' . $f;
                @mkdir(dirname($dest), 0755, true);
                @copy($src, $dest);
            }
        }
        $backupCreated = true;
    }

    // 5. Unzip
    sendProgress(60, t('step_unzip'));
    $zip = new ZipArchive;
    if ($zip->open(ZIP_FILE) !== true) throw new Exception(t('err_zip'));
    if (!@mkdir(TEMP_DIR, 0755, true)) throw new Exception(t('err_mkdir', TEMP_DIR));
    $zip->extractTo(TEMP_DIR);
    $zip->close();

    // 6. Install (Atomic-ish copy)
    sendProgress(75, t('step_install'));
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(TEMP_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $rel = substr($item->getPathname(), strlen(TEMP_DIR) + 1);
        if (strpos($rel, '__MACOSX') === 0) continue;
        $target = __DIR__ . '/' . $rel;
        
        if ($item->isDir()) {
            if (!is_dir($target)) @mkdir($target, 0755, true);
        } else {
            if (file_exists($target)) @unlink($target);
            if (!@copy($item->getPathname(), $target)) throw new Exception(t('err_copy', $rel));
            @chmod($target, 0644); // Shared Hosting Friendly
        }
    }

    // 7. OPcache Reset (Critico per evitare esecuzione codice vecchio)
    sendProgress(85, t('step_opcache'));
    if (function_exists('opcache_reset')) {
        @opcache_reset();
        logTech("OPcache reset performed.");
    }

    // 8. Health Check (Verifica fisica)
    sendProgress(88, t('step_health'));
    if (!file_exists(__DIR__ . '/artisan') || !file_exists(__DIR__ . '/public/index.php')) {
        throw new Exception(t('err_health', 'artisan, public/index.php'));
    }

    // 9. .htaccess (Esattamente quello richiesto)
    sendProgress(90, t('step_htaccess'));
$htaccess = <<<HTA
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # 1. Priorità installer
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^index\.php$ - [L]

    # 2. Authorization
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # 3. Trailing slash
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # 4. Blocchi sicurezza
    RewriteRule ^(storage|public/uploads)/.*\.php$ - [F,L,NC]
    RewriteRule ^(storage|bootstrap|vendor|config)/.* - [F,L,NC]

    # 5. Routing Laravel
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]

    # 6. Fallback
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ public/index.php [L]
</IfModule>

<FilesMatch "(^\.env|^composer\.(json|lock)|^\.git|\.gitignore|\.zip|\.log|\.sql|\.bak|\.old|\.installed)">
    Require all denied
</FilesMatch>
HTA;
    file_put_contents(__DIR__ . '/.htaccess', $htaccess);
    @chmod(__DIR__ . '/.htaccess', 0644);

    // 10. Cleanup & Self-destruct (CACHE CLEAR FIX)
    sendProgress(95, t('step_clean'));
    safeRmdir(TEMP_DIR);
    @unlink(ZIP_FILE);
    
    // --- FIX CACHE AVANZATO (PHP NATIVE) ---
    // Cancella config, route e tutte le cache di Laravel senza bisogno di shell_exec
    $cacheDir = __DIR__ . '/bootstrap/cache';
    $files = ['config.php', 'routes.php', 'packages.php', 'services.php'];
    foreach ($files as $f) {
        if (file_exists("$cacheDir/$f")) @unlink("$cacheDir/$f");
    }
    // Pulisce anche le view compilate per evitare conflitti UI
    $viewDir = __DIR__ . '/storage/framework/views';
    if (is_dir($viewDir)) {
        foreach (glob("$viewDir/*.php") as $v) @unlink($v);
    }
    // --------------------------------------
    
    if ($backupCreated) safeRmdir(BACKUP_DIR);

    // Se Install Mode -> Crea .env
    if ($mode === 'install' && !file_exists('.env') && file_exists('.env.example')) {
        copy('.env.example', '.env');
        $key = 'base64:' . base64_encode(random_bytes(32));
        $content = file_get_contents('.env');
        file_put_contents('.env', preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $content));
    }

    register_shutdown_function(function() {
        if (!@unlink(__FILE__)) {
            @file_put_contents(__FILE__, "<?php header('HTTP/1.1 410 Gone'); ?>");
        }
    });

    // 11. Final Success
    sendProgress(100, t('step_done'), 'success');
    
    $redirect = ($mode === 'update') ? '/system/upgrade/finalize' : '/install';
    
    // FIX 4: ADDED NONCE TO FINAL SCRIPT
    echo "<script nonce='{$nonce}'>
        showFinal('$redirect');
        setTimeout(() => { window.location.href = '$redirect'; }, 2000);
    </script>";

} catch (Exception $e) {
    logTech("ERROR: " . $e->getMessage());
    $msg = addslashes($e->getMessage());
    // FIX 5: Use sendProgress instead of echo script for errors
    sendProgress(100, 'ERRORE: ' . $msg, 'error');
    
    // --- GESTIONE ROLLBACK ---
    if ($backupCreated) {
        sendProgress(100, t('step_rollback'), 'error');
        if (performRollback()) {
            echo "<div style='color:#f59e0b; text-align:center; font-weight:bold; margin-top:10px;'>" . t('msg_rollback_ok') . "</div>";
        } else {
            echo "<div style='color:#ef4444; text-align:center; font-weight:bold; margin-top:10px;'>" . t('msg_rollback_ko') . "</div>";
        }
    }
    
    @unlink(ZIP_FILE);
    safeRmdir(TEMP_DIR);
}
?>
</body>
</html>