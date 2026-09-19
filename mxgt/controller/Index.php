<?php
/**
 * 沫兮App控制插件 - 前台控制器
 * 路由：/index.php/addons/mxgt/index/{action}
 *
 * 提供 App 云端配置下发接口（/index.php/addons/mxgt/index/config），
 * App 将该地址填入「配置地址」即可读取插件后台设置的采集接口、播放器、公告等配置。
 */

namespace addons\mxgt\controller;

use think\addons\Controller;

class Index extends Controller
{
    /**
     * 前台入口页
     */
    public function index()
    {
        $info = get_addon_info('mxgt');
        $this->assign('addon_info', $info);
        $this->assign('version', isset($info['version']) ? $info['version'] : '');
        return $this->fetch('index/index');
    }

    /**
     * App 云端配置下发接口（TVBoxOS 兼容 JSON）
     * 由后台 Admin::buildAppConfig() 装配，App 直接读取本接口返回的 JSON。
     * 未启用 App 配置下发时返回空 JSON 对象。
     */
    public function config()
    {
        $cfg = get_addon_config('mxgt');
        $enabled = isset($cfg['app_config_enable']) && $cfg['app_config_enable'] === '0' ? false : true;
        $data = $enabled ? (new \addons\mxgt\controller\Admin())->buildAppConfig($cfg) : [];
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}