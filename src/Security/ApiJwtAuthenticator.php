<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiJwtManager $jwtManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if (!str_starts_with($request->getPathInfo(), '/api/v1')) {
            return false;
        }

        if ('/api/v1/login_check' === $request->getPathInfo()) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = $request->headers->get('Authorization', '');
        $token = trim(substr($authorization, 7));

        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Token JWT manquant.');
        }

        try {
            $payload = $this->jwtManager->decodeToken($token);
        } catch (\Throwable) {
            throw new CustomUserMessageAuthenticationException('Token JWT invalide ou expiré.');
        }

        $username = $payload['username'] ?? null;
        if (!is_string($username) || '' === $username) {
            throw new CustomUserMessageAuthenticationException('Payload JWT invalide.');
        }

        return new SelfValidatingPassport(new UserBadge($username, function (string $identifier) {
            return $this->userRepository->findOneByUsername($identifier);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'unauthorized',
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'error' => 'unauthorized',
            'message' => 'Authentification JWT requise (header Authorization: Bearer <token>).',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
