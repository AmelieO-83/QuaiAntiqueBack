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
    /** @throws Exception */
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $restaurant = (new Restaurant())
                ->setName("Restaurant n°$i")
                ->setDescription("Description n°$i")
                ->setAmOpeningTime([])
                ->setPmOpeningTime([])
                ->setMaxGuest(random_int(10, 50))
                ->setCreatedAt(new DateTimeImmutable())
                // ➜ owner obligatoire dans ton schéma
                ->setOwner($this->getReference('user'.$i, User::class)); // un propriétaire unique

            $manager->persist($restaurant);

            // ➜ garde EXACTEMENT ce format si tu utilises "restaurant".random_int(1,20) après
            $this->addReference('restaurant'.$i, $restaurant);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
