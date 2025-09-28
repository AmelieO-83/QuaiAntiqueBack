<?php

namespace App\Controller;

use App\Entity\Food;
use App\Entity\Category;
use App\Repository\FoodRepository;
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

#[Route('/api/food', name:'app_api_food_')]
final class FoodController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private FoodRepository $repository,
        private CategoryRepository $categoryRepo,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Route(name:'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/food',
        summary: 'Créer un plat',
        requestBody: new OA\RequestBody(
            required: true,
            description: "Payload de création d'un plat",
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property:'title', type:'string', example:'Ravioles aux cèpes'),
                    new OA\Property(property:'description', type:'string', example:'Pâtes fraîches, crème de cèpes, parmesan'),
                    new OA\Property(property:'price', type:'integer', example:18),
                    new OA\Property(
                        property:'categoryIds',
                        type:'array',
                        items: new OA\Items(type:'integer'),
                        example: [1, 3]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Plat créé',
                content: new OA\JsonContent(
                    type:'object',
                    properties: [
                        new OA\Property(property:'id', type:'integer', example: 42),
                        new OA\Property(property:'title', type:'string'),
                        new OA\Property(property:'description', type:'string'),
                        new OA\Property(property:'price', type:'integer'),
                        new OA\Property(property:'categoryIds', type:'array', items:new OA\Items(type:'integer')),
                        new OA\Property(property:'createdAt', type:'string', format:'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'JSON invalide'),
            new OA\Response(response: 404, description: 'Catégorie introuvable'),
        ]
    )]
    public function new(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Désérialisation safe (ignore les relations/champs serveur)
        $food = $this->serializer->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            Food::class,
            'json',
            [
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['id','categories','createdAt','updatedAt'],
            ]
        );

        // Catégories optionnelles
        $categoryIds = $payload['categoryIds'] ?? [];
        if (is_array($categoryIds)) {
            foreach ($categoryIds as $cid) {
                $cat = $this->categoryRepo->find((int)$cid);
                if ($cat) {
                    $food->addCategory($cat);
                }
            }
        }

        $food->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($food);
        $this->manager->flush();

        $location = $this->urlGenerator->generate(
            'app_api_food_show',
            ['id' => $food->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->json(
            [
                'id'          => $food->getId(),
                'title'       => $food->getTitle(),
                'description' => $food->getDescription(),
                'price'       => $food->getPrice(),
                'categoryIds' => array_map(fn(Category $c) => $c->getId(), $food->getCategories()->toArray()),
                'createdAt'   => $food->getCreatedAt()?->format(DATE_ATOM),
            ],
            Response::HTTP_CREATED,
            ['Location' => $location]
        );
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $list = $this->repository->findBy([], ['createdAt' => 'DESC']);

        $out = array_map(static function (Food $f) {
            return [
                'id'          => $f->getId(),
                'title'       => $f->getTitle(),
                'description' => $f->getDescription(),
                'price'       => $f->getPrice(),
                // on exporte les ids de catégories pour le regroupement côté front
                'categoryIds' => array_map(fn(Category $c) => $c->getId(), $f->getCategories()->toArray()),
                'createdAt'   => $f->getCreatedAt()?->format(DATE_ATOM),
                'updatedAt'   => $f->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }, $list);

        return $this->json($out);
    }


    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/food/{id}',
        summary: 'Afficher un plat par ID',
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
        $food = $this->repository->find($id);
        if (!$food) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id'          => $food->getId(),
            'title'       => $food->getTitle(),
            'description' => $food->getDescription(),
            'price'       => $food->getPrice(),
            'categoryIds' => array_map(fn(Category $c) => $c->getId(), $food->getCategories()->toArray()),
            'createdAt'   => $food->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $food->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/food/{id}',
        summary: 'Modifier un plat',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type:'object',
                properties: [
                    new OA\Property(property:'title', type:'string'),
                    new OA\Property(property:'description', type:'string'),
                    new OA\Property(property:'price', type:'integer'),
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
        $food = $this->repository->find($id);
        if (!$food) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Champs simples
        $this->serializer->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            Food::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $food,
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['id','categories','createdAt','updatedAt'],
            ]
        );

        // Remplacer les catégories si categoryIds fourni
        if (array_key_exists('categoryIds', $payload) && is_array($payload['categoryIds'])) {
            foreach ($food->getCategories()->toArray() as $existing) {
                $food->removeCategory($existing);
            }
            foreach ($payload['categoryIds'] as $cid) {
                $cat = $this->categoryRepo->find((int)$cid);
                if ($cat) {
                    $food->addCategory($cat);
                }
            }
        }

        $food->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->json([
            'id'          => $food->getId(),
            'title'       => $food->getTitle(),
            'description' => $food->getDescription(),
            'price'       => $food->getPrice(),
            'categoryIds' => array_map(fn(Category $c) => $c->getId(), $food->getCategories()->toArray()),
            'createdAt'   => $food->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $food->getUpdatedAt()?->format(DATE_ATOM),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/food/{id}',
        summary: 'Supprimer un plat',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $food = $this->repository->find($id);
        if (!$food) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $this->manager->remove($food);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
