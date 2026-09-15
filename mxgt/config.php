<?php
/**
 * 沫兮官替官解系统 - 配置项定义
 *
 * 新版苹果CMS（fastadmin-addons）配置机制：
 * - 后台「应用插件 → 设置」依据本文件渲染表单并保存（写回本文件）；
 * - 插件页面通过 get_addon_config('mxgt') 读取，模板中可直接使用 {$config.xxx}。
 * select/radio 的选项存放在 content 键（value => label）。
 */

return [
    [
        'title' => '更新源',
        'name' => 'update_source',
        'type' => 'select',
        'value' => 'mirror',
        'content' => [
            'github' => 'GitHub 官方源',
            'mirror' => '国内镜像源（ghproxy 加速）',
            'custom' => '自定义源',
        ],
        'tip' => '在线更新使用的远程更新源，国内用户推荐「国内镜像源」。',
    ],
    [
        'title' => 'GitHub 仓库',
        'name' => 'github_repo',
        'type' => 'string',
        'value' => 'ssmhdssmhd/mxgtcms',
        'tip' => '托管更新包的 GitHub 仓库，格式：用户名/仓库名。',
    ],
    [
        'title' => '自定义更新地址',
        'name' => 'update_custom_url',
        'type' => 'string',
        'value' => '',
        'tip' => '更新源选择「自定义源」时填写，例如：https://example.com/mxgt/update.php',
    ],
    [
        'title' => 'API 接口地址',
        'name' => 'api_url',
        'type' => 'string',
        'value' => '',
        'tip' => '官替/官解核心功能使用的接口地址（后续迭代开放）。',
    ],
];
