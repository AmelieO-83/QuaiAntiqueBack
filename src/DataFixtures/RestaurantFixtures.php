<?php

namespace App\DataFixtures;

use App\Entity\Restaurant;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Exception;

class RestaurantFixtures extends Fixture implements DependentFixtureInterface
{
    public const RESTAURANT_NB_TUPLES = 20;
    public const RESTAURANT_REFERENCE = 'restaurant';

    /** @throws Exception */
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');
        $faker->seed(1002);

        for ($i = 1; $i <= self::RESTAURANT_NB_TUPLES; $i++) {
            // créneaux d’ouverture simples (JSON[])
            $am = $faker->randomElements([
                'Mon 12:00-14:00','Tue 12:00-14:00','Wed 12:00-14:00','Thu 12:00-14:00','Fri 12:00-14:30'
            ], $faker->numberBetween(2, 5));
            $pm = $faker->randomElements([
                'Mon 19:00-22:00','Tue 19:00-22:00','Wed 19:00-22:00','Thu 19:00-22:00','Fri 19:00-23:00','Sat 19:00-23:00'
            ], $faker->numberBetween(3, 6));
            $name = $faker->company().' '.$faker->randomElement(['Bistro','Brasserie','Maison','Atelier']);
            // coupe proprement à 32 caractères
            $name = mb_substr($name, 0, 32);

            /** @var User $owner */
            $owner = $this->getReference('user'.$i, User::class);

            $restaurant = (new Restaurant())
                ->setName($name)
                ->setDescription($faker->sentence(12))
                ->setAmOpeningTime(array_values($am))
                ->setPmOpeningTime(array_values($pm))
                ->setMaxGuest($faker->numberBetween(20, 80))
                ->setCreatedAt(new DateTimeImmutable())
                ->setOwner($owner);

            $manager->persist($restaurant);
            $this->addReference(self::RESTAURANT_REFERENCE.$i, $restaurant);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
