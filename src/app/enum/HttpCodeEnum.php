<?php

namespace ManCurd\App\enum;

/**
 * HTTP状态码枚举
 */
enum HttpCodeEnum: int
{
    case SUCCESS = 200;
    case CREATED_OR_UPDATED = 201;

    case QUEUED = 202;
    case DELETED = 204;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case REQUEST_TIMEOUT = 408;
    case SERVER_ERROR = 500;
    case NOT_IMPLEMENTED = 501;
    case NETWORK_ERROR = 502;
    case SERVICE_UNAVAILABLE = 503;
    case GATEWAY_TIMEOUT = 504;

    public function process(): string
    {
        return match ($this) {
            self::SUCCESS => '服务器成功返回请求的数据',
            self::CREATED_OR_UPDATED => '新建或修改数据成功',
            self::QUEUED => '一个请求已经进入后台排队（异步任务）',
            self::DELETED => '删除数据成功',
            self::BAD_REQUEST => '请求错误(400)',
            self::UNAUTHORIZED => '未授权，请重新登录(401)',
            self::FORBIDDEN => '拒绝访问(403)',
            self::NOT_FOUND => '请求出错(404)',
            self::REQUEST_TIMEOUT => '请求超时(408)',
            self::SERVER_ERROR => '服务器错误(500)',
            self::NOT_IMPLEMENTED => '服务未实现(501)',
            self::NETWORK_ERROR => '网络错误(502)',
            self::SERVICE_UNAVAILABLE => '服务不可用(503)',
            self::GATEWAY_TIMEOUT => '网络超时(504)'
        };
    }
}