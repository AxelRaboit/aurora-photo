<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Enum\HttpStatusEnum;
use Aurora\Core\Frontend\Controller\JsonRequestTrait;
use Aurora\Core\Frontend\Controller\JsonResponseTrait;
use Aurora\Core\Validation\Service\PayloadValidator;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemAddInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemBulkDeleteInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemCaptionInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemReorderInput;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemCommentInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemInterface;
use Aurora\Module\Photo\Gallery\Manager\GalleryItemManagerInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemCommentRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Aurora\Module\Photo\Gallery\Serializer\GallerySerializerInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryCommentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gallery items sub-domain — add / reorder / caption / delete /
 * bulk-delete items, plus per-item comment deletion (cleanup endpoint
 * for reviewer comments). Split from `GalleriesController`. Route
 * names preserved (`backend_galleries_items_*`, `_comments_delete`).
 */
#[Route('/backend/galleries', name: 'backend_galleries')]
#[IsGranted('photo.galleries.view')]
final class GalleryItemsController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly GalleryItemRepository $itemRepository,
        private readonly GalleryItemManagerInterface $itemManager,
        private readonly GallerySerializerInterface $gallerySerializer,
        private readonly GalleryItemCommentRepository $commentRepository,
        private readonly GalleryCommentService $commentService,
        private readonly PayloadValidator $payloadValidator,
    ) {}

    #[Route('/{id}/items/add', name: '_items_add', requirements: ['id' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function add(GalleryInterface $gallery, Request $request): JsonResponse
    {
        $input = GalleryItemAddInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $added = $this->itemManager->addItems($gallery, $input->mediaIds);

        return $this->jsonSuccess([
            'added' => $added,
            'items' => $this->gallerySerializer->serializeItems($gallery),
        ]);
    }

    #[Route('/{id}/items/reorder', name: '_items_reorder', requirements: ['id' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function reorder(GalleryInterface $gallery, Request $request): JsonResponse
    {
        $input = GalleryItemReorderInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $this->itemManager->reorder($gallery, $input->itemIds);

        return $this->jsonSuccess();
    }

    #[Route('/{id}/items/{itemId}/caption', name: '_items_caption', requirements: ['id' => '\d+|__id__', 'itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function caption(GalleryInterface $gallery, int $itemId, Request $request): JsonResponse
    {
        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItemInterface) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $input = GalleryItemCaptionInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $this->itemManager->updateCaption($item, $input->caption);

        return $this->jsonSuccess();
    }

    #[Route('/{id}/items/{itemId}/delete', name: '_items_delete', requirements: ['id' => '\d+|__id__', 'itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function delete(GalleryInterface $gallery, int $itemId): JsonResponse
    {
        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItemInterface) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->itemManager->delete($item);

        return $this->jsonSuccess();
    }

    #[Route('/{id}/items/bulk-delete', name: '_items_bulk_delete', requirements: ['id' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function bulkDelete(GalleryInterface $gallery, Request $request): JsonResponse
    {
        $input = GalleryItemBulkDeleteInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $deleted = $this->itemManager->bulkDelete($gallery, $input->itemIds);

        return $this->jsonSuccess(['deleted' => $deleted]);
    }

    #[Route('/{id}/comments/{commentId}/delete', name: '_comments_delete', requirements: ['id' => '\d+|__id__', 'commentId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function deleteComment(GalleryInterface $gallery, int $commentId): JsonResponse
    {
        $comment = $this->commentRepository->findInGallery($commentId, (int) $gallery->getId());
        if (!$comment instanceof GalleryItemCommentInterface) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->commentService->delete($comment);

        return $this->jsonSuccess();
    }
}
