<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\RestaurantRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/booking', name: 'app_api_booking_')]
final class BookingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private BookingRepository $bookingRepo,
        private RestaurantRepository $restaurantRepo,
        private SerializerInterface $serializer,
    ) {}

    #[Route(name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/booking',
        summary: 'Créer une réservation',
        requestBody: new OA\RequestBody(
            required: true,
            description: "Données de la réservation",
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'restaurantId', type: 'integer', example: 1),
                    new OA\Property(property: 'guestNumber', type: 'integer', example: 4),
                    new OA\Property(property: 'orderDate', type: 'string', format: 'date', example: '2025-10-12'),
                    new OA\Property(property: 'orderHour', type: 'string', example: '19:30:00'),
                    new OA\Property(property: 'allergy', type: 'string', nullable: true, example: 'Arachides'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Réservation créée'),
            new OA\Response(response: 400, description: 'JSON invalide / données manquantes'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Restaurant introuvable'),
        ]
    )]
    public function new(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifs minimales
        foreach (['restaurantId','guestNumber','orderDate','orderHour'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->json(['message' => "Missing field: $field"], Response::HTTP_BAD_REQUEST);
            }
        }

        $restaurant = $this->restaurantRepo->find((int)$data['restaurantId']);
        if (!$restaurant) {
            return $this->json(['message' => 'Restaurant not found'], Response::HTTP_NOT_FOUND);
        }

        $booking = new Booking();
        $booking->setRestaurant($restaurant);
        $booking->setClient($user);
        $booking->setGuestNumber((int)$data['guestNumber']);
        $booking->setOrderDate(new \DateTime($data['orderDate']));
        $booking->setOrderHour(new \DateTime($data['orderHour']));
        $booking->setAllergy($data['allergy'] ?? null);
        $booking->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($booking);
        $this->manager->flush();

        return $this->json([
            'id'           => $booking->getId(),
            'restaurantId' => $restaurant->getId(),
            'clientId'     => $user->getId(),
            'guestNumber'  => $booking->getGuestNumber(),
            'orderDate'    => $booking->getOrderDate()?->format('Y-m-d'),
            'orderHour'    => $booking->getOrderHour()?->format('H:i:s'),
            'allergy'      => $booking->getAllergy(),
            'createdAt'    => $booking->getCreatedAt()?->format(DATE_ATOM),
        ], Response::HTTP_CREATED);
    }

    #[Route(name: 'index', methods: ['GET'])]
    public function index(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) return $this->json(['message'=>'Unauthorized'], 401);
        $items = $this->bookingRepo->findBy(['client' => $user], ['orderDate' => 'DESC', 'orderHour' => 'DESC']);
        return $this->json(array_map(fn($b)=>[
            'id' => $b->getId(),
            'restaurantId' => $b->getRestaurant()?->getId(),
            'guestNumber'  => $b->getGuestNumber(),
            'orderDate'    => $b->getOrderDate()?->format('Y-m-d'),
            'orderHour'    => $b->getOrderHour()?->format('H:i:s'),
            'allergy'      => $b->getAllergy(),
        ], $items));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/booking/{id}',
        summary: 'Afficher une réservation',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function show(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $booking = $this->bookingRepo->find($id);
        if (!$booking) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($user && $booking->getClient()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'id'           => $booking->getId(),
            'restaurantId' => $booking->getRestaurant()?->getId(),
            'clientId'     => $booking->getClient()?->getId(),
            'guestNumber'  => $booking->getGuestNumber(),
            'orderDate'    => $booking->getOrderDate()?->format('Y-m-d'),
            'orderHour'    => $booking->getOrderHour()?->format('H:i:s'),
            'allergy'      => $booking->getAllergy(),
            'createdAt'    => $booking->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'    => $booking->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/booking/{id}',
        summary: 'Modifier une réservation (client propriétaire)',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type:'object',
                properties: [
                    new OA\Property(property:'guestNumber', type:'integer'),
                    new OA\Property(property:'orderDate', type:'string', format:'date'),
                    new OA\Property(property:'orderHour', type:'string', example:'20:00:00'),
                    new OA\Property(property:'allergy', type:'string', nullable:true),
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
        $booking = $this->bookingRepo->find($id);
        if (!$booking) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$user) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
        if ($booking->getClient()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Mise à jour champ par champ (évite les relations)
        if (array_key_exists('guestNumber', $data)) {
            $booking->setGuestNumber((int)$data['guestNumber']);
        }
        if (array_key_exists('orderDate', $data)) {
            $booking->setOrderDate(new \DateTime($data['orderDate']));
        }
        if (array_key_exists('orderHour', $data)) {
            $booking->setOrderHour(new \DateTime($data['orderHour']));
        }
        if (array_key_exists('allergy', $data)) {
            $booking->setAllergy($data['allergy'] ?? null);
        }

        $booking->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->json([
            'id'           => $booking->getId(),
            'restaurantId' => $booking->getRestaurant()?->getId(),
            'clientId'     => $booking->getClient()?->getId(),
            'guestNumber'  => $booking->getGuestNumber(),
            'orderDate'    => $booking->getOrderDate()?->format('Y-m-d'),
            'orderHour'    => $booking->getOrderHour()?->format('H:i:s'),
            'allergy'      => $booking->getAllergy(),
            'createdAt'    => $booking->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'    => $booking->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/booking/{id}',
        summary: 'Supprimer une réservation (client propriétaire)',
        parameters: [new OA\Parameter(name:'id', in:'path', required:true, schema:new OA\Schema(type:'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Interdit'),
            new OA\Response(response: 404, description: 'Non trouvé'),
        ]
    )]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $booking = $this->bookingRepo->find($id);
        if (!$booking) {
            return $this->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if (!$user) {
            return $this->json(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }
        if ($booking->getClient()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->manager->remove($booking);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}

