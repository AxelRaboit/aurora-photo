<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Module\Photo\Gallery\DTO\GalleryItemCommentInput;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemComment;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemCommentRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GalleryCommentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryItemCommentRepository $commentRepository,
    ) {}

    public function add(GalleryItem $item, string $visitorToken, GalleryItemCommentInput $input): GalleryItemComment
    {
        $comment = new GalleryItemComment();
        $comment->setGalleryItem($item);
        $comment->setVisitorToken($visitorToken);
        $comment->setContent($input->content);
        $comment->setVisitorName($input->visitorName);
        $comment->setVisitorEmail($input->visitorEmail);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }

    public function delete(GalleryItemComment $comment): void
    {
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }

    /**
     * @return list<GalleryItemComment>
     */
    public function listForGallery(int $galleryId): array
    {
        return $this->commentRepository->findAllForGallery($galleryId);
    }
}
