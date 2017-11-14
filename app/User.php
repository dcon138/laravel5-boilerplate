<?php

namespace App;

use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Authenticatable;
use App\BaseModel;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\PasswordReset;
use App\Traits\CanBeEmailed;

class User extends BaseModel implements AuthenticatableContract,
                                    AuthorizableContract,
                                    CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword, CanBeEmailed;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['first_name', 'last_name', 'email', 'password', 'phone'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'password', 'remember_token'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    public static $PARENT_MODELS = [
        
    ];

    protected $unconventionalForeignKeys = [

    ];
    
    public static function getRetrieveWith()
    {
        return [
            //
        ];
    }
    
    /**
     * Get the identifier that will be stored in the subject claim of the JWT
     *
     * @return mixed
     */
    public function getJWTIdentifier() {
        return $this->attributes['uuid'];
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT
     *
     * @return array
     */
    public function getJWTCustomClaims() {
        return [];
    }

    /**
     * Mutator to automatically hash any password being set.
     *
     * Note that from what i've read online this may break Laravel's inbuilt password reset functionality because
     * that functionality also hashes the password, causing a double-hash.
     *
     * @param $password - the plain text password to be hashed
     */
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = Hash::make($password);
    }

    public function password_resets()
    {
        return $this->hasMany('App\PasswordReset');
    }

    public function createPasswordReset()
    {
        $reset = new PasswordReset();
        $reset->user_uuid = $this->uuid;
        $reset->save();
        return $reset;
    }
}
