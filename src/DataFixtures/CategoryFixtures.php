<?php

namespace App\DataFixtures;

use App\Entity\Category;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public const COUNT = 8;

    public function load(ObjectManager $manager): void
    {
        $labels = [
            'Entrées','Plats','Desserts','Végétarien',
            'Viandes','Poissons','Fromages','Boissons'
        ];

        foreach ($labels as $i => $label) {
            $cat = (new Category())
                ->setTitle($label)
                ->setCreatedAt(new DateTimeImmutable());

            $manager->persist($cat);
            $this->addReference('category'.($i+1), $cat);
        }

        $manager->flush();
    }
}
