<?php

namespace ManCurd\App;

use DateTimeInterface;
use support\Model;


class AbstractModel extends Model
{

    /**
     * 格式化日期
     *
     * @param DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
