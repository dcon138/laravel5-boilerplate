<?php

namespace Api\Requests;

use Api\Requests\BaseFormRequest;
use Api\Requests\UpdateClientRequest;
use Illuminate\Container\Container;

abstract class BaseCreateEntityFormRequest extends BaseFormRequest
{
    protected $updateFormRequest;
    protected $requiredFields;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];

        if (!empty($this->updateFormRequest)) {
            $childRequestClass = $this->updateFormRequest;
            $updateFormRequest = $childRequestClass::createFromBase($this);
            $updateFormRequest->setRouteResolver($this->getRouteResolver());
            $updateFormRequest->setContainer(Container::getInstance());
            $rules = $updateFormRequest->rules();

            if (!empty($this->requiredFields)) {
                foreach ($this->requiredFields as $field) {
                    if (!empty($rules[$field]) && strpos($rules[$field], 'required') === false) {
                        $rules[$field] = 'required|' . $rules[$field];
                    }
                }
            }
        }
        return $rules;
    }
}
