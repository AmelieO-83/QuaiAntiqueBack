<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Menu;
use App\Entity\Food;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testTitleAndTimestamps(): void
    {
        $c = new Category();
        $c->setTitle('Desserts');
        $now = new \DateTimeImmutable();
        $c->setCreatedAt($now)->setUpdatedAt($now);

        $this->assertSame('Desserts', $c->getTitle());
        $this->assertSame($now, $c->getCreatedAt());
        $this->assertSame($now, $c->getUpdatedAt());
    }

    public function testAddRemoveMenuKeepsBothSidesIfImplemented(): void
    {
        $c = new Category();
        $m = new Menu();

        $c->addMenu($m);
        $this->assertTrue($c->getMenus()->contains($m));
        // selon ton implémentation, Menu::addCategory() est appelé par Category::addMenu()
        $this->assertTrue($m->getCategories()->contains($c));

        $c->removeMenu($m);
        $this->assertFalse($c->getMenus()->contains($m));
        $this->assertFalse($m->getCategories()->contains($c));
    }

    public function testAddRemoveFoodKeepsBothSidesIfImplemented(): void
    {
        $c = new Category();
        $f = new Food();

        $c->addFood($f);
        $this->assertTrue($c->getFoods()->contains($f));
        $this->assertTrue($f->getCategories()->contains($c));

        $c->removeFood($f);
        $this->assertFalse($c->getFoods()->contains($f));
        $this->assertFalse($f->getCategories()->contains($c));
    }
}
