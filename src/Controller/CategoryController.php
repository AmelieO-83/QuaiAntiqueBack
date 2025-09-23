<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Food;
use App\Entity\Menu;
use App\Repository\CategoryRepository;
use App\Repository\FoodRepository;
use App\Repository\MenuRepository;
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

#[Route('/api/category', name:'app_api_category_')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CategoryRepository $repository,
        private FoodRepository $foodRepo,
        private MenuRepository $menuRepo,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Route(name:'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/category',
        summary: 'Créer une catégorie',
        requestBody: new OA\RequestBody(
            required: true,
            description: "Payload de création d'une catégorie",
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property:'title', type:'string', example:'Entrées'),
                    new OA\Property(property:'foodIds', type:'array', items:new OA\Items(type:'integer'), nullable:true, example:[1,2]),
                    new OA\Property(property:'menuIds', type:'array', items:new OA\Items(type:'integer'), nullable:true, example:[3]),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Catégorie créée',
                content: new OA\JsonContent(
                    type:'object',
                    properties: [
                        new OA\Property(property:'id', type:'integer', example: 10),
                        new OA\Property(property:'title', type:'string'),
                        new OA\Property(property:'foodIds', type:'array', items:new OA\Items(type:'integer')),
                        new OA\Property(property:'menuIds', type:'array', items:new OA\Items(type:'integer')),
                        new OA\Property(property:'createdAt', type:'string', format:'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'JSON invalide'),
        ]
    )]
    public function new(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Désérialisation safe (ignore relations & champs serveur)
        $category = $this->serializer->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            Category::class,
            'json',
            [
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['id','foods','menus','createdAt','updatedAt'],
            ]
        );

        $category->setCreatedAt(new DateTimeImmutable());

        // Rattachements optionnels
        foreach (($payload['foodIds'] ?? []) as $fid) {
            if ($f = $this->foodRepo->find((int)$fid)) {
                $category->addFood($f);
            }
        }
        foreach (($payload['menuIds'] ?? []) as $mid) {
            if ($m = $this->menuRepo->find((int)$mid)) {
                $category->addMenu($m);
            }
        }

        $this->manager->persist($category);
        $this->manager->flush();

        $location = $this->urlGenerator->generate(
            'app_api_category_show',
            ['id' => $category->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->json(
            [
                'id'        => $category->getId(),
                'title'     => $category->getTitle(),
                'foodIds'   => array_map(fn(Food $f) => $f->getId(), $category->getFoods()->toArray()),
                'menuIds'   => array_map(fn(Menu $m) => $m->getId(), $category->getMenus()->toArray()),
                'createdAt' => $category->getCreatedAt()?->format(DATE_ATOM),
            ],
            Response::HTTP_CREATED,
            ['Location' => $location]
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/category/{id}',
        summary: 'Afficher une catégorie par ID',
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
                        new OA\Property(property:'foodIds', type:'array', items:new OA\Items(type:'integer')),
                        new OA\Property(property:'menuIds', type:'array', items:new OA\Items(type:'integer')),
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
        $c = $this->repository->find($id);
        if (!$c) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id'        => $c->getId(),
            'title'     => $c->getTitle(),
            'foodIds'   => array_map(fn(Food $f) => $f->getId(), $c->getFoods()->toArray()),
            'menuIds'   => array_map(fn(Menu $m) => $m->getId(), $c->getMenus()->toArray()),
            'createdAt' => $c->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $c->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/category/{id}',
        summary: 'Modifier une catégorie',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type:'object',
                properties: [
                    new OA\Property(property:'title', type:'string'),
                    new OA\Property(property:'foodIds', type:'array', items:new OA\Items(type:'integer'), nullable:true),
                    new OA\Property(property:'menuIds', type:'array', items:new OA\Items(type:'integer'), nullable:true),
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
        $c = $this->repository->find($id);
        if (!$c) {
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
            Category::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $c,
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['id','foods','menus','createdAt','updatedAt'],
            ]
        );

        // Remplacer les food si fourni
        if (array_key_exists('foodIds', $payload) && is_array($payload['foodIds'])) {
            foreach ($c->getFoods()->toArray() as $existing) {
                $c->removeFood($existing);
            }
            foreach ($payload['foodIds'] as $fid) {
                if ($f = $this->foodRepo->find((int)$fid)) {
                    $c->addFood($f);
                }
            }
        }

        // Remplacer les menus si fourni
        if (array_key_exists('menuIds', $payload) && is_array($payload['menuIds'])) {
            foreach ($c->getMenus()->toArray() as $existing) {
                $c->removeMenu($existing);
            }
            foreach ($payload['menuIds'] as $mid) {
                if ($m = $this->menuRepo->find((int)$mid)) {
                    $c->addMenu($m);
                }
            }
        }

        $c->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->json([
            'id'        => $c->getId(),
            'title'     => $c->getTitle(),
            'foodIds'   => array_map(fn(Food $f) => $f->getId(), $c->getFoods()->toArray()),
            'menuIds'   => array_map(fn(Menu $m) => $m->getId(), $c->getMenus()->toArray()),
            'createdAt' => $c->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $c->getUpdatedAt()?->format(DATE_ATOM),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/category/{id}',
        summary: 'Supprimer une catégorie',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $c = $this->repository->find($id);
        if (!$c) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $this->manager->remove($c);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
