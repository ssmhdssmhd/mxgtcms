<?php
/**
 * 沫兮官替官解系统 - 云端更新核心
 * 本文件位于苹果CMS根目录/mxthxt/core/Update.php
 *
 * 职责：
 * 1. check()    检查远端最新版本，与本地版本对比，并返回更新包下载地址；
 * 2. download() 下载更新包（GitHub 官方源 / 国内镜像 / 自定义源）；
 * 3. apply()    解压更新包，备份现有代码后覆盖 addons/mxgt 与 mxthxt，
 *               并保留站长的配置与插件启用状态。
 *
 * 更新包约定：zip 内包含 mxgt/（含 info.ini）与 mxthxt/（含 core/Update.php）目录，
 * 与 GitHub 仓库 tag 的 archive zip（{repo}-{tag}/ 单层目录）结构兼容。
 */

class MxthxtUpdate
{
    protected $config = [];
    protected $rootPath = '';

    public function __construct()
    {
        $configFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($configFile)) {
            $cfg = include $configFile;
            if (is_array($cfg)) {
                $this->config = $cfg;
            }
        }
        $this->rootPath = defined('ROOT_PATH')
            ? ROOT_PATH
            : rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/';
    }

    /**
     * 检查更新
     * 优先使用 GitHub Releases 发行版中上传的更新包资产（asset）；
     * 未匹配到资产时回退到源码 zip（zipball）。
     * 国内镜像源下自动按候选镜像列表依次尝试，全部失败后回退 GitHub 官方源直连。
     * @param string $localVersion 本地插件版本号，如 v0.0.1
     * @param string $source       更新源：github / mirror / custom
     * @param string $repo         GitHub 仓库，格式 用户名/仓库名
     * @param string $customUrl    自定义更新接口地址
     * @return array code:1成功 0失败; has_update; latest_version; download_url; zipball_url; asset_name; msg
     */
    public function check($localVersion = '', $source = 'mirror', $repo = '', $customUrl = '')
    {
        $endpoints = $this->buildApiEndpoints($source, $repo, $customUrl);
        if (empty($endpoints)) {
            return $this->fail('更新源配置不完整：请填写 GitHub 仓库或自定义更新地址');
        }

        $json = false;
        $data = null;
        $lastErr = '请求更新源失败，请检查服务器外网与更新源地址';
        foreach ($endpoints as $ep) {
            $json = $this->httpGet($ep);
            if ($json === false) {
                $lastErr = '请求更新源失败：' . $ep;
                continue;
            }
            $tmp = json_decode($json, true);
            if (is_array($tmp) && !empty($tmp['tag_name'])) {
                $data = $tmp;
                break;
            }
            // 非目标数据（限流/错误页等）：记录原因并继续尝试下一个地址
            if (is_array($tmp) && !empty($tmp['message']) && stripos((string) $tmp['message'], 'rate limit') !== false) {
                $lastErr = '更新源接口触发限流（Rate Limit）：' . $ep;
            } else {
                $lastErr = '更新源返回数据异常：' . $ep;
            }
            $json = false;
        }
        if ($data === null) {
            return $this->fail($lastErr);
        }

        $latestVersion = ltrim(trim($data['tag_name']), 'vV');
        $compare = $this->compareVersion($latestVersion, ltrim($localVersion, 'vV'));

        // 在 Release 资产中匹配更新包 zip（优先 mxgtcms 前缀，其次 mxgt 前缀）
        $zipball = isset($data['zipball_url']) ? (string) $data['zipball_url'] : '';
        $downloadUrl = '';
        $assetName = '';
        $prefixes = array(
            isset($this->config['asset_name_prefix']) ? (string) $this->config['asset_name_prefix'] : 'mxgtcms',
            'mxgt',
        );
        $assets = isset($data['assets']) && is_array($data['assets']) ? $data['assets'] : [];
        foreach ($assets as $a) {
            if (!is_array($a) || empty($a['name']) || empty($a['browser_download_url'])) {
                continue;
            }
            $name = (string) $a['name'];
            if (!preg_match('/\.zip$/i', $name)) {
                continue;
            }
            foreach ($prefixes as $p) {
                if ($p !== '' && strpos($name, $p) === 0) {
                    $downloadUrl = (string) $a['browser_download_url'];
                    $assetName = $name;
                    break 2;
                }
            }
        }
        // 未匹配到发行版资产时回退到源码 zip
        if ($downloadUrl === '' && !empty($this->config['fallback_zipball'])) {
            $downloadUrl = $zipball;
        }

        return [
            'code' => 1,
            'has_update' => $compare > 0,
            'latest_version' => 'v' . $latestVersion,
            'local_version' => $localVersion,
            'download_url' => $downloadUrl,
            'zipball_url' => $zipball,
            'asset_name' => $assetName,
            'msg' => $compare > 0 ? '发现新版本 v' . $latestVersion : '当前已是最新版本',
        ];
    }

    /**
     * 下载更新包到 runtime/mxthxt/，返回本地文件路径
     * 传入 $progressFile 时（如 runtime/mxthxt/update_progress.json），下载期间
     * 持续向该文件写入进度（step=check/speed/download/apply/done），供前端轮询显示。
     * 国内镜像源下：先对候选镜像逐一测速，优先使用最快镜像下载，失败自动切换下一个，最后回退官方直连。
     * @param string $zipballUrl   更新包下载地址（GitHub 发行版资产或 zipball）
     * @param string $source       更新源：github / mirror / custom
     * @param string $progressFile 进度文件绝对路径（可选）
     * @return array code:1成功 0失败; path:本地文件路径; msg
     */
    public function download($zipballUrl = '', $source = 'mirror', $progressFile = '')
    {
        $url = trim((string) $zipballUrl);
        if ($url === '') {
            return $this->fail('更新包下载地址为空');
        }

        // 构造候选下载地址：镜像源时生成各镜像前缀地址 + 官方直连兜底
        $urls = $this->buildCandidateUrls($url, $source);

        // 多镜像：先测速选优（写入 step=speed 进度），按速度从高到低下载
        $speedTested = false;
        if (count($urls) > 1) {
            if ($progressFile !== '') {
                $this->writeProgressFile($progressFile, 'speed', 30, '正在对 ' . count($urls) . ' 个镜像测速选优...');
            }
            $urls = $this->speedTest($urls, $progressFile);
            $speedTested = true;
            if ($progressFile !== '') {
                $this->writeProgressFile($progressFile, 'speed', 60, '测速完成，使用最快镜像：' . $this->shortHost($urls[0]) . ' 下载');
            }
        }

        $dir = $this->runtimeDir();
        $save = $dir . 'update_' . date('YmdHis') . '.zip';

        $ok = false;
        foreach ($urls as $u) {
            if ($progressFile !== '') {
                // 带进度：curl 下载到文件并实时写进度
                $startPct = $speedTested ? 62 : 8;
                $this->writeProgressFile($progressFile, 'download', $startPct, '正在下载更新包（' . $this->shortHost($u) . '）...');
                $ok = $this->httpDownloadToFile($u, $save, $progressFile, $startPct, 88);
                if ($ok) {
                    $this->writeProgressFile($progressFile, 'download', 88, '下载完成，准备应用更新...');
                }
            } else {
                // 无进度：沿用原逻辑（整包读入内存后落盘）
                $body = $this->httpGet($u);
                $ok = ($body !== false && $body !== '');
                if ($ok) {
                    $ok = @file_put_contents($save, $body) !== false;
                }
            }
            if ($ok) {
                return array('code' => 1, 'path' => $save, 'msg' => '更新包下载完成');
            }
            @unlink($save); // 清理本次失败残留，尝试下一个地址
        }
        return $this->fail('更新包下载失败，请检查服务器外网与更新源地址');
    }

    /**
     * 构造候选下载地址列表（镜像源：各镜像前缀 + 官方直连兜底；非镜像：仅原地址）
     * @param string $url    原始下载地址
     * @param string $source 更新源：github / mirror / custom
     * @return array
     */
    protected function buildCandidateUrls($url, $source)
    {
        $urls = array($url);
        if ($source === 'mirror' && strpos($url, 'https://github.com') === 0) {
            $prefixes = (isset($this->config['mirror_download_prefixes']) && is_array($this->config['mirror_download_prefixes']))
                ? $this->config['mirror_download_prefixes']
                : array(isset($this->config['mirror_download_prefix']) ? $this->config['mirror_download_prefix'] : '');
            $prefixes = array_values(array_filter(array_map('trim', $prefixes), 'strlen'));
            if (empty($prefixes) && isset($this->config['mirror_download_prefix'])) {
                $prefixes = array(trim((string) $this->config['mirror_download_prefix']));
            }
            $urls = array();
            foreach ($prefixes as $p) {
                $urls[] = $p . $url;
            }
            $urls[] = $url; // 最后直连 GitHub 官方
        }
        return $urls;
    }

    /**
     * 镜像测速选优：对候选地址逐一测速（下载前 512KB），按速度从高到低排序返回地址列表
     * @param array  $urls         候选下载地址列表
     * @param string $progressFile 进度文件绝对路径（可选）
     * @return array 按速度降序排列的地址列表
     */
    protected function speedTest($urls, $progressFile = '')
    {
        $results = array();
        $total = max(1, count($urls));
        foreach ($urls as $i => $u) {
            if ($progressFile !== '') {
                $this->writeProgressFile($progressFile, 'speed', 30 + intval(($i + 1) / $total * 20), '测速中（' . ($i + 1) . '/' . $total . '）：' . $this->shortHost($u));
            }
            $results[] = array('url' => $u, 'speed' => $this->measureSpeed($u));
        }
        usort($results, function ($a, $b) {
            return $b['speed'] - $a['speed'];
        });
        $sorted = array();
        foreach ($results as $r) {
            $sorted[] = $r['url'];
        }
        return $sorted;
    }

    /**
     * 单地址测速：curl 下载前 512KB，测量每秒字节数；失败返回 0（排序时自动靠后）
     * @param string $url
     * @return int 字节/秒
     */
    protected function measureSpeed($url)
    {
        if (!function_exists('curl_init')) {
            return 0;
        }
        $bytes = 0;
        $start = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_RANGE, '0-524287'); // 仅测速前 512KB
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (MxgtUpdate)');
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$bytes) {
            $bytes += strlen($data);
            return strlen($data);
        });
        $ok = curl_exec($ch);
        curl_close($ch);
        $elapsed = microtime(true) - $start;
        if (!$ok || $bytes <= 0 || $elapsed <= 0) {
            return 0;
        }
        return intval($bytes / $elapsed);
    }

    /**
     * 应用更新：解压、备份、覆盖 addons/mxgt 与 mxthxt，保留配置与启用状态
     * @param string $packagePath 更新包本地路径（zip）
     * @return array code:1成功 0失败; msg
     */
    public function apply($packagePath = '')
    {
        if (empty($packagePath) || !is_file($packagePath)) {
            return $this->fail('更新包文件不存在');
        }
        if (!class_exists('ZipArchive')) {
            return $this->fail('服务器缺少 ZipArchive 扩展，无法解压更新包');
        }

        $runtime = $this->runtimeDir();
        $tmp = $runtime . 'apply_' . time() . '/';
        if (!@mkdir($tmp, 0755, true)) {
            return $this->fail('无法创建临时目录：' . $tmp);
        }

        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            $this->removeDir($tmp);
            return $this->fail('更新包无法打开或已损坏');
        }
        $zip->extractTo($tmp);
        $zip->close();

        // 定位内容根目录（兼容 GitHub archive 的 {repo}-{tag}/ 单层目录）
        $root = $this->findContentRoot($tmp);
        if ($root === '') {
            $this->removeDir($tmp);
            return $this->fail('更新包内容不完整：缺少 mxgt 或 mxthxt 目录');
        }

        $hasMxgt = is_dir($root . 'mxgt') && is_file($root . 'mxgt/info.ini');
        $hasMxthxt = is_dir($root . 'mxthxt') && is_file($root . 'mxthxt/core/Update.php');
        if (!$hasMxgt && !$hasMxthxt) {
            $this->removeDir($tmp);
            return $this->fail('更新包内容不完整：mxgt/info.ini 或 mxthxt/core/Update.php 缺失');
        }

        // 备份当前代码
        $backup = $runtime . 'backup_' . date('YmdHis') . '/';
        @mkdir($backup, 0755, true);

        $errors = [];
        $addonsPath = $this->rootPath . 'addons/';

        if ($hasMxgt) {
            $dst = $addonsPath . 'mxgt/';
            $oldState = $this->readIniState($dst . 'info.ini');
            if (is_dir($dst)) {
                @rename($dst, $backup . 'mxgt');
            }
            if (!$this->copyDir($root . 'mxgt', $dst)) {
                $errors[] = 'mxgt';
            } else {
                // 合并保留旧配置（用户保存过的值），新增配置项使用新默认值
                $this->mergeConfigFile($backup . 'mxgt/config.php', $dst . 'config.php');
                // 保留插件启用状态
                if ($oldState === 1) {
                    $this->writeIniState($dst . 'info.ini', 1);
                }
            }
        }

        if ($hasMxthxt) {
            $dst = $this->rootPath . 'mxthxt/';
            if (is_dir($dst)) {
                @rename($dst, $backup . 'mxthxt');
            }
            if (!$this->copyDir($root . 'mxthxt', $dst)) {
                $errors[] = 'mxthxt';
            }
        }

        // 静态资源（static/addons/mxgt/ 下的 logo/背景图等），更新包包含时同步覆盖
        $staticSrc = $root . 'static/addons/mxgt';
        $staticDst = $this->rootPath . 'static/addons/mxgt/';
        if (is_dir($staticSrc)) {
            if (is_dir($staticDst)) {
                @rename($staticDst, $backup . 'static_mxgt');
            }
            if (!$this->copyDir($staticSrc, $staticDst)) {
                $errors[] = 'static';
            }
        }

        $this->removeDir($tmp);

        if ($errors) {
            return $this->fail('更新部分失败：' . implode('、', $errors) . '，更新前备份位于 ' . $backup);
        }
        $this->removeDir($backup);
        return ['code' => 1, 'msg' => '更新完成，已覆盖 addons/mxgt 与 mxthxt，配置与启用状态已保留'];
    }

    /**
     * 根据更新源构建更新信息接口候选地址列表（按顺序尝试）
     * - custom：仅自定义地址；
     * - github：官方源直连；
     * - mirror：候选镜像列表，全部失败后由调用方回退官方源（末尾追加官方源）。
     * @return array 接口地址列表
     */
    protected function buildApiEndpoints($source, $repo, $customUrl)
    {
        $source = $source ?: 'mirror';
        if ($source === 'custom') {
            $url = trim($customUrl);
            return $url !== '' ? array($url) : array();
        }

        $repo = trim($repo);
        if (empty($repo)) {
            $repo = isset($this->config['github_repo']) ? $this->config['github_repo'] : '';
        }
        if (empty($repo)) {
            return array();
        }

        $official = isset($this->config['github_api_url']) ? $this->config['github_api_url'] : 'https://api.github.com/repos/{repo}/releases/latest';
        if ($source === 'github') {
            return array(str_replace('{repo}', $repo, $official));
        }

        // mirror：候选镜像列表（兼容旧版单值 mirror_api_url）
        $list = (isset($this->config['mirror_api_urls']) && is_array($this->config['mirror_api_urls']))
            ? $this->config['mirror_api_urls']
            : array(isset($this->config['mirror_api_url']) ? $this->config['mirror_api_url'] : '');
        $list = array_values(array_filter(array_map('trim', $list), 'strlen'));
        if (empty($list) && isset($this->config['mirror_api_url'])) {
            $list = array(trim((string) $this->config['mirror_api_url']));
        }

        $endpoints = array();
        foreach ($list as $tpl) {
            $endpoints[] = str_replace('{repo}', $repo, $tpl);
        }
        $endpoints[] = str_replace('{repo}', $repo, $official); // 末尾回退官方源直连
        return $endpoints;
    }

    /**
     * 提取 URL 主机名（用于进度提示，避免暴露完整下载地址）
     * @param string $url
     * @return string
     */
    protected function shortHost($url)
    {
        $host = parse_url((string) $url, PHP_URL_HOST);
        return ($host !== null && $host !== false && $host !== '') ? (string) $host : (string) $url;
    }

    /**
     * HTTP GET 请求（优先 cURL，其次 file_get_contents）
     * 自动跟随重定向，并区分“连接超时”与“读取超时”，提升各镜像源兼容性。
     * @return string|false
     */
    protected function httpGet($url)
    {
        $timeout = isset($this->config['timeout']) ? intval($this->config['timeout']) : 15;
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (MxgtUpdate)');
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            return $errno === 0 ? $body : false;
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => "User-Agent: Mozilla/5.0 (MxgtUpdate)\r\n",
                'ignore_errors' => true,
            ],
        ];
        return @file_get_contents($url, false, stream_context_create($opts));
    }

    /**
     * 带进度下载：curl 边下载边写入本地文件，并通过 CURLOPT_PROGRESSFUNCTION
     * 持续更新进度文件（percent 映射到 $startPercent-$endPercent 区间，前端轮询显示）。
     * 无 curl 扩展时回退为整包下载（不产生中间进度）。
     * @param string $url           下载地址
     * @param string $saveFile      保存的本地文件
     * @param string $progressFile  进度文件
     * @param int    $startPercent  起始百分比（默认 8，测速场景从 62 起）
     * @param int    $endPercent    结束百分比（默认 88）
     * @return bool
     */
    protected function httpDownloadToFile($url, $saveFile, $progressFile, $startPercent = 8, $endPercent = 88)
    {
        if (!function_exists('curl_init')) {
            $body = $this->httpGet($url);
            if ($body === false || $body === '') {
                return false;
            }
            return @file_put_contents($saveFile, $body) !== false;
        }

        $startPercent = max(0, min(100, intval($startPercent)));
        $endPercent = max($startPercent, min(100, intval($endPercent)));
        $range = max(1, $endPercent - $startPercent);

        $fp = @fopen($saveFile, 'wb');
        if ($fp === false) {
            return false;
        }
        $timeout = isset($this->config['timeout']) ? intval($this->config['timeout']) : 15;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (MxgtUpdate)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($ch, $dlTotal, $dlNow, $ulTotal, $ulNow) use ($progressFile, $startPercent, $range) {
            if ($dlTotal > 0) {
                $percent = $startPercent + intval($dlNow / $dlTotal * $range);
                $pct = intval($dlNow / $dlTotal * 100);
                $this->writeProgressFile($progressFile, 'download', $percent, '正在下载更新包 ' . $pct . '%...');
            }
            return 0;
        });
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        fclose($fp);
        return $ok !== false && $errno === 0;
    }

    /**
     * 写入更新进度文件（JSON），供前端轮询 /addons/mxgt/admin/updateProgress 读取
     * 约定 step：check / download / apply / done / error
     * @param string $file    进度文件绝对路径
     * @param string $step    步骤
     * @param int    $percent 百分比 0-100
     * @param string $msg     提示文字
     */
    public function writeProgressFile($file, $step, $percent, $msg = '')
    {
        if ($file === '') {
            return;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode(array(
            'step' => (string) $step,
            'percent' => intval($percent),
            'msg' => (string) $msg,
            'time' => time(),
        ), JSON_UNESCAPED_UNICODE));
    }

    /**
     * 比较两个版本号（纯数字段比较，支持 v0.0.1 格式）
     * @return int -1小于 0等于 1大于
     */
    protected function compareVersion($a, $b)
    {
        $pa = explode('.', $a);
        $pb = explode('.', $b);
        $len = max(count($pa), count($pb));
        for ($i = 0; $i < $len; $i++) {
            $na = isset($pa[$i]) ? intval($pa[$i]) : 0;
            $nb = isset($pb[$i]) ? intval($pb[$i]) : 0;
            if ($na > $nb) {
                return 1;
            }
            if ($na < $nb) {
                return -1;
            }
        }
        return 0;
    }

    /**
     * 运行时目录 runtime/mxthxt/
     */
    protected function runtimeDir()
    {
        $dir = $this->rootPath . 'runtime/mxthxt/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * 在解压目录中定位内容根目录（含 mxgt/ 或 mxthxt/ 的那一层）
     */
    protected function findContentRoot($dir)
    {
        $dir = rtrim($dir, '/') . '/';
        if (is_dir($dir . 'mxgt') || is_dir($dir . 'mxthxt')) {
            return $dir;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return '';
        }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') {
                continue;
            }
            $sub = $dir . $it . '/';
            if (is_dir($sub) && (is_dir($sub . 'mxgt') || is_dir($sub . 'mxthxt'))) {
                return $sub;
            }
        }
        return '';
    }

    /**
     * 递归复制目录
     */
    protected function copyDir($src, $dst)
    {
        $src = rtrim($src, '/');
        $dst = rtrim($dst, '/');
        if (!is_dir($src)) {
            return false;
        }
        if (!is_dir($dst) && !@mkdir($dst, 0755, true)) {
            return false;
        }
        $items = @scandir($src);
        if ($items === false) {
            return false;
        }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') {
                continue;
            }
            $s = $src . '/' . $it;
            $d = $dst . '/' . $it;
            if (is_dir($s)) {
                if (!$this->copyDir($s, $d)) {
                    return false;
                }
            } else {
                if (!@copy($s, $d)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 递归删除目录
     */
    protected function removeDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') {
                continue;
            }
            $p = $dir . '/' . $it;
            if (is_dir($p)) {
                $this->removeDir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }

    /**
     * 读取 info.ini 的 state
     */
    protected function readIniState($iniFile)
    {
        if (!is_file($iniFile)) {
            return 0;
        }
        $ini = @parse_ini_file($iniFile);
        return (is_array($ini) && isset($ini['state'])) ? intval($ini['state']) : 0;
    }

    /**
     * 写回 info.ini 的 state
     */
    protected function writeIniState($iniFile, $state)
    {
        if (!is_file($iniFile)) {
            return;
        }
        $content = @file_get_contents($iniFile);
        if ($content === false) {
            return;
        }
        $content = preg_replace('/^state\s*=\s*\d+/m', 'state = ' . intval($state), $content);
        @file_put_contents($iniFile, $content);
    }

    /**
     * 用旧配置文件中用户保存过的值覆盖新配置文件的同名项（新配置项保留默认值）
     * @param string $oldFile 旧 config.php
     * @param string $newFile 新 config.php
     */
    protected function mergeConfigFile($oldFile, $newFile)
    {
        if (!is_file($newFile)) {
            return;
        }
        $new = @include $newFile;
        if (!is_array($new)) {
            return;
        }
        $old = is_file($oldFile) ? @include $oldFile : [];
        if (!is_array($old)) {
            $old = [];
        }
        $oldValues = [];
        foreach ($old as $item) {
            if (is_array($item) && isset($item['name'])) {
                $oldValues[$item['name']] = isset($item['value']) ? $item['value'] : '';
            }
        }
        // 已知错误的旧默认值不合并（使用新配置的默认值），例如早期版本误填的仓库地址
        $badOldValues = array('moxi/mxgtcms');
        $changed = false;
        foreach ($new as $k => $item) {
            if (is_array($item) && isset($item['name']) && array_key_exists($item['name'], $oldValues)) {
                if (in_array($oldValues[$item['name']], $badOldValues, true)) {
                    continue;
                }
                $new[$k]['value'] = $oldValues[$item['name']];
                $changed = true;
            }
        }
        if ($changed) {
            @file_put_contents($newFile, "<?php\n\nreturn " . var_export($new, true) . ";\n");
        }
    }

    /**
     * 返回失败结果
     */
    protected function fail($msg)
    {
        return ['code' => 0, 'has_update' => false, 'latest_version' => '', 'download_url' => '', 'zipball_url' => '', 'asset_name' => '', 'msg' => $msg];
    }
}
