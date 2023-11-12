<?php

namespace Zhaojiaxuan\ManCurd\app\util;

use Zhaojiaxuan\ManCurd\exception\BusinessException;

class StringUtil
{
    /**
     * @param string $pass
     * @param string $salt
     * @return string
     */
    public static function generatePassword(string $pass, string $salt): string
    {
        return md5(StringUtil . phpmd5($pass) . md5($salt));
    }

    /**
     * @param int $length
     * @return string
     */
    public static function generateRandStr(int $length = 32): string
    {
        $md5 = md5(StringUtil . phpuniqid(md5((string)time())) . mt_rand(10000, 9999999));
        return substr($md5, 0, $length);
    }

    /**
     * @param $len
     * @param $type
     * @return string
     */
    public static function getRandStr($len = 16, $type = 0): string
    {
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $strlen = strlen($str);
        $randstr = '';
        for ($i = 0; $i < $len; $i++) {
            $randstr .= $str[mt_rand(0, $strlen - 1)];
        }
        if ($type == 1) {
            $randstr = strtoupper($randstr);
        } elseif ($type == 2) {
            $randstr = strtolower($randstr);
        }
        return $randstr;
    }

    /**
     * 格式化文件大小
     * @param $file_size
     * @return string
     */
    public static function formatBytes($file_size): string
    {
        $size = sprintf("%u", $file_size);
        if($size == 0) {
            return("0 Bytes");
        }
        $size_name = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
        return StringUtil . phpround($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $size_name[$i];
    }


    /**
     * 检查表名是否合法
     * @param string $table
     * @return string
     * @throws BusinessException
     */
    public static function checkTableName(string $table): string
    {
        if (!preg_match('/^[a-zA-Z_0-9]+$/', $table)) {
            throw new BusinessException('表名不合法');
        }
        return $table;
    }

    /**
     * 变量或数组中的元素只能是字母数字下划线组合
     * @param $var
     * @return mixed
     * @throws BusinessException
     */
    public static function filterAlphaNum($var)
    {
        $vars = (array)$var;
        array_walk_recursive($vars, function ($item) {
            if (is_string($item) && !preg_match('/^[a-zA-Z_0-9]+$/', $item)) {
                throw new BusinessException('参数不合法');
            }
        });
        return $var;
    }

    /**
     * 变量或数组中的元素只能是字母数字
     * @param $var
     * @return mixed
     * @throws BusinessException
     */
    public static function filterNum($var)
    {
        $vars = (array)$var;
        array_walk_recursive($vars, function ($item) {
            if (is_string($item) && !preg_match('/^[0-9]+$/', $item)) {
                throw new BusinessException('参数不合法');
            }
        });
        return $var;
    }

    /**
     * 检测是否是合法URL Path
     * @param $var
     * @return string
     * @throws BusinessException
     */
    public static function filterUrlPath($var): string
    {
        if (!is_string($var) || !preg_match('/^[a-zA-Z0-9_\-\/&?.]+$/', $var)) {
            throw new BusinessException('参数不合法');
        }
        return $var;
    }

    /**
     * 检测是否是合法Path
     * @param $var
     * @return string
     * @throws BusinessException
     */
    public static function filterPath($var): string
    {
        if (!is_string($var) || !preg_match('/^[a-zA-Z0-9_\-\/]+$/', $var)) {
            throw new BusinessException('参数不合法');
        }
        return $var;
    }

    /**
     * 将URL转为大驼峰名字
     * @param string $path
     * @return string
     */
    public static function transformPathToName(string $path): string
    {
        if (!$path) return '';
        if (self::isExternal($path)) return '';
        // 示例: '/page-gg/detail' =>  ['page-gg', 'detail']
        $pathArray = array_filter(explode('/', $path));
        $arr = array_map(function($i) {
            if (str_contains($i, '-')) {
                // 'page-gg' => 'PageGg'
                $arr1 = explode('-', $i);
                return implode('', array_map(function($a) {
                    return ucfirst($a);
                }, $arr1));
            } else {
                // 'detail' => 'Detail'
                return ucfirst($i);
            }
        }, $pathArray);
        // ['PageGg', 'Detail'] => PageGgDetail
        return implode('', $arr);
    }

    public static function isExternal($url): bool
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        return !empty($host) && $host !== $_SERVER['HTTP_HOST'];
    }

    /**
     * 字符转bool
     * @param string $char
     * @return bool
     */
    public static function charToBool(string $char): bool {
        return $char === '1';
    }
}