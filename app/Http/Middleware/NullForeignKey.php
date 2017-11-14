<?php

namespace App\Http\Middleware;

use Closure;
use App\Traits\UuidModel;

class NullForeignKey
{
    //Unique fields need to be converted to null if they are given as an empty string as they are unique but nullable fields
    //If the string is not converted to null it will not pass database validation for unique the next time an empty string is passed through
    public static $unique_fields = array('abn');

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        foreach ($request->input() as $fieldName => $value) {
            if (empty($value) && (in_array($fieldName, static::$unique_fields) || UuidModel::isForeignKeyField($fieldName))) {
                $request->merge([$fieldName => null]);
            }
        }
        return $next($request);
    }
}