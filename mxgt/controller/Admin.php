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
        $updateCoreFile = ROOT_PATH . 'mxthxt' . DS . 'Update.php';
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

        $this->assign('config', get_addon_fullconfig('mxgt'));
        return $this->fetch('admin/config');
    }

    /**
     * 在线更新：检查 → 下载 → 备份 → 覆盖（依赖根目录 mxthxt 云端更新核心）
     */
    public function doUpdate()
    {
        $updateCoreFile = ROOT_PATH . 'mxthxt' . DS . 'Update.php';
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

        // 1. 检查更新
        $result = $update->check($localVersion, $source, $repo, $customUrl);
        if (empty($result['code'])) {
            $this->error(isset($result['msg']) ? $result['msg'] : '检查更新失败，请稍后重试');
        }
        if (empty($result['has_update'])) {
            $this->error('当前已是最新版本：' . $localVersion);
        }

        // 2. 下载更新包
        $zipballUrl = isset($result['zipball_url']) ? $result['zipball_url'] : '';
        $dl = $update->download($zipballUrl, $source);
        if (empty($dl['code'])) {
            $this->error(isset($dl['msg']) ? $dl['msg'] : '更新包下载失败');
        }

        // 3. 应用更新（备份 → 覆盖 → 保留配置与启用状态）
        $ap = $update->apply(isset($dl['path']) ? $dl['path'] : '');
        if (empty($ap['code'])) {
            $this->error(isset($ap['msg']) ? $ap['msg'] : '更新应用失败');
        }

        // 4. 刷新插件缓存
        \think\Cache::rm('addons');
        \think\Cache::rm('hooks');
        \think\addons\Service::refresh();

        $this->success('在线更新完成（' . $result['latest_version'] . '）：' . $ap['msg']);
    }

    /**
     * 检查更新（对接根目录 mxthxt 云端更新核心）
     */
    public function checkUpdate()
    {
        $updateCoreFile = ROOT_PATH . 'mxthxt' . DS . 'Update.php';
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
                $this->error('发现新版本：' . $result['latest_version'] . '，当前版本：' . $localVersion . '，在线下载升级将在后续版本开放');
            }
            $this->success('当前已是最新版本：' . $localVersion);
        }
        $this->error(isset($result['msg']) ? $result['msg'] : '检查更新失败，请稍后重试');
    }
}
