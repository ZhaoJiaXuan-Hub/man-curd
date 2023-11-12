<?php

namespace app;

use support\Request;

abstract class AbstractService
{
    public AbstractMapper $mapper;

    abstract public function assignMapper(): void;

    public function __construct()
    {
        $this->assignMapper();
    }

    /**
     * 查询
     * @param Request $request
     * @return array
     * @throws exception\BusinessException
     */
    public function retrieve(Request $request): array
    {
        return $this->mapper->retrieve($request);
    }

    /**
     * 创建
     * @param Request $request
     * @return array
     * @throws exception\BusinessException
     */
    public function create(Request $request): array
    {
        return $this->mapper->create($request);
    }

    /**
     * 更新
     * @param Request $request
     * @return array
     * @throws exception\BusinessException
     */
    public function update(Request $request): array
    {
        return $this->mapper->update($request);
    }

    /**
     * 删除
     * @param Request $request
     * @return array
     * @throws exception\BusinessException
     */
    public function delete(Request $request): array
    {
        return $this->mapper->delete($request);
    }
}