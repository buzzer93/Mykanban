<?php

namespace App\Security;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ApiJwtManager
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl,
    ) {
    }

    public function createToken(User $user): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->ttl;

        $payload = [
            'sub' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

        return (array) $decoded;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
