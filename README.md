## Laravel 5 API (based off vue-starter Backend API)

This application serves as a boilerplate for a laravel 5 API project. It is a Laravel 5 API, using Dingo and JWT for authentication.

The boilerplate was largely adopted from [vue-starter Backend API](https://github.com/layer7be/vue-starter-laravel-api)

## Installation

### Step 1: Clone the repo
```
git clone $repo_path
```

### Step 2: Prerequisites
```
remove the pre-update-cmd from composer.json
in config/jwt.php, comment out the exception inside the if (empty($secret)) block
composer install
re-add the pre-update-cmd to composer.json
update .env with DB details
php artisan migrate (only if you are creating a new DB, instead of using an existing one)
php artisan db:seed (only if you are creating a new DB, instead of using an existing one)
note, if the above command gives you a ReflectionException, run the command composer dump-autoload then try again.
php artisan key:generate
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\JWTAuthServiceProvider"
php artisan jwt:generate
the above command should give output like 'jwt-auth secret [$randomToken] set successfully.'...
...In .env, add the line "JWT_SECRET=$randomToken"
uncomment the line you commented out (in config/jwt.php)
if you are on a windows machine, you will need to add the following line to .env:
PDF_BINARY_PATH=vendor\wemersonjanuario\wkhtmltopdf-windows\bin\64bit\wkhtmltopdf.exe
if you are on an environment where you want to log emails instead of sending them, be sure to update the mail settings in .env
```

### Step 3: Serve (with Apache)
```
Set up a Virtual Host
Set up an entry in hosts file
Start Apache
```

### Note: If NOT Using Apache
If you don't use Apache to serve this, you will need to remove the following 2 lines from your .htaccess:
```
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]
```


