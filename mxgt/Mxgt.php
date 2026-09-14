<?php
/**
 * 沫兮官替官解系统 - 插件入口
 *
 * 新版苹果CMS（fastadmin-addons 规范）要求插件目录 addons/mxgt/ 内必须存在
 * 主入口文件 Mxgt.php 与元信息文件 info.ini，后台才能识别、启用与卸载。
 *
 * 注意：
 * 1. 插件元信息的唯一来源是 info.ini（name/title/version/state...），本类不重复声明；
 * 2. 除 install/enable/disable/uninstall 与钩子方法（如 appInit）外，
 *    不要声明其它 public 方法，否则会被插件框架自动注册为全局钩子；
 * 3. 本插件的配置由苹果CMS框架管理（addons/mxgt/config.php 文件），
 *    无需数据库配置表。
 */

namespace addons\mxgt;

use think\Config;
use think\Db;

class Mxgt extends \think\Addons
{
    /** 本次请求是否为兼容模式 URL（?s=/addons/mxgt/...） */
    public static $compatUrl = false;

    /**
     * 安装：目录分发方式（直接放入 addons/）后台只有「启用」入口，
     * 不会走 Service::install()；为保证两条路径都能建表，install() 与
     * enable() 都调用幂等的 setup()。
     */
    public function install()
    {
        return $this->setup();
    }

    /**
     * 启用（框架 Service::enable() 调用，先于 info.ini state 写回）
     */
    public function enable()
    {
        return $this->setup();
    }

    /**
     * 停用：数据一律保留，重新启用后仍可恢复
     */
    public function disable()
    {
        return true;
    }

    /**
     * 卸载：删除元数据表，并清理启用时复制到核心目录的残留
     */
    public function uninstall()
    {
        // 删除元数据表
        try {
            Db::execute('DROP TABLE IF EXISTS `' . config('database.prefix') . 'mxgt_meta`');
        } catch (\Throwable $e) {
            // 表不存在等异常不阻断卸载
        }

        // Service::uninstall() 只回收 static/addons/mxgt/，
        // enable() 铺出去的另外两份残留需在此自行清理
        if (function_exists('rmdirs')) {
            $leftovers = array(
                APP_PATH . 'admin' . DS . 'view_new' . DS . 'mxgt' . DS,
                ROOT_PATH . 'static_new' . DS . 'addons' . DS . 'mxgt' . DS,
                ROOT_PATH . 'static_new' . DS . 'mxgt' . DS,
            );
            foreach ($leftovers as $dir) {
                if (is_dir($dir)) {
                    @rmdirs($dir);
                }
            }
        }
        return true;
    }

    /**
     * 钩子：app_init
     * 苹果CMS 默认 url_route_on=false，addons/:addon 路由匹配不到。
     * 仅当请求确实指向本插件时才打开路由检查，避免影响全站 URL 解析。
     * 同时识别兼容模式 ?s= 请求。
     */
    public function appInit()
    {
        $pathVar = (string) Config::get('var_pathinfo');
        $compatPath = ($pathVar !== '' && isset($_GET[$pathVar]) && is_string($_GET[$pathVar]))
            ? $_GET[$pathVar] : '';
        if ($compatPath !== '') {
            self::$compatUrl = true;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = strtok($uri, '?');
        $hit = ($path !== false && strpos($path, 'addons/mxgt') !== false)
            || ($compatPath !== '' && strpos($compatPath, 'addons/mxgt') !== false);
        if ($hit) {
            \think\App::route(true);
        }
    }

    /**
     * 安装/启用公共逻辑（幂等）
     * @return bool
     */
    private function setup()
    {
        $this->ensureMeta();
        return true;
    }

    /**
     * 确保元数据表存在并写入安装记录（幂等）
     */
    private function ensureMeta()
    {
        $prefix = config('database.prefix');
        try {
            Db::execute("CREATE TABLE IF NOT EXISTS `{$prefix}mxgt_meta` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `meta_key` varchar(100) NOT NULL DEFAULT '' COMMENT '键',
                `meta_value` varchar(255) NOT NULL DEFAULT '' COMMENT '值',
                `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_meta_key` (`meta_key`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='沫兮官替官解系统-元数据表'");

            $row = Db::name('mxgt_meta')->where('meta_key', 'installed_at')->find();
            if (empty($row)) {
                Db::name('mxgt_meta')->insert(array(
                    'meta_key' => 'installed_at',
                    'meta_value' => date('Y-m-d H:i:s'),
                    'update_time' => time(),
                ));
            }
        } catch (\Throwable $e) {
            // 建表失败不阻断安装（如库账号无建表权限），运行期可重试
        }
    }
}
