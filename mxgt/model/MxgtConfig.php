<?php
/**
 * 沫兮官替官解系统 - 配置模型
 * 负责插件配置的读取与保存，数据表 {pre}mxgt_config（key-value 结构）。
 */

namespace addons\mxgt\model;

use think\Model;

class MxgtConfig extends Model
{
    protected $name = 'mxgt_config';
    protected $autoWriteTimestamp = false;

    /**
     * 读取单个配置
     * @param string $key 配置键
     * @param mixed  $default 默认值
     * @return mixed
     */
    public static function getConfig($key, $default = '')
    {
        $row = self::where('key', $key)->find();
        return empty($row) ? $default : $row['value'];
    }

    /**
     * 写入单个配置
     * @param string $key 配置键
     * @param mixed  $value 配置值
     * @return bool
     */
    public static function setConfig($key, $value)
    {
        $exists = self::where('key', $key)->find();
        if (empty($exists)) {
            return self::insert(['key' => $key, 'value' => $value, 'update_time' => time()]) ? true : false;
        }
        return self::where('key', $key)->update(['value' => $value, 'update_time' => time()]) !== false;
    }

    /**
     * 读取全部配置（键值对数组）
     * @return array
     */
    public static function allConfig()
    {
        $list = self::select();
        $data = [];
        if (!empty($list)) {
            foreach ($list as $row) {
                $data[$row['key']] = $row['value'];
            }
        }
        return $data;
    }
}
