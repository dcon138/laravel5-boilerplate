<?php

namespace Api\Controllers;

use App\PasswordReset;
use Api\Controllers\RestResourceController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\User;
use Api\Requests\SetPasswordRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Psy\Exception\FatalErrorException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class PasswordResetsController extends RestResourceController
{
    public function __construct()
    {
        $this->modelClass = PasswordReset::class;
        parent::__construct();
    }

    public function createOneForUser(Request $request, $user_email)
    {
        try {
            $user = User::where('email', $user_email)->firstOrFail();

            $reset = $user->createPasswordReset();

            //NOTE: up to you to determine if you want to use this code to send the password reset email
            //set variables and send password reset email
            //$first_name = $user->first_name;
            //$link = app('request')->header('X-Related-Url') . '/' . $user->email . '/' . $reset->uuid;
            //Mail::send('emails.password_reset', compact('first_name', 'link'), function($m) use ($user) {
            //    $m->to($user->email, $user->formatToNameForEmail());
            //    $m->subject(Config::get('custom.system_name') . ' - Password Reset Request');
            //});

            return response()->make('', 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        }
    }

    public function validatePasswordReset(Request $request, $user_email, $uuid)
    {
        $this->denyGetHttpMethod($request);

        try {
            $reset = PasswordReset::where('uuid', $uuid)->firstOrFail();
            $user = User::where('uuid', $reset->user_uuid)->where('email', $user_email)->firstOrFail();
            $statusCode = 200;
        } catch (\Exception $e) {
            $statusCode = 404;
        }
        return response('', $statusCode);
    }
    
    public function setNewPassword(SetPasswordRequest $request, $user_email, $uuid)
    {
        try {
            $newPassword = $request->input('password');
            $reset = PasswordReset::where('uuid', $uuid)->firstOrFail();
            DB::transaction(function() use ($reset, $newPassword, $user_email, $uuid) {
                if (!empty($reset->user_uuid)) {
                    $user = User::where('uuid', $reset->user_uuid)->where('email', $user_email)->firstOrFail();
                    $user->password = $newPassword;
                    $user->save();
                } else {
                    throw new FatalErrorException('Invalid password reset');
                }
            });

            return response()->make('', 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    public function resetPassword(SetPasswordRequest $request, $user_email, $uuid)
    {
        try {
            $reset = PasswordReset::uuid($uuid);

            if (empty($reset->user_uuid)) {
                return response()->json(['error' => 'Passwords can only be reset for users'], 400);
            }

            $user = User::where('uuid', $reset->user_uuid)->where('email', $user_email)->firstOrFail();
            $user->password = $request->input('password');
            $user->save();

            return response()->make('', 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }
}