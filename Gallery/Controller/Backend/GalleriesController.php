<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Enum\HttpStatusEnum;
use Aurora\Core\Http\JsonRequestTrait;
use Aurora\Core\Http\JsonResponseTrait;
use Aurora\Core\Validation\Dto\PaginationRequest;
use Aurora\Core\Validation\Service\PayloadValidator;
use Aurora\Module\Photo\Gallery\Dto\GalleryInputFactoryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryFinalizationInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Manager\GalleryManagerInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryFinalizationRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;
use Aurora\Module\Photo\Gallery\Serializer\GallerySerializerInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryExportService;
use Aurora\Module\Photo\Gallery\Service\GalleryPickService;
use Aurora\Module\Photo\Gallery\View\GalleryViewBuilder;
use Aurora\Module\Platform\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gallery root CRUD + edit/reopen/export + finalizations cleanup.
 * Sub-domains live in sibling controllers:
 *  - `GalleryInvitesController` — invite create/send/delete
 *  - `GalleryItemsController` — item add/reorder/caption/delete/bulk-delete + comment cleanup.
 */
#[Route('/backend/galleries', name: 'backend_galleries')]
#[IsGranted('photo.galleries.view')]
final class GalleriesController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly GalleryRepository $galleryRepository,
        private readonly GallerySerializerInterface $gallerySerializer,
        private readonly GalleryManagerInterface $galleryManager,
        private readonly GalleryFinalizationRepository $finalizationRepository,
        private readonly GalleryPickService $pickService,
        private readonly GalleryExportService $exportService,
        private readonly GalleryViewBuilder $viewBuilder,
        private readonly GalleryInputFactoryInterface $galleryInputFactory,
        private readonly PayloadValidator $payloadValidator,
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

    #[Route('/{id}/update', name: '_update', requirements: ['id' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function update(GalleryInterface $gallery, Request $request): JsonResponse
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

    #[Route('/{id}/delete', name: '_delete', requirements: ['id' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.delete')]
    public function delete(GalleryInterface $gallery): JsonResponse
    {
        $this->galleryManager->delete($gallery);

        return $this->jsonSuccess();
    }

    #[Route('/{id}/edit', name: '_edit', requirements: ['id' => '\d+|__id__'], methods: [HttpMethodEnum::Get->value])]
    #[IsGranted('photo.galleries.edit')]
    public function edit(GalleryInterface $gallery): Response
    {
        $gallery = $this->galleryRepository->findOneWithItemsAndMedia((int) $gallery->getId()) ?? $gallery;

        return $this->render('@Photo/backend/galleries/edit.html.twig', $this->viewBuilder->editView($gallery));
    }

    #[Route('/{id}/reopen', name: '_reopen', requirements: ['id' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function reopen(GalleryInterface $gallery): JsonResponse
    {
        $this->galleryManager->reopen($gallery);

        return $this->jsonSuccess(['gallery' => $this->gallerySerializer->serialize($gallery)]);
    }

    #[Route('/{id}/finalizations/{finalizationId}', name: '_finalizations_delete', requirements: ['id' => '\d+', 'finalizationId' => '\d+|__id__'], methods: [HttpMethodEnum::Delete->value])]
    #[IsGranted('photo.galleries.edit')]
    public function deleteFinalization(GalleryInterface $gallery, int $finalizationId): JsonResponse
    {
        $finalization = $this->finalizationRepository->findInGallery($finalizationId, (int) $gallery->getId());
        if (!$finalization instanceof GalleryFinalizationInterface) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->pickService->reopenFor($gallery, $finalization->getVisitorToken());

        return $this->jsonSuccess([
            'gallery' => $this->gallerySerializer->serialize($gallery),
            'finalizations' => $this->gallerySerializer->serializeFinalizations($gallery),
            'invites' => $this->gallerySerializer->serializeInvites($gallery),
        ]);
    }

    #[Route('/{id}/export.xlsx', name: '_export', requirements: ['id' => '\d+|__id__'], methods: [HttpMethodEnum::Get->value])]
    #[IsGranted('photo.galleries.edit')]
    public function export(GalleryInterface $gallery): Response
    {
        $gallery = $this->galleryRepository->findOneWithItemsAndMedia((int) $gallery->getId()) ?? $gallery;

        return $this->exportService->buildXlsxResponse($gallery);
    }
}
