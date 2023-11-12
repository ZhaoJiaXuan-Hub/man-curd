<?php

namespace ManCurd\App;

use ManCurd\App\command\enum\HttpCodeEnum;
use support\Response;

class AbstractController
{
    /**
     * 统一接口格式返回
     * @param int $code
     * @param string $message
     * @param array $data
     * @param bool $success
     * @return Response
     */
    protected function json(int $code, string $message, array $data = [], bool $success = true): Response
    {
        return json(['code' => $code, 'data' => $data, 'message' => $message, 'success' => $success]);
    }

    /**
     * 常用成功返回
     * @param array $data
     * @param string $message
     * @return Response
     */
    protected function success(array $data, string $message = ''): Response
    {
        return $this->json(HttpCodeEnum::SUCCESS->value, $message ?: HttpCodeEnum::SUCCESS->process(), $data ?: [], true);
    }

    /**
     * 常用错误返回
     * @param string $message
     * @param array $data
     * @return Response
     */
    protected function error(string $message, array $data = []): Response
    {
        return $this->json(HttpCodeEnum::FORBIDDEN->value, $message ?: HttpCodeEnum::FORBIDDEN->process(), $data ?: [], false);
    }
}