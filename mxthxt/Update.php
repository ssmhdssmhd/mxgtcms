<?php
/**
 * 沫兮官替官解系统 - 云端更新核心（骨架）
 * 本文件位于苹果CMS根目录/mxthxt/Update.php
 *
 * 职责：
 * 1. check()      检查远端最新版本，与本地版本对比；
 * 2. download()   下载更新包（后续迭代实现）；
 * 3. apply()      解压并覆盖更新（后续迭代实现）。
 */

class MxthxtUpdate
{
    protected $config = [];

    public function __construct()
    {
        $configFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($configFile)) {
            $cfg = include $configFile;
            if (is_array($cfg)) {
                $this->config = $cfg;
            }
        }
    }

    /**
     * 检查更新
     * @param string $localVersion 本地插件版本号，如 v0.0.1
     * @param string $source       更新源：github / mirror / custom
     * @param string $repo         GitHub 仓库，格式 用户名/仓库名
     * @param string $customUrl    自定义更新接口地址
     * @return array code:1成功 0失败; has_update:是否有新版本; latest_version:最新版本; msg:提示
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
            'msg' => $compare > 0 ? '发现新版本 v' . $latestVersion : '当前已是最新版本',
        ];
    }

    /**
     * 下载更新包（后续迭代实现）
     */
    public function download($version, $source = 'mirror', $repo = '', $customUrl = '')
    {
        return $this->fail('在线下载升级功能将在后续版本开放');
    }

    /**
     * 解压并应用更新（后续迭代实现）
     */
    public function apply($packagePath)
    {
        return $this->fail('在线安装升级功能将在后续版本开放');
    }

    /**
     * 根据更新源构建请求地址
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
     * 返回失败结果
     */
    protected function fail($msg)
    {
        return ['code' => 0, 'has_update' => false, 'latest_version' => '', 'msg' => $msg];
    }
}
