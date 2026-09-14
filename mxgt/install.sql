-- 沫兮官替官解系统 - 安装SQL
-- {pre} 会被苹果CMS自动替换为数据库表前缀
CREATE TABLE IF NOT EXISTS `{pre}mxgt_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `key` varchar(100) NOT NULL DEFAULT '' COMMENT '配置键',
  `value` text COMMENT '配置值',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='沫兮官替官解系统-配置表';

-- 写入默认配置
INSERT INTO `{pre}mxgt_config` (`key`, `value`, `update_time`) VALUES
('update_source', 'mirror', 1768356000),
('update_custom_url', '', 1768356000),
('github_repo', '', 1768356000),
('api_url', '', 1768356000);
