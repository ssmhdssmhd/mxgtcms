<?php
/**
 * 沫兮官替官解系统 - 手动更新脚本
 *
 * 当后台「在线更新」（自动更新）无法运行时（如页面打不开、鉴权异常、网络受限等），
 * 可使用本脚本手动从当前 GitHub 仓库拉取最新发行版并覆盖更新 addons/mxgt 与 mxthxt，
 * 自动保留站长的插件配置与启用状态。
 *
 * 用法（推荐 SSH 命令行，在苹果CMS根目录或任意目录执行均可，脚本自动定位目录）：
 *   php mxgt/manual_update.php              # 检查并更新到最新发行版（会确认）
 *   php mxgt/manual_update.php --yes        # 跳过交互确认，直接更新
 *   php mxgt/manual_update.php --force      # 忽略“已是最新”，强制重新拉取最新发行版覆盖
 *   php mxgt/manual_update.php --help       # 查看帮助
 *
 * 也支持直接通过浏览器访问本文件执行更新，输出为纯文本进度。
 * 注意：手动更新命令在服务器上执行，请确保服务器具备外网访问更新源的能力。
 */

// ---------- 运行环境加固 ----------
if (PHP_VERSION_ID < 50600) {
    exit('本脚本需要 PHP >= 5.6');
}
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');
set_time_limit(120);
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('ROOT_PATH')) {
    // 本脚本可位于 {根}/addons/mxgt/ 或开发目录 {根}/mxgt/，向上逐级定位含 mxthxt/core/Update.php 的根目录
    $rootDir = __DIR__;
    for ($i = 0; $i <= 4; $i++) {
        $dir = ($i === 0) ? __DIR__ : dirname(__DIR__, $i);
        if (is_file(rtrim($dir, '/\\') . DS . 'mxthxt' . DS . 'core' . DS . 'Update.php')) {
            $rootDir = rtrim($dir, '/\\');
            break;
        }
    }
    define('ROOT_PATH', $rootDir . '/');
}

// ---------- 通用输出（CLI 用 [..] 前缀，WEB 用 <pre> 包裹） ----------
$isCli = (PHP_SAPI === 'cli');
if ($isCli) {
    function out($msg = '', $nl = true) { echo $msg . ($nl ? "\n" : ''); }
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "<pre style=\"font:13px/1.6 Menlo,Consolas,monospace;color:#333\">\n";
    function out($msg = '', $nl = true) { echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . ($nl ? "\n" : ''); }
    register_shutdown_function(function () { echo "</pre>"; });
}
function warn($msg) { out('[!] ' . $msg); }
function fail($msg) { warn($msg); if (function_exists('exit')) exit(1); }

// ---------- 参数解析 ----------
$argv = isset($argv) && is_array($argv) ? $argv : array(array(''));
$force = false;
$yes = false;
foreach ($argv as $a) {
    switch ($a) {
        case '--force': $force = true; break;
        case '--yes':   $yes = true; break;
        case '-y':      $yes = true; break;
        case '--help':
        case '-h':
            out(basename(__FILE__) . ' 手动更新脚本帮助（沫兮官替官解系统）');
            out('  无参数        : 检查并更新到最新发行版（会确认）');
            out('  --yes / -y    : 跳过交互确认，直接更新');
            out('  --force       : 忽略“已是最新”，强制重新拉取最新发行版覆盖');
            out('  --help / -h   : 显示本帮助');
            exit(0);
    }
}
if (!$isCli && !$yes) { $yes = true; } // 浏览器访问视为已授权直接执行

// ---------- 载入云端更新核心 ----------
$updateCore = ROOT_PATH . 'mxthxt' . DS . 'core' . DS . 'Update.php';
if (!is_file($updateCore)) {
    fail('未检测到云端更新组件：' . $updateCore . "（请将 mxthxt 文件夹上传至苹果CMS根目录，与 addons 同级）");
}
require_once $updateCore;
if (!class_exists('MxthxtUpdate')) {
    fail('云端更新组件加载失败，请检查 mxthxt/core/Update.php 是否完整');
}
$update = new MxthxtUpdate();

// ---------- 读取插件配置（源/仓库/自定义地址） ----------
$localVersion = '';
$iniFile = ROOT_PATH . 'addons' . DS . 'mxgt' . DS . 'info.ini';
if (!is_file($iniFile)) { $iniFile = __DIR__ . DS . 'info.ini'; }
if (is_file($iniFile)) {
    $ini = @parse_ini_file($iniFile);
    $localVersion = (is_array($ini) && isset($ini['version'])) ? $ini['version'] : '';
}

