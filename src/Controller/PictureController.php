<?php

namespace App\Controller;

use App\Entity\Picture;
use App\Entity\User;
use App\Repository\PictureRepository;
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
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/picture', name: 'app_api_picture_')]
final class PictureController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private PictureRepository $pictures,
        private RestaurantRepository $restaurants,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    // ---------------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------------
    #[Route(name:'new', methods: ['POST'])]
    public function new(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try { $data = $request->toArray(); }
        catch (\Throwable) { return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST); }

        $title = trim((string)($data['title'] ?? ''));
        $slug  = trim((string)($data['slug'] ?? ''));
        $restaurantId = (int)($data['restaurantId'] ?? 0);

        if ($title === '' || $slug === '' || $restaurantId <= 0) {
            return $this->json(['message' => 'Missing "title", "slug" or "restaurantId"'], Response::HTTP_BAD_REQUEST);
        }

        $restaurant = $this->restaurants->find($restaurantId);
        if (!$restaurant) {
            return $this->json(['message' => 'Restaurant not found'], Response::HTTP_NOT_FOUND);
        }

        // autoriser owner OU admin
        $ownerId = $restaurant->getOwner()?->getId();
        if (!$this->isGranted('ROLE_ADMIN') && $ownerId !== $user->getId()) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        // -------- gestion du fichier envoyé en base64 (depuis le front) --------
        $imageBase64 = $data['imageBase64'] ?? null;
        $filename    = $data['filename']    ?? null; // "photo.jpg"
        $mimeType    = $data['mimeType']    ?? null; // "image/jpeg"
        $maxSize     = 6 * 1024 * 1024;             // 6 Mo

        $publicPath = null;

        if ($imageBase64) {
            // nettoyer "data:*;base64," si présent
            if (is_string($imageBase64) && str_contains($imageBase64, ',')) {
                $parts = explode(',', $imageBase64, 2);
                $imageBase64 = $parts[1];
            }

            $bin = base64_decode((string)$imageBase64, true);
            if ($bin === false) {
                return $this->json(['message' => 'Invalid imageBase64'], Response::HTTP_BAD_REQUEST);
            }
            if (strlen($bin) > $maxSize) {
                return $this->json(['message' => 'File too large (max 6MB)'], Response::HTTP_BAD_REQUEST);
            }

            // extension
            $ext = 'jpg';
            $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if ($mimeType && isset($map[$mimeType])) {
                $ext = $map[$mimeType];
            } elseif (is_string($filename) && preg_match('/\.(jpe?g|png|webp)$/i', $filename, $m)) {
                $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            }

            // dossier d’upload côté public
            $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/pictures';
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $safeSlug = preg_replace('/[^a-z0-9\-]+/i', '-', $slug) ?: ('img-'.bin2hex(random_bytes(4)));
            $unique   = bin2hex(random_bytes(4));
            $targetFs = $targetDir . '/' . $safeSlug . '-' . $unique . '.' . $ext;

            if (@file_put_contents($targetFs, $bin) === false) {
                return $this->json(['message' => 'Cannot write file'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // chemin public (pour le front)
            $publicPath = '/uploads/pictures/' . basename($targetFs);
        }

        $picture = (new Picture())
            ->setTitle($title)
            ->setSlug($slug)
            ->setRestaurant($restaurant)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setPath($publicPath); // <= null si pas d’image envoyée

        $this->manager->persist($picture);
        $this->manager->flush();

        $location = $this->urlGenerator->generate(
            'app_api_picture_show',
            ['id' => $picture->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->json([
            'id'           => $picture->getId(),
            'title'        => $picture->getTitle(),
            'slug'         => $picture->getSlug(),
            'restaurantId' => $restaurant->getId(),
            'path'         => $picture->getPath(), // 👈 renvoyé
            'createdAt'    => $picture->getCreatedAt()?->format(DATE_ATOM),
        ], Response::HTTP_CREATED, ['Location' => $location]);
    }

    // ---------------------------------------------------------------------
    // LIST (public)
    // ---------------------------------------------------------------------
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $list = $this->pictures->findBy([], ['createdAt' => 'DESC'], 50);

        $data = array_map(static function (Picture $p) {
            return [
                'id'           => $p->getId(),
                'title'        => $p->getTitle(),
                'slug'         => $p->getSlug(),
                'restaurantId' => $p->getRestaurant()?->getId(),
                'path'         => $p->getPath(), // 👈
                'createdAt'    => $p->getCreatedAt()?->format(DATE_ATOM),
            ];
        }, $list);

        return new JsonResponse($data);
    }

    // ---------------------------------------------------------------------
    // SHOW (public)
    // ---------------------------------------------------------------------
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/picture/{id}',
        summary: 'Afficher une image',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $p = $this->pictures->find($id);
        if (!$p) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id'           => $p->getId(),
            'title'        => $p->getTitle(),
            'slug'         => $p->getSlug(),
            'restaurantId' => $p->getRestaurant()?->getId(),
            'path'         => $p->getPath(), // 👈
            'createdAt'    => $p->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'    => $p->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    // ---------------------------------------------------------------------
    // EDIT
    // ---------------------------------------------------------------------
    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/picture/{id}',
        summary: 'Modifier une image (owner ou admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'slug',  type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 400, description: 'JSON invalide'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function edit(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $p = $this->pictures->find($id);
        if (!$p) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$user) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $ownerId = $p->getRestaurant()?->getOwner()?->getId();
        if (!$this->isGranted('ROLE_ADMIN') && $ownerId !== $user->getId()) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('title', $data)) {
            $t = trim((string)$data['title']);
            if ($t === '') {
                return $this->json(['message' => 'Invalid "title"'], Response::HTTP_BAD_REQUEST);
            }
            $p->setTitle($t);
        }
        if (array_key_exists('slug', $data)) {
            $s = trim((string)$data['slug']);
            if ($s === '') {
                return $this->json(['message' => 'Invalid "slug"'], Response::HTTP_BAD_REQUEST);
            }
            $p->setSlug($s);
        }

        $p->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->json([
            'id'           => $p->getId(),
            'title'        => $p->getTitle(),
            'slug'         => $p->getSlug(),
            'restaurantId' => $p->getRestaurant()?->getId(),
            'createdAt'    => $p->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'    => $p->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    // ---------------------------------------------------------------------
    // DELETE
    // ---------------------------------------------------------------------
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/picture/{id}',
        summary: 'Supprimer une image (owner ou admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimée'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $p = $this->pictures->find($id);
        if (!$p) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$user) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $ownerId = $p->getRestaurant()?->getOwner()?->getId();
        if (!$this->isGranted('ROLE_ADMIN') && $ownerId !== $user->getId()) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->manager->remove($p);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
