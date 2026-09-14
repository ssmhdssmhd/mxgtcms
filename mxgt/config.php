<?php
/**
 * 沫兮官替官解系统 - 插件配置项定义
 *
 * 苹果CMS 后台「配置」页与插件自带配置页均依据本文件渲染表单。
 * 配置项最终保存至数据表 {pre}mxgt_config。
 */

return [
    [
        'title' => '更新源',
        'name' => 'update_source',
        'type' => 'select',
        'value' => 'mirror',
        'options' => [
            'github' => 'GitHub 官方源',
            'mirror' => '国内镜像源（ghproxy 加速）',
            'custom' => '自定义源',
        ],
        'tip' => '在线更新使用的远程更新源，国内用户推荐「国内镜像源」。',
    ],
    [
        'title' => '自定义更新地址',
        'name' => 'update_custom_url',
        'type' => 'text',
        'value' => '',
        'tip' => '更新源选择「自定义源」时，填写自定义更新接口地址，例如：https://example.com/mxgt/update.php',
    ],
    [
        'title' => 'GitHub 仓库',
        'name' => 'github_repo',
        'type' => 'text',
        'value' => '',
        'tip' => '托管更新包的 GitHub 仓库，格式：用户名/仓库名，例如：moxi/mxgtcms',
    ],
    [
        'title' => 'API 接口地址',
        'name' => 'api_url',
        'type' => 'text',
        'value' => '',
        'tip' => '官替/官解核心功能使用的接口地址（后续迭代开放）。',
    ],
];
