<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Serializer;

use Aurora\Module\Crm\Contact\Entity\ContactInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemCommentInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryFinalizationRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryInviteRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemCommentRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryPickRepository;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GallerySerializerInterface::class)]
class GallerySerializer implements GallerySerializerInterface
{
    public function __construct(
        protected readonly GalleryPickRepository $pickRepository,
        protected readonly GalleryItemCommentRepository $commentRepository,
        protected readonly GalleryFinalizationRepository $finalizationRepository,
        protected readonly GalleryInviteRepository $inviteRepository,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeInvites(GalleryInterface $gallery): array
    {
        $invites = $this->inviteRepository->findAllForGallery((int) $gallery->getId());
        if ([] === $invites) {
            return [];
        }

        // Pre-index finalizations by visitor_token so we can flag each invite
        // that's already validated without N+1 lookups.
        $finalizedAtByToken = [];
        foreach ($this->finalizationRepository->findAllForGallery((int) $gallery->getId()) as $finalization) {
            $finalizedAtByToken[$finalization->getVisitorToken()] = $finalization->getFinalizedAt()->format(DateTimeInterface::ATOM);
        }

        return array_map(static fn ($invite): array => [
            'id' => $invite->getId(),
            'name' => $invite->getName(),
            'email' => $invite->getEmail(),
            'invitedAt' => $invite->getInvitedAt()->format(DateTimeInterface::ATOM),
            'sentAt' => $invite->getSentAt()?->format(DateTimeInterface::ATOM),
            'lastSeenAt' => $invite->getLastSeenAt()?->format(DateTimeInterface::ATOM),
            'finalizedAt' => $finalizedAtByToken[$invite->getVisitorToken()] ?? null,
        ], $invites);
    }

    /** @return array<string, mixed> */
    public function serialize(GalleryInterface $gallery): array
    {
        return [
            'id' => $gallery->getId(),
            'slug' => $gallery->getSlug(),
            'title' => $gallery->getTitle(),
            'description' => $gallery->getDescription(),
            'hasPassword' => $gallery->hasPassword(),
            'coverMediaId' => $gallery->getCoverMedia()?->getId(),
            'coverMediaUrl' => $gallery->getCoverMedia()?->getVariantUrl('medium') ?? $gallery->getCoverMedia()?->getPublicUrl(),
            'expiresAt' => $gallery->getExpiresAt()?->format(DateTimeInterface::ATOM),
            'allowOriginals' => $gallery->isAllowOriginals(),
            'allowZipDownload' => $gallery->isAllowZipDownload(),
            'picksRequireIdentity' => $gallery->isPicksRequireIdentity(),
            'maxPicks' => $gallery->getMaxPicks(),
            'allowVisitorComments' => $gallery->isAllowVisitorComments(),
            'watermarkEnabled' => $gallery->isWatermarkEnabled(),
            'watermarkText' => $gallery->getWatermarkText(),
            'client' => $gallery->getClientContact() instanceof ContactInterface ? [
                'id' => $gallery->getClientContact()->getId(),
                'name' => $gallery->getClientContact()->getFullName(),
                'email' => $gallery->getClientContact()->getEmail(),
            ] : null,
            'finalizedAt' => $gallery->getFinalizedAt()?->format(DateTimeInterface::ATOM),
            'finalizedByName' => $gallery->getFinalizedByName(),
            'finalizedByEmail' => $gallery->getFinalizedByEmail(),
            'finalizationCount' => $this->finalizationRepository->countForGallery((int) $gallery->getId()),
            'itemCount' => $gallery->getItems()->count(),
            'createdAt' => $gallery->getCreatedAt()->format(DateTimeInterface::ATOM),
            'updatedAt' => $gallery->getUpdatedAt()->format(DateTimeInterface::ATOM),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function serializeItems(GalleryInterface $gallery): array
    {
        $items = [];
        foreach ($gallery->getItems() as $item) {
            $media = $item->getMedia();
            $items[] = [
                'id' => $item->getId(),
                'mediaId' => $media->getId(),
                'thumb' => $media->getVariantUrl('medium') ?? $media->getVariantUrl('large') ?? $media->getPublicUrl(),
                'medium' => $media->getVariantUrl('medium') ?? $media->getVariantUrl('large') ?? $media->getPublicUrl(),
                'full' => $media->getVariantUrl('large') ?? $media->getPublicUrl(),
                'caption' => $item->getCaption(),
                'alt' => $media->getAlt(),
                'position' => $item->getPosition(),
                'number' => $item->getNumber(),
                'takenAt' => $item->getTakenAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *   total: int,
     *   totalsByKind: array<string, int>,
     *   byItemId: array<int, array<string, int>>,
     *   visitorCount: int,
     *   consensusByItemId: array<int, array<string, int>>
     * }
     */
    public function serializePickStats(GalleryInterface $gallery): array
    {
        $picks = $this->pickRepository->findAllForGallery((int) $gallery->getId());

        $totalsByKind = [];
        $byItem = [];
        // Count of *distinct visitors* per item per kind (vs raw pick count above).
        // A single visitor toggling favorite then print should count as 1 in
        // each kind, not 2 — that's what makes consensus meaningful.
        $consensusByItem = [];
        $seen = [];
        $visitors = [];
        foreach ($picks as $pick) {
            $itemId = (int) $pick->getGalleryItem()->getId();
            $kind = $pick->getKind()->value;
            $token = $pick->getVisitorToken();
            $visitors[$token] = true;

            $totalsByKind[$kind] = ($totalsByKind[$kind] ?? 0) + 1;
            $byItem[$itemId][$kind] = ($byItem[$itemId][$kind] ?? 0) + 1;

            $seenKey = $itemId.'|'.$kind.'|'.$token;
            if (!isset($seen[$seenKey])) {
                $seen[$seenKey] = true;
                $consensusByItem[$itemId][$kind] = ($consensusByItem[$itemId][$kind] ?? 0) + 1;
            }
        }

        return [
            'total' => count($picks),
            'totalsByKind' => $totalsByKind,
            'byItemId' => $byItem,
            'visitorCount' => count($visitors),
            'consensusByItemId' => $consensusByItem,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeComments(GalleryInterface $gallery): array
    {
        $comments = $this->commentRepository->findAllForGallery((int) $gallery->getId());

        return array_map($this->serializeComment(...), $comments);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeComment(GalleryItemCommentInterface $comment): array
    {
        return [
            'id' => $comment->getId(),
            'itemId' => $comment->getGalleryItem()->getId(),
            'content' => $comment->getContent(),
            'visitorName' => $comment->getVisitorName(),
            'visitorEmail' => $comment->getVisitorEmail(),
            'createdAt' => $comment->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeFinalizations(GalleryInterface $gallery): array
    {
        $finalizations = $this->finalizationRepository->findAllForGallery((int) $gallery->getId());
        if ([] === $finalizations) {
            return [];
        }

        // Group all picks for this gallery by visitor token, then per kind, so each
        // finalization row carries the exact item ids the visitor validated.
        $picksByToken = [];
        foreach ($this->pickRepository->findAllForGallery((int) $gallery->getId()) as $pick) {
            $token = $pick->getVisitorToken();
            $kind = $pick->getKind()->value;
            $picksByToken[$token][$kind][] = (int) $pick->getGalleryItem()->getId();
        }

        // Index invites by visitor_token so we can flag identity mismatches —
        // when a visitor edits their name/email at validation time, the admin
        // still sees who they were originally invited as.
        $inviteByToken = [];
        foreach ($this->inviteRepository->findAllForGallery((int) $gallery->getId()) as $invite) {
            $inviteByToken[$invite->getVisitorToken()] = $invite;
        }

        return array_map(static function ($f) use ($picksByToken, $inviteByToken): array {
            $byKind = $picksByToken[$f->getVisitorToken()] ?? [];
            $invite = $inviteByToken[$f->getVisitorToken()] ?? null;
            $invitedAs = null;
            if (null !== $invite && ($invite->getName() !== $f->getVisitorName() || $invite->getEmail() !== $f->getVisitorEmail())) {
                $invitedAs = ['name' => $invite->getName(), 'email' => $invite->getEmail()];
            }

            return [
                'id' => $f->getId(),
                'visitorToken' => $f->getVisitorToken(),
                'visitorName' => $f->getVisitorName(),
                'visitorEmail' => $f->getVisitorEmail(),
                'finalizedAt' => $f->getFinalizedAt()->format(DateTimeInterface::ATOM),
                'invitedAs' => $invitedAs,
                'picksByKind' => [
                    'favorite' => $byKind['favorite'] ?? [],
                    'print' => $byKind['print'] ?? [],
                    'discard' => $byKind['discard'] ?? [],
                ],
            ];
        }, $finalizations);
    }

    /**
     * @param array{items: list<GalleryInterface>, total: int, page: int, totalPages: int} $paginated
     *
     * @return array<string, mixed>
     */
    public function serializeListPayload(array $paginated): array
    {
        return [
            'success' => true,
            'items' => array_map($this->serialize(...), $paginated['items']),
            'total' => $paginated['total'],
            'page' => $paginated['page'],
            'totalPages' => $paginated['totalPages'],
        ];
    }
}
