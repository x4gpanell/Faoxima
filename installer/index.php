<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('default_charset', 'UTF-8');

$uPOST = sanitizeInput($_POST);
$rootDirectory = dirname(__DIR__).'/';
$configDirectory = $rootDirectory.'config.php';
$tablesDirectory = $rootDirectory.'table.php';
if(!file_exists($configDirectory) || !file_exists($tablesDirectory)) {
    $ERROR[] = "فایل های پروژه ناقص هستند.";
    $ERROR[] = "فایل های پروژه را مجددا دانلود و بارگذاری کنید (<a href='https://github.com/Mmd-Amir/Faoxima/releases/'>‎🌐 Github</a>)";
}
if(phpversion() < 8.2){
    $ERROR[] = "نسخه PHP شما باید حداقل 8.2 باشد.";
    $ERROR[] = "نسخه فعلی: ".phpversion();
    $ERROR[] = "لطفا نسخه PHP خود را به 8.2 یا بالاتر ارتقا دهید.";
}

$tempPath = dirname(dirname($_SERVER['SCRIPT_NAME']));

$tempPath = str_replace('//', '/', '/' . trim($tempPath, '/'));
$webAddress = rtrim($_SERVER['HTTP_HOST'] . $tempPath, '/') . '/';
$success = false;
$tgBot = [];
$botFirstMessage = '';
// Only the simple cPanel-host installation is supported. The migration
// (migrate_free_to_pro) and dedicated-server (VPS) install paths were removed,
// so these are hard-forced regardless of any posted value.
$installType = 'simple';
$serverType  = 'cpanel';
$hasDbBackup = 'no';
$currentStep = 1;
$installFieldTotal = 7;
$currentInstallField = isset($uPOST['current_install_field']) ? (int)$uPOST['current_install_field'] : 1;
$currentStep = 1;
$currentInstallField = max(1, min($installFieldTotal, $currentInstallField));


function isHttps() {
    return (
        ($_SERVER['REQUEST_SCHEME'] ?? 'http') === 'https' ||
        ($_SERVER['HTTPS'] ?? 'off') === 'on' ||
        ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    );
}
if(isset($uPOST['submit']) && $uPOST['submit']) {
    $ERROR = [];
    $SUCCESS[] = "✅ ربات با موفقیت نصب شد !";
    $rawConfigData = file_get_contents($configDirectory);
    $tgAdminId = $uPOST['admin_id'];
    $tgBotToken = $uPOST['tg_bot_token'];
    $dbInfo['host'] = 'mysql.railway.internal';
    $dbInfo['name'] = $uPOST['database_name'];
    $dbInfo['username'] = $uPOST['database_username'];
    $dbInfo['password'] = $uPOST['database_password'];
    $inputUrl = $uPOST['bot_address_webhook'] ?? $webAddress . '/index.php';
    $document = normalizeDomainAddress($inputUrl);
    if ($document === null) {
        $ERROR[] = 'آدرس ارائه شده برای ربات نامعتبر است.';
    }
    if(!isHttps()) {
        $ERROR[] = 'برای فعال سازی ربات تلگرام نیازمند فعال بودن SSL (https) هستید';
        $ERROR[] = '<i>اگر از فعال بودن SSL مطمئن هستید، سرور پشت proxy/CDN (مثل Cloudflare) است – headers را در cPanel چک کنید یا با https مستقیم باز کنید.</i>';
        $sslLink = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        $ERROR[] = '<a href="' . $sslLink . '">' . $sslLink . '</a>';
    }
    $isValidToken = isValidTelegramToken($tgBotToken);
    if(!$isValidToken) {
        $ERROR[] = "توکن ربات صحیح نمی باشد.";
    }
    if (!isValidTelegramId($tgAdminId)) {
        $ERROR[] = "آیدی عددی ادمین نامعتبر است.";
    }
    if($isValidToken) {
        $tgBot['details'] = getContents("https://api.telegram.org/bot".$tgBotToken."/getMe");
        if($tgBot['details']['ok'] == false) {
            $ERROR[] = "توکن ربات را بررسی کنید. <i>عدم توانایی دریافت جزئیات ربات.</i>";
        }
        else {
            $tgBot['recognition'] = getContents("https://api.telegram.org/bot".$tgBotToken."/getChat?chat_id=".$tgAdminId);
            if($tgBot['recognition']['ok'] == false) {
                $ERROR[] = "<b>عدم شناسایی مدیر ربات:</b>";
                $ERROR[] = "ابتدا ربات را فعال/استارت کنید با اکانت که میخواهید مدیر اصلی ربات باشد.";
                $ERROR[] = "<a href='https://t.me/".$tgBot['details']['result']['username']."'>@".$tgBot['details']['result']['username']."</a>";
            }
        }
    }
    try {
        $dsn = "mysql:host=" . $dbInfo['host'] . ";dbname=" . $dbInfo['name'] . ";charset=utf8mb4";
        $pdo = new PDO($dsn, $dbInfo['username'], $dbInfo['password']);
        $SUCCESS[] = "✅ اتصال به دیتابیس موفقیت آمیز بود!";
    }
    catch (\PDOException $e) {
        $ERROR[] = "❌ عدم اتصال به دیتابیس: ";
        $ERROR[] = "اطلاعات ورودی را بررسی کنید.";
        $ERROR[] = "<code>".$e->getMessage()."</code>";
    }
    if(empty($ERROR)) {
        $replacements = [
            '{database_name}' => $dbInfo['name'],
            '{username_db}' => $dbInfo['username'],
            '{password_db}' => $dbInfo['password'],
            '{API_KEY}' => $tgBotToken,
            '{admin_number}' => $tgAdminId,
            '{domain_name}' => $document['address'],
            '{username_bot}' => $tgBot['details']['result']['username']
        ];
        $replacementCount = 0;
        $newConfigData = updateConfigValues($rawConfigData, $replacements, $replacementCount);
        if($replacementCount === 0 || file_put_contents($configDirectory,$newConfigData) === false) {
            $ERROR[] = '✏️❌ خطا در زمان بازنویسی اطلاعات فایل اصلی ربات';
            $ERROR[] = "فایل های پروژه را مجددا دانلود و بارگذاری کنید (<a href='https://github.com/Mmd-Amir/Faoxima/releases/'>‎🌐 Github</a>)";
    }
        else {
            $baseAddress = rtrim($document['address'], '/');

            $tableResult = getContents("https://".$baseAddress."/table.php");
            $SUCCESS[] = "✅ جداول دیتابیس ایجاد/بروزرسانی شد";
            ensureAdminRecord($dbInfo, $tgAdminId);
            $SUCCESS[] = "✅ Webhook تنظیم شد";
            $botFirstMessage = "\n[🤖] شما به عنوان ادمین معرفی شدید.";
            $telegramMessage = urlencode(' '.$SUCCESS[0].$botFirstMessage);
            $replyMarkup = urlencode(json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '⚙️ شروع ربات ', 'callback_data' => 'start']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));
            getContents("https://api.telegram.org/bot{$tgBotToken}/sendMessage?chat_id={$tgAdminId}&text={$telegramMessage}&reply_markup={$replyMarkup}");
            $success = true;

            scheduleInstallerSelfDelete(__DIR__);
        }
    }
}

