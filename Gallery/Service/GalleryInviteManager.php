<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Core\Sequence\SequencePrefixEnum;
use Aurora\Core\Setting\Enum\ApplicationParameterEnum;
use Aurora\Core\Setting\Repository\SettingRepository;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryInvite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class GalleryInviteManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryNotificationService $notificationService,
        private UrlGeneratorInterface $urlGenerator,
        private GalleryAccessService $accessService,
        private SequenceGenerator $sequenceGenerator,
        private SettingRepository $settingRepository,
    ) {}

    public function create(Gallery $gallery, string $name, string $email): GalleryInvite
    {
        $token = bin2hex(random_bytes(24));
        $prefix = $this->settingRepository->get(ApplicationParameterEnum::PhotoGalleryInvitePrefix->value, SequencePrefixEnum::GalleryInvite->value) ?? SequencePrefixEnum::GalleryInvite->value;

        $invite = new GalleryInvite();
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

    public function delete(GalleryInvite $invite): void
    {
        $this->entityManager->remove($invite);
        $this->entityManager->flush();
    }

    public function send(GalleryInvite $invite): void
    {
        $magicUrl = $this->urlGenerator->generate('frontend_gallery_invite_redeem', [
            'slug' => $invite->getGallery()->getSlug(),
            'token' => $invite->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->notificationService->notifyInvite($invite, $magicUrl);

        $invite->markSent();
        $this->entityManager->flush();
    }

    public function markSeen(GalleryInvite $invite): void
    {
        $invite->markSeen();
        $this->entityManager->flush();
    }
}
