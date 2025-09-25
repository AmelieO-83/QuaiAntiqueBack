<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Food;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FoodFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 30;

    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');
        $faker->seed(1004);

        for ($i = 1; $i <= self::COUNT; $i++) {
            $food = (new Food())
                ->setTitle(ucfirst($faker->words(3, true)))
                ->setDescription($faker->sentence(12))
                ->setPrice($faker->numberBetween(6, 45))
                ->setCreatedAt(new DateTimeImmutable());

            // 1 à 3 catégories distinctes
            $nb = $faker->numberBetween(1, 3);
            $picked = [];
            for ($k = 0; $k < $nb; $k++) {
                $idx = $faker->numberBetween(1, CategoryFixtures::COUNT);
                if (in_array($idx, $picked, true)) { $k--; continue; }
                $picked[] = $idx;

                /** @var Category $cat */
                $cat = $this->getReference('category'.$idx, Category::class);
                $food->addCategory($cat);
            }

            $manager->persist($food);
            $this->addReference('food'.$i, $food);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CategoryFixtures::class];
    }
}
