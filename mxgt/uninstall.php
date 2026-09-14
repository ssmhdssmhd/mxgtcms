<?php
/**
 * 沫兮官替官解系统 - 卸载脚本（兜底）
 * 当苹果CMS未发现 uninstall.sql 时执行本脚本。
 * 幂等：重复执行不会报错。
 */

use think\Db;

// 删除配置表
Db::execute('DROP TABLE IF EXISTS `' . config('database.prefix') . 'mxgt_config`');
