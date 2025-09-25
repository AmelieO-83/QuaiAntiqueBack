<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Menu;
use App\Entity\Restaurant;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MenuFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 20;

    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');
        $faker->seed(1005);

        for ($i = 1; $i <= self::COUNT; $i++) {
            /** @var Restaurant $resto */
            $resto = $this->getReference(
                RestaurantFixtures::RESTAURANT_REFERENCE.$faker->numberBetween(1, RestaurantFixtures::RESTAURANT_NB_TUPLES),
                Restaurant::class
            );

            $menu = (new Menu())
                ->setTitle('Menu '.$faker->word())
                ->setDescription($faker->sentence(10))
                ->setPrice($faker->numberBetween(15, 95))
                ->setRestaurant($resto)
                ->setCreatedAt(new DateTimeImmutable());

            // 1 à 4 catégories
            $nb = $faker->numberBetween(1, 4);
            $picked = [];
            for ($k = 0; $k < $nb; $k++) {
                $idx = $faker->numberBetween(1, CategoryFixtures::COUNT);
                if (in_array($idx, $picked, true)) { $k--; continue; }
                $picked[] = $idx;

                /** @var Category $cat */
                $cat = $this->getReference('category'.$idx, Category::class);
                $menu->addCategory($cat);
            }

            $manager->persist($menu);
            $this->addReference('menu'.$i, $menu);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RestaurantFixtures::class, CategoryFixtures::class];
    }
}
