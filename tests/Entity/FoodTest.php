<?php

namespace App\Tests\Entity;

use App\Entity\Food;
use App\Entity\Category;
use PHPUnit\Framework\TestCase;

final class FoodTest extends TestCase
{
    public function testBasic(): void
    {
        $f = new Food();
        $f->setTitle('Risotto')->setDescription('Crémeux')->setPrice(18);

        $this->assertSame('Risotto', $f->getTitle());
        $this->assertSame('Crémeux', $f->getDescription());
        $this->assertSame(18, $f->getPrice());
    }

    public function testCategoriesManyToMany(): void
    {
        $f = new Food();
        $c = new Category();

        $f->addCategory($c);
        $this->assertTrue($f->getCategories()->contains($c));

        $f->addCategory($c);
        $this->assertCount(1, $f->getCategories());

        $f->removeCategory($c);
        $this->assertFalse($f->getCategories()->contains($c));
    }
}
