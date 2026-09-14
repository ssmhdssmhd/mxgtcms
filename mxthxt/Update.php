<?php
/**
 * 沫兮官替官解系统 - 云端更新核心
 * 本文件位于苹果CMS根目录/mxthxt/Update.php
 *
 * 职责：
 * 1. check()    检查远端最新版本，与本地版本对比，并返回更新包下载地址；
 * 2. download() 下载更新包（GitHub 官方源 / 国内镜像 / 自定义源）；
 * 3. apply()    解压更新包，备份现有代码后覆盖 addons/mxgt 与 mxthxt，
 *               并保留站长的配置与插件启用状态。
 *
 * 更新包约定：zip 内包含 mxgt/（含 info.ini）与 mxthxt/（含 Update.php）目录，
 * 与 GitHub 仓库 tag 的 archive zip（{repo}-{tag}/ 单层目录）结构兼容。
 */

class MxthxtUpdate
{
    protected $config = [];
    protected $rootPath = '';

    public function __construct()
    {
        $configFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
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
     * @param string $localVersion 本地插件版本号，如 v0.0.1
     * @param string $source       更新源：github / mirror / custom
     * @param string $repo         GitHub 仓库，格式 用户名/仓库名
     * @param string $customUrl    自定义更新接口地址
     * @return array code:1成功 0失败; has_update; latest_version; zipball_url; msg
     */
    public function check($localVersion = '', $source = 'mirror', $repo = '', $customUrl = '')
    {
        $apiUrl = $this->buildApiUrl($source, $repo, $customUrl);
        if (empty($apiUrl)) {
            return $this->fail('更新源配置不完整：请填写 GitHub 仓库或自定义更新地址');
        }

        $json = $this->httpGet($apiUrl);
        if ($json === false) {
            return $this->fail('请求更新源失败，请检查服务器外网与更新源地址');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['tag_name'])) {
            return $this->fail('更新源返回数据异常，请稍后重试');
        }

        $latestVersion = ltrim(trim($data['tag_name']), 'vV');
        $compare = $this->compareVersion($latestVersion, ltrim($localVersion, 'vV'));

        return [
            'code' => 1,
            'has_update' => $compare > 0,
            'latest_version' => 'v' . $latestVersion,
            'local_version' => $localVersion,
            'zipball_url' => isset($data['zipball_url']) ? (string) $data['zipball_url'] : '',
            'msg' => $compare > 0 ? '发现新版本 v' . $latestVersion : '当前已是最新版本',
        ];
    }

    /**
     * 下载更新包到 runtime/mxthxt/，返回本地文件路径
     * @param string $zipballUrl 更新包下载地址（GitHub zipball_url 或自定义地址）
     * @param string $source     更新源：github / mirror / custom
     * @return array code:1成功 0失败; path:本地文件路径; msg
     */
    public function download($zipballUrl = '', $source = 'mirror')
    {
        $url = trim((string) $zipballUrl);
        if ($url === '') {
            return $this->fail('更新包下载地址为空');
        }

        // 国内镜像：给 GitHub 官方下载地址加镜像前缀加速
        if ($source === 'mirror' && strpos($url, 'https://github.com') === 0) {
            $prefix = isset($this->config['mirror_download_prefix']) ? (string) $this->config['mirror_download_prefix'] : 'https://mirror.ghproxy.com/';
            $url = $prefix . $url;
        }

        $body = $this->httpGet($url);
        if ($body === false || $body === '') {
            return $this->fail('更新包下载失败，请检查服务器外网与更新源地址');
        }

        $dir = $this->runtimeDir();
        $save = $dir . 'update_' . date('YmdHis') . '.zip';
        if (@file_put_contents($save, $body) === false) {
            return $this->fail('更新包保存失败，请检查 runtime 目录写入权限');
        }
        return ['code' => 1, 'path' => $save, 'msg' => '更新包下载完成'];
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
        $hasMxthxt = is_dir($root . 'mxthxt') && is_file($root . 'mxthxt/Update.php');
        if (!$hasMxgt && !$hasMxthxt) {
            $this->removeDir($tmp);
            return $this->fail('更新包内容不完整：mxgt/info.ini 或 mxthxt/Update.php 缺失');
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

        $this->removeDir($tmp);

        if ($errors) {
            return $this->fail('更新部分失败：' . implode('、', $errors) . '，更新前备份位于 ' . $backup);
        }
        $this->removeDir($backup);
        return ['code' => 1, 'msg' => '更新完成，已覆盖 addons/mxgt 与 mxthxt，配置与启用状态已保留'];
    }

    /**
     * 根据更新源构建更新信息接口地址
     */
    protected function buildApiUrl($source, $repo, $customUrl)
    {
        $source = $source ?: 'mirror';
        if ($source === 'custom') {
            return trim($customUrl);
        }

        $repo = trim($repo);
        if (empty($repo)) {
            $repo = isset($this->config['github_repo']) ? $this->config['github_repo'] : '';
        }
        if (empty($repo)) {
            return '';
        }

        $tpl = $source === 'github'
            ? (isset($this->config['github_api_url']) ? $this->config['github_api_url'] : 'https://api.github.com/repos/{repo}/releases/latest')
            : (isset($this->config['mirror_api_url']) ? $this->config['mirror_api_url'] : 'https://mirror.ghproxy.com/https://api.github.com/repos/{repo}/releases/latest');

        return str_replace('{repo}', $repo, $tpl);
    }

    /**
     * HTTP GET 请求（优先 cURL，其次 file_get_contents）
     * @return string|false
     */
    protected function httpGet($url)
    {
        $timeout = isset($this->config['timeout']) ? intval($this->config['timeout']) : 15;
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
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
        $changed = false;
        foreach ($new as $k => $item) {
            if (is_array($item) && isset($item['name']) && array_key_exists($item['name'], $oldValues)) {
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
        return ['code' => 0, 'has_update' => false, 'latest_version' => '', 'zipball_url' => '', 'msg' => $msg];
    }
}
