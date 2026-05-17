<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Manager;

use Aurora\Module\Dev\Audit\Service\AuditLogger;
use Aurora\Module\Media\Library\Repository\MediaRepository;
use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Core\Platform\User\Entity\User;
use Aurora\Module\Crm\Contact\Repository\ContactRepository;
use Aurora\Module\Photo\Gallery\Dto\GalleryInputInterface;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryWatermarkService;
use Aurora\Module\Photo\Setting\PhotoSettingEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GalleryManagerInterface::class)]
class GalleryManager implements GalleryManagerInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly MediaRepository $mediaRepository,
        protected readonly ContactRepository $contactRepository,
        protected readonly AuditLogger $auditLogger,
        protected readonly GalleryWatermarkService $watermarkService,
        protected readonly SequenceGenerator $sequenceGenerator,
        protected readonly SettingRepository $settingRepository,
    ) {}

    public function create(GalleryInputInterface $input, User $createdBy): GalleryInterface
    {
        $gallery = $this->createGallery();
        $gallery->setCreatedBy($createdBy);
        $this->applyInput($gallery, $input);
        $prefix = $this->settingRepository->getOrDefault(PhotoSettingEnum::GalleryPrefix);
        $gallery->setReference($this->sequenceGenerator->next($prefix));
        $this->entityManager->persist($gallery);
        $this->entityManager->flush();

        $this->auditCreated($gallery);

        return $gallery;
    }

    public function update(GalleryInterface $gallery, GalleryInputInterface $input): void
    {
        $previousWatermarkSignature = $this->watermarkSignature($gallery);

        $this->applyInput($gallery, $input);
        $this->entityManager->flush();

        if ($previousWatermarkSignature !== $this->watermarkSignature($gallery)) {
            $this->watermarkService->clearCacheForGallery($gallery);
        }

        $this->auditUpdated($gallery);
    }

    /**
     * Re-opens a finalized gallery so the visitor can edit their selection again.
     * Idempotent on already-open galleries.
     */
    public function reopen(GalleryInterface $gallery): void
    {
        if (!$gallery->isFinalized()) {
            return;
        }

        $gallery->setFinalizedAt(null);
        $gallery->setFinalizedByName(null);
        $gallery->setFinalizedByEmail(null);

        $this->entityManager->flush();

        $this->auditLogger->log('photo', 'gallery.reopened', 'Gallery', $gallery->getId(), $this->auditPayload($gallery));
    }

    public function delete(GalleryInterface $gallery): void
    {
        $this->auditDeleted($gallery);

        $this->watermarkService->clearCacheForGallery($gallery);

        $this->entityManager->remove($gallery);
        $this->entityManager->flush();
    }

    protected function createGallery(): GalleryInterface
    {
        return new Gallery();
    }

    protected function applyInput(GalleryInterface $gallery, GalleryInputInterface $input): void
    {
        $gallery->setTitle($input->getTitle());
        $gallery->setSlug($input->getSlug());
        $gallery->setDescription($input->getDescription());
        $gallery->setExpiresAt($input->getExpiresAt());
        $gallery->setAllowOriginals($input->isAllowOriginals());
        $gallery->setAllowZipDownload($input->isAllowZipDownload());
        $gallery->setPicksRequireIdentity($input->isPicksRequireIdentity());
        $gallery->setMaxPicks($input->getMaxPicks());
        $gallery->setAllowVisitorComments($input->isAllowVisitorComments());
        $gallery->setWatermarkEnabled($input->isWatermarkEnabled());
        $gallery->setWatermarkText($input->getWatermarkText());
        $gallery->setCoverMedia(null !== $input->getCoverMediaId() ? $this->mediaRepository->find($input->getCoverMediaId()) : null);
        $gallery->setClientContact(null !== $input->getClientContactId() ? $this->contactRepository->find($input->getClientContactId()) : null);

        // Password handling: hash a new one if provided, clear when explicitly asked.
        if ($input->shouldClearPassword()) {
            $gallery->setPasswordHash(null);
        } elseif (null !== $input->getPassword() && '' !== $input->getPassword()) {
            $gallery->setPasswordHash(password_hash($input->getPassword(), PASSWORD_BCRYPT));
        }

        // else: leave existing hash untouched on update.
    }

    protected function auditCreated(GalleryInterface $gallery): void
    {
        $this->auditLogger->log('photo', 'gallery.created', 'Gallery', $gallery->getId(), $this->auditPayload($gallery));
    }

    protected function auditUpdated(GalleryInterface $gallery): void
    {
        $this->auditLogger->log('photo', 'gallery.updated', 'Gallery', $gallery->getId(), $this->auditPayload($gallery));
    }

    protected function auditDeleted(GalleryInterface $gallery): void
    {
        $this->auditLogger->log('photo', 'gallery.deleted', 'Gallery', $gallery->getId(), $this->auditPayload($gallery));
    }

    protected function auditPayload(GalleryInterface $gallery): array
    {
        return ['title' => $gallery->getTitle(), 'slug' => $gallery->getSlug()];
    }

    private function watermarkSignature(GalleryInterface $gallery): string
    {
        return ($gallery->isWatermarkEnabled() ? '1' : '0').'|'.($gallery->getWatermarkText() ?? '');
    }
}
