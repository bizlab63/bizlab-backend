<?php

namespace App\Helper;

use ReallySimpleJWT\Token;

class JWT
{
    private string $salt;

    public function __construct()
    {
        $this->salt = 'sec!ReT423*&';
    }

    public function generate(int $uid): string
    {
        return Token::create($uid, $this->salt, time()+3600, 'localhost');
    }

    public function validate(int $uid, string $token): bool
    {
        $payload = Token::getPayload($token);

        if ($uid == $payload['user_id']) {
            return true;
        } else {
            return false;
        }
    }
}