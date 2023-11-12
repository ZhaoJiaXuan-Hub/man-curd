<?php

namespace ManCurd\App;

use think\Validate;

class AbstractRequest extends Validate
{
    public function __construct()
    {
        $this->rule = $this->rules();
        $this->message($this->messages());
    }

    /**
     * 公共规则
     * @return array
     */
    public function commonRules(): array
    {
        return [];
    }

    public function rules(): array
    {
        $operation = $this->getOperation();
        $method = $operation . 'Rules';
        $rules = ( $operation && method_exists($this, $method) ) ? $this->$method() : [];
        return array_merge($rules, $this->commonRules());
    }

    public function messages(): array
    {
        $operation = $this->getOperation();
        $method = $operation . 'Messages';
        return ( $operation && method_exists($this, $method) ) ? $this->$method() : [];
    }

    protected function getOperation(): ?string
    {
        $path = explode('/', request()->path());
        do {
            $operation = array_pop($path);
        } while (is_numeric($operation));

        return $operation;
    }
}