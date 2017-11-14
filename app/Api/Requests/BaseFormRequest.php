<?php

namespace Api\Requests;

use Dingo\Api\Http\FormRequest;
use Psy\Exception\FatalErrorException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Token;


abstract class BaseFormRequest extends FormRequest
{
    protected static $CURRENCY_REGEX = '/^\d*(\.\d{1,2})?$/'; //currency, excluding currency sign
    protected static $PASSWORD_VALIDATION = 'min:4';
    protected static $HEXADECIMAL_REGEX = '/^[0-9a-fA-F]+$/';

    public function authorize()
    {
        return true;
    }
}