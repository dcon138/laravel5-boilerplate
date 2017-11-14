<?php

namespace Api\Controllers;

use App\User;
use Api\Controllers\RestResourceController;
use Api\Requests\CreateUserRequest;
use Api\Requests\UpdateUserRequest;
use Dingo\Api\Http\Request;

class UsersController extends RestResourceController
{
    public function __construct()
    {
        $this->modelClass = User::class;
        $this->requestClasses = [
            'create' => CreateUserRequest::class,
            'update' => UpdateUserRequest::class,
        ];
        parent::__construct();
    }
    
}