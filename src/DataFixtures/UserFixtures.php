<?php

namespace App\DataFixtures;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const USER_NB_TUPLES = 20;

    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    /** @throws Exception */
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');
        $faker->seed(1001);

        for ($i = 1; $i <= self::USER_NB_TUPLES; $i++) {
            $first = $faker->firstName();
            $last  = $faker->lastName();
            $email = "email.$i@studi.fr"; // déterministe pour éviter les collisions

            $user = (new User())
                ->setFirstName($first)
                ->setLastName($last)
                ->setGuestNumber($faker->numberBetween(0, 5))
                ->setEmail($email)
                ->setCreatedAt(new DateTimeImmutable());

            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'.$i));

            $manager->persist($user);
            $this->addReference('user'.$i, $user);
        }

        $manager->flush();
    }
}
