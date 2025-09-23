<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\Restaurant;
use App\Entity\Category;
use App\Repository\MenuRepository;
use App\Repository\RestaurantRepository;
use App\Repository\CategoryRepository;
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

#[Route('/api/menu', name:'app_api_menu_')]
final class MenuController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private MenuRepository $repository,
        private RestaurantRepository $restaurantRepo,
        private CategoryRepository $categoryRepo,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Route(name:'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/menu',
        summary: 'Créer un menu',
        requestBody: new OA\RequestBody(
            required: true,
            description: "Payload de création d'un menu",
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Menu du jour'),
                    new OA\Property(property: 'description', type: 'string', example: 'Entrée + Plat + Dessert'),
                    new OA\Property(property: 'price', type: 'integer', example: 32),
                    new OA\Property(property: 'restaurantId', type: 'integer', example: 1),
                    new OA\Property(
                        property: 'categoryIds',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [2, 5]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Menu créé',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property:'id', type:'integer', example: 10),
                        new OA\Property(property:'title', type:'string'),
                        new OA\Property(property:'description', type:'string'),
                        new OA\Property(property:'price', type:'integer'),
                        new OA\Property(property:'restaurantId', type:'integer'),
                        new OA\Property(property:'categoryIds', type:'array', items:new OA\Items(type:'integer')),
                        new OA\Property(property:'createdAt', type:'string', format:'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 404, description: 'Restaurant/Catégorie introuvable'),
        ]
    )]
    public function new(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Désérialisation safe (on ignore les relations + champs serveur)
        $menu = $this->serializer->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            Menu::class,
            'json',
            [
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['id', 'restaurant', 'categories', 'createdAt', 'updatedAt'],
            ]
        );

        // Restaurant (obligatoire si JoinColumn nullable=false)
        $restaurantId = $payload['restaurantId'] ?? null;
        if (!$restaurantId) {
            return $this->json(['message' => 'restaurantId is required'], Response::HTTP_BAD_REQUEST);
        }
        $restaurant = $this->restaurantRepo->find($restaurantId);
        if (!$restaurant) {
            return $this->json(['message' => 'Restaurant not found'], Response::HTTP_NOT_FOUND);
        }
        $menu->setRestaurant($restaurant);

        // Catégories optionnelles
        $categoryIds = $payload['categoryIds'] ?? [];
        if (is_array($categoryIds)) {
            foreach ($categoryIds as $cid) {
                $cat = $this->categoryRepo->find((int)$cid);
                if ($cat) {
                    $menu->addCategory($cat);
                }
            }
        }

        $menu->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($menu);
        $this->manager->flush();

        $location = $this->urlGenerator->generate(
            'app_api_menu_show',
            ['id' => $menu->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->json(
            [
                'id'          => $menu->getId(),
                'title'       => $menu->getTitle(),
                'description' => $menu->getDescription(),
                'price'       => $menu->getPrice(),
                'restaurantId'=> $menu->getRestaurant()->getId(),
                'categoryIds' => array_map(fn(Category $c) => $c->getId(), $menu->getCategories()->toArray()),
                'createdAt'   => $menu->getCreatedAt()?->format(DATE_ATOM),
            ],
            Response::HTTP_CREATED,
            ['Location' => $location]
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/menu/{id}',
        summary: 'Afficher un menu par ID',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type:'object',
                    properties: [
                        new OA\Property(property:'id', type:'integer'),
                        new OA\Property(property:'title', type:'string'),
                        new OA\Property(property:'description', type:'string'),
                        new OA\Property(property:'price', type:'integer'),
                        new OA\Property(property:'restaurantId', type:'integer'),
                        new OA\Property(property:'categoryIds', type:'array', items:new OA\Items(type:'integer')),
                        new OA\Property(property:'createdAt', type:'string', format:'date-time'),
                        new OA\Property(property:'updatedAt', type:'string', format:'date-time', nullable:true),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $menu = $this->repository->find($id);
        if (!$menu) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id'          => $menu->getId(),
            'title'       => $menu->getTitle(),
            'description' => $menu->getDescription(),
            'price'       => $menu->getPrice(),
            'restaurantId'=> $menu->getRestaurant()->getId(),
            'categoryIds' => array_map(fn(Category $c) => $c->getId(), $menu->getCategories()->toArray()),
            'createdAt'   => $menu->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $menu->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/menu/{id}',
        summary: 'Modifier un menu',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type:'object',
                properties: [
                    new OA\Property(property:'title', type:'string'),
                    new OA\Property(property:'description', type:'string'),
                    new OA\Property(property:'price', type:'integer'),
                    new OA\Property(property:'restaurantId', type:'integer', nullable:true),
                    new OA\Property(property:'categoryIds', type:'array', items:new OA\Items(type:'integer'), nullable:true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 400, description: 'JSON invalide'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function edit(int $id, Request $request): JsonResponse
    {
        $menu = $this->repository->find($id);
        if (!$menu) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Update simple fields (title/description/price)
        $this->serializer->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            Menu::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $menu,
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['id','restaurant','categories','createdAt','updatedAt'],
            ]
        );

        // Changer de restaurant si restaurantId fourni
        if (array_key_exists('restaurantId', $payload)) {
            $r = $this->restaurantRepo->find((int)$payload['restaurantId']);
            if (!$r) {
                return $this->json(['message' => 'Restaurant not found'], Response::HTTP_NOT_FOUND);
            }
            $menu->setRestaurant($r);
        }

        // Remplacer les catégories si categoryIds fourni
        if (array_key_exists('categoryIds', $payload) && is_array($payload['categoryIds'])) {
            foreach ($menu->getCategories()->toArray() as $existing) {
                $menu->removeCategory($existing);
            }
            foreach ($payload['categoryIds'] as $cid) {
                $cat = $this->categoryRepo->find((int)$cid);
                if ($cat) {
                    $menu->addCategory($cat);
                }
            }
        }

        $menu->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->json([
            'id'          => $menu->getId(),
            'title'       => $menu->getTitle(),
            'description' => $menu->getDescription(),
            'price'       => $menu->getPrice(),
            'restaurantId'=> $menu->getRestaurant()->getId(),
            'categoryIds' => array_map(fn(Category $c) => $c->getId(), $menu->getCategories()->toArray()),
            'createdAt'   => $menu->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $menu->getUpdatedAt()?->format(DATE_ATOM),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/menu/{id}',
        summary: 'Supprimer un menu',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $menu = $this->repository->find($id);
        if (!$menu) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $this->manager->remove($menu);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
