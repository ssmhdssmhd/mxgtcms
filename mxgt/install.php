<?php
/**
 * 沫兮官替官解系统 - 安装脚本（兜底）
 * 当苹果CMS未发现 install.sql 时执行本脚本。
 * 幂等：重复执行不会报错。
 */

use think\Db;

$prefix = config('database.prefix');

// 创建配置表
Db::execute("CREATE TABLE IF NOT EXISTS `{$prefix}mxgt_config` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `key` varchar(100) NOT NULL DEFAULT '' COMMENT '配置键',
    `value` text COMMENT '配置值',
    `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='沫兮官替官解系统-配置表'");

// 写入默认配置（已存在则忽略）
$defaults = [
    'update_source'      => 'mirror',
    'update_custom_url'  => '',
    'github_repo'        => '',
    'api_url'            => '',
];
foreach ($defaults as $k => $v) {
    $exists = Db::name('mxgt_config')->where('key', $k)->find();
    if (empty($exists)) {
        Db::name('mxgt_config')->insert(['key' => $k, 'value' => $v, 'update_time' => time()]);
    }
}
