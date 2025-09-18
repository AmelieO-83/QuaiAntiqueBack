<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/category', name:'app_api_category_')]
final class CategoryController extends AbstractController
{
    public function __construct(private EntityManagerInterface $manager, private CategoryRepository $repository)
    {
    }

    #[Route(name:'new', methods: ['POST'])]
    public function new(): Response
    {
        $category = new Category();
        $category->setTitle('Plats');
        $category->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($category);
        $this->manager->flush();

        return $this->json(
            ['message' => "Category resource created with {$category->getId()} id"],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $category = $this->repository->findOneBy(['id' => $id]);

        if (!$category) {
            throw $this->createNotFoundException("No Category found for {$id} id");
        }

        return $this->json(
            ['message' => "A Category was found : {$category->getTitle()} for {$category->getId()} id"]
        );
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $category = $this->repository->findOneBy(['id' => $id]);

        if (!$category) {
            throw $this->createNotFoundException("No Category found for {$id} id");
        }

        $category->setTitle('Category name updated');
        $this->manager->flush();

        return $this->redirectToRoute('app_api_category_show', ['id' => $category->getId()]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $category = $this->repository->findOneBy(['id' => $id]);
        if (!$category) {
            throw $this->createNotFoundException("No Category found for {$id} id");
        }

        $this->manager->remove($category);
        $this->manager->flush();

        return $this->json(['message' => "Category resource deleted"], Response::HTTP_NO_CONTENT);
    }
}
