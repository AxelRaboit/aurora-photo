<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\View;

use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Serializer\GallerySerializerInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryAccessService;
use Aurora\Module\Photo\Gallery\Service\GalleryPickService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the Twig payloads consumed by the public gallery views. Centralises
 * URL generation + serialisation so controller actions stay focused on flow
 * (auth, redirects, error responses) instead of view-shape concerns.
 */
final readonly class GalleryFrontViewBuilder
{
    public function __construct(
        private GallerySerializerInterface $gallerySerializer,
        private GalleryPickService $pickService,
        private GalleryAccessService $accessService,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function unlockView(Gallery $gallery): array
    {
        return [
            'gallery' => $gallery,
            'unlockPath' => $this->urlGenerator->generate('frontend_gallery_unlock', ['slug' => $gallery->getSlug()]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function galleryView(Gallery $gallery, string $visitorToken, bool $readOnly): array
    {
        [$name, $email] = $this->pickService->recoverIdentity($visitorToken, null, null);
        $slug = $gallery->getSlug();

        return [
            'gallery' => $gallery,
            'items' => $this->gallerySerializer->serializeItems($gallery),
            'visitorPicks' => $this->pickService->picksByVisitor($gallery, $visitorToken),
            'favoriteCount' => $this->pickService->favoriteCount($gallery, $visitorToken),
            'visitorIdentity' => ['name' => $name, 'email' => $email],
            // In read-only (shared) mode the recipient is locked out of all
            // mutations and can't share the link further; in editable mode the
            // visitor sees their own finalize state and gets a share URL.
            'finalizedByVisitor' => $readOnly || $this->pickService->isFinalizedBy($gallery, $visitorToken),
            'readOnly' => $readOnly,
            'pickPath' => $readOnly ? '' : $this->urlGenerator->generate('frontend_gallery_pick', ['slug' => $slug, 'itemId' => '__id__']),
            'commentPath' => $readOnly ? '' : $this->urlGenerator->generate('frontend_gallery_comment', ['slug' => $slug, 'itemId' => '__id__']),
            'finalizePath' => $readOnly ? '' : $this->urlGenerator->generate('frontend_gallery_finalize', ['slug' => $slug]),
            'downloadItemPath' => $readOnly ? '' : $this->urlGenerator->generate('frontend_gallery_download_item', ['slug' => $slug, 'itemId' => '__id__']),
            'downloadZipPath' => $readOnly ? '' : $this->urlGenerator->generate('frontend_gallery_download_zip', ['slug' => $slug]),
            'sharePath' => $readOnly ? null : $this->urlGenerator->generate('frontend_gallery_shared', [
                'slug' => $slug,
                'visitorToken' => $visitorToken,
                'signature' => $this->accessService->computeShareSignature($gallery, $visitorToken),
            ]),
        ];
    }
}
