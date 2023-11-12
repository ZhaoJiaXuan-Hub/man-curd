<?php

namespace Zhaojiaxuan\ManCurd\app\util;

class DateUtil
{
    /**
     * @param string|null $format
     * @param int $reduce
     * @return string
     */
    public static function current(string $format = null, int $reduce = 0): string
    {
        return $format ? date($format, time() - $reduce) : date("Y-m-d H:i:s", time() - $reduce);
    }

    /**
     * @return string
     */
    public static function initialDate(): string
    {
        return "0000-00-00 00:00:00";
    }

    /**
     * 获取格式化显示时间
     * @param string $time 时间戳
     * @return string
     */
    public static function formatTime(string $time): string
    {
        $time = strtotime($time);
        $time = (int)substr((string)$time, 0, 10);
        $int = time() - $time;
        $str = '';
        if ($int <= 2) {
            $str = "刚刚";
        } elseif ($int < 60) {
            $str = sprintf('%d秒前', $int);
        } elseif ($int < 3600) {
            $str = sprintf('%d分钟前', floor($int / 60));
        } elseif ($int < 86400) {
            $str = sprintf('%d小时前', floor($int / 3600));
        } elseif ($int < 1728000) {
            $str = sprintf('%d天前', floor($int / 86400));
        } else {
            $str = date('Y年m月d日', $time);
        }
        return $str;
    }
}