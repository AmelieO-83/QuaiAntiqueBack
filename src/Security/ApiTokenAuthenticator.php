<?php
// src/Security/ApiTokenAuthenticator.php
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

final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(private UserRepository $users) {}

    public function supports(Request $request): ?bool
    {
        // On n’essaie d’authentifier que si le header est présent
        return $request->headers->has('X-AUTH-TOKEN');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->headers->get('X-AUTH-TOKEN');
        if (!$token) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        // Le loader charge l’utilisateur par apiToken
        $badge = new UserBadge($token, fn (string $t) => $this->users->findOneBy(['apiToken' => $t]));

        return new SelfValidatingPassport($badge);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // continuer le flux normal
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $e): ?Response
    {
        return new JsonResponse(['message' => $e->getMessageKey()], Response::HTTP_UNAUTHORIZED);
    }
}
