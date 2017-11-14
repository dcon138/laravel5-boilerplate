<?php

namespace Api\Requests;

use Api\Requests\BaseFormRequest;

class SetPasswordRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'password' => BaseFormRequest::$PASSWORD_VALIDATION,
        ];
    }
}