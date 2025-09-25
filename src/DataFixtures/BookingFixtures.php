<?php

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Restaurant;
use App\Entity\User;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BookingFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 50;

    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');
        $faker->seed(1006);

        for ($i = 1; $i <= self::COUNT; $i++) {
            /** @var Restaurant $resto */
            $resto = $this->getReference(
                RestaurantFixtures::RESTAURANT_REFERENCE.$faker->numberBetween(1, RestaurantFixtures::RESTAURANT_NB_TUPLES),
                Restaurant::class
            );

            // date dans les 1–30 jours
            $date = (new DateTime('today'))->add(new DateInterval('P'.$faker->numberBetween(1, 30).'D'));
            // horaires plausibles
            $hour = $faker->randomElement([12,13,19,20,21]);
            $minute = $faker->randomElement([0,15,30,45]);
            $orderHour = (clone $date)->setTime($hour, $minute, 0);

            $guests = $faker->numberBetween(1, min(8, max(1, $resto->getMaxGuest() ?? 8)));

            $booking = (new Booking())
                ->setGuestNumber($guests)
                ->setOrderDate($date)       // \DateTime (DATE_MUTABLE)
                ->setOrderHour($orderHour)  // \DateTime
                ->setAllergy($faker->boolean(20) ? $faker->randomElement(['gluten','lactose','arachides']) : null)
                ->setRestaurant($resto)
                ->setCreatedAt(new DateTimeImmutable());

            // client 70% du temps
            if ($faker->boolean(70)) {
                /** @var User $user */
                $user = $this->getReference('user'.$faker->numberBetween(1, UserFixtures::USER_NB_TUPLES), User::class);
                $booking->setClient($user);
            }

            $manager->persist($booking);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class, RestaurantFixtures::class];
    }
}
