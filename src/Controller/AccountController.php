<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/account', name: 'app_api_account_')]
final class AccountController extends AbstractController
{
    public function __construct(private EntityManagerInterface $manager) {}

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/me',
        summary: 'Récupérer mon profil',
        security: [['X-AUTH-TOKEN' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil courant',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                        new OA\Property(property: 'firstName', type: 'string', nullable: true),
                        new OA\Property(property: 'lastName', type: 'string', nullable: true),
                        new OA\Property(property: 'guestNumber', type: 'integer', nullable: true),
                        new OA\Property(property: 'allergy', type: 'string', nullable: true),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id'          => $user->getId(),
            'email'       => $user->getEmail(),
            'firstName'   => $user->getFirstName(),
            'lastName'    => $user->getLastName(),
            'guestNumber' => $user->getGuestNumber(),
            'allergy'     => $user->getAllergy(),
            'roles'       => $user->getRoles(),
            'createdAt'   => $user->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $user->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/edit', name: 'edit', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/account/edit',
        summary: 'Modifier mon profil',
        security: [['X-AUTH-TOKEN' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            description: 'Champs modifiables',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'firstName', type: 'string', nullable: true),
                    new OA\Property(property: 'lastName', type: 'string', nullable: true),
                    new OA\Property(property: 'guestNumber', type: 'integer', nullable: true, minimum: 1),
                    new OA\Property(property: 'allergy', type: 'string', nullable: true),
                    new OA\Property(property: 'password', type: 'string', nullable: true, description: 'Nouveau mot de passe'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 400, description: 'JSON invalide / données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function edit(
        Request $request,
        UserPasswordHasherInterface $hasher,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Whitelist + petits contrôles
        if (array_key_exists('firstName', $data))   { $user->setFirstName($data['firstName'] ?: null); }
        if (array_key_exists('lastName', $data))    { $user->setLastName($data['lastName'] ?: null); }
        if (array_key_exists('guestNumber', $data)) {
            $gn = $data['guestNumber'];
            if ($gn !== null && (!is_numeric($gn) || (int)$gn < 1)) {
                return $this->json(['message' => 'guestNumber must be >= 1'], Response::HTTP_BAD_REQUEST);
            }
            $user->setGuestNumber($gn !== null ? (int)$gn : null);
        }
        if (array_key_exists('allergy', $data))     { $user->setAllergy($data['allergy'] ?: null); }

        if (!empty($data['password'] ?? '')) {
            // règle simple : longueur mini
            if (strlen((string)$data['password']) < 8) {
                return $this->json(['message' => 'Password too short (min 8)'], Response::HTTP_BAD_REQUEST);
            }
            $user->setPassword($hasher->hashPassword($user, (string)$data['password']));
        }

        $user->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        // On renvoie le profil “plat”
        return $this->json([
            'id'          => $user->getId(),
            'email'       => $user->getEmail(),
            'firstName'   => $user->getFirstName(),
            'lastName'    => $user->getLastName(),
            'guestNumber' => $user->getGuestNumber(),
            'allergy'     => $user->getAllergy(),
            'roles'       => $user->getRoles(),
            'createdAt'   => $user->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $user->getUpdatedAt()?->format(DATE_ATOM),
        ], Response::HTTP_OK);
    }
}
