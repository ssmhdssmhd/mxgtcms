<?php
/**
 * 沫兮官替官解系统 - 后台控制器
 * 路由：addons/mxgt/admin/{action}
 */

namespace addons\mxgt\controller;

use think\Db;
use addons\mxgt\model\MxgtConfig;

class Admin extends \app\admin\controller\Base
{
    // 插件目录名
    protected $addonName = 'mxgt';

    /**
     * 首页看板
     */
    public function index()
    {
        // 插件版本信息
        $versionFile = ADDON_PATH . $this->addonName . DS . 'version.php';
        $versionInfo = is_file($versionFile) ? include $versionFile : [];

        // 插件状态（addon.php 中的 status，由苹果CMS维护）
        $addonFile = ADDON_PATH . $this->addonName . DS . 'addon.php';
        $addonInfo = is_file($addonFile) ? include $addonFile : [];
        $enabled = isset($addonInfo['status']) ? intval($addonInfo['status']) : 0;

        // 数据表是否已安装
        $tableName = config('database.prefix') . 'mxgt_config';
        $installed = 0;
        try {
            $installed = Db::query("SHOW TABLES LIKE '{$tableName}'") ? 1 : 0;
        } catch (\Exception $e) {
            $installed = 0;
        }

        // 云端更新组件是否存在（mxthxt 需放在苹果CMS根目录）
        $updateCoreFile = ROOT_PATH . 'mxthxt' . DS . 'Update.php';
        $updateCoreExists = is_file($updateCoreFile) ? 1 : 0;

        $this->assign('versionInfo', $versionInfo);
        $this->assign('enabled', $enabled);
        $this->assign('installed', $installed);
        $this->assign('updateCoreExists', $updateCoreExists);
        return $this->addonFetch('admin_index');
    }

    /**
     * 插件配置页
     */
    public function config()
    {
        if (request()->isPost()) {
            $param = input('param.');
            $config = isset($param['config']) ? $param['config'] : [];
            if (!is_array($config)) {
                $config = [];
            }
            foreach ($config as $k => $v) {
                MxgtConfig::setConfig($k, is_array($v) ? json_encode($v) : $v);
            }
            $this->success('配置保存成功', mac_url('addons/mxgt/admin/config'));
        }

        // 配置项定义
        $configFile = ADDON_PATH . $this->addonName . DS . 'config.php';
        $configList = is_file($configFile) ? include $configFile : [];

        // 已保存的配置（覆盖默认值）
        $saved = MxgtConfig::allConfig();
        foreach ($configList as &$item) {
            if (isset($saved[$item['name']])) {
                $item['value'] = $saved[$item['name']];
            }
            $item['default'] = isset($item['value']) ? $item['value'] : '';
        }
        unset($item);

        $this->assign('configList', $configList);
        return $this->addonFetch('admin_config');
    }

    /**
     * 检查更新（对接 mxthxt 云端更新核心）
     */
    public function checkUpdate()
    {
        $updateCoreFile = ROOT_PATH . 'mxthxt' . DS . 'Update.php';
        if (!is_file($updateCoreFile)) {
            $this->error('未检测到云端更新组件，请将 mxthxt 文件夹上传至苹果CMS根目录（与 addons 同级）');
        }

        require_once $updateCoreFile;
        $update = new \MxthxtUpdate();

        // 插件本地版本
        $versionFile = ADDON_PATH . $this->addonName . DS . 'version.php';
        $versionInfo = is_file($versionFile) ? include $versionFile : [];
        $localVersion = isset($versionInfo['version']) ? $versionInfo['version'] : '';

        // 更新源配置
        $source = MxgtConfig::getConfig('update_source', 'mirror');
        $customUrl = MxgtConfig::getConfig('update_custom_url', '');
        $repo = MxgtConfig::getConfig('github_repo', '');

        $result = $update->check($localVersion, $source, $repo, $customUrl);

        if (isset($result['code']) && $result['code'] === 1) {
            if (!empty($result['has_update'])) {
                $this->error('发现新版本：' . $result['latest_version'] . '，当前版本：' . $localVersion . '，在线下载升级将在后续版本开放。');
            }
            $this->success('当前已是最新版本：' . $localVersion);
        }
        $this->error(isset($result['msg']) ? $result['msg'] : '检查更新失败，请稍后重试');
    }

    /**
     * 渲染插件模板（显式指定 addons/mxgt/view 模板目录）
     * @param string $template 模板名（不含后缀，如 admin_index）
     * @return string
     */
    protected function addonFetch($template, $vars = [])
    {
        return $this->view->fetch($template, $vars, [
            'view_path' => ADDON_PATH . $this->addonName . DS . 'view' . DS,
        ]);
    }
}
