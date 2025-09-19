<?php
// src/Controller/AccountController.php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/account', name: 'app_api_account_')]
class AccountController extends AbstractController
{
    public function __construct(private EntityManagerInterface $manager) {}

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        // on ne renvoie ni password ni apiToken
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
    public function edit(Request $request, UserPasswordHasherInterface $hasher): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        // Body JSON obligatoire
        try {
            $data = $request->toArray(); // nécessite Content-Type: application/json
        } catch (\JsonException $e) {
            return $this->json(['message' => 'Invalid JSON'], 400);
        }

        // maj champs si fournis
        if (array_key_exists('firstName', $data))   { $user->setFirstName($data['firstName']); }
        if (array_key_exists('lastName', $data))    { $user->setLastName($data['lastName']); }
        if (array_key_exists('guestNumber', $data)) { $user->setGuestNumber((int) $data['guestNumber']); }
        if (array_key_exists('allergy', $data))     { $user->setAllergy($data['allergy'] ?? null); }

        if (!empty($data['password'] ?? '')) {
            $user->setPassword($hasher->hashPassword($user, $data['password']));
        }

        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->manager->flush();

        return $this->json(['message' => 'Profile updated']);
    }
}