function ensureAdminRecord($dbInfo, $adminNumber) {
    try {
        $connect = @new mysqli('mysql.railway.internal', $dbInfo['username'], $dbInfo['password'], $dbInfo['name']);
        if ($connect->connect_error) {
            return false;
        }
        $connect->set_charset("utf8mb4");
        $tableCheck = $connect->query("SHOW TABLES LIKE 'admin'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $connect->query("SELECT COUNT(*) as cnt FROM admin");
            $countRow = $result ? $result->fetch_assoc() : ['cnt' => 0];
            $count = (int)($countRow['cnt'] ?? 0);
            if ($count == 0) {
                $stmt = $connect->prepare("INSERT INTO `admin` (`id_admin`, `username`, `password`, `rule`) VALUES (?, 'admin', '14e9eab674', 'administrator')");
                if ($stmt) {
                    $stmt->bind_param('s', $adminNumber);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $adminNumberEscaped = $connect->real_escape_string($adminNumber);
                $connect->query("UPDATE `admin` SET `id_admin` = '{$adminNumberEscaped}', `username` = 'admin', `password` = '14e9eab674', `rule` = 'administrator' LIMIT 1");
            }
        } else {
            $connect->query("CREATE TABLE `admin` (
              `id_admin` varchar(500) NOT NULL,
              `username` varchar(1000) NOT NULL,
              `password` varchar(1000) NOT NULL,
              `rule` varchar(500) NOT NULL,
              PRIMARY KEY (`id_admin`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci");
            $stmt = $connect->prepare("INSERT INTO `admin` (`id_admin`, `username`, `password`, `rule`) VALUES (?, 'admin', '14e9eab674', 'administrator')");
            if ($stmt) {
                $stmt->bind_param('s', $adminNumber);
                $stmt->execute();
                $stmt->close();
            }
        }
        $connect->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa" data-theme="red">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c0a09">
    <title>نصب خودکار ربات فاکسیما</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>

        @font-face {
            font-family: 'AradMediumDots';
            src: url('./fonts/Arad-MediumDots2.ttf') format('truetype');
            font-weight: 400 700;
            font-style: normal;
            font-display: swap;
        }


        :root {
            --bg-base:        #0c0a09;
            --bg-surface:     #1c1917;
            --bg-elevated:    #292524;
            --bg-input:       #16130f;
            --border-subtle:  rgba(255, 255, 255, 0.08);
            --border-strong:  rgba(255, 255, 255, 0.18);
            --text-primary:   #fafaf9;
            --text-secondary: #d6d3d1;
            --text-muted:     #a8a29e;
            --shadow-glow:    0 0 0 1px rgba(255, 255, 255, 0.05), 0 20px 60px -20px rgba(0, 0, 0, 0.5);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="red"]    { --accent: #ef4444; --accent-soft: rgba(239,  68,  68, 0.15); --accent-strong: #dc2626; --accent-shadow: rgba(239,  68,  68, 0.45); }
        [data-theme="blue"]   { --accent: #3b82f6; --accent-soft: rgba( 59, 130, 246, 0.15); --accent-strong: #2563eb; --accent-shadow: rgba( 59, 130, 246, 0.45); }
        [data-theme="purple"] { --accent: #a855f7; --accent-soft: rgba(168,  85, 247, 0.15); --accent-strong: #9333ea; --accent-shadow: rgba(168,  85, 247, 0.45); }
        [data-theme="green"]  { --accent: #22c55e; --accent-soft: rgba( 34, 197,  94, 0.15); --accent-strong: #16a34a; --accent-shadow: rgba( 34, 197,  94, 0.45); }
        [data-theme="orange"] { --accent: #f97316; --accent-soft: rgba(249, 115,  22, 0.15); --accent-strong: #ea580c; --accent-shadow: rgba(249, 115,  22, 0.45); }


        *, *::before, *::after { box-sizing: border-box; }
        * { margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body {
            font-family: 'AradMediumDots', 'Vazirmatn', system-ui, -apple-system, sans-serif;
            background: var(--bg-base);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            font-size: 15px;
            background-image:
                radial-gradient(at 20% 0%, var(--accent-soft) 0px, transparent 50%),
                radial-gradient(at 80% 100%, var(--accent-soft) 0px, transparent 50%);
            background-attachment: fixed;
        }
        code, kbd, pre { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.9em; }


        .app-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .app-header {
            border-bottom: 1px solid var(--border-subtle);
            background: rgba(12, 10, 9, 0.7);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .app-header-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-primary);
            text-decoration: none;
        }
        .brand-mark {
            width: 36px; height: 36px;
            display: grid; place-items: center;
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            border-radius: var(--radius-sm);
            color: var(--accent);
        }
        .brand-mark svg { width: 18px; height: 18px; }
        .app-main {
            flex: 1;
            max-width: 880px;
            width: 100%;
            margin: 0 auto;
            padding: 32px 24px 80px;
        }
        .app-footer {
            border-top: 1px solid var(--border-subtle);
            padding: 20px 24px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }
        .app-footer a {
            color: var(--accent);
            text-decoration: none;
            transition: color var(--transition);
        }
        .app-footer a:hover { color: var(--accent-strong); }


        .theme-switcher {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
        }
        .theme-swatch {
            width: 22px; height: 22px;
            border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer;
            transition: transform var(--transition), border-color var(--transition);
            position: relative;
        }
        .theme-swatch:hover { transform: scale(1.15); }
        .theme-swatch.is-active { border-color: var(--text-primary); }
        .theme-swatch[data-theme="red"]    { background: #ef4444; }
        .theme-swatch[data-theme="blue"]   { background: #3b82f6; }
        .theme-swatch[data-theme="purple"] { background: #a855f7; }
        .theme-swatch[data-theme="green"]  { background: #22c55e; }
        .theme-swatch[data-theme="orange"] { background: #f97316; }


        .hero {
            text-align: center;
            margin-bottom: 40px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            border-radius: 999px;
            color: var(--accent);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
        }
        .hero-badge .pulse-dot {
            width: 8px; height: 8px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.6; transform: scale(0.85); }
        }
        .hero h1 {
            font-size: clamp(28px, 5vw, 42px);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        .hero h1 .accent { color: var(--accent); }
        .hero p {
            color: var(--text-secondary);
            font-size: 16px;
            max-width: 560px;
            margin: 0 auto;
        }


        .alert {
            display: flex;
            gap: 12px;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            border: 1px solid;
            font-size: 14px;
            line-height: 1.7;
        }
        .alert svg { flex-shrink: 0; width: 20px; height: 20px; margin-top: 2px; }
        .alert-danger {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }
        .alert-danger svg { color: #ef4444; }
        .alert-success {
            background: rgba(34, 197, 94, 0.08);
            border-color: rgba(34, 197, 94, 0.4);
            color: #86efac;
        }
        .alert-success svg { color: #22c55e; }
        .alert a { color: inherit; text-decoration: underline; font-weight: 600; }


        .stepper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
            transition: all var(--transition);
        }
        .step-num {
            width: 24px; height: 24px;
            background: var(--bg-elevated);
            border-radius: 50%;
            display: grid; place-items: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            transition: all var(--transition);
        }
        .step-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            transition: color var(--transition);
        }
        .step.is-active {
            background: var(--accent-soft);
            border-color: var(--accent);
            box-shadow: 0 0 24px var(--accent-shadow);
        }
        .step.is-active .step-num {
            background: var(--accent);
            color: var(--bg-base);
        }
        .step.is-active .step-label { color: var(--accent); }
        .step.is-completed .step-num {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .step.is-completed .step-label { color: var(--text-secondary); }
        .step-divider {
            width: 24px;
            height: 1px;
            background: var(--border-subtle);
        }


        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 28px;
            margin-bottom: 20px;
        }
        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        .card-title svg {
            width: 22px; height: 22px;
            color: var(--accent);
        }


        .step-section { display: none; }
        .step-section.is-active {
            display: block;
            animation: fadeSlide 280ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }


        .choice-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
        }
        .choice-card {
            position: relative;
            padding: 20px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition);
        }
        .choice-card:hover {
            border-color: var(--border-strong);
            transform: translateY(-2px);
        }
        .choice-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .choice-card.is-active {
            border-color: var(--accent);
            background: var(--accent-soft);
            box-shadow: 0 0 0 1px var(--accent), 0 8px 24px var(--accent-shadow);
        }
        .choice-card-icon {
            width: 40px; height: 40px;
            display: grid; place-items: center;
            background: var(--bg-base);
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            color: var(--text-secondary);
            transition: all var(--transition);
        }
        .choice-card.is-active .choice-card-icon {
            background: var(--accent);
            color: var(--bg-base);
        }
        .choice-card-icon svg { width: 20px; height: 20px; }
        .choice-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-primary);
        }
        .choice-card p {
            font-size: 13px;
            color: var(--text-muted);
        }
        .choice-card .check-mark {
            position: absolute;
            top: 14px;
            left: 14px;
            opacity: 0;
            transition: opacity var(--transition);
            color: var(--accent);
        }
        .choice-card.is-active .check-mark { opacity: 1; }
        .choice-card .check-mark svg { width: 20px; height: 20px; }


        .sub-question { margin-top: 24px; }
        .sub-question h3 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sub-question h3 svg { width: 18px; height: 18px; color: var(--accent); }
        .pill-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: 999px;
            color: var(--text-secondary);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
        }
        .pill-btn svg { width: 16px; height: 16px; }
        .pill-btn:hover { border-color: var(--border-strong); color: var(--text-primary); }
        .pill-btn.is-active {
            background: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }
        .migration-section { margin-top: 24px; }


        .form-field {
            margin-bottom: 0;
        }
        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        .form-label svg { width: 16px; height: 16px; color: var(--accent); }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 14px;
            direction: ltr;
            text-align: left;
            transition: all var(--transition);
        }
        .form-input::placeholder {
            color: var(--text-muted);
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--bg-base);
            box-shadow: 0 0 0 3px var(--accent-shadow);
        }
        .form-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
            line-height: 1.7;
        }
        .file-input {
            position: relative;
            display: block;
            padding: 24px;
            background: var(--bg-elevated);
            border: 2px dashed var(--border-strong);
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition);
        }
        .file-input:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
        }
        .file-input input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .file-input-icon { color: var(--accent); margin-bottom: 8px; }
        .file-input-icon svg { width: 32px; height: 32px; }
        .file-input-text {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .file-input-hint { font-size: 12px; color: var(--text-muted); }


        .field-progress {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0 16px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .field-progress-bar {
            flex: 1;
            height: 4px;
            background: var(--bg-elevated);
            border-radius: 999px;
            overflow: hidden;
        }
        .field-progress-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 999px;
            transition: width 350ms cubic-bezier(0.4, 0, 0.2, 1);
        }


        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: 1px solid;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            min-height: 44px;
        }
        .btn svg { width: 16px; height: 16px; }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--bg-base);
        }
        .btn-primary:hover {
            background: var(--accent-strong);
            border-color: var(--accent-strong);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px var(--accent-shadow);
        }
        .btn-secondary {
            background: var(--bg-elevated);
            border-color: var(--border-strong);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: var(--bg-surface);
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-success {
            background: rgba(34, 197, 94, 0.15);
            border-color: rgba(34, 197, 94, 0.4);
            color: #86efac;
        }
        .btn-success:hover {
            background: rgba(34, 197, 94, 0.25);
            color: #bbf7d0;
        }
        .btn-grow { flex: 1 1 auto; min-width: 140px; }
        .btn[disabled], .btn[hidden] { display: none; }
        .install-submit { margin-top: 20px; }
        .install-submit .btn { width: 100%; padding: 16px; font-size: 16px; }


        .field-step { display: none; }
        .field-step.is-active {
            display: block;
            animation: fadeSlide 240ms ease-out;
        }
        details {
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            margin-bottom: 12px;
        }
        details[open] { padding-bottom: 18px; }
        summary {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--text-primary);
            list-style: none;
        }
        summary::-webkit-details-marker { display: none; }
        summary svg { width: 16px; height: 16px; color: var(--accent); }
        details > label:not(summary) {
            display: block;
            margin-top: 12px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        details > input { margin-top: 8px; }
        .warn-block {
            display: flex;
            gap: 12px;
            padding: 16px;
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: var(--radius-md);
            color: #fcd34d;
            font-size: 13px;
            line-height: 1.7;
        }
        .warn-block svg {
            flex-shrink: 0;
            width: 20px; height: 20px;
            color: #f59e0b;
            margin-top: 2px;
        }


        .checkbox-block {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
            padding: 14px 16px;
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            border-radius: var(--radius-md);
            cursor: pointer;
            user-select: none;
            transition: background var(--transition);
        }
        .checkbox-block:hover {
            background: rgba(239, 68, 68, 0.18);
        }
        [data-theme="blue"]   .checkbox-block:hover { background: rgba(91, 158, 255, 0.18); }
        [data-theme="purple"] .checkbox-block:hover { background: rgba(183, 148, 246, 0.18); }
        [data-theme="green"]  .checkbox-block:hover { background: rgba(104, 211, 145, 0.18); }
        [data-theme="orange"] .checkbox-block:hover { background: rgba(255, 146, 72, 0.18); }
        .checkbox-block input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
            flex-shrink: 0;
        }
        .checkbox-block span {
            font-size: 14px;
            color: var(--text-primary);
            line-height: 1.6;
        }


        .success-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 16px;
            padding: 16px 24px;
            background: var(--accent);
            color: var(--bg-base);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: all var(--transition);
        }
        .success-cta:hover {
            background: var(--accent-strong);
            transform: translateY(-1px);
            box-shadow: 0 12px 28px var(--accent-shadow);
        }
        .success-cta svg { width: 22px; height: 22px; }
        .success-message {
            text-align: center;
            margin-top: 24px;
            color: var(--text-secondary);
        }
        .success-message p { margin-bottom: 4px; }


        @media (max-width: 640px) {
            .app-header-inner { padding: 12px 16px; }
            .app-main { padding: 24px 16px 60px; }
            .card { padding: 20px; }
            .step-divider { display: none; }
            .stepper { gap: 6px; }
            .step { padding: 6px 10px; }
            .step-label { display: none; }
            .step.is-active .step-label { display: inline; font-size: 12px; }
            .choice-grid { grid-template-columns: 1fr; }
            .btn-row { flex-direction: column-reverse; }
            .btn-row .btn { width: 100%; }
            .theme-switcher { padding: 3px; gap: 4px; }
            .theme-swatch { width: 20px; height: 20px; }
        }


        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <symbol id="i-cog" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </symbol>
            <symbol id="i-server" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2"/>
                <rect x="2" y="14" width="20" height="8" rx="2"/>
                <line x1="6" y1="6" x2="6.01" y2="6"/>
                <line x1="6" y1="18" x2="6.01" y2="18"/>
            </symbol>
            <symbol id="i-cloud" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>
            </symbol>
            <symbol id="i-cogs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M12 1v6m0 10v6"/>
                <path d="m4.22 4.22 4.24 4.24m7.08 7.08 4.24 4.24"/>
                <path d="M1 12h6m10 0h6"/>
                <path d="m4.22 19.78 4.24-4.24m7.08-7.08 4.24-4.24"/>
            </symbol>
            <symbol id="i-download" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </symbol>
            <symbol id="i-upgrade" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="19" x2="12" y2="5"/>
                <polyline points="5 12 12 5 19 12"/>
            </symbol>
            <symbol id="i-database" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
            </symbol>
            <symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </symbol>
            <symbol id="i-key" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/>
            </symbol>
            <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </symbol>
            <symbol id="i-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="2" y1="12" x2="22" y2="12"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </symbol>
            <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </symbol>
            <symbol id="i-check-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </symbol>
            <symbol id="i-upload" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </symbol>
            <symbol id="i-archive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="21 8 21 21 3 21 3 8"/>
                <rect x="1" y="3" width="22" height="5"/>
                <line x1="10" y1="12" x2="14" y2="12"/>
            </symbol>
            <symbol id="i-help" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </symbol>
            <symbol id="i-rocket" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/>
                <path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/>
                <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/>
                <path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>
            </symbol>
            <symbol id="i-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"/>
                <polyline points="12 5 19 12 12 19"/>
            </symbol>
            <symbol id="i-arrow-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </symbol>
            <symbol id="i-warn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </symbol>
            <symbol id="i-x-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </symbol>
            <symbol id="i-bot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="10" rx="2" ry="2"/>
                <circle cx="12" cy="5" r="2"/>
                <path d="M12 7v4"/>
                <line x1="8" y1="16" x2="8" y2="16"/>
                <line x1="16" y1="16" x2="16" y2="16"/>
            </symbol>
            <symbol id="i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </symbol>
            <symbol id="i-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </symbol>
            <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </symbol>
        </defs>
    </svg>

    <div class="app-shell">
        
        <header class="app-header">
            <div class="app-header-inner">
                <a class="brand" href="#">
                    <span class="brand-mark"><svg><use href="#i-cog"/></svg></span>
                    <span>فاکسیما</span>
                </a>
                <div class="theme-switcher" role="radiogroup" aria-label="انتخاب رنگ پنل">
                    <button type="button" class="theme-swatch is-active" data-theme="red"    role="radio" aria-checked="true"  aria-label="قرمز"></button>
                    <button type="button" class="theme-swatch"           data-theme="blue"   role="radio" aria-checked="false" aria-label="آبی"></button>
                    <button type="button" class="theme-swatch"           data-theme="purple" role="radio" aria-checked="false" aria-label="بنفش"></button>
                    <button type="button" class="theme-swatch"           data-theme="green"  role="radio" aria-checked="false" aria-label="سبز"></button>
                    <button type="button" class="theme-swatch"           data-theme="orange" role="radio" aria-checked="false" aria-label="نارنجی"></button>
                </div>
            </div>
        </header>

        
        <main class="app-main">
            <div class="hero">
                <div class="hero-badge">
                    <span class="pulse-dot"></span>
                    <span>نصب کننده‌ی هوشمند</span>
                </div>
                <h1>نصب خودکار <span class="accent">ربات فاکسیما</span></h1>
                <p>تنها چند مرحله ساده تا راه‌اندازی کامل ربات شما — بدون نیاز به دانش فنی پیچیده.</p>
            </div>

            <?php if (!empty($ERROR)): ?>
                <div class="alert alert-danger">
                    <svg><use href="#i-x-circle"/></svg>
                    <div><?php echo implode("<br>", $ERROR); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="card">
                    <div class="alert alert-success">
                        <svg><use href="#i-check-circle"/></svg>
                        <div><?php echo implode("<br>", $SUCCESS); ?></div>
                    </div>
                    <a class="success-cta" href="https://t.me/<?php echo $tgBot['details']['result']['username']; ?>">
                        <svg><use href="#i-bot"/></svg>
                        رفتن به ربات <?php echo "@" . $tgBot['details']['result']['username']; ?>
                        <svg><use href="#i-arrow-left"/></svg>
                    </a>

                    <div class="success-message">
                        <p><strong>نصب با موفقیت تکمیل شد!</strong></p>
                        <p style="color: var(--text-muted); font-size: 13px;">پوشه‌ی <code>installer</code> به‌صورت خودکار در حال حذف است — برای امنیت سرور.</p>
                    </div>
                </div>
            <?php endif; ?>

            <form id="installer-form" <?php if ($success) echo 'style="display:none"'; ?> method="post" enctype="multipart/form-data">

                <!-- Only the simple cPanel-host install remains. Server type and
                     install type are fixed and submitted as hidden values. -->
                <input type="hidden" name="server_type" value="cpanel">
                <input type="hidden" name="install_type" value="simple">

                
                <section class="step-section is-active" id="step-1" aria-labelledby="step-3-title">
                    <div class="card">
                        <h2 class="card-title" id="step-3-title">
                            <svg><use href="#i-database"/></svg>
                            اطلاعات نصب
                        </h2>

                        <div class="field-step <?php echo $currentInstallField === 1 ? 'is-active' : ''; ?>" data-field-step="1">
                            <div class="form-field">
                                <label class="form-label" for="admin_id"><svg><use href="#i-user"/></svg> آیدی عددی ادمین</label>
                                <input class="form-input" type="text" id="admin_id" name="admin_id" placeholder="ADMIN TELEGRAM #ID" value="<?php echo escapeHtml($uPOST['admin_id'] ?? ''); ?>" required>
                                <p class="form-hint">می‌توانید آیدی عددی خود را از ربات <code>@userinfobot</code> بگیرید.</p>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 2 ? 'is-active' : ''; ?>" data-field-step="2">
                            <div class="form-field">
                                <label class="form-label" for="tg_bot_token"><svg><use href="#i-key"/></svg> توکن ربات تلگرام</label>
                                <input class="form-input" type="text" id="tg_bot_token" name="tg_bot_token" placeholder="123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" value="<?php echo escapeHtml($uPOST['tg_bot_token'] ?? ''); ?>" required>
                                <p class="form-hint">توکن را از <code>@BotFather</code> دریافت کنید.</p>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 3 ? 'is-active' : ''; ?>" data-field-step="3">
                            <div class="form-field">
                                <label class="form-label" for="database_username"><svg><use href="#i-user"/></svg> نام کاربری دیتابیس</label>
                                <input class="form-input" type="text" id="database_username" name="database_username" placeholder="DATABASE USERNAME" value="<?php echo escapeHtml($uPOST['database_username'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 4 ? 'is-active' : ''; ?>" data-field-step="4">
                            <div class="form-field">
                                <label class="form-label" for="database_password"><svg><use href="#i-lock"/></svg> رمز عبور دیتابیس</label>
                                <input class="form-input" type="text" id="database_password" name="database_password" placeholder="DATABASE PASSWORD" value="<?php echo escapeHtml($uPOST['database_password'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 5 ? 'is-active' : ''; ?>" data-field-step="5">
                            <div class="form-field">
                                <label class="form-label" for="database_name"><svg><use href="#i-database"/></svg> نام دیتابیس</label>
                                <input class="form-input" type="text" id="database_name" name="database_name" placeholder="DATABASE NAME" value="<?php echo escapeHtml($uPOST['database_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 6 ? 'is-active' : ''; ?>" data-field-step="6">
                            <div class="form-field">
                                <details>
                                    <summary><svg><use href="#i-globe"/></svg> آدرس سورس ربات (پیشرفته)</summary>
                                    <label for="bot_address_webhook">آدرس صفحه‌ی سورس ربات (نه installer)</label>
                                    <input class="form-input" type="text" id="bot_address_webhook" name="bot_address_webhook" placeholder="https://yourdomain.com/path/index.php" value="<?php echo escapeHtml($uPOST['bot_address_webhook'] ?? ($webAddress . '/index.php')); ?>" required>
                                </details>
                            </div>
                        </div>

                        <div class="field-step <?php echo $currentInstallField === 7 ? 'is-active' : ''; ?>" data-field-step="7">
                            <div class="warn-block">
                                <svg><use href="#i-warn"/></svg>
                                <div>
                                    <strong>هشدار:</strong> پس از نصب موفقیت‌آمیز، پوشه‌ی <code>installer</code> به‌صورت <strong>خودکار حذف</strong> خواهد شد. این کار برای حفظ امنیت سرور انجام می‌شود.
                                </div>
                            </div>
                        </div>

                        <div class="field-progress">
                            <span>فیلد <span id="install-progress-text"><?php echo $currentInstallField; ?></span> از <?php echo $installFieldTotal; ?></span>
                            <div class="field-progress-bar">
                                <div class="field-progress-fill" id="field-progress-fill" style="width: <?php echo round($currentInstallField * 100 / $installFieldTotal); ?>%"></div>
                            </div>
                        </div>

                        <div class="btn-row">
                            <button type="button" class="btn btn-secondary" id="install-prev-btn" <?php echo $currentInstallField <= 1 ? 'hidden' : ''; ?>>
                                <svg><use href="#i-arrow-right"/></svg>
                                فیلد قبل
                            </button>
                            <button type="button" class="btn btn-primary btn-grow" id="install-next-btn" <?php echo $currentInstallField >= $installFieldTotal ? 'hidden' : ''; ?>>
                                فیلد بعد
                                <svg><use href="#i-arrow-left"/></svg>
                            </button>
                        </div>
                        <div class="install-submit" id="install-submit" style="<?php echo $currentInstallField >= $installFieldTotal ? '' : 'display:none'; ?>">
                            <button type="submit" name="submit" value="submit" class="btn btn-primary">
                                <svg><use href="#i-rocket"/></svg>
                                شروع نصب ربات
                            </button>
                        </div>
                    </div>
                </section>

                
                <div class="btn-row" style="display:none;">
                    <button type="button" class="btn btn-secondary" id="prev-btn" hidden>
                        <svg><use href="#i-arrow-right"/></svg>
                        مرحله قبل
                    </button>
                    <button type="button" class="btn btn-primary btn-grow" id="next-btn" hidden>
                        مرحله بعد
                        <svg><use href="#i-arrow-left"/></svg>
                    </button>
                </div>

                <input type="hidden" name="current_step"          id="current_step"          value="<?php echo (int) $currentStep; ?>">
                <input type="hidden" name="current_install_field" id="current_install_field" value="<?php echo (int) $currentInstallField; ?>">
            </form>
        </main>

        
        <footer class="app-footer">
            <p>
                Faoxima Installer
                ·
                <a href="https://github.com/Mmd-Amir/Faoxima" target="_blank" rel="noopener">گیت‌هاب</a>
                ·
                <a href="https://t.me/faoxima" target="_blank" rel="noopener">تلگرام</a>
                ·
                &copy; <?php echo date('Y'); ?>
            </p>
        </footer>
    </div>

    <script>
    (function () {
        'use strict';


        const TOTAL_WIZARD_STEPS  = 1;
        const TOTAL_INSTALL_FIELDS_INITIAL = <?php echo (int) $installFieldTotal; ?>;
        const THEME_STORAGE_KEY   = 'faoxima_installer_theme';

        let currentStep         = <?php echo (int) $currentStep; ?>;
        let currentInstallField = <?php echo (int) $currentInstallField; ?>;
        let installFieldSteps   = [];
        let totalInstallFields  = TOTAL_INSTALL_FIELDS_INITIAL;


        const $ = (sel, root) => (root || document).querySelector(sel);
        const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));


        function applyTheme(theme) {
            const validThemes = ['red', 'blue', 'purple', 'green', 'orange'];
            if (!validThemes.includes(theme)) theme = 'red';
            document.documentElement.setAttribute('data-theme', theme);
            try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch (e) {  }
            $$('.theme-swatch').forEach(swatch => {
                const isActive = swatch.dataset.theme === theme;
                swatch.classList.toggle('is-active', isActive);
                swatch.setAttribute('aria-checked', isActive ? 'true' : 'false');
            });
        }

        function initThemeSwitcher() {
            let saved = 'red';
            try { saved = localStorage.getItem(THEME_STORAGE_KEY) || 'red'; } catch (e) {}
            applyTheme(saved);
            $$('.theme-swatch').forEach(swatch => {
                swatch.addEventListener('click', () => applyTheme(swatch.dataset.theme));
            });
        }


        function changeInstallField(delta) {
            if (!installFieldSteps.length) return;
            if (delta > 0 && !validateInstallField(currentInstallField)) return;
            const next = currentInstallField + delta;
            if (next < 1 || next > totalInstallFields) return;
            currentInstallField = next;
            updateInstallFieldDisplay();
        }

        function validateInstallField(stepIndex) {
            const step = installFieldSteps[stepIndex - 1];
            if (!step) return true;
            const inputs = step.querySelectorAll('input[required], select[required], textarea[required]');
            for (const input of inputs) {
                const textTypes = ['text', 'password', 'tel', 'email', 'url', 'search', 'number'];
                if (textTypes.includes(input.type) && input.value.trim() === '') {
                    input.reportValidity();
                    return false;
                }
                if ((input.tagName === 'SELECT' || input.tagName === 'TEXTAREA') && input.value.trim() === '') {
                    input.reportValidity();
                    return false;
                }
                if (input.type === 'file' && !input.files.length) {
                    input.reportValidity();
                    return false;
                }
                if (input.type === 'checkbox' && !input.checked) {
                    input.reportValidity();
                    return false;
                }
            }
            return true;
        }

        function updateInstallFieldDisplay() {
            if (!installFieldSteps.length) return;
            currentInstallField = Math.max(1, Math.min(totalInstallFields, currentInstallField));
            installFieldSteps.forEach((step, idx) => {
                step.classList.toggle('is-active', idx === currentInstallField - 1);
            });
            const prevBtn      = $('#install-prev-btn');
            const nextBtn      = $('#install-next-btn');
            const submitBlock  = $('#install-submit');
            const progressText = $('#install-progress-text');
            const progressFill = $('#field-progress-fill');
            const hiddenField  = $('#current_install_field');
            if (prevBtn)     prevBtn.hidden     = currentInstallField === 1;
            if (nextBtn)     nextBtn.hidden     = currentInstallField === totalInstallFields;
            if (submitBlock) submitBlock.style.display = currentInstallField === totalInstallFields ? '' : 'none';
            if (progressText) progressText.textContent = currentInstallField;
            if (progressFill) progressFill.style.width = Math.round(currentInstallField * 100 / totalInstallFields) + '%';
            if (hiddenField) hiddenField.value = currentInstallField;
        }


        function updateWizardDisplay() {
            currentStep = Math.max(1, Math.min(TOTAL_WIZARD_STEPS, currentStep));
            $$('.step').forEach(step => {
                const stepNum = parseInt(step.dataset.step, 10);
                step.classList.remove('is-active', 'is-completed');
                if (stepNum === currentStep) step.classList.add('is-active');
                else if (stepNum < currentStep) step.classList.add('is-completed');
            });
            $$('.step-section').forEach(section => {
                section.classList.toggle('is-active', section.id === 'step-' + currentStep);
            });
            if (currentStep === TOTAL_WIZARD_STEPS) updateInstallFieldDisplay();
            const prevBtn = $('#prev-btn');
            const nextBtn = $('#next-btn');
            if (prevBtn) prevBtn.hidden = currentStep === 1;
            if (nextBtn) nextBtn.hidden = currentStep === TOTAL_WIZARD_STEPS;
            const hiddenStep = $('#current_step');
            if (hiddenStep) hiddenStep.value = currentStep;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }


        function initFileInputFeedback() {
            const fileInput   = $('#backup_file');
            const fileDisplay = $('#file-name-display');
            if (!fileInput || !fileDisplay) return;
            fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files.length > 0) {
                    fileDisplay.textContent = fileInput.files[0].name;
                } else {
                    fileDisplay.textContent = 'انتخاب فایل بکاپ';
                }
            });
        }


        document.addEventListener('DOMContentLoaded', () => {
            initThemeSwitcher();
            initFileInputFeedback();

            installFieldSteps = $$('.field-step');
            if (installFieldSteps.length) totalInstallFields = installFieldSteps.length;

            updateInstallFieldDisplay();
            updateWizardDisplay();


            const installNextBtn = $('#install-next-btn');
            const installPrevBtn = $('#install-prev-btn');
            if (installNextBtn) installNextBtn.addEventListener('click', () => changeInstallField(1));
            if (installPrevBtn) installPrevBtn.addEventListener('click', () => changeInstallField(-1));

            const nextBtn = $('#next-btn');
            const prevBtn = $('#prev-btn');
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    if (currentStep < TOTAL_WIZARD_STEPS) {
                        currentStep++;
                        updateWizardDisplay();
                    }
                });
            }
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (currentStep > 1) {
                        currentStep--;
                        updateWizardDisplay();
                    }
                });
            }


            const installerForm = $('#installer-form');
            if (installerForm) {
                installerForm.addEventListener('submit', (event) => {
                    const stepField  = $('#current_step');
                    const fieldField = $('#current_install_field');
                    if (stepField)  stepField.value  = currentStep;
                    if (fieldField) fieldField.value = currentInstallField;
                });
            }
        });


    })();
