<?php
/**
 * 沫兮官替官解系统 - 前台控制器
 * 路由：addons/mxgt/index/{action}
 */

namespace addons\mxgt\controller;

class Index extends \think\Controller
{
    // 插件目录名
    protected $addonName = 'mxgt';

    /**
     * 前台入口页
     */
    public function index()
    {
        $versionFile = ADDON_PATH . $this->addonName . DS . 'version.php';
        $versionInfo = is_file($versionFile) ? include $versionFile : [];
        $this->assign('versionInfo', $versionInfo);
        return $this->view->fetch('index_index', [], [
            'view_path' => ADDON_PATH . $this->addonName . DS . 'view' . DS,
        ]);
    }
}
