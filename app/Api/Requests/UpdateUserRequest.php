<?php


namespace Api\Requests;

use Api\Requests\BaseFormRequest;

class UpdateUserRequest extends BaseFormRequest {
    public function rules()
    {
        $rules = [
            'first_name' => 'max:255',
            'last_name'=> 'max:255',
            'email' => 'email|max:255|unique:users',
            'password' => BaseFormRequest::$PASSWORD_VALIDATION,
        ];
        $uuid = $this->route('uuid');
        if (!empty($uuid)) {
            $rules['email'] .= ',email,' . $uuid . ',uuid';
        }
        return $rules;
    }
}