<?php
/**
 * 沫兮官替官解系统 - 云端更新配置
 * 本文件位于苹果CMS根目录/mxthxt/config.php
 * 部署后按需修改为实际 GitHub 仓库地址。
 */

return [
    // 更新包托管的 GitHub 仓库（格式：用户名/仓库名）
    'github_repo' => 'ssmhdssmhd/mxgtcms',
    // GitHub 官方源地址模板
    'github_api_url' => 'https://api.github.com/repos/{repo}/releases/latest',
    // 国内镜像源地址模板（ghproxy，国内用户加速）
    'mirror_api_url' => 'https://mirror.ghproxy.com/https://api.github.com/repos/{repo}/releases/latest',
    // 国内镜像下载前缀（给 GitHub 官方下载地址加此前缀加速）
    'mirror_download_prefix' => 'https://mirror.ghproxy.com/',
    // 更新包命名规则：{插件名称}.{版本号} {yyyyMMddHHmm}，例如 mxgtcms.v0.0.4 202609142255.zip
    'package_pattern' => 'mxgtcms.{version} {date}.zip',
    // 发行版资产名前缀：在线更新时在 GitHub Release 资产中按此前缀匹配更新包 zip
    'asset_name_prefix' => 'mxgtcms',
    // 未匹配到发行版资产时是否回退下载源码 zip
    'fallback_zipball' => 1,
    // 请求超时（秒）
    'timeout' => 15,
];
