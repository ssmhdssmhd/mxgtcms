<?php
/**
 * 沫兮官替官解系统 - 后台控制器
 * 路由：/index.php/addons/mxgt/admin/{action}
 *
 * 新版苹果CMS 的插件后台页面不继承核心 Base，而是继承 think\addons\Controller，
 * 登录与节点权限在 _initialize() 中自行校验。
 */

namespace addons\mxgt\controller;

use think\addons\Controller;
use think\addons\Service;

class Admin extends Controller
{
    /**
     * 鉴权：必须为已登录的苹果CMS后台管理员，且拥有插件节点权限
     */
    protected function _initialize()
    {
        parent::_initialize();

        if (session('admin_auth') !== '1' || empty(session('admin_info'))) {
            self::deny('未登录或登录已过期，请先登录苹果CMS后台');
        }
        $info = session('admin_info');
        if (!is_array($info) || empty($info['admin_id'])) {
            self::deny('管理员信息异常，请重新登录');
        }
        // 超级管理员（admin_id=1）直接放行；其余校验节点权限
        if ((string) $info['admin_id'] !== '1') {
            $auths = ',' . (isset($info['admin_auth']) ? (string) $info['admin_auth'] : '') . ',';
            if (strpos($auths, ',addons/mxgt/admin/*,') === false) {
                self::deny('无权访问该插件');
            }
        }

        // 插件独立登录校验：未登录时（登录/退出动作除外）一律跳转到弹窗登录页
        $action = strtolower($this->request->action());
        if (session('mxgt_login') !== 1 && !in_array($action, ['login', 'doLogin', 'logout'], true)) {
            $this->redirect(addon_url('mxgt/admin/login'));
            exit;
        }
    }

    /**
     * 插件独立登录页（弹窗式，展示于苹果CMS后台右侧内容区）
     */
    public function login()
    {
        if (session('mxgt_login') === 1) {
            $this->redirect(addon_url('mxgt/admin/index'));
        }
        return $this->fetch('admin/login');
    }

    /**
     * 插件独立登录校验（AJAX，返回 JSON）
     * 账号密码读取插件配置：login_user / login_pass；两者均为空时视为免密登录
     */
    public function doLogin()
    {
        if (!$this->request->isPost()) {
            $this->jsonOut(['code' => 0, 'msg' => '非法请求']);
        }
        $username = trim((string) $this->request->post('username'));
        $password = (string) $this->request->post('password');

        $cfg = get_addon_config('mxgt');
        $cu = isset($cfg['login_user']) ? trim((string) $cfg['login_user']) : 'admin';
        $cp = isset($cfg['login_pass']) ? (string) $cfg['login_pass'] : 'admin888';

        if (($cu === '' && $cp === '') || ($username === $cu && $password === $cp)) {
            session('mxgt_login', 1);
            $this->jsonOut(['code' => 1, 'msg' => '登录成功']);
        }
        $this->jsonOut(['code' => 0, 'msg' => '用户名或密码错误']);
    }

    /**
     * 退出插件独立登录
     */
    public function logout()
    {
        session('mxgt_login', null);
        $this->success('已退出登录', addon_url('mxgt/admin/login'));
    }

