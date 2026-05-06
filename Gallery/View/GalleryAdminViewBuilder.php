<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\View;

use Aurora\Core\Validation\DTO\PaginationRequest;
use Aurora\Module\Crm\Service\CrmContext;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Repository\GalleryRepository;
use Aurora\Module\Photo\Gallery\Serializer\GallerySerializer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the Twig payloads for the admin gallery views. Keeps URL generation
 * and serialisation in one place so the controller stays focused on the HTTP
 * lifecycle (auth, validation, error responses).
 */
final readonly class GalleryAdminViewBuilder
{
    public function __construct(
        private GallerySerializer $gallerySerializer,
        private GalleryRepository $galleryRepository,
        private CrmContext $crmContext,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexView(PaginationRequest $pagination): array
    {
        $crmEnabled = $this->crmContext->isAdminEnabled();

        return [
            'galleries' => $this->gallerySerializer->serializeListPayload($this->galleryRepository->findPaginated($pagination->page, search: $pagination->search)),
            'search' => $pagination->search ?? '',
            'createPath' => $this->urlGenerator->generate('backend_galleries_create'),
            'updatePath' => $this->urlGenerator->generate('backend_galleries_update', ['id' => '__id__']),
            'deletePath' => $this->urlGenerator->generate('backend_galleries_delete', ['id' => '__id__']),
            'listPath' => $this->urlGenerator->generate('backend_galleries_list'),
            'editPath' => $this->urlGenerator->generate('backend_galleries_edit', ['id' => '__id__']),
            'crmEnabled' => $crmEnabled,
            'contactsSearchPath' => $crmEnabled ? $this->urlGenerator->generate('backend_crm_contacts_list') : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editView(Gallery $gallery): array
    {
        return [
            'gallery' => $this->gallerySerializer->serialize($gallery),
            'items' => $this->gallerySerializer->serializeItems($gallery),
            'picks' => $this->gallerySerializer->serializePickStats($gallery),
            'comments' => $this->gallerySerializer->serializeComments($gallery),
            'finalizations' => $this->gallerySerializer->serializeFinalizations($gallery),
            'invites' => $this->gallerySerializer->serializeInvites($gallery),
            ...$this->editPaths($gallery),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function editPaths(Gallery $gallery): array
    {
        $id = $gallery->getId();

        return [
            'updatePath' => $this->urlGenerator->generate('backend_galleries_update', ['id' => $id]),
            'reopenPath' => $this->urlGenerator->generate('backend_galleries_reopen', ['id' => $id]),
            'finalizationDeletePath' => $this->urlGenerator->generate('backend_galleries_finalizations_delete', ['id' => $id, 'finalizationId' => '__id__']),
            'invitesCreatePath' => $this->urlGenerator->generate('backend_galleries_invites_create', ['id' => $id]),
            'invitesSendPath' => $this->urlGenerator->generate('backend_galleries_invites_send', ['id' => $id, 'inviteId' => '__id__']),
            'invitesDeletePath' => $this->urlGenerator->generate('backend_galleries_invites_delete', ['id' => $id, 'inviteId' => '__id__']),
            'exportPath' => $this->urlGenerator->generate('backend_galleries_export', ['id' => $id]),
            'itemsAddPath' => $this->urlGenerator->generate('backend_galleries_items_add', ['id' => $id]),
            'itemsReorderPath' => $this->urlGenerator->generate('backend_galleries_items_reorder', ['id' => $id]),
            'itemsCaptionPath' => $this->urlGenerator->generate('backend_galleries_items_caption', ['id' => $id, 'itemId' => '__id__']),
            'itemsDeletePath' => $this->urlGenerator->generate('backend_galleries_items_delete', ['id' => $id, 'itemId' => '__id__']),
            'itemsBulkDeletePath' => $this->urlGenerator->generate('backend_galleries_items_bulk_delete', ['id' => $id]),
            'commentDeletePath' => $this->urlGenerator->generate('backend_galleries_comments_delete', ['id' => $id, 'commentId' => '__id__']),
            'previewPath' => $this->urlGenerator->generate('frontend_gallery', ['slug' => $gallery->getSlug()]),
        ];
    }
}
