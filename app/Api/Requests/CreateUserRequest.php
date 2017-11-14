<?php
namespace Api\Requests;

use Api\Requests\BaseCreateEntityFormRequest;
use Api\Requests\UpdateUserRequest;

class CreateUserRequest extends BaseCreateEntityFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     * @throws FatalErrorException
     */
    public function rules()
    {
        $this->requiredFields = [
            'first_name',
            'last_name',
            'email',
            'password',
        ];

        $this->updateFormRequest = UpdateUserRequest::class;
        return parent::rules();
    }
}