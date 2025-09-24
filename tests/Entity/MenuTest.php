<?php

namespace App\Tests\Entity;

use App\Entity\Menu;
use App\Entity\Category;
use App\Entity\Restaurant;
use PHPUnit\Framework\TestCase;

final class MenuTest extends TestCase
{
    public function testBasic(): void
    {
        $m = new Menu();
        $m->setTitle('Menu Midi')->setDescription('Entrée + Plat')->setPrice(25);

        $this->assertSame('Menu Midi', $m->getTitle());
        $this->assertSame('Entrée + Plat', $m->getDescription());
        $this->assertSame(25, $m->getPrice());
    }

    public function testRestaurantLink(): void
    {
        $m = new Menu();
        $r = new Restaurant();
        $m->setRestaurant($r);

        $this->assertSame($r, $m->getRestaurant());
    }

    public function testCategoriesManyToMany(): void
    {
        $m = new Menu();
        $c = new Category();

        $m->addCategory($c);
        $this->assertTrue($m->getCategories()->contains($c));

        // idempotence
        $m->addCategory($c);
        $this->assertCount(1, $m->getCategories());

        $m->removeCategory($c);
        $this->assertFalse($m->getCategories()->contains($c));
    }
}
