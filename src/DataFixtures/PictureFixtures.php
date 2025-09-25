<?php

namespace App\DataFixtures;

use App\Entity\Restaurant;
use App\Entity\Picture;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class PictureFixtures extends Fixture implements DependentFixtureInterface
{
    /** @throws Exception */
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');
        $faker->seed(1003);

        for ($i = 1; $i <= 20; $i++) {
            /** @var Restaurant $restaurant */
            $restaurant = $this->getReference(
                RestaurantFixtures::RESTAURANT_REFERENCE.$faker->numberBetween(1, RestaurantFixtures::RESTAURANT_NB_TUPLES),
                Restaurant::class
            );

            $title = ucfirst($faker->words(3, true));

            $picture = (new Picture())
                ->setTitle($title)
                ->setSlug('picture-'.$i) // unique simple
                ->setRestaurant($restaurant)
                ->setCreatedAt(new DateTimeImmutable());

            $manager->persist($picture);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RestaurantFixtures::class];
    }
}
