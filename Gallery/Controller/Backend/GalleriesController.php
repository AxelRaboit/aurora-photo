<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Enum\HttpStatusEnum;
use Aurora\Core\Frontend\Controller\JsonRequestTrait;
use Aurora\Core\Frontend\Controller\JsonResponseTrait;
use Aurora\Core\User\Entity\User;
use Aurora\Core\Validation\Dto\PaginationRequest;
use Aurora\Core\Validation\Service\PayloadValidator;
use Aurora\Module\Photo\Gallery\Dto\GalleryInputFactoryInterface;
use Aurora\Module\Photo\Gallery\Dto\GalleryInviteInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemAddInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemBulkDeleteInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemCaptionInput;
use Aurora\Module\Photo\Gallery\Dto\GalleryItemReorderInput;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalization;
use Aurora\Module\Photo\Gallery\Entity\GalleryInvite;
use Aurora\Module\Photo\Gallery\Entity\GalleryItem;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemComment;
use Aurora\Module\Photo\Gallery\Manager\GalleryInviteManagerInterface;
use Aurora\Module\Photo\Gallery\Manager\GalleryItemManagerInterface;
use Aurora\Module\Photo\Gallery\Manager\GalleryManagerInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryFinalizationRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryInviteRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemCommentRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;
use Aurora\Module\Photo\Gallery\Serializer\GallerySerializerInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryCommentService;
use Aurora\Module\Photo\Gallery\Service\GalleryExportService;
use Aurora\Module\Photo\Gallery\Service\GalleryPickService;
use Aurora\Module\Photo\Gallery\View\GalleryViewBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/backend/galleries', name: 'backend_galleries')]
#[IsGranted('photo.galleries.view')]
final class GalleriesController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly GalleryRepository $galleryRepository,
        private readonly GalleryItemRepository $itemRepository,
        private readonly GallerySerializerInterface $gallerySerializer,
        private readonly GalleryManagerInterface $galleryManager,
        private readonly GalleryItemManagerInterface $itemManager,
        private readonly PayloadValidator $payloadValidator,
        private readonly GalleryExportService $exportService,
        private readonly GalleryCommentService $commentService,
        private readonly GalleryItemCommentRepository $commentRepository,
        private readonly GalleryFinalizationRepository $finalizationRepository,
        private readonly GalleryPickService $pickService,
        private readonly GalleryInviteRepository $inviteRepository,
        private readonly GalleryInviteManagerInterface $inviteManager,
        private readonly GalleryViewBuilder $viewBuilder,
        private readonly GalleryInputFactoryInterface $galleryInputFactory,
    ) {}

    #[Route('', name: '', methods: [HttpMethodEnum::Get->value])]
    public function index(PaginationRequest $pagination): Response
    {
        return $this->render('@Photo/backend/galleries/index.html.twig', $this->viewBuilder->indexView($pagination));
    }

    #[Route('/list', name: '_list', methods: [HttpMethodEnum::Get->value])]
    public function list(PaginationRequest $pagination): JsonResponse
    {
        return $this->json($this->gallerySerializer->serializeListPayload($this->galleryRepository->findPaginated($pagination->page, search: $pagination->search)));
    }

    #[Route('/create', name: '_create', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.create')]
    public function create(Request $request): JsonResponse
    {
        $input = $this->galleryInputFactory->fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);

        if ([] === $errors && $this->galleryRepository->isSlugTaken($input->getSlug())) {
            $errors['slug'] = 'photo.galleries.errors.slug_taken';
        }

        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        /** @var User $user */
        $user = $this->getUser();
        $gallery = $this->galleryManager->create($input, $user);

        return $this->jsonSuccess(['gallery' => $this->gallerySerializer->serialize($gallery)]);
    }

    #[Route('/{id}/update', name: '_update', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function update(Gallery $gallery, Request $request): JsonResponse
    {
        $input = $this->galleryInputFactory->fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);

        if ([] === $errors && $this->galleryRepository->isSlugTaken($input->getSlug(), $gallery->getId())) {
            $errors['slug'] = 'photo.galleries.errors.slug_taken';
        }

        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $this->galleryManager->update($gallery, $input);

        return $this->jsonSuccess(['gallery' => $this->gallerySerializer->serialize($gallery)]);
    }

    #[Route('/{id}/delete', name: '_delete', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.delete')]
    public function delete(Gallery $gallery): JsonResponse
    {
        $this->galleryManager->delete($gallery);

        return $this->jsonSuccess();
    }

    #[Route('/{id}/edit', name: '_edit', methods: [HttpMethodEnum::Get->value])]
    #[IsGranted('photo.galleries.edit')]
    public function edit(Gallery $gallery): Response
    {
        $gallery = $this->galleryRepository->findOneWithItemsAndMedia((int) $gallery->getId()) ?? $gallery;

        return $this->render('@Photo/backend/galleries/edit.html.twig', $this->viewBuilder->editView($gallery));
    }

    #[Route('/{id}/reopen', name: '_reopen', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function reopen(Gallery $gallery): JsonResponse
    {
        $this->galleryManager->reopen($gallery);

        return $this->jsonSuccess(['gallery' => $this->gallerySerializer->serialize($gallery)]);
    }

    #[Route('/{id}/invites/create', name: '_invites_create', requirements: ['id' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function createInvite(Gallery $gallery, Request $request): JsonResponse
    {
        $input = GalleryInviteInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $existing = $this->inviteRepository->findOneByGalleryAndEmail((int) $gallery->getId(), $input->email);
        if ($existing instanceof GalleryInvite) {
            return $this->jsonInvalidInput(['email' => 'photo.galleries.errors.invite_email_taken'], HttpStatusEnum::Conflict->value);
        }

        $this->inviteManager->create($gallery, $input->name, $input->email);

        return $this->jsonSuccess(['invites' => $this->gallerySerializer->serializeInvites($gallery)]);
    }

    #[Route('/{id}/invites/{inviteId}/send', name: '_invites_send', requirements: ['id' => '\d+', 'inviteId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function sendInvite(Gallery $gallery, int $inviteId): JsonResponse
    {
        $invite = $this->inviteRepository->findInGallery($inviteId, (int) $gallery->getId());
        if (!$invite instanceof GalleryInvite) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->inviteManager->send($invite);

        return $this->jsonSuccess(['invites' => $this->gallerySerializer->serializeInvites($gallery)]);
    }

    #[Route('/{id}/invites/{inviteId}', name: '_invites_delete', requirements: ['id' => '\d+', 'inviteId' => '\d+|__id__'], methods: [HttpMethodEnum::Delete->value])]
    #[IsGranted('photo.galleries.edit')]
    public function deleteInvite(Gallery $gallery, int $inviteId): JsonResponse
    {
        $invite = $this->inviteRepository->findInGallery($inviteId, (int) $gallery->getId());
        if (!$invite instanceof GalleryInvite) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->inviteManager->delete($invite);

        return $this->jsonSuccess(['invites' => $this->gallerySerializer->serializeInvites($gallery)]);
    }

    #[Route('/{id}/finalizations/{finalizationId}', name: '_finalizations_delete', requirements: ['id' => '\d+', 'finalizationId' => '\d+|__id__'], methods: [HttpMethodEnum::Delete->value])]
    #[IsGranted('photo.galleries.edit')]
    public function deleteFinalization(Gallery $gallery, int $finalizationId): JsonResponse
    {
        $finalization = $this->finalizationRepository->findInGallery($finalizationId, (int) $gallery->getId());
        if (!$finalization instanceof GalleryFinalization) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->pickService->reopenFor($gallery, $finalization->getVisitorToken());

        return $this->jsonSuccess([
            'gallery' => $this->gallerySerializer->serialize($gallery),
            'finalizations' => $this->gallerySerializer->serializeFinalizations($gallery),
            'invites' => $this->gallerySerializer->serializeInvites($gallery),
        ]);
    }

    #[Route('/{id}/export.xlsx', name: '_export', methods: [HttpMethodEnum::Get->value])]
    #[IsGranted('photo.galleries.edit')]
    public function export(Gallery $gallery): Response
    {
        $gallery = $this->galleryRepository->findOneWithItemsAndMedia((int) $gallery->getId()) ?? $gallery;

        return $this->exportService->buildXlsxResponse($gallery);
    }

    #[Route('/{id}/items/add', name: '_items_add', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function addItems(Gallery $gallery, Request $request): JsonResponse
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

    #[Route('/{id}/items/reorder', name: '_items_reorder', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function reorderItems(Gallery $gallery, Request $request): JsonResponse
    {
        $input = GalleryItemReorderInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $this->itemManager->reorder($gallery, $input->itemIds);

        return $this->jsonSuccess();
    }

    #[Route('/{id}/items/{itemId}/caption', name: '_items_caption', requirements: ['itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function updateItemCaption(Gallery $gallery, int $itemId, Request $request): JsonResponse
    {
        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItem) {
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

    #[Route('/{id}/items/{itemId}/delete', name: '_items_delete', requirements: ['itemId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function deleteItem(Gallery $gallery, int $itemId): JsonResponse
    {
        $item = $this->itemRepository->findInGallery($itemId, (int) $gallery->getId());
        if (!$item instanceof GalleryItem) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->itemManager->delete($item);

        return $this->jsonSuccess();
    }

    #[Route('/{id}/items/bulk-delete', name: '_items_bulk_delete', methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function bulkDeleteItems(Gallery $gallery, Request $request): JsonResponse
    {
        $input = GalleryItemBulkDeleteInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $deleted = $this->itemManager->bulkDelete($gallery, $input->itemIds);

        return $this->jsonSuccess(['deleted' => $deleted]);
    }

    #[Route('/{id}/comments/{commentId}/delete', name: '_comments_delete', requirements: ['commentId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function deleteComment(Gallery $gallery, int $commentId): JsonResponse
    {
        $comment = $this->commentRepository->findInGallery($commentId, (int) $gallery->getId());
        if (!$comment instanceof GalleryItemComment) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->commentService->delete($comment);

        return $this->jsonSuccess();
    }
}
