<?php

namespace ManCurd\App;

use ManCurd\App\exception\BusinessException;
use ManCurd\App\util\Tree;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use support\Db;
use support\Model;
use support\Request;

abstract class AbstractMapper
{
    public Model $model;

    abstract public function assignModel(): void;

    public function __construct()
    {
        $this->assignModel();
    }

    /**
     * 查询
     * @param Request $request
     * @return array
     * @throws BusinessException
     */
    public function retrieve(Request $request): array
    {
        [$where, $format, $limit, $field, $order] = $this->retrieveInput($request);
        $query = $this->doRetrieve($where, $field, $order);
        return $this->doFormat($query, $format, $limit);
    }

    /**
     * 创建
     * @param Request $request
     * @return array
     * @throws BusinessException
     */
    public function create(Request $request): array
    {
        $data = $this->createInput($request);
        $id = $this->doCreate($data);
        return ['id' => $id];
    }

    /**
     * 更新
     * @param Request $request
     * @return array
     * @throws BusinessException
     */
    public function update(Request $request): array
    {
        [$id, $data] = $this->updateInput($request);
        $this->doUpdate($id, $data);
        return [];
    }

    /**
     * 删除
     * @param Request $request
     * @return array
     * @throws BusinessException
     */
    public function delete(Request $request): array
    {
        $ids = $this->deleteInput($request);
        $this->doDelete($ids);
        return [];
    }

    /**
     * 删除前置方法
     * @param Request $request
     * @return array
     * @throws BusinessException
     */
    protected function deleteInput(Request $request): array
    {
        $primary_key = $this->model->getKeyName();
        if (!$primary_key) {
            throw new BusinessException('该表无主键，不支持删除');
        }
        return (array)$request->post($primary_key, []);
    }

    /**
     * 执行删除
     * @param array $ids
     * @return void
     */
    protected function doDelete(array $ids): void
    {
        if (!$ids) {
            return;
        }
        $primary_key = $this->model->getKeyName();
        $this->model->whereIn($primary_key, $ids)->delete();
    }

    /**
     * 更新前置方法
     * @param Request $request
     * @return array
     * @throws BusinessException
     */
    protected function updateInput(Request $request): array
    {
        $primary_key = $this->model->getKeyName();
        $id = $request->post($primary_key);
        $data = $this->inputFilter($request->post());
        unset($data[$primary_key]);
        return [$id, $data];
    }

    /**
     * 执行更新
     * @param $id
     * @param $data
     * @return void
     * @throws BusinessException
     */
    protected function doUpdate($id, $data): void
    {
        $model = $this->model->find($id);
        if (!$model) {
            throw new BusinessException('记录不存在', 2);
        }
        foreach ($data as $key => $val) {
            $model->{$key} = $val;
        }
        $model->save();
    }

    /**
     * 执行创建
     * @param array $data
     * @return mixed
     */
    protected function doCreate(array $data): mixed
    {
        $primary_key = $this->model->getKeyName();
        $model_class = get_class($this->model);
        $model = new $model_class;
        foreach ($data as $key => $val) {
            $model->{$key} = $val;
        }
        $model->save();
        return $primary_key ? $model->$primary_key : null;
    }

    /**
     * 创建前置方法
     * @param Request $request
     * @return array
     * @throws BusinessException
     */
    protected function createInput(Request $request): array
    {
        return $this->inputFilter($request->post());
    }

    /**
     * 对用户输入表单过滤
     * @param array $data
     * @return array
     * @throws BusinessException
     */
    protected function inputFilter(array $data): array
    {
        $table = AbstractMapper . phpconfig('database.connections.mysql.prefix') . $this->model->getTable();
        $allow_column = $this->model->getConnection()->select("desc `$table`");
        if (!$allow_column) {
            throw new BusinessException('数据表不存在', 2);
        }
        $columns = array_column($allow_column, 'Type', 'Field');
        foreach ($data as $col => $item) {
            if (!isset($columns[$col])) {
                unset($data[$col]);
                continue;
            }
            // 非字符串类型传空则为null
            if ($item === '' && strpos(strtolower($columns[$col]), 'varchar') === false && strpos(strtolower($columns[$col]), 'text') === false) {
                $data[$col] = null;
            }
            if (is_array($item)) {
                $data[$col] = implode(',', $item);
            }
        }
        if (empty($data['created_at'])) {
            unset($data['created_at']);
        }
        if (empty($data['updated_at'])) {
            unset($data['updated_at']);
        }
        return $data;
    }

