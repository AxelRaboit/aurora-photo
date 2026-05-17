<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Manager;

use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInvite;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryAccessService;
use Aurora\Module\Photo\Gallery\Service\GalleryNotificationService;
use Aurora\Module\Photo\Setting\PhotoSettingEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsAlias(GalleryInviteManagerInterface::class)]
class GalleryInviteManager implements GalleryInviteManagerInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly GalleryNotificationService $notificationService,
        protected readonly UrlGeneratorInterface $urlGenerator,
        protected readonly GalleryAccessService $accessService,
        protected readonly SequenceGenerator $sequenceGenerator,
        protected readonly SettingRepository $settingRepository,
    ) {}

    public function create(GalleryInterface $gallery, string $name, string $email): GalleryInviteInterface
    {
        $token = bin2hex(random_bytes(24));
        $prefix = $this->settingRepository->getOrDefault(PhotoSettingEnum::GalleryInvitePrefix);

        $invite = $this->createGalleryInvite();
        $invite->setGallery($gallery);
        $invite->setName($name);
        $invite->setEmail(mb_strtolower($email));
        $invite->setToken($token);
        $invite->setVisitorToken($this->accessService->visitorTokenForInviteToken($token));
        $invite->setReference($this->sequenceGenerator->next($prefix));

        $this->entityManager->persist($invite);
        $this->entityManager->flush();

        return $invite;
    }

    public function delete(GalleryInviteInterface $invite): void
    {
        $this->entityManager->remove($invite);
        $this->entityManager->flush();
    }

    public function send(GalleryInviteInterface $invite): void
    {
        $magicUrl = $this->urlGenerator->generate('frontend_gallery_invite_redeem', [
            'slug' => $invite->getGallery()->getSlug(),
            'token' => $invite->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->notificationService->notifyInvite($invite, $magicUrl);

        $invite->markSent();
        $this->entityManager->flush();
    }

    public function markSeen(GalleryInviteInterface $invite): void
    {
        $invite->markSeen();
        $this->entityManager->flush();
    }

    protected function createGalleryInvite(): GalleryInviteInterface
    {
        return new GalleryInvite();
    }
}