</script>
</body>
</html>
<?php


function scheduleInstallerSelfDelete(string $installerDir): void {
    static $scheduled = false;
    if ($scheduled) {
        return;
    }
    $scheduled = true;

    @ignore_user_abort(true);

    register_shutdown_function(static function () use ($installerDir): void {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        @set_time_limit(60);

        if (!is_dir($installerDir) || !is_writable($installerDir)) {
            return;
        }

        $rrmdir = static function ($dir) use (&$rrmdir): void {
            if (!is_dir($dir)) {
                return;
            }
            $items = @scandir($dir);
            if ($items === false) {
                return;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path) && !is_link($path)) {
                    $rrmdir($path);
                } else {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        };

        $rrmdir($installerDir);
    });
}

function getContents($url) {
    $context = stream_context_create([
        'http' => ['timeout' => 30],
        'https' => ['timeout' => 30],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['ok' => false];
    }
    $decoded = json_decode($response, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return ['ok' => false];
    }
    return $decoded;
}
function isValidTelegramToken($token) {
    return preg_match('/^\d{6,12}:[A-Za-z0-9_-]{35}$/', $token);
}
function isValidTelegramId($id) {
    return preg_match('/^\d{6,12}$/', $id);
}
function sanitizeInput(&$INPUT, array $options = []) {
    $defaultOptions = [
        'allow_html' => false,
        'allowed_tags' => '',
        'remove_spaces' => false,
        'connection' => null,
        'max_length' => 0,
        'encoding' => 'UTF-8'
    ];
    $options = array_merge($defaultOptions, $options);
    if (is_array($INPUT)) {
        return array_map(function($item) use ($options) {
            return sanitizeInput($item, $options);
        }, $INPUT);
    }
    if ($INPUT === null || $INPUT === false) {
        return '';
    }
    $INPUT = trim((string)$INPUT);
    $INPUT = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $INPUT);
    if ($options['max_length'] > 0) {
        $INPUT = mb_substr($INPUT, 0, $options['max_length'], $options['encoding']);
    }
    if (!$options['allow_html']) {
        $INPUT = strip_tags($INPUT);
    } elseif (!empty($options['allowed_tags'])) {
        $INPUT = strip_tags($INPUT, $options['allowed_tags']);
    }
    if ($options['remove_spaces']) {
        $INPUT = preg_replace('/\s+/', ' ', trim($INPUT));
    }
    if ($options['connection'] instanceof mysqli) {
        $INPUT = $options['connection']->real_escape_string($INPUT);
    }
    return $INPUT;
}
function normalizeDomainAddress($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $parsedUrl = parse_url($url);
    if (empty($parsedUrl['host'])) {
        return null;
    }
    $path = $parsedUrl['path'] ?? '';
    $path = preg_replace('#/index\.php$#i', '', $path);
    $path = preg_replace('#/installer/?$#', '', $path);
    $path = rtrim($path, '/');
    $path = ltrim($path, '/');
    $address = $parsedUrl['host'];
    if ($path !== '') {
        $address .= '/' . $path;
    }
    return [
        'address' => $address
    ];
}
function updateConfigValues($configContents, array $placeholderValues, &$replacementCount = 0) {
    $replacementCount = 0;
    $configData = str_replace(array_keys($placeholderValues), array_values($placeholderValues), $configContents, $placeholderReplacementCount);
    if ($placeholderReplacementCount > 0) {
        $replacementCount += $placeholderReplacementCount;
    }
    $variableMap = [
        'dbname' => $placeholderValues['{database_name}'] ?? '',
        'usernamedb' => $placeholderValues['{username_db}'] ?? '',
        'passworddb' => $placeholderValues['{password_db}'] ?? '',
        'APIKEY' => $placeholderValues['{API_KEY}'] ?? '',
        'adminnumber' => $placeholderValues['{admin_number}'] ?? '',
        'domainhosts' => $placeholderValues['{domain_name}'] ?? '',
        'usernamebot' => $placeholderValues['{username_bot}'] ?? '',
    ];
    $updatedConfig = $configData;
    foreach ($variableMap as $variable => $value) {
        $pattern = '/(\$' . preg_quote($variable, '/') . '\s*=\s*)([\'\"])(.*?)(\2)(\s*;)([^\n]*)(\n?)/u';
        $updatedConfig = preg_replace_callback(
            $pattern,
            function ($matches) use ($value, &$replacementCount) {
                $replacementCount++;
                $quoteChar = $matches[2];
                $formattedValue = formatConfigValue($value, $quoteChar);
                return $matches[1] . $formattedValue . $matches[5] . $matches[6] . $matches[7];
            },
            $updatedConfig,
            1
        );
    }
    return $updatedConfig;
}
function escapeHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function formatConfigValue($value, $quoteChar = '\'') {
    if ($value === null) {
        return 'null';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($quoteChar !== "'" && $quoteChar !== '"') {
        $quoteChar = "'";
    }
    $stringValue = (string) $value;
    $escapedValue = addcslashes($stringValue, "\\$quoteChar");
    return $quoteChar . $escapedValue . $quoteChar;
}
?>