    /**
     * 查询前置
     * @param Request $request
     * @return array
     * @throws BusinessException
     */
    protected function retrieveInput(Request $request): array
    {
        $field = $request->get('field');
        $order = $request->get('order', 'asc');
        $format = $request->get('format', 'normal');
        $limit = (int)$request->get('limit', $format === 'tree' ? 1000 : 10);
        $limit = $limit <= 0 ? 10 : $limit;
        $order = $order === 'asc' ? 'asc' : 'desc';
        $where = $request->get();
        $page = (int)$request->get('page');
        $page = $page > 0 ? $page : 1;
        $table = AbstractMapper . phpconfig('database.connections.mysql.prefix') . $this->model->getTable();

        $allow_column = Db::select("desc `$table`");
        if (!$allow_column) {
            throw new BusinessException('数据表不存在');
        }
        $allow_column = array_column($allow_column, 'Field', 'Field');
        if (!in_array($field, $allow_column)) {
            $field = null;
        }
        foreach ($where as $column => $value) {
            if (
                $value === '' || !isset($allow_column[$column]) ||
                is_array($value) && (empty($value) || !in_array($value[0], ['null', 'not null']) && !isset($value[1]))
            ) {
                unset($where[$column]);
            }
        }
        return [$where, $format, $limit, $field, $order, $page];
    }

    /**
     * 指定查询where条件,并没有真正的查询数据库操作
     * @param array $where
     * @param string|null $field
     * @param string $order
     * @return EloquentBuilder|QueryBuilder|Model
     */
    protected function doRetrieve(array $where, string $field = null, string $order = 'desc'): EloquentBuilder|Model|QueryBuilder
    {
        $model = $this->model;
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                if ($value[0] === 'like' || $value[0] === 'not like') {
                    $model = $model->where($column, $value[0], "%$value[1]%");
                } elseif (in_array($value[0], ['>', '=', '<', '<>'])) {
                    $model = $model->where($column, $value[0], $value[1]);
                } elseif ($value[0] == 'in' && !empty($value[1])) {
                    $valArr = $value[1];
                    if (is_string($value[1])) {
                        $valArr = explode(",", trim($value[1]));
                    }
                    $model = $model->whereIn($column, $valArr);
                } elseif ($value[0] == 'not in' && !empty($value[1])) {
                    $valArr = $value[1];
                    if (is_string($value[1])) {
                        $valArr = explode(",", trim($value[1]));
                    }
                    $model = $model->whereNotIn($column, $valArr);
                } elseif ($value[0] == 'null') {
                    $model = $model->whereNull($column);
                } elseif ($value[0] == 'not null') {
                    $model = $model->whereNotNull($column);
                } elseif ($value[0] !== '' || $value[1] !== '') {
                    $model = $model->whereBetween($column, $value);
                }
            } else {
                $model = $model->where($column, $value);
            }
        }
        if ($field) {
            $model = $model->orderBy($field, $order);
        }
        return $model;
    }

    /**
     * 执行真正查询，并返回格式化数据
     * @param $query
     * @param $format
     * @param $limit
     * @return array
     */
    protected function doFormat($query, $format, $limit): array
    {
        $methods = [
            'select' => 'formatSelect',
            'tree' => 'formatTree',
            'table_tree' => 'formatTableTree',
            'normal' => 'formatNormal',
        ];
        $paginator = $query->paginate($limit);
        $total = $paginator->total();
        $items = $paginator->items();
        $format_function = $methods[$format] ?? 'formatNormal';
        return call_user_func([$this, $format_function], $items, $total);
    }

    /**
     * 格式化下拉列表
     * @param array $items
     * @return array
     */
    protected function formatSelect(array $items): array
    {
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_items[] = [
                'name' => $item->title ?? $item->name ?? $item->id,
                'value' => $item->id
            ];
        }
        return $this->formatResponse($formatted_items);
    }

    /**
     * 格式化树
     * @param array $items
     * @return array
     */
    protected function formatTree(array $items): array
    {
        $format_items = [];
        foreach ($items as $item) {
            $format_items[] = [
                'name' => $item->title ?? $item->name ?? $item->id,
                'value' => (string)$item->id,
                'id' => $item->id,
                'pid' => $item->pid,
            ];
        }
        $tree = new Tree($format_items);
        return $this->formatResponse($tree->getTree());
    }

    /**
     * 格式化表格树
     * @param array $items
     * @return array
     */
    protected function formatTableTree(array $items): array
    {
        $tree = new Tree($items);
        return $this->formatResponse($tree->getTree());
    }

    /**
     * 通用格式化
     * @param array $items
     * @param int $total
     * @return array
     */
    protected function formatNormal(array $items, int $total): array
    {
        return $this->formatResponse(['list' => $items, 'count' => $total]);
    }

    /**
     * 最终返回
     * @param array $data
     * @return array
     */
    protected function formatResponse(array $data): array
    {
        return $data;
    }
}