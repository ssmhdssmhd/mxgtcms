<?php
/**
 * 沫兮官替官解系统 - 前台控制器
 * 路由：/index.php/addons/mxgt/index/{action}
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
}
