<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\DataFixtures;

use Aurora\Core\DataFixtures\CoreDemoFixtures;
use Aurora\Module\Crm\Contact\Entity\Contact;
use Aurora\Module\Crm\DataFixtures\CrmDemoFixtures;
use Aurora\Module\Ged\DataFixtures\GedDemoFixtures;
use Aurora\Module\Ged\Document\Entity\Document;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalization;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemComment;
use Aurora\Module\Photo\Gallery\Entity\GalleryPick;
use Aurora\Module\Platform\User\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Demo photo galleries with items, picks, invites and comments. Uses the demo
 * users and (if installed) CRM contacts as gallery clients. Dev/test only.
 */
class PhotoDemoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['demo'];
    }

    public function getDependencies(): array
    {
        $deps = [CoreDemoFixtures::class, GedDemoFixtures::class];
        if (class_exists(CrmDemoFixtures::class)) {
            $deps[] = CrmDemoFixtures::class;
        }

        return $deps;
    }

    public function load(ObjectManager $manager): void
    {
        assert($manager instanceof EntityManagerInterface);

        $users = [];
        for ($i = 0; $i < CoreDemoFixtures::USER_COUNT; ++$i) {
            $users[] = $this->getReference(CoreDemoFixtures::userRef($i), User::class);
        }

        $media = [];
        for ($i = 0; $this->hasReference(GedDemoFixtures::mediaRef($i), Document::class); ++$i) {
            $media[] = $this->getReference(GedDemoFixtures::mediaRef($i), Document::class);
        }

        $contacts = [];
        if (class_exists(CrmDemoFixtures::class)) {
            for ($i = 0; $this->hasReference(CrmDemoFixtures::contactRef($i), Contact::class); ++$i) {
                $contacts[] = $this->getReference(CrmDemoFixtures::contactRef($i), Contact::class);
            }
        }

        $this->createPhoto($manager, $media, $users, $contacts);

        $manager->flush();
    }

    private function createPhoto(EntityManagerInterface $em, array $media, array $users, array $contacts): void
    {
        [$marie] = $users;

        // Gallery 1 — Portfolio (with visitor picks + comments)
        $g1 = new Gallery();
        $g1->setTitle('Portfolio Projets Aurora Tech 2025')
           ->setSlug('portfolio-aurora-tech-2025')
           ->setDescription('Sélection de visuels réalisés pour nos projets clients en 2025. Galerie privée — accès sur invitation.')
           ->setCreatedBy($marie)
           ->setAllowOriginals(true)
           ->setAllowZipDownload(true)
           ->setAllowVisitorComments(true)
           ->setMaxPicks(5);

        if (isset($media[0])) {
            $g1->setCoverMedia($media[0]);
        }

        $em->persist($g1);

        $imageMedia = array_values(array_filter(array_slice($media, 0, 5), fn ($m): bool => str_contains((string) $m->getMimeType(), 'image')));
        $items1 = [];
        foreach ($imageMedia as $i => $m) {
            $item = new GalleryItem();
            $item->setGallery($g1)->setMedia($m)->setNumber($i + 1);
            $em->persist($item);
            $items1[] = $item;
        }

        // Visitor comments on gallery 1
        $commentTexts = [
            'Superbes photos ! Le rendu est vraiment professionnel.',
            'J\'adore la 3ème photo, les couleurs sont magnifiques.',
            'Peut-on télécharger les originaux ? Merci !',
        ];
        foreach ($commentTexts as $ci => $text) {
            if (!isset($items1[$ci % count($items1)])) {
                continue;
            }

            $c = new GalleryItemComment();
            $c->setGalleryItem($items1[$ci % count($items1)])
              ->setContent($text)
              ->setVisitorToken(bin2hex(random_bytes(8)))
              ->setVisitorName('Visiteur '.($ci + 1));
            $em->persist($c);
        }

        // Visitor picks on gallery 1
        $visitors = [
            ['token' => bin2hex(random_bytes(8)), 'name' => 'Pierre Dubois',  'email' => 'pierre.dubois@tech-innovation.fr'],
            ['token' => bin2hex(random_bytes(8)), 'name' => 'Camille Leroy',  'email' => 'c.leroy@biomed-france.com'],
        ];
        foreach ($visitors as $vi => $visitor) {
            foreach (array_slice($items1, 0, 2) as $item) {
                $pick = new GalleryPick();
                $pick->setGalleryItem($item)
                     ->setVisitorToken($visitor['token'])
                     ->setVisitorName($visitor['name'])
                     ->setVisitorEmail($visitor['email']);
                $em->persist($pick);
            }

            // Finalization for first visitor
            if (0 === $vi) {
                $fin = new GalleryFinalization();
                $fin->setGallery($g1)
                    ->setVisitorToken($visitor['token'])
                    ->setVisitorName($visitor['name'])
                    ->setVisitorEmail($visitor['email']);
                $em->persist($fin);
            }
        }

        // Gallery 2 — Mariage Dupont (simple, no interactions yet)
        $g2 = new Gallery();
        $g2->setTitle('Mariage Dupont — Juin 2025')
           ->setSlug('mariage-dupont-juin-2025')
           ->setDescription('Livraison photos du mariage de Pierre & Julie Dupont, 14 juin 2025.')
           ->setCreatedBy($marie)
           ->setAllowOriginals(true)
           ->setAllowZipDownload(true)
           ->setAllowVisitorComments(true)
           ->setMaxPicks(30);

        if (isset($media[2])) {
            $g2->setCoverMedia($media[2]);
        }

        $em->persist($g2);

        foreach ($imageMedia as $i => $m) {
            $item = new GalleryItem();
            $item->setGallery($g2)->setMedia($m)->setNumber($i + 1);
            $em->persist($item);
        }

        // Gallery 3 — Conférence Aurora Tech Day
        $g3 = new Gallery();
        $g3->setTitle('Aurora Tech Day 2025 — Photos')
           ->setSlug('aurora-tech-day-2025-photos')
           ->setDescription("Galerie des photos officielles de l'Aurora Tech Day du 15 mai 2025.")
           ->setCreatedBy($marie)
           ->setAllowOriginals(false)
           ->setAllowZipDownload(false)
           ->setAllowVisitorComments(false);

        if (isset($media[1])) {
            $g3->setCoverMedia($media[1]);
        }

        $em->persist($g3);

        foreach ($imageMedia as $i => $m) {
            $item = new GalleryItem();
            $item->setGallery($g3)->setMedia($m)->setNumber($i + 1);
            $em->persist($item);
        }
    }
}
