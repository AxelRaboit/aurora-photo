<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Enum\HttpStatusEnum;
use Aurora\Core\Http\JsonRequestTrait;
use Aurora\Core\Http\JsonResponseTrait;
use Aurora\Core\Validation\Service\PayloadValidator;
use Aurora\Module\Photo\Gallery\Dto\GalleryInviteInput;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use Aurora\Module\Photo\Gallery\Manager\GalleryInviteManagerInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryInviteRepository;
use Aurora\Module\Photo\Gallery\Serializer\GallerySerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gallery invites sub-domain — create / send / delete an invite for a
 * gallery. Split from `GalleriesController`. Route names preserved
 * (`backend_galleries_invites_*`).
 */
#[Route('/backend/galleries', name: 'backend_galleries')]
#[IsGranted('photo.galleries.view')]
final class GalleryInvitesController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly GalleryInviteRepository $inviteRepository,
        private readonly GalleryInviteManagerInterface $inviteManager,
        private readonly GallerySerializerInterface $gallerySerializer,
        private readonly PayloadValidator $payloadValidator,
    ) {}

    #[Route('/{id}/invites/create', name: '_invites_create', requirements: ['id' => '\d+'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function create(GalleryInterface $gallery, Request $request): JsonResponse
    {
        $input = GalleryInviteInput::fromArray($this->decodeJson($request));
        $errors = $this->payloadValidator->errors($input);
        if ([] !== $errors) {
            return $this->jsonInvalidInput($errors);
        }

        $existing = $this->inviteRepository->findOneByGalleryAndEmail((int) $gallery->getId(), $input->email);
        if ($existing instanceof GalleryInviteInterface) {
            return $this->jsonInvalidInput(['email' => 'photo.galleries.errors.invite_email_taken'], HttpStatusEnum::Conflict->value);
        }

        $this->inviteManager->create($gallery, $input->name, $input->email);

        return $this->jsonSuccess(['invites' => $this->gallerySerializer->serializeInvites($gallery)]);
    }

    #[Route('/{id}/invites/{inviteId}/send', name: '_invites_send', requirements: ['id' => '\d+', 'inviteId' => '\d+|__id__'], methods: [HttpMethodEnum::Post->value])]
    #[IsGranted('photo.galleries.edit')]
    public function send(GalleryInterface $gallery, int $inviteId): JsonResponse
    {
        $invite = $this->inviteRepository->findInGallery($inviteId, (int) $gallery->getId());
        if (!$invite instanceof GalleryInviteInterface) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->inviteManager->send($invite);

        return $this->jsonSuccess(['invites' => $this->gallerySerializer->serializeInvites($gallery)]);
    }

    #[Route('/{id}/invites/{inviteId}', name: '_invites_delete', requirements: ['id' => '\d+', 'inviteId' => '\d+|__id__'], methods: [HttpMethodEnum::Delete->value])]
    #[IsGranted('photo.galleries.edit')]
    public function delete(GalleryInterface $gallery, int $inviteId): JsonResponse
    {
        $invite = $this->inviteRepository->findInGallery($inviteId, (int) $gallery->getId());
        if (!$invite instanceof GalleryInviteInterface) {
            return $this->jsonFailure('not_found', HttpStatusEnum::NotFound->value);
        }

        $this->inviteManager->delete($invite);

        return $this->jsonSuccess(['invites' => $this->gallerySerializer->serializeInvites($gallery)]);
    }
}
