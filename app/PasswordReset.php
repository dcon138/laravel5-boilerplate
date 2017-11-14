<?php

namespace App;

use App\BaseModel;
use App\User;
use App\CustomerContact;

class PasswordReset extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    public static $PARENT_MODELS = [
        'users' => [
            'class' => User::class,
            'function' => 'password_resets',
        ],
    ];

    public static function getRetrieveWith()
    {
        return [
            'user',
        ];
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public static function getUuidByUserId($user_id)
    {
        $reset = static::where('user_id', $user_id)->orderBy('created_at', 'DESC')->first();
        return !empty($reset) && !empty($reset->uuid) ? $reset->uuid : null;
    }
}