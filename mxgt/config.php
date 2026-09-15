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
    [
        'title' => '插件登录账号',
        'name' => 'login_user',
        'type' => 'string',
        'value' => 'admin',
        'tip' => '插件独立登录用户名（进入插件时弹出的登录页使用），与苹果CMS后台登录互不影响。',
    ],
    [
        'title' => '插件登录密码',
        'name' => 'login_pass',
        'type' => 'string',
        'value' => 'admin888',
        'tip' => '插件独立登录密码。用户名与密码同时为空时跳过插件登录。',
    ],
    // ===== 搜索设置（search_*，独立设置页「搜索设置」） =====
    [
        'title' => '启用搜索',
        'name' => 'search_enable',
        'type' => 'select',
        'value' => '1',
        'content' => ['1' => '启用', '0' => '停用'],
        'tip' => '是否启用官方搜索功能。',
    ],
    [
        'title' => '搜索接口地址',
        'name' => 'search_url',
        'type' => 'string',
        'value' => '',
        'tip' => '搜索接口地址，支持 {keyword} 占位符，例如：https://api.example.com/search?wd={keyword}',
    ],
    [
        'title' => '请求参数模板',
        'name' => 'search_params',
        'type' => 'string',
        'value' => 'wd={keyword}&page={page}',
        'tip' => '请求参数模板，可用占位符：{keyword}（关键词）、{page}（页码）。',
    ],
    [
        'title' => '每次返回条数',
        'name' => 'search_limit',
        'type' => 'number',
        'value' => '20',
        'tip' => '搜索接口单次返回的最大条数。',
    ],
    [
        'title' => '请求超时（秒）',
        'name' => 'search_timeout',
        'type' => 'number',
        'value' => '10',
        'tip' => '搜索请求的超时时间。',
    ],
    // ===== 匹配设置（match_*，独立设置页「匹配设置」） =====
    [
        'title' => '启用匹配',
        'name' => 'match_enable',
        'type' => 'select',
        'value' => '1',
        'content' => ['1' => '启用', '0' => '停用'],
        'tip' => '是否启用官替/官解匹配处理。',
    ],
    [
        'title' => '匹配类型',
        'name' => 'match_type',
        'type' => 'select',
        'value' => 'replace',
        'content' => [
            'replace' => '官替（官方替换）',
            'parse' => '官解（官方解析）',
        ],
        'tip' => '匹配处理类型：官替用于官方接口替换，官解用于官方解析。',
    ],
    [
        'title' => '匹配规则',
        'name' => 'match_rule',
        'type' => 'textarea',
        'value' => '',
        'tip' => '匹配规则内容，支持正则规则或 JSON 规则（按实际接口格式填写）。',
    ],
    [
        'title' => '每次匹配数量',
        'name' => 'match_limit',
        'type' => 'number',
        'value' => '10',
        'tip' => '单次任务处理的最大匹配数量。',
    ],
    // ===== JSON 调用（json_*，独立设置页「JSON 调用」） =====
    [
        'title' => '启用 JSON 调用',
        'name' => 'json_enable',
        'type' => 'select',
        'value' => '1',
        'content' => ['1' => '启用', '0' => '停用'],
        'tip' => '是否启用 JSON 接口调用能力。',
    ],
    [
        'title' => 'JSON 接口地址',
        'name' => 'json_url',
        'type' => 'string',
        'value' => '',
        'tip' => 'JSON 接口地址，支持 {keyword} 等占位符，例如：https://api.example.com/json?kw={keyword}',
    ],
    [
        'title' => '调用密钥',
        'name' => 'json_key',
        'type' => 'string',
        'value' => '',
        'tip' => '调用凭证/密钥参数（留空则不传）。',
    ],
    [
        'title' => '请求超时（秒）',
        'name' => 'json_timeout',
        'type' => 'number',
        'value' => '10',
        'tip' => 'JSON 接口请求超时时间。',
    ],
];
