<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $title = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Food>
     */
    #[ORM\ManyToMany(targetEntity: Food::class, inversedBy: 'categories')]
    private Collection $foodCategory;

    /**
     * @var Collection<int, Menu>
     */
    #[ORM\ManyToMany(targetEntity: Menu::class, inversedBy: 'categories')]
    private Collection $menuCategory;

    public function __construct()
    {
        $this->foodCategory = new ArrayCollection();
        $this->menuCategory = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Food>
     */
    public function getFoodCategory(): Collection
    {
        return $this->foodCategory;
    }

    public function addFoodCategory(Food $foodCategory): static
    {
        if (!$this->foodCategory->contains($foodCategory)) {
            $this->foodCategory->add($foodCategory);
        }

        return $this;
    }

    public function removeFoodCategory(Food $foodCategory): static
    {
        $this->foodCategory->removeElement($foodCategory);

        return $this;
    }

    /**
     * @return Collection<int, Menu>
     */
    public function getMenuCategory(): Collection
    {
        return $this->menuCategory;
    }

    public function addMenuCategory(Menu $menuCategory): static
    {
        if (!$this->menuCategory->contains($menuCategory)) {
            $this->menuCategory->add($menuCategory);
        }

        return $this;
    }

    public function removeMenuCategory(Menu $menuCategory): static
    {
        $this->menuCategory->removeElement($menuCategory);

        return $this;
    }
}
