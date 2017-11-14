<?php

namespace Api\Requests;

use Api\Requests\BaseFormRequest;

class StateRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|max:255|unique:states',
            'short_name' => 'required|max:10|unique:states',
        ];
    }
}
