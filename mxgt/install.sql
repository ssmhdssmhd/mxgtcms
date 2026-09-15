-- 沫兮官替官解系统 - 安装SQL
-- __PREFIX__ 会被苹果CMS自动替换为数据库表前缀
CREATE TABLE IF NOT EXISTS `__PREFIX__mxgt_meta` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `meta_key` varchar(100) NOT NULL DEFAULT '' COMMENT '键',
  `meta_value` varchar(255) NOT NULL DEFAULT '' COMMENT '值',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meta_key` (`meta_key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='沫兮官替官解系统-元数据表';
