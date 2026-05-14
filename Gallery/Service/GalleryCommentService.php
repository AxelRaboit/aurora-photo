<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Core\Sequence\SequenceGenerator;
use Aurora\Core\Sequence\SequencePrefixEnum;
use Aurora\Core\Setting\Enum\ApplicationParameterEnum;
use Aurora\Core\Setting\Repository\SettingRepository;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemCommentInput;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemComment;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemCommentInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemCommentRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GalleryCommentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryItemCommentRepository $commentRepository,
        private GalleryNotificationService $notificationService,
        private SequenceGenerator $sequenceGenerator,
        private SettingRepository $settingRepository,
    ) {}

    public function add(GalleryItemInterface $item, string $visitorToken, GalleryItemCommentInput $input): GalleryItemCommentInterface
    {
        $comment = new GalleryItemComment();
        $comment->setGalleryItem($item);
        $comment->setVisitorToken($visitorToken);
        $comment->setContent($input->content);
        $comment->setVisitorName($input->visitorName);
        $comment->setVisitorEmail($input->visitorEmail);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $commentPrefix = $this->settingRepository->get(ApplicationParameterEnum::PhotoGalleryItemCommentPrefix->value, SequencePrefixEnum::GalleryItemComment->value) ?? SequencePrefixEnum::GalleryItemComment->value;
        $comment->setReference($this->sequenceGenerator->next($commentPrefix));
        $this->entityManager->flush();

        $this->notificationService->notifyItemComment($comment);

        return $comment;
    }

    public function delete(GalleryItemCommentInterface $comment): void
    {
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }

    /**
     * @return list<GalleryItemCommentInterface>
     */
    public function listForGallery(int $galleryId): array
    {
        return $this->commentRepository->findAllForGallery($galleryId);
    }
}
