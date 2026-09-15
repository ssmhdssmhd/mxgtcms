<?php
/**
 * 沫兮官替官解系统 - 云端更新配置
 * 本文件位于苹果CMS根目录/mxthxt/config/config.php
 * 部署后按需修改为实际 GitHub 仓库地址。
 */

return [
    // 更新包托管的 GitHub 仓库（格式：用户名/仓库名）
    'github_repo' => 'ssmhdssmhd/mxgtcms',
    // 更新分支：固定为 main（插件后台不可更改、不展示，保持默认隐藏）
    'branch' => 'main',
    // GitHub 官方源地址模板
    'github_api_url' => 'https://api.github.com/repos/{repo}/releases/latest',
    // 国内镜像源地址模板（默认，兼容旧配置）
    'mirror_api_url' => 'https://mirror.ghproxy.com/https://api.github.com/repos/{repo}/releases/latest',
    // 国内镜像 API 地址候选列表（按顺序尝试，失败自动切换下一个；全部失败后自动回退官方源直连）
    'mirror_api_urls' => [
        'https://ghfast.top/https://api.github.com/repos/{repo}/releases/latest',
        'https://gh-proxy.com/https://api.github.com/repos/{repo}/releases/latest',
        'https://ghproxy.net/https://api.github.com/repos/{repo}/releases/latest',
        'https://mirror.ghproxy.com/https://api.github.com/repos/{repo}/releases/latest',
    ],
    // 国内镜像下载前缀（默认，兼容旧配置）
    'mirror_download_prefix' => 'https://mirror.ghproxy.com/',
    // 国内镜像下载前缀候选列表（按顺序尝试，失败自动切换下一个；全部失败后回退 GitHub 官方直连）
    'mirror_download_prefixes' => [
        'https://ghfast.top/',
        'https://gh-proxy.com/',
        'https://ghproxy.net/',
        'https://mirror.ghproxy.com/',
    ],
    // 更新包命名规则：{插件名称}.{版本号} {yyyyMMddHHmm}，例如 mxgtcms.v0.0.4 202609142255.zip
    'package_pattern' => 'mxgtcms.{version} {date}.zip',
    // 发行版资产名前缀：在线更新时在 GitHub Release 资产中按此前缀匹配更新包 zip
    'asset_name_prefix' => 'mxgtcms',
    // 未匹配到发行版资产时是否回退下载源码 zip
    'fallback_zipball' => 1,
    // 请求超时（秒）
    'timeout' => 15,
];
