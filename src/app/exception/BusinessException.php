<?php

namespace ManCurd\App\exception;

use ManCurd\App\enum\HttpCodeEnum;
use support\exception\BusinessException as BaseException;
use Webman\Http\Request;
use Webman\Http\Response;

class BusinessException extends BaseException
{
    public function render(Request $request): ?Response
    {
        return json(['code' => $this->getCode() ?: HttpCodeEnum::BAD_REQUEST->value, 'message' => $this->getMessage() ?: HttpCodeEnum::BAD_REQUEST->process()]);
    }
}