    /**
     * 鉴权失败出口（直出 HTML 并终止）
     */
    protected static function deny($msg, $status = 403)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(intval($status));
        }
        echo '<meta charset="utf-8"><div style="padding:40px;font-size:14px;color:#666;text-align:center">'
            . htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') . '</div>';
        exit;
    }

    /**
     * 首页看板
     */
    public function index()
    {
        $info = get_addon_info('mxgt');
        $version = isset($info['version']) ? $info['version'] : '';
        $state = isset($info['state']) ? intval($info['state']) : 0;

        // 元数据表状态
        $metaInstalled = 0;
        $installedAt = '';
        try {
            $row = \think\Db::name('mxgt_meta')->where('meta_key', 'installed_at')->find();
            if (!empty($row)) {
                $metaInstalled = 1;
                $installedAt = $row['meta_value'];
            }
        } catch (\Throwable $e) {
            $metaInstalled = 0;
        }

        // 云端更新组件（mxthxt 需放在苹果CMS根目录，与 addons 同级）
        $updateCoreFile = $this->updateCoreFile();
        $updateCoreExists = is_file($updateCoreFile) ? 1 : 0;

        // 当前配置（模板中也可用 {$config.xxx}）
        $cfg = get_addon_config('mxgt');

        $this->assign('addon_info', $info);
        $this->assign('version', $version);
        $this->assign('state', $state);
        $this->assign('meta_installed', $metaInstalled);
        $this->assign('installed_at', $installedAt);
        $this->assign('update_core_exists', $updateCoreExists);
        $this->assign('update_source', isset($cfg['update_source']) ? $cfg['update_source'] : '');
        // 版本对比信息（本地 vs 远程，带缓存）
        $this->assign('vinfo', $this->cachedVersionCheck());
        $this->assign('active', 'index');
        return $this->fetch('admin/index');
    }

    /**
     * 插件自带配置页（保存走框架 set_addon_fullconfig，与后台「设置」同源）
     */
    public function config()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (!is_array($params)) {
                $params = [];
            }
            $full = get_addon_fullconfig('mxgt');
            if (!is_array($full)) {
                $full = [];
            }
            foreach ($full as &$item) {
                if (isset($params[$item['name']])) {
                    $item['value'] = is_array($params[$item['name']])
                        ? implode(',', $params[$item['name']])
                        : (string) $params[$item['name']];
                }
            }
            unset($item);
            set_addon_fullconfig('mxgt', $full);
            Service::refresh();
            Cache('addons', null);
            Cache('hooks', null);
            $this->success('配置保存成功', addon_url('mxgt/admin/config'));
        }

        $force = intval($this->request->get('check', 0));
        $this->assign('vinfo', $this->cachedVersionCheck($force === 1));
        $this->assign('config', get_addon_fullconfig('mxgt'));
        $this->assign('active', 'config');
        return $this->fetch('admin/config');
    }

    /**
     * 在线更新：检查 → 下载 → 备份 → 覆盖（依赖根目录 mxthxt 云端更新核心）
     * 全程向 runtime/mxthxt/update_progress.json 写入进度，前端轮询 updateProgress 显示进度条。
     */
    public function doUpdate()
    {
        $progressFile = ROOT_PATH . 'runtime/mxthxt' . DS . 'update_progress.json';
        $updateCoreFile = $this->updateCoreFile();
        if (!is_file($updateCoreFile)) {
            $this->writeProgress($progressFile, 'error', 0, '未检测到云端更新组件，请将 mxthxt 文件夹上传至苹果CMS根目录（与 addons 同级）');
            $this->jsonOut(['code' => 0, 'msg' => '未检测到云端更新组件，请将 mxthxt 文件夹上传至苹果CMS根目录（与 addons 同级）']);
        }
        require_once $updateCoreFile;
        $update = new \MxthxtUpdate();

        $info = get_addon_info('mxgt');
        $localVersion = isset($info['version']) ? $info['version'] : '';

        $cfg = get_addon_config('mxgt');
        $source = isset($cfg['update_source']) ? $cfg['update_source'] : 'mirror';
        $repo = isset($cfg['github_repo']) ? $cfg['github_repo'] : '';
        $customUrl = isset($cfg['update_custom_url']) ? $cfg['update_custom_url'] : '';

        // 1. 检查更新
        $this->writeProgress($progressFile, 'check', 5, '正在检查远程更新源...');
        $result = $update->check($localVersion, $source, $repo, $customUrl);
        if (empty($result['code'])) {
            $msg = isset($result['msg']) ? $result['msg'] : '检查更新失败，请稍后重试';
            $this->writeProgress($progressFile, 'error', 0, $msg);
            $this->jsonOut(['code' => 0, 'msg' => $msg]);
        }
        if (empty($result['has_update'])) {
            $msg = '当前已是最新版本：' . $localVersion;
            $this->writeProgress($progressFile, 'done', 100, $msg);
            $this->jsonOut(['code' => 0, 'msg' => $msg]);
        }

        // 2. 下载更新包（优先发行版资产，未配置时回退源码 zip），带进度
        $zipballUrl = isset($result['download_url']) ? $result['download_url'] : '';
        if ($zipballUrl === '') {
            $zipballUrl = isset($result['zipball_url']) ? $result['zipball_url'] : '';
        }
        $dl = $update->download($zipballUrl, $source, $progressFile);
        if (empty($dl['code'])) {
            $msg = isset($dl['msg']) ? $dl['msg'] : '更新包下载失败';
            $this->writeProgress($progressFile, 'error', 0, $msg);
            $this->jsonOut(['code' => 0, 'msg' => $msg]);
        }

        // 3. 应用更新（备份 → 覆盖 → 保留配置与启用状态）
        $this->writeProgress($progressFile, 'apply', 88, '正在应用更新（备份/覆盖/保留配置）...');
        $ap = $update->apply(isset($dl['path']) ? $dl['path'] : '');
        if (empty($ap['code'])) {
            $msg = isset($ap['msg']) ? $ap['msg'] : '更新应用失败';
            $this->writeProgress($progressFile, 'error', 0, $msg);
            $this->jsonOut(['code' => 0, 'msg' => $msg]);
        }

        // 4. 刷新插件缓存
        \think\Cache::rm('addons');
        \think\Cache::rm('hooks');
        \think\addons\Service::refresh();
        // 5. 清除版本检查缓存
        $checkCache = ROOT_PATH . 'runtime/mxthxt/check_cache.json';
        if (is_file($checkCache)) {
            @unlink($checkCache);
        }

        $msg = '在线更新完成（' . $result['latest_version'] . '）：' . $ap['msg'];
        $this->writeProgress($progressFile, 'done', 100, $msg);
        $this->jsonOut(['code' => 1, 'msg' => $msg]);
    }

    /**
     * 在线更新进度（前端轮询）
     * 返回 runtime/mxthxt/update_progress.json 当前进度：step/percent/msg/time
     */
    public function updateProgress()
    {
        $file = ROOT_PATH . 'runtime/mxthxt' . DS . 'update_progress.json';
        $data = array('step' => 'idle', 'percent' => 0, 'msg' => '暂无进行中的更新', 'time' => time());
        if (is_file($file)) {
            $d = json_decode(@file_get_contents($file), true);
            if (is_array($d)) {
                $data = $d;
            }
        }
        $this->jsonOut($data);
    }

    /**
     * 输出 JSON 并终止（兼容 AJAX 与直接访问）
     * @param array $data
     */
    protected function jsonOut($data)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 写入更新进度文件
     * @param string $file
     * @param string $step
     * @param int    $percent
     * @param string $msg
     */
    protected function writeProgress($file, $step, $percent, $msg = '')
    {
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
     * 检查更新（对接根目录 mxthxt 云端更新核心）
     */
    public function checkUpdate()
    {
        $updateCoreFile = $this->updateCoreFile();
        if (!is_file($updateCoreFile)) {
            $this->error('未检测到云端更新组件，请将 mxthxt 文件夹上传至苹果CMS根目录（与 addons 同级）');
        }
        require_once $updateCoreFile;
        $update = new \MxthxtUpdate();

        $info = get_addon_info('mxgt');
        $localVersion = isset($info['version']) ? $info['version'] : '';

        $cfg = get_addon_config('mxgt');
        $source = isset($cfg['update_source']) ? $cfg['update_source'] : 'mirror';
        $repo = isset($cfg['github_repo']) ? $cfg['github_repo'] : '';
        $customUrl = isset($cfg['update_custom_url']) ? $cfg['update_custom_url'] : '';

        $result = $update->check($localVersion, $source, $repo, $customUrl);

        if (isset($result['code']) && $result['code'] === 1) {
            if (!empty($result['has_update'])) {
                $this->error('发现新版本：' . $result['latest_version'] . '，当前版本：' . $localVersion . '，请到「插件配置 → 在线更新」执行升级');
            }
            $this->success('当前已是最新版本：' . $localVersion);
        }
        $this->error(isset($result['msg']) ? $result['msg'] : '检查更新失败，请稍后重试');
    }

    /**
     * 获取版本对比信息（本地 vs 远程）
     * 远程检查结果缓存 10 分钟，避免每次打开页面都请求更新源；
     * 传入 $force=true 强制重新检查。
     * @param bool $force
     * @return array ok:检查是否成功; local_version; remote_version; has_update; msg; time
     */
    protected function cachedVersionCheck($force = false)
    {
        $cacheFile = ROOT_PATH . 'runtime/mxthxt/check_cache.json';
        $ttl = 600;

        if (!$force && is_file($cacheFile) && (time() - @filemtime($cacheFile)) < $ttl) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $info = get_addon_info('mxgt');
        $localVersion = isset($info['version']) ? $info['version'] : '';
        $cfg = get_addon_config('mxgt');
        $source = isset($cfg['update_source']) ? $cfg['update_source'] : 'mirror';
        $repo = isset($cfg['github_repo']) ? $cfg['github_repo'] : '';
        $customUrl = isset($cfg['update_custom_url']) ? $cfg['update_custom_url'] : '';

        $data = [
            'ok' => 0,
            'local_version' => $localVersion,
            'remote_version' => '',
            'has_update' => 0,
            'msg' => '未检测到云端更新组件（mxthxt）',
            'time' => time(),
        ];

        $updateCoreFile = $this->updateCoreFile();
        if (is_file($updateCoreFile)) {
            require_once $updateCoreFile;
            try {
                $update = new \MxthxtUpdate();
                $result = $update->check($localVersion, $source, $repo, $customUrl);
                if (isset($result['code']) && $result['code'] === 1) {
                    $data['ok'] = 1;
                    $data['remote_version'] = isset($result['latest_version']) ? $result['latest_version'] : '';
                    $data['has_update'] = !empty($result['has_update']) ? 1 : 0;
                    $data['msg'] = isset($result['msg']) ? $result['msg'] : '';
                } else {
                    $data['msg'] = isset($result['msg']) ? $result['msg'] : '检查更新失败';
                }
            } catch (\Throwable $e) {
                $data['msg'] = '检查更新异常：' . $e->getMessage();
            }
        }

        @mkdir(ROOT_PATH . 'runtime/mxthxt', 0755, true);
        @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }

    /**
     * 云端更新核心文件路径（mxthxt/core/Update.php）
     * @return string
     */
    protected function updateCoreFile()
    {
        return ROOT_PATH . 'mxthxt' . DS . 'core' . DS . 'Update.php';
    }
}
