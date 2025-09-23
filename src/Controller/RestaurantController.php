<?php

namespace App\Controller;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\RestaurantRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/restaurant', name: 'app_api_restaurant_')]
final class RestaurantController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private RestaurantRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Route(name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/restaurant',
        summary: 'Créer un restaurant',
        requestBody: new OA\RequestBody(
            required: true,
            description: "Données du restaurant à créer",
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Nom du restaurant'),
                    new OA\Property(property: 'description', type: 'string', example: 'Description du restaurant'),
                    new OA\Property(property: 'maxGuest', type: 'integer', example: 80),
                    new OA\Property(property: 'amOpeningTime', type: 'array', items: new OA\Items(type: 'string'), example: ['Mon-Thu 12:00-14:00','Fri 12:00-14:30']),
                    new OA\Property(property: 'pmOpeningTime', type: 'array', items: new OA\Items(type: 'string'), example: ['Mon-Thu 19:00-22:00','Fri 19:00-23:00']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Restaurant créé'),
            new OA\Response(response: 400, description: 'JSON invalide / données manquantes'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function new(Request $request, #[CurrentUser] ?User $owner): JsonResponse
    {
        if (!$owner) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $request->toArray(); // force JSON
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Désérialisation « safe » : on ignore relations & champs serveur
        $restaurant = $this->serializer->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            Restaurant::class,
            'json',
            [
                AbstractNormalizer::IGNORED_ATTRIBUTES => [
                    'id', 'owner', 'pictures', 'bookings', 'menus', 'createdAt', 'updatedAt',
                ],
            ]
        );

        // Valeurs serveur
        $restaurant->setCreatedAt(new DateTimeImmutable());
        $restaurant->setOwner($owner);

        $am = $payload['amOpeningTime'] ?? [];
        $pm = $payload['pmOpeningTime'] ?? [];

        $restaurant->setAmOpeningTime(is_array($am) ? array_values($am) : []);
        $restaurant->setPmOpeningTime(is_array($pm) ? array_values($pm) : []);

        $this->manager->persist($restaurant);
        $this->manager->flush();

        $location = $this->urlGenerator->generate(
            'app_api_restaurant_show',
            ['id' => $restaurant->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Réponse « plate » : pas de boucle de sérialisation
        return $this->json(
            [
                'id'        => $restaurant->getId(),
                'name'      => $restaurant->getName(),
                'description' => $restaurant->getDescription(),
                'maxGuest'  => $restaurant->getMaxGuest(),
                'createdAt' => $restaurant->getCreatedAt()?->format(DATE_ATOM),
            ],
            Response::HTTP_CREATED,
            ['Location' => $location]
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/restaurant/{id}',
        summary: 'Afficher un restaurant par son ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du restaurant',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property:'id', type:'integer', example: 1),
                        new OA\Property(property:'name', type:'string', example: 'Quai Antique'),
                        new OA\Property(property:'description', type:'string', example: 'Restaurant du chef...'),
                        new OA\Property(property:'maxGuest', type:'integer', example: 80),
                        new OA\Property(property:'createdAt', type:'string', format:'date-time'),
                        new OA\Property(property:'updatedAt', type:'string', format:'date-time', nullable: true),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $r = $this->repository->find($id);
        if (!$r) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        // Whitelist d’attributs pour éviter les relations
        return $this->json([
            'id'          => $r->getId(),
            'name'        => $r->getName(),
            'description' => $r->getDescription(),
            'maxGuest'    => $r->getMaxGuest(),
            'createdAt'   => $r->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $r->getUpdatedAt()?->format(DATE_ATOM),
            // si tu veux aussi renvoyer l’owner :
            // 'owner' => $r->getOwner()?->getId(),
        ]);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/restaurant/{id}',
        summary: 'Modifier un restaurant',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type:'object',
                properties: [
                    new OA\Property(property:'name', type:'string'),
                    new OA\Property(property:'description', type:'string'),
                    new OA\Property(property:'maxGuest', type:'integer'),
                    new OA\Property(property:'amOpeningTime', type:'array', items:new OA\Items(type:'string')),
                    new OA\Property(property:'pmOpeningTime', type:'array', items:new OA\Items(type:'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type:'object',
                    properties: [
                        new OA\Property(property:'id', type:'integer'),
                        new OA\Property(property:'name', type:'string'),
                        new OA\Property(property:'description', type:'string'),
                        new OA\Property(property:'maxGuest', type:'integer'),
                        new OA\Property(property:'amOpeningTime', type:'array', items:new OA\Items(type:'string')),
                        new OA\Property(property:'pmOpeningTime', type:'array', items:new OA\Items(type:'string')),
                        new OA\Property(property:'createdAt', type:'string', format:'date-time'),
                        new OA\Property(property:'updatedAt', type:'string', format:'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'JSON invalide'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function edit(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $restaurant = $this->repository->find($id);
        if (!$restaurant) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        // Auth + ownership
        if (!$user) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
        if ($restaurant->getOwner()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Désérialisation partielle sans toucher aux relations/owner
        $this->serializer->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            Restaurant::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $restaurant,
                AbstractNormalizer::IGNORED_ATTRIBUTES => [
                    'id', 'owner', 'createdAt', 'updatedAt', 'pictures','bookings','menus',
                ],
            ]
        );

        $restaurant->setUpdatedAt(new DateTimeImmutable());

        if (array_key_exists('amOpeningTime', $payload)) {
            $restaurant->setAmOpeningTime(is_array($payload['amOpeningTime']) ? array_values($payload['amOpeningTime']) : []);
        }
        if (array_key_exists('pmOpeningTime', $payload)) {
            $restaurant->setPmOpeningTime(is_array($payload['pmOpeningTime']) ? array_values($payload['pmOpeningTime']) : []);
        }

        $this->manager->flush();

        return $this->json([
            'id'          => $restaurant->getId(),
            'name'        => $restaurant->getName(),
            'description' => $restaurant->getDescription(),
            'maxGuest'    => $restaurant->getMaxGuest(),
            'amOpeningTime' => $restaurant->getAmOpeningTime(),
            'pmOpeningTime' => $restaurant->getPmOpeningTime(),
            'createdAt'   => $restaurant->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $restaurant->getUpdatedAt()?->format(DATE_ATOM),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/restaurant/{id}',
        summary: 'Supprimer un restaurant',
        security: [['X-AUTH-TOKEN' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du restaurant',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Interdit (pas propriétaire)'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $restaurant = $this->repository->find($id);
        if (!$restaurant) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$user) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
        if ($restaurant->getOwner()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->manager->remove($restaurant);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
