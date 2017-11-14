<?php
$api = app('Dingo\Api\Routing\Router');

$regexes = [
	'uuid' => \App\Traits\UuidModel::$UUID_REGEX,
];

// Version 1 of our API
$api->version('v1', function ($api) use ($regexes) {

	// Set our namespace for the underlying routes
	$api->group(['namespace' => 'Api\Controllers', 'middleware' => '\Barryvdh\Cors\HandleCors::class'], function ($api) use ($regexes) {

		// Login route - use Basic Auth
		$api->group( [ 'middleware' => 'auth.basic.once' ], function($api) {
			$api->get('auth/jwt/login', [
				'as' => 'login',
				'uses' => 'AuthController@authenticate',
			]);
		});

		$api->get('auth/jwt/third', [
			'as' => 'third',
			'uses' => 'AuthController@thirdPartyToken',
		]);

		$api->get('auth/jwt/token', [
			'as' => 'token login',
			'uses' => 'AuthController@getAnonymousToken',
		]);

		$api->post('register', [
			'as' => 'register',
			'uses' => 'AuthController@register',
		]);
                
		$regexes['field'] = 'email';
		$api->get('users/{fieldValue}/{field}', [
			'as' => 'validate unique user email',
			'uses' => 'UsersController@checkEntityByField',
		])->where($regexes);
		unset($regexes['field']);

		$api->post('users/{user_email}/password-resets', [
			'as' => 'create a password reset',
			'uses' => 'PasswordResetsController@createOneForUser',
		])->where($regexes);

		$api->get('users/{user_email}/password-resets/{uuid}', [
			'as' => 'validate password reset uuid',
			'uses' => 'PasswordResetsController@validatePasswordReset',
		])->where($regexes);

		$api->post('users/{user_email}/password-resets/{uuid}', [
			'as' => 'set new password',
			'uses' => 'PasswordResetsController@setNewPassword',
		])->where($regexes);

		$api->put('users/{user_email}/password-resets/{uuid}', [
			'as' => 'reset password',
			'uses' => 'PasswordResetsController@resetPassword',
		])->where($regexes);

		// All routes in here are protected and thus need a valid token
		//$api->group( [ 'protected' => true, 'middleware' => 'jwt.refresh' ], function ($api) {
		$api->group( [ 'middleware' => 'jwt.auth' ], function ($api) use ($regexes) {

			$api->get('auth/jwt/refresh', [
					'as' => 'refresh token',
					'uses' => 'AuthController@refreshToken',
			]);

			$api->get('states', [
					'as' => 'get all states',
					'uses' => 'StatesController@getAll',
			]);

			$api->get('users', [
				'as' => 'get all users',
				'uses' => 'UsersController@getAll',
			]);

			$api->get('users/{uuid}', [
				'as' => 'get a user',
				'uses' => 'UsersController@getOne',
			])->where($regexes);

			$api->post('users', [
				'as' => 'create a user',
				'uses' => 'UsersController@create',
			]);

			$api->delete('users/{uuid}', [
				'as' => 'delete a user',
				'uses' => 'UsersController@deleteOne',
			])->where($regexes);

			$api->patch('users/{uuid}', [
				'as' => 'update a user',
				'uses' => 'UsersController@updateOne',
			])->where($regexes);
                        
		});

	});

});
