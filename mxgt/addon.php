<?php
/**
 * 沫兮官替官解系统 - 插件主配置
 *
 * 注意：本文件必须位于 addons/mxgt/addon.php，
 * 苹果CMS 后台通过读取本文件来识别插件，并依据
 * tables / install_sql / uninstall_sql / menu 完成
 * 安装、启用、停用、卸载等操作。
 */

return [
    // 插件标识（必须与目录名 mxgt 一致）
    'name' => 'mxgt',
    // 插件名称
    'title' => '沫兮官替官解系统',
    // 插件版本号（与 version.php 保持一致）
    'version' => 'v0.0.1',
    // 作者
    'author' => '沫兮',
    // 插件描述
    'description' => '沫兮官替官解系统：新版苹果CMS后台完美使用的官方替换/官方解析插件，支持在线更新与GitHub国内镜像远程更新。',
    // 是否系统内置（0 否 1 是）
    'system' => 0,
    // 插件状态（1 启用 0 停用，由苹果CMS后台维护）
    'status' => 1,
    // 安装时创建的数据表（{pre} 会被替换为数据库表前缀）
    'tables' => [
        "CREATE TABLE IF NOT EXISTS `{pre}mxgt_config` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
            `key` varchar(100) NOT NULL DEFAULT '' COMMENT '配置键',
            `value` text COMMENT '配置值',
            `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_key` (`key`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='沫兮官替官解系统-配置表';",
    ],
    // 安装SQL文件（相对本插件目录）
    'install_sql' => 'install.sql',
    // 卸载SQL文件（相对本插件目录）
    'uninstall_sql' => 'uninstall.sql',
    // 后台菜单（启用后显示在后台左侧）
    'menu' => [
        [
            'title' => '沫兮官替官解',
            'url' => 'addons/mxgt/admin/index',
            'icon' => 'layui-icon layui-icon-app',
        ],
        [
            'title' => '插件配置',
            'url' => 'addons/mxgt/admin/config',
            'icon' => 'layui-icon layui-icon-set',
        ],
    ],
];
