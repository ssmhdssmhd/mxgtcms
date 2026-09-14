<?php
/**
 * 沫兮官替官解系统 - 云端更新配置
 * 本文件位于苹果CMS根目录/mxthxt/config.php
 * 部署后按需修改为实际 GitHub 仓库地址。
 */

return [
    // 更新包托管的 GitHub 仓库（格式：用户名/仓库名）
    'github_repo' => 'moxi/mxgtcms',
    // GitHub 官方源地址模板
    'github_api_url' => 'https://api.github.com/repos/{repo}/releases/latest',
    // 国内镜像源地址模板（ghproxy，国内用户加速）
    'mirror_api_url' => 'https://mirror.ghproxy.com/https://api.github.com/repos/{repo}/releases/latest',
    // 更新包命名规则：{插件名称}.{版本号} {yyyyMMddHHmm}，例如 mxgt.v0.0.2 202609141200.zip
    'package_pattern' => 'mxgt.{version} {date}.zip',
    // 请求超时（秒）
    'timeout' => 15,
];