// 插件配置：优先读部署到 addons/mxgt/config.php，其次本目录 config.php，最后回退 mxthxt/config/config.php
$source = 'mirror';
$repo = '';
$customUrl = '';
$ref = array(
    ROOT_PATH . 'addons' . DS . 'mxgt' . DS . 'config.php',
    __DIR__ . DS . 'config.php',
    ROOT_PATH . 'mxthxt' . DS . 'config' . DS . 'config.php',
);
foreach ($ref as $cfgFile) {
    if (is_file($cfgFile) && ($c = @include $cfgFile) && is_array($c)) {
        foreach ($c as $item) {
            if (!is_array($item) || !isset($item['name'])) { continue; }
            if ($item['name'] === 'update_source' && $source === 'mirror')    { $source = isset($item['value']) ? $item['value'] : 'mirror'; }
            if ($item['name'] === 'github_repo' && $repo === '')              { $repo = isset($item['value']) ? $item['value'] : ''; }
            if ($item['name'] === 'update_custom_url' && $customUrl === '')   { $customUrl = isset($item['value']) ? $item['value'] : ''; }
        }
        // 兼容 mxthxt/config/config.php 的顶层 github_repo 键
        if ($repo === '' && isset($c['github_repo'])) { $repo = (string) $c['github_repo']; }
        break;
    }
}

out('========== 沫兮官替官解系统 - 手动更新 ==========');
out('根目录      : ' . ROOT_PATH);
out('本地版本    : ' . ($localVersion !== '' ? 'v' . $localVersion : '（未读取到，请确认插件已安装）'));
out('更新源      : ' . $source);
out('GitHub 仓库 : ' . ($repo !== '' ? $repo : '（未配置仓库，请在插件配置中填写）'));
out('');

if ($localVersion === '' || $repo === '') {
    fail('缺少必要配置，已终止更新。请先确保插件已安装并正确配置 GitHub 仓库。');
}

// ---------- 交互确认 ----------
if ($isCli && !$yes) {
    out('将把 addons/mxgt 与 mxthxt 更新到 ' . $repo . ' 的最新发行版（更新前自动备份，配置与启用状态保留）。');
    fwrite(STDOUT, '确认继续？[y/N] ');
    $answer = trim((string) fgets(STDIN));
    if (!in_array(strtolower($answer), array('y', 'yes', '确认'), true)) {
        out('已取消更新。');
        exit(0);
    }
}

// ---------- 1. 检查远程版本 ----------
out('[1/3] 正在检查远程最新发行版...');
$result = $update->check($localVersion, $source, $repo, $customUrl);
if (empty($result['code'])) {
    fail('检查更新失败：' . (isset($result['msg']) ? $result['msg'] : '未知错误'));
}
$latestVersion = isset($result['latest_version']) ? $result['latest_version'] : '';
out('远程版本    : ' . $latestVersion);
if (empty($result['has_update'])) {
    if (!$force) {
        out('');
        out('当前已是最新版本：v' . $localVersion . '，无需更新。');
        out('如需强制重新覆盖，请加 --force 参数执行：php ' . basename(__FILE__) . ' --force --yes');
        exit(0);
    }
    out('（未发现新版本，但因 --force 强制重新拉取最新发行版覆盖）');
}

$downloadUrl = isset($result['download_url']) ? $result['download_url'] : '';
if ($downloadUrl === '') {
    $downloadUrl = isset($result['zipball_url']) ? $result['zipball_url'] : '';
}
if ($downloadUrl === '') {
    fail('未获取到更新包下载地址，请稍后重试或更换更新源。');
}

// ---------- 2. 下载更新包（可从浏览器或 CLI 实时看到文件名） ----------
out('[2/3] 正在下载更新包...');
$dl = $update->download($downloadUrl, $source);
if (empty($dl['code'])) {
    fail('下载更新包失败：' . (isset($dl['msg']) ? $dl['msg'] : '未知错误'));
}
out('下载完成    : ' . (isset($dl['path']) ? $dl['path'] : ''));

// ---------- 3. 应用更新（备份 → 覆盖 addons/mxgt 与 mxthxt → 保留配置与启用状态） ----------
out('[3/3] 正在应用更新（备份/覆盖/保留配置）...');
$ap = $update->apply(isset($dl['path']) ? $dl['path'] : '');
if (empty($ap['code'])) {
    // 更新失败时删除临时下载包，避免残留
    if (isset($dl['path']) && is_file($dl['path'])) { @unlink($dl['path']); }
    fail('应用更新失败：' . (isset($ap['msg']) ? $ap['msg'] : '未知错误'));
}
if (isset($dl['path']) && is_file($dl['path'])) { @unlink($dl['path']); } // 清理临时更新包

// ---------- 完成 ----------
out('');
out('========== 更新完成 ==========');
out(isset($ap['msg']) ? $ap['msg'] : '更新完成，已覆盖 addons/mxgt 与 mxthxt');
out($latestVersion !== '' ? '当前版本：' . $latestVersion : '');
out('提示：在苹果CMS后台依次执行 index.php?s=/admin/index/cache 或重启PHP后即可生效。');
exit(0);