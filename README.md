## Stripe Payment integration with wallet in laravel 6.


#### Please follow below steps to run this project on your local machine.

1. Clone this repository.
2. Install project dependencies using composer.
3. Configure your database in .ENV file.
```php
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aveosoft
DB_USERNAME=root
DB_PASSWORD=
```
4. Configure Stripe in .ENV
```php
STRIPE_API_KEY=<your_api_key>
STRIPE_PUBLISHABLE_KEY=<your_publishable_key>
```
5. Run below command to migrate database tables and generate secure access tokens.
```php
 1. php artisan migrate
 2. php artisan passport:install
```
6. Serve the project
```php
php artisan serve
```

#### Api endpoints
1.  http://127.0.0.1:8000/api/v1/user/register
2.  http://127.0.0.1:8000/api/v1/user/login
3.  http://127.0.0.1:8000/api/v1/user/credit
4.  http://127.0.0.1:8000/api/v1/user/wallet

#### You can refer apis\api.json file to test the apis. 