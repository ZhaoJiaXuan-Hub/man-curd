<?php

namespace ManCurd\App\command\util;

class ArrayUtil
{
    /**
     * 数组无限嵌套
     * @param array $array 需要嵌套的数组
     * @param int $parentId 父级ID
     * @param string $pidField 父级ID字段名称
     * @param string $childrenField 嵌套字段名称
     * @param string $idField ID字段名称
     * @return array
     */
    public static function buildNestedArray(array $array, int $parentId = 0, string $pidField = 'parentId', string $childrenField = 'children', string $idField = 'id'): array
    {
        $result = [];
        foreach ($array as $item) {
            if ($item[$pidField] == $parentId) {
                $children =self::buildNestedArray($array, $item[$idField], $pidField, $childrenField, $idField);
                if (!empty($children)) {
                    $item[$childrenField] = $children;
                }
                $result[] = $item;
            }
        }
        return $result;
    }
}