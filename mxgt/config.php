<?php
/**
 * 沫兮App控制插件 - 配置项定义
 *
 * 新版苹果CMS（fastadmin-addons）配置机制：
 * - 后台「应用插件 → 设置」依据本文件渲染表单并保存（写回本文件）；
 * - 插件页面通过 get_addon_config('mxgt') 读取，模板中可直接使用 {$config.xxx}。
 * select/radio 的选项存放在 content 键（value => label）。
 *
 * 本插件精简为核心：仅保留「在线更新 / 插件配置 / App 云端配置下发」。
 * 已移除原「官替官解」的搜索、匹配、JSON 调用等接口（search_*、match_*、json_*）。
 */

return [
    // ===== 在线更新 =====
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
    // ===== 插件登录 =====
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
    // ===== App 云端配置下发（app_*） =====
    [
        'title' => '启用 App 配置下发',
        'name' => 'app_config_enable',
        'type' => 'select',
        'value' => '1',
        'content' => ['1' => '启用', '0' => '停用'],
        'tip' => '是否开放 App 云端配置接口。App 将该接口地址填入「配置地址」即可读取下方配置来控制采集接口、播放器、公告等功能。',
    ],
    [
        'title' => '采集接口(Spider)地址',
        'name' => 'app_spider',
        'type' => 'string',
        'value' => '',
        'tip' => 'App 使用的采集接口(Spider/Jar) 地址，如 https://example.com/spider.jar，对应配置 JSON 的 spider 字段。',
    ],
    [
        'title' => '采集站点(Sites)',
        'name' => 'app_sites',
        'type' => 'textarea',
        'value' => '',
        'tip' => '采集站点源，JSON 数组，如 [{"key":"cn","name":"站点","type":3,"api":"https://...","searchable":1}]。留空则不下发 sites。',
    ],
    [
        'title' => '解析地址(Parses)',
        'name' => 'app_parses',
        'type' => 'textarea',
        'value' => '',
        'tip' => '视频解析地址，JSON 数组，如 [{"name":"解析","url":"https://...","type":0}]。留空则不下发 parses。',
    ],
    [
        'title' => '直播源(Lives)',
        'name' => 'app_lives',
        'type' => 'textarea',
        'value' => '',
        'tip' => '直播源，可为文本直播源地址或 JSON 数组。留空则不下发 lives。',
    ],
    [
        'title' => '默认播放器类型',
        'name' => 'app_player',
        'type' => 'select',
        'value' => 'auto',
        'content' => [
            'auto' => '默认（由 App 自行选择）',
            '0' => '系统播放器',
            '1' => 'IJK 播放器',
            '2' => 'EXO 播放器',
        ],
        'tip' => '控制 App 默认使用的播放器类型。',
    ],
    [
        'title' => '壁纸地址',
        'name' => 'app_wallpaper',
        'type' => 'string',
        'value' => '',
        'tip' => 'App 首页壁纸图片地址，对应配置 JSON 的 wallpaper 字段。',
    ],
    [
        'title' => '公告内容',
        'name' => 'app_announcement',
        'type' => 'textarea',
        'value' => '',
        'tip' => 'App 启动公告/通知内容（纯文本或富文本 HTML）。留空则不下发公告。',
    ],
    [
        'title' => 'App 更新地址(APK)',
        'name' => 'app_update_url',
        'type' => 'string',
        'value' => '',
        'tip' => 'App 新版本安装包(APK)下载地址，供 App 检测更新使用。留空则不下发。',
    ],
];