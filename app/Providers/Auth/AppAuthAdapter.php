<?php

namespace App\Providers\Auth;

use Tymon\JWTAuth\Providers\Auth\IlluminateAuthAdapter;

class AppAuthAdapter extends IlluminateAuthAdapter {
    /**
     * Override method from IlluminateAuthAdapter
     *
     * Authenticate a user via the UUID instead of the id.
     *
     * @param  mixed  $uuid
     * @return bool
     */
    public function byId($uuid)
    {
        $credentials = ['uuid' => $uuid];
        if (! is_null($user = $this->auth->getProvider()->retrieveByCredentials($credentials))) {
            $this->auth->setUser($user);

            return true;
        }

        return false;
    }
}