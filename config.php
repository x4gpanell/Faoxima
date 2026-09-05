<?php

$dbname     = '';
$usernamedb = '';
$passworddb = '';


$connect = null;
$pdo     = null;
$dsn     = '';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

if ($dbname !== '' && $usernamedb !== '') {
    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }
    try {
        $connect = @mysqli_connect('mysql.railway.internal', $usernamedb, $passworddb, $dbname);
    } catch (\Throwable $rxMysqliConnectError) {
        $connect = null;
        error_log('config.php mysqli_connect failed: ' . $rxMysqliConnectError->getMessage());
    }
    if ($connect instanceof mysqli) {
        @mysqli_set_charset($connect, 'utf8mb4');
    } else {
        $connect = null;
    }

    $dsn = 'mysql:host=mysql.railway.internal;dbname=' . $dbname . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, $usernamedb, $passworddb, $options);
    } catch (\PDOException $rxPdoError) {
        $pdo = null;
        error_log('config.php PDO connection failed: ' . $rxPdoError->getMessage());
    }
} else {
    $rxInstallerPending = is_file(__DIR__ . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'index.php');
    if (!$rxInstallerPending) {
        $rxConfigEmptyMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rx_config_empty.flag';
        if (!is_file($rxConfigEmptyMarker) || (time() - (int) @filemtime($rxConfigEmptyMarker)) > 3600) {
            error_log('config.php: database credentials are empty — fill $dbname/$usernamedb/$passworddb to enable DB-backed features.');
            @touch($rxConfigEmptyMarker);
        }
        unset($rxConfigEmptyMarker);
    }
    unset($rxInstallerPending);
}

$APIKEY                     = '';
$adminnumber                = '';
$domainhosts                = '';
$usernamebot                = '';
$telegramCurlTimeout        = 10;
$telegramStrictIpValidation = true;
$domainhosts                = rtrim(preg_replace('#^https?://#', '', $domainhosts), '/');


if (!defined('APP_ORIGIN') && $domainhosts !== '') {
    define('APP_ORIGIN', 'https://' . $domainhosts);
}


$GLOBALS['dbname']                     = $dbname;
$GLOBALS['usernamedb']                 = $usernamedb;
$GLOBALS['passworddb']                 = $passworddb;
$GLOBALS['dsn']                        = $dsn;
$GLOBALS['options']                    = $options;
$GLOBALS['pdo']                        = $pdo;
$GLOBALS['connect']                    = $connect;
$GLOBALS['APIKEY']                     = $APIKEY;
$GLOBALS['adminnumber']                = $adminnumber;
$GLOBALS['domainhosts']                = $domainhosts;
$GLOBALS['usernamebot']                = $usernamebot;
$GLOBALS['telegramCurlTimeout']        = $telegramCurlTimeout;
$GLOBALS['telegramStrictIpValidation'] = $telegramStrictIpValidation;

require_once __DIR__ . '/proxy.php';
