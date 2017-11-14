<?php

namespace Api\Controllers;

use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Psy\Exception\FatalErrorException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTFactory;
use Illuminate\Support\Facades\DB;

class AuthController extends BaseController
{
    public static $THIRD_PARTY = 'third-party';

    /**
     * Login as an existing user - generate and return a new JWT
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authenticate(Request $request)
    {
        // grab credentials from the request
        $credentials = [
            'email' => $request->getUser(),
            'password' => $request->getPassword(),
        ];

        try {
            // attempt to verify the credentials and create a token for the user
            if (! $token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong whilst attempting to encode the token
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        $data = JWTAuth::getPayload($token)->toArray();
        if (empty($data['sub'])) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
        $user_uuid = $data['sub'];
        $responseData = [
            'token' => $token,
            'data' => $data + ['user' => User::with(User::getRetrieveWith())->uuid($user_uuid)],
        ];

        // all good so return the token
        return response()->json($responseData);
    }

    /**
     * Refresh an existing valid JWT - generate and return the new JWT, invalidate the old one
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken()
    {
        $token = JWTAuth::getToken();
        if (!$token) {
            return response()->json(['error' => 'token_not_provided'], 400);
        }

        try {
            $token = JWTAuth::refresh($token);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'token_invalid', 401]);
        }

        $data = JWTAuth::getPayload($token)->toArray();
        if (!empty($data['typ']) && $data['typ'] === self::$THIRD_PARTY) {
            $userData = [];
        } else {
            if (empty($data['sub'])) {
                return response()->json(['error' => 'An internal error has occurred'], 500);
            }
            $user_uuid = $data['sub'];
            $userData = ['user' => User::with(User::getRetrieveWith())->uuid($user_uuid)];
        }

        $responseData = [
            'token' => $token,
            'data' => $data + $userData,
        ];

        return response()->json($responseData);
    }

    /**
     * Essentially refreshes a JWT - but also works for third party tokens (tokens not linked to a registered user)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnonymousToken()
    {
        //this function is not currently accessible, but was implemented for testing so for now it is not allowed.
        return response()->json(['error' => 'method not allowed'], 405);

        $token = JWTAuth::getToken();
        if (!$token) {
            return response()->json(['error' => 'token_not_provided'], 400);
        }

        try {
            $payload = JWTAuth::getPayload($token)->toArray();
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'token_invalid'], 401);
        }

        try {
            $token = JWTAuth::refresh($token);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'token_invalid', 401]);
        }

        if (!empty($payload['typ']) && $payload['typ'] === self::$THIRD_PARTY) {
            $userData = [];
        } else {
            if (empty($payload['sub'])) {
                return response()->json(['error' => 'An internal error has occurred'], 500);
            }
            $user_uuid = $payload['sub'];
            $userData = ['user' => User::with(User::getRetrieveWith())->uuid($user_uuid)];
        }

        $responseData = ['token' => $token, 'data' => JWTAuth::getPayload($token)->toArray() + $userData];

        return response()->json($responseData);
    }

    /**
     * User registration function. Used to initially register a user and create their client and client group.
     *
     * For field sent in the request, see RegisterRequest::rules().
     *
     * @param RegisterRequest $request - the request object
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $responseData = [];
            DB::transaction(function() use ($request, &$responseData) {
                //create user
                $user = User::create($request->input());

                //login the user by generating a token for the new user
                $token = JWTAuth::fromUser($user);
                $payload = JWTAuth::getPayload($token)->toArray();

                //return the token data along with the data of the entities just created
                $responseData = [
                    'token' => $token,
                    'data' => $payload + [
                            'user' => User::with(User::getRetrieveWith())->uuid($user->uuid)->toArray(),
                        ],
                ];
            });

            return response()->json($responseData, 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found'], 404);
        } catch (FatalErrorException $e) {
            return response()->json(['error' => 'An internal error has occurred'], 500);
        }
    }

    /**
     * Generates a 'third party' JWT - one not linked to a registered user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function thirdPartyToken()
    {
        //this function is not currently accessible, but was implemented for testing so for now it is not allowed.
        return response()->json(['error' => 'method not allowed'], 405);

        //if this function becomes allowed, you will need to update the credentials to something more
        //meaningful - this was just a sample to test
        $credentials = ['typ' => self::$THIRD_PARTY, 'sub' => 'front-end'];

        $payload = JWTFactory::make($credentials);
        $token = JWTAuth::encode($payload);
        $responseData = [
            'token' => $token->get(),
            'data' => $payload->toArray(),
        ];
        return response()->json($responseData);
    }

}