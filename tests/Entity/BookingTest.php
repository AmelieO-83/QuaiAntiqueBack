<?php

namespace App\Tests\Entity;

use App\Entity\Booking;
use App\Entity\Restaurant;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    public function testSetters(): void
    {
        $b = new Booking();
        $b->setGuestNumber(3);

        $date = new \DateTime('2025-09-30');
        $hour = new \DateTime('19:30');

        $b->setOrderDate($date);
        $b->setOrderHour($hour);
        $b->setAllergy('Peanuts');

        $this->assertSame(3, $b->getGuestNumber());
        $this->assertSame($date, $b->getOrderDate());
        $this->assertSame($hour, $b->getOrderHour());
        $this->assertSame('Peanuts', $b->getAllergy());
    }

    public function testClientAndRestaurantReferences(): void
    {
        $b = new Booking();
        $u = new User();
        $r = new Restaurant();

        $b->setClient($u);
        $b->setRestaurant($r);

        $this->assertSame($u, $b->getClient());
        $this->assertSame($r, $b->getRestaurant());
    }
}
