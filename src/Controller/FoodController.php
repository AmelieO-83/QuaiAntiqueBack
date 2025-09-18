<?php

namespace App\Controller;

use App\Entity\Food;
use App\Repository\FoodRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/food', name:'app_api_food_')]
final class FoodController extends AbstractController
{
    public function __construct(private EntityManagerInterface $manager, private FoodRepository $repository)
    {
    }

    #[Route(name:'new', methods: ['POST'])]
    public function new(): Response
    {
        $food = new Food();
        $food->setTitle('Lasagne');
        $food->setDescription('Lasagne maison');
        $food->setPrice(19);
        $food->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($food);
        $this->manager->flush();

        return $this->json(
            ['message' => "Food resource created with {$food->getId()} id"],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $food = $this->repository->findOneBy(['id' => $id]);

        if (!$food) {
            throw $this->createNotFoundException("No Food found for {$id} id");
        }

        return $this->json(
            ['message' => "A Food was found : {$food->getTitle()} for {$food->getId()} id"]
        );
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $food = $this->repository->findOneBy(['id' => $id]);

        if (!$food) {
            throw $this->createNotFoundException("No Food found for {$id} id");
        }

        $food->setTitle('Food name updated');
        $this->manager->flush();

        return $this->redirectToRoute('app_api_food_show', ['id' => $food->getId()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $food = $this->repository->findOneBy(['id' => $id]);
        if (!$food) {
            throw $this->createNotFoundException("No Food found for {$id} id");
        }

        $this->manager->remove($food);
        $this->manager->flush();

        return $this->json(['message' => "Food resource deleted"], Response::HTTP_NO_CONTENT);
    }
}
