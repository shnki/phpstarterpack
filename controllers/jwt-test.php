<?php

$heading = 'jwt Test';
require __DIR__ . '/../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

$config = require('config.php');

$db = new Database($config['database']);






if ($_SERVER['REQUEST_METHOD'] == 'POST') { // trigger if ther's a POST request like form is submited
    $errors = [];
    if (isset($_COOKIE['jwt'])) {
        try {

            JWT::decode($_COOKIE['jwt'], new key($config['JWT_SECRET'], 'HS512'));

            dd('token valid');

            throw new Exception("no jwt token stored in the client side");
        } catch (\Throwable $th) {
            logIn($db, $config,$errors);
        }
    } else {
        logIn($db, $config,$errors);
    }
}


function logIn($db, $config, &$errors)
{

    $issuedAt   = time();
    $expire     = $issuedAt + 2592000;      // Add 1 month 
    $serverName = "localhost";

    //query the database to get the user that matches the email which the user submited
    $user =  $db->query('SELECT * FROM users WHERE email = :email ', [
        'email' => $_POST['email']
    ])->fetch();

    if (!is_bool($user) && isset($user)) {  //sql return an false bool when it dont find matches

        //using PHP built-in function password_verify to check if the two hashed passwords has the same value

        if (password_verify($_POST['password'], $user['password'])) {
            //user password is correct
            $payload = [
                'iat'  => $issuedAt,         // Issued at: time when the token was generated
                'iss'  =>    $serverName,                       // Issuer
                'nbf'  => $issuedAt,         // Not before
                'exp'  => $expire,                           // Expire
                'userName' => $user['email'],                     // User name
            ];

            $jwt = JWT::encode($payload, $config['JWT_SECRET'], 'HS512');

            setcookie('jwt', $jwt, time() + (86400 * 30));

            $db->query(
                'UPDATE users 
            SET token = 
            :token WHERE id =:id',
                [
                    'token' => $jwt,
                    'id' => $user['id']
                ]
            );
        } else {   //passwords do not have the same value , thus password is incorrect


            $errors['body'] = "credentials are incorrect";
        }
    }
    $errors['body'] = "No such a user";
}

require "views/jwtLogin.php";
