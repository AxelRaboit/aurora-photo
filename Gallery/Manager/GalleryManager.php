<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Manager;

use Aurora\Core\Audit\Service\AuditLogger;
use Aurora\Core\Media\Repository\MediaRepository;
use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Core\Sequence\SequencePrefixEnum;
use Aurora\Core\Setting\Enum\ApplicationParameterEnum;
use Aurora\Core\Setting\Repository\SettingRepository;
use Aurora\Core\User\Entity\User;
use Aurora\Module\Crm\Contact\Repository\ContactRepository;
use Aurora\Module\Photo\Gallery\Contract\GalleryManagerInterface;
use Aurora\Module\Photo\Gallery\DTO\GalleryInput;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Service\GalleryWatermarkService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GalleryManagerInterface::class)]
final readonly class GalleryManager implements GalleryManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaRepository $mediaRepository,
        private ContactRepository $contactRepository,
        private AuditLogger $auditLogger,
        private GalleryWatermarkService $watermarkService,
        private SequenceGenerator $sequenceGenerator,
        private SettingRepository $settingRepository,
    ) {}

    public function create(GalleryInput $input, User $createdBy): Gallery
    {
        $gallery = new Gallery();
        $gallery->setCreatedBy($createdBy);
        $this->applyInput($gallery, $input, isCreate: true);
        $prefix = $this->settingRepository->get(ApplicationParameterEnum::PhotoGalleryPrefix->value, SequencePrefixEnum::Gallery->value) ?? SequencePrefixEnum::Gallery->value;
        $gallery->setReference($this->sequenceGenerator->next($prefix));
        $this->entityManager->persist($gallery);
        $this->entityManager->flush();

        $this->auditLogger->log('photo', 'gallery.created', 'Gallery', $gallery->getId(), ['title' => $gallery->getTitle(), 'slug' => $gallery->getSlug()]);

        return $gallery;
    }

    public function update(Gallery $gallery, GalleryInput $input): void
    {
        $previousWatermarkSignature = $this->watermarkSignature($gallery);

        $this->applyInput($gallery, $input, isCreate: false);
        $this->entityManager->flush();

        if ($previousWatermarkSignature !== $this->watermarkSignature($gallery)) {
            $this->watermarkService->clearCacheForGallery($gallery);
        }

        $this->auditLogger->log('photo', 'gallery.updated', 'Gallery', $gallery->getId(), ['title' => $gallery->getTitle()]);
    }

    /**
     * Re-opens a finalized gallery so the visitor can edit their selection again.
     * Idempotent on already-open galleries.
     */
    public function reopen(Gallery $gallery): void
    {
        if (!$gallery->isFinalized()) {
            return;
        }

        $gallery->setFinalizedAt(null);
        $gallery->setFinalizedByName(null);
        $gallery->setFinalizedByEmail(null);

        $this->entityManager->flush();

        $this->auditLogger->log('photo', 'gallery.reopened', 'Gallery', $gallery->getId(), ['title' => $gallery->getTitle()]);
    }

    public function delete(Gallery $gallery): void
    {
        $id = $gallery->getId();
        $title = $gallery->getTitle();

        $this->watermarkService->clearCacheForGallery($gallery);

        $this->entityManager->remove($gallery);
        $this->entityManager->flush();

        $this->auditLogger->log('photo', 'gallery.deleted', 'Gallery', $id, ['title' => $title]);
    }

    private function applyInput(Gallery $gallery, GalleryInput $input, bool $isCreate): void
    {
        $gallery->setTitle($input->title);
        $gallery->setSlug($input->slug);
        $gallery->setDescription($input->description);
        $gallery->setExpiresAt($input->expiresAt);
        $gallery->setAllowOriginals($input->allowOriginals);
        $gallery->setAllowZipDownload($input->allowZipDownload);
        $gallery->setPicksRequireIdentity($input->picksRequireIdentity);
        $gallery->setMaxPicks($input->maxPicks);
        $gallery->setAllowVisitorComments($input->allowVisitorComments);
        $gallery->setWatermarkEnabled($input->watermarkEnabled);
        $gallery->setWatermarkText($input->watermarkText);
        $gallery->setCoverMedia(null !== $input->coverMediaId ? $this->mediaRepository->find($input->coverMediaId) : null);
        $gallery->setClientContact(null !== $input->clientContactId ? $this->contactRepository->find($input->clientContactId) : null);

        // Password handling: hash a new one if provided, clear when explicitly asked.
        if ($input->clearPassword) {
            $gallery->setPasswordHash(null);
        } elseif (null !== $input->password && '' !== $input->password) {
            $gallery->setPasswordHash(password_hash($input->password, PASSWORD_BCRYPT));
        }

        // else: leave existing hash untouched on update.

        if ($isCreate) {
            // Constructor + PrePersist populate timestamps automatically.
        }
    }

    private function watermarkSignature(Gallery $gallery): string
    {
        return ($gallery->isWatermarkEnabled() ? '1' : '0').'|'.($gallery->getWatermarkText() ?? '');
    }
}
