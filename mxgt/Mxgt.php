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
        $this->setup();
        $this->registerMenu();
        return true;
    }

    /**
     * 启用（框架 Service::enable() 调用，先于 info.ini state 写回）
     */
    public function enable()
    {
        $this->setup();
        $this->registerMenu();
        return true;
    }

    /**
     * 停用：数据一律保留，重新启用后仍可恢复；同时移除后台快捷菜单
     */
    public function disable()
    {
        $this->unregisterMenu();
        return true;
    }

    /**
     * 卸载：删除元数据表，清理启用时复制到核心目录的残留，并移除后台快捷菜单
     */
    public function uninstall()
    {
        // 移除后台快捷菜单
        $this->unregisterMenu();

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
     * 注册后台快捷菜单（侧边栏入口）
     * 苹果CMS 后台「自定义菜单配置」存储于 application/extra/quickmenu.php（新版本）
     * 或 application/data/config/quickmenu.txt（旧版本），每行格式：菜单名,链接地址。
     * 重复注册时幂等（已存在同名菜单则不重复添加）。
     * @return bool
     */
    private function registerMenu()
    {
        $line = $this->menuLine();
        $menus = $this->readQuickMenu();
        foreach ($menus as $item) {
            if (trim($item) === $line) {
                return true;
            }
        }
        $menus[] = $line;
        return $this->writeQuickMenu($menus);
    }

    /**
     * 移除后台快捷菜单（停用/卸载时调用），幂等
     * @return bool
     */
    private function unregisterMenu()
    {
        $line = $this->menuLine();
        $menus = $this->readQuickMenu();
        $kept = [];
        foreach ($menus as $item) {
            if (trim($item) === $line) {
                continue;
            }
            $kept[] = $item;
        }
        if (count($kept) === count($menus)) {
            return true;
        }
        return $this->writeQuickMenu($kept);
    }

    /**
     * 快捷菜单单行内容：菜单名,插件首页链接
     * 链接以 / 开头（绝对路径），苹果CMS 后台 Index::index() 会直接使用该 URL。
     * @return string
     */
    private function menuLine()
    {
        return '沫兮官替官解系统,/index.php/addons/mxgt/admin/index';
    }

    /**
     * 读取当前快捷菜单配置（数组，每项为一行“名称,链接”）
     * @return array
     */
    private function readQuickMenu()
    {
        // 新版本：application/extra/quickmenu.php（config('quickmenu')）
        $menus = Config::get('quickmenu');
        if (is_array($menus) && !empty($menus)) {
            return array_values($menus);
        }
        // 旧版本：application/data/config/quickmenu.txt
        $txtFile = APP_PATH . 'data' . DS . 'config' . DS . 'quickmenu.txt';
        if (is_file($txtFile)) {
            $content = @file_get_contents($txtFile);
            $arr = explode(chr(13), (string) $content);
            $arr = array_map('trim', $arr);
            return array_values(array_filter($arr, 'strlen'));
        }
        return [];
    }

    /**
     * 写入快捷菜单配置
     * 优先写入新版本 extra/quickmenu.php（mac_arr2file），失败时回退旧版本 txt 文件。
     * @param array $menus
     * @return bool
     */
    private function writeQuickMenu($menus)
    {
        $menus = array_values(array_filter(array_map('trim', $menus), 'strlen'));
        $extraFile = APP_PATH . 'extra' . DS . 'quickmenu.php';
        if (function_exists('mac_arr2file')) {
            $res = mac_arr2file($extraFile, $menus);
            if ($res !== false) {
                return true;
            }
        }
        // 回退：旧版本 txt 文件
        $txtFile = APP_PATH . 'data' . DS . 'config' . DS . 'quickmenu.txt';
        $txtDir = dirname($txtFile);
        if (!is_dir($txtDir)) {
            @mkdir($txtDir, 0755, true);
        }
        return @file_put_contents($txtFile, implode(chr(13), $menus)) !== false;
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
