<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalization;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalizationInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryPick;
use Aurora\Module\Photo\Gallery\Entity\GalleryPickInterface;
use Aurora\Module\Photo\Gallery\Enum\PickKindEnum;
use Aurora\Module\Photo\Gallery\Exception\MaxPicksReachedException;
use Aurora\Module\Photo\Gallery\Repository\GalleryFinalizationRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryInviteRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryPickRepository;
use Aurora\Module\Photo\Setting\PhotoSettingEnum;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GalleryPickService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryPickRepository $pickRepository,
        private GalleryFinalizationRepository $finalizationRepository,
        private GalleryInviteRepository $inviteRepository,
        private SequenceGenerator $sequenceGenerator,
        private SettingRepository $settingRepository,
    ) {}

    /**
     * Toggles a pick of the given kind for the visitor on a gallery item.
     *
     * @return bool true if the item is now picked, false if it was just removed
     *
     * @throws MaxPicksReachedException when adding a Favorite pick would exceed Gallery::maxPicks
     */
    public function toggle(
        GalleryItemInterface $item,
        string $visitorToken,
        ?string $visitorName = null,
        ?string $visitorEmail = null,
        PickKindEnum $kind = PickKindEnum::Favorite,
    ): bool {
        $existing = $this->pickRepository->findOneBy([
            'galleryItem' => $item,
            'visitorToken' => $visitorToken,
            'kind' => $kind,
        ]);
        if ($existing instanceof GalleryPickInterface) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();

            return false;
        }

        $gallery = $item->getGallery();
        if (PickKindEnum::Favorite === $kind && null !== $gallery->getMaxPicks()) {
            $current = $this->pickRepository->countForVisitor($visitorToken, (int) $gallery->getId(), $kind);
            if ($current >= $gallery->getMaxPicks()) {
                throw new MaxPicksReachedException($gallery->getMaxPicks());
            }
        }

        $pick = new GalleryPick();
        $pick->setGalleryItem($item);
        $pick->setVisitorToken($visitorToken);
        $pick->setVisitorName($visitorName);
        $pick->setVisitorEmail($visitorEmail);
        $pick->setKind($kind);

        $this->entityManager->persist($pick);
        $this->entityManager->flush();

        $pickPrefix = $this->settingRepository->getOrDefault(PhotoSettingEnum::GalleryPickPrefix);
        $pick->setReference($this->sequenceGenerator->next($pickPrefix));
        $this->entityManager->flush();

        return true;
    }

    /**
     * Records that the visitor (identified by token) has validated their own selection.
     * Idempotent — returns the existing finalization unchanged when the visitor already
     * validated this gallery. Other visitors' selections are unaffected.
     */
    public function finalize(GalleryInterface $gallery, string $visitorToken, ?string $visitorName, ?string $visitorEmail): GalleryFinalizationInterface
    {
        $existing = $this->finalizationRepository->findOneByVisitor((int) $gallery->getId(), $visitorToken);
        if ($existing instanceof GalleryFinalizationInterface) {
            return $existing;
        }

        $finalization = new GalleryFinalization();
        $finalization->setGallery($gallery);
        $finalization->setVisitorToken($visitorToken);
        $finalization->setVisitorName($visitorName);
        $finalization->setVisitorEmail($visitorEmail);

        $this->entityManager->persist($finalization);
        $this->entityManager->flush();

        $finPrefix = $this->settingRepository->getOrDefault(PhotoSettingEnum::GalleryFinalizationPrefix);
        $finalization->setReference($this->sequenceGenerator->next($finPrefix));
        $this->entityManager->flush();

        return $finalization;
    }

    public function isFinalizedBy(GalleryInterface $gallery, string $visitorToken): bool
    {
        return $this->finalizationRepository->findOneByVisitor((int) $gallery->getId(), $visitorToken) instanceof GalleryFinalizationInterface;
    }

    /**
     * Removes a visitor's validation so they can edit their picks again.
     */
    public function reopenFor(GalleryInterface $gallery, string $visitorToken): void
    {
        $existing = $this->finalizationRepository->findOneByVisitor((int) $gallery->getId(), $visitorToken);
        if (!$existing instanceof GalleryFinalizationInterface) {
            return;
        }

        $this->entityManager->remove($existing);
        $this->entityManager->flush();
    }

    /**
     * True when the visitor has supplied identity now or on a previous pick.
     */
    public function visitorHasIdentity(string $visitorToken, ?string $name, ?string $email): bool
    {
        if (null !== $name && '' !== $name && null !== $email && '' !== $email) {
            return true;
        }

        $previous = $this->pickRepository->findOneBy(['visitorToken' => $visitorToken]);
        if ($previous instanceof GalleryPickInterface && null !== $previous->getVisitorName() && null !== $previous->getVisitorEmail()) {
            return true;
        }

        // Invitees always have a known identity even before their first pick.
        return null !== $this->inviteRepository->findOneBy(['visitorToken' => $visitorToken]);
    }

    /**
     * @return array{0: ?string, 1: ?string} [name, email]
     */
    public function recoverIdentity(string $visitorToken, ?string $name, ?string $email): array
    {
        if (null !== $name && null !== $email) {
            return [$name, $email];
        }

        $previous = $this->pickRepository->findOneBy(['visitorToken' => $visitorToken]);
        if ($previous instanceof GalleryPickInterface) {
            $name ??= $previous->getVisitorName();
            $email ??= $previous->getVisitorEmail();
        }

        // Magic-link visitors carry their identity on the invite itself; this
        // makes the very first pick (no prior picks yet) still attribute the
        // pick to the named invitee.
        if (null === $name || null === $email) {
            $invite = $this->inviteRepository->findOneBy(['visitorToken' => $visitorToken]);
            if ($invite instanceof GalleryInviteInterface) {
                $name ??= $invite->getName();
                $email ??= $invite->getEmail();
            }
        }

        return [$name, $email];
    }

    /**
     * @return list<GalleryItemInterface>
     */
    public function itemsPickedBy(GalleryInterface $gallery, string $visitorToken, PickKindEnum $kind = PickKindEnum::Favorite): array
    {
        $picks = $this->pickRepository->findByVisitorForGallery($visitorToken, (int) $gallery->getId());
        $items = [];
        foreach ($picks as $pick) {
            if ($pick->getKind() !== $kind) {
                continue;
            }

            $items[] = $pick->getGalleryItem();
        }

        return $items;
    }

    /**
     * Map of itemId → list<kind> for every pick the visitor made on this gallery.
     *
     * @return array<int, list<string>>
     */
    public function picksByVisitor(GalleryInterface $gallery, string $visitorToken): array
    {
        $picks = $this->pickRepository->findByVisitorForGallery($visitorToken, (int) $gallery->getId());
        $byItem = [];
        foreach ($picks as $pick) {
            $itemId = (int) $pick->getGalleryItem()->getId();
            $byItem[$itemId] ??= [];
            $byItem[$itemId][] = $pick->getKind()->value;
        }

        return $byItem;
    }

    public function favoriteCount(GalleryInterface $gallery, string $visitorToken): int
    {
        return $this->pickRepository->countForVisitor($visitorToken, (int) $gallery->getId(), PickKindEnum::Favorite);
    }
}
