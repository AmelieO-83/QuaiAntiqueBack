<?php

namespace App\Tests\Entity;

use App\Entity\Restaurant;
use App\Entity\Picture;
use App\Entity\Booking;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class RestaurantTest extends TestCase
{
    public function testBasicSetters(): void
    {
        $r = new Restaurant();
        $r->setName('Quai Antique');
        $r->setDescription('Bistronomique');
        $r->setMaxGuest(80);

        $this->assertSame('Quai Antique', $r->getName());
        $this->assertSame('Bistronomique', $r->getDescription());
        $this->assertSame(80, $r->getMaxGuest());
    }

    public function testAmPmOpeningTimeStoredAsArray(): void
    {
        $r = new Restaurant();
        $r->setAmOpeningTime(['Mon 12:00-14:00']);
        $r->setPmOpeningTime(['Mon 19:00-22:00']);

        $this->assertSame(['Mon 12:00-14:00'], $r->getAmOpeningTime());
        $this->assertSame(['Mon 19:00-22:00'], $r->getPmOpeningTime());
    }

    public function testOwnerAssignment(): void
    {
        $r = new Restaurant();
        $u = new User();
        $r->setOwner($u);

        $this->assertSame($u, $r->getOwner());
        // selon ton choix d’éviter les boucles, ne teste pas la synchro inverse automatique ici
    }

    public function testAddRemovePictureSyncsOwningSide(): void
    {
        $r = new Restaurant();
        $p = new Picture();

        $r->addPicture($p);
        $this->assertTrue($r->getPictures()->contains($p));
        $this->assertSame($r, $p->getRestaurant());

        $r->removePicture($p);
        $this->assertFalse($r->getPictures()->contains($p));
        $this->assertNull($p->getRestaurant());
    }

    public function testAddRemoveBookingSyncsOwningSide(): void
    {
        $r = new Restaurant();
        $b = new Booking();

        $r->addBooking($b);
        $this->assertTrue($r->getBookings()->contains($b));
        $this->assertSame($r, $b->getRestaurant());

        $r->removeBooking($b);
        $this->assertFalse($r->getBookings()->contains($b));
        $this->assertNull($b->getRestaurant());
    }
}
