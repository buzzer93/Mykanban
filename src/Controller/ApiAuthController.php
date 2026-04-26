<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\ApiJwtManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAuthController extends AbstractController
{
    #[Route('/api/v1/login_check', name: 'api_v1_login_check', methods: ['POST'])]
    public function loginCheck(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ApiJwtManager $jwtManager,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->json([
                'error' => 'invalid_json',
                'message' => 'Le corps de requete JSON est invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $username = isset($payload['username']) ? trim((string) $payload['username']) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ('' === $username || '' === $password) {
            return $this->json([
                'error' => 'missing_credentials',
                'message' => 'Les champs username et password sont obligatoires.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneByUsername($username);
        if (null === $user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'error' => 'invalid_credentials',
                'message' => 'Identifiants invalides.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'token_type' => 'Bearer',
            'token' => $jwtManager->createToken($user),
            'expires_in' => $jwtManager->getTtl(),
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUserIdentifier(),
            ],
        ]);
    }
}
