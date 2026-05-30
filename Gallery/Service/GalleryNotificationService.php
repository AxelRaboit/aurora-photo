<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Core\Mail\Service\MailService;
use Aurora\Core\Reference\EntityReferenceResolver;
use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemCommentInterface;
use Aurora\Module\Photo\Gallery\Repository\GalleryPickRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class GalleryNotificationService
{
    public function __construct(
        private MailService $mail,
        private GalleryPickRepository $pickRepository,
        private UrlGeneratorInterface $urlGenerator,
        private EntityReferenceResolver $referenceResolver,
    ) {}

    public function notifyFinalized(GalleryInterface $gallery, string $visitorToken, ?string $visitorName = null, ?string $visitorEmail = null): void
    {
        $adminEmail = $this->mail->adminEmail();
        if (null === $adminEmail) {
            return;
        }

        // Per-visitor counts: a finalize event reports what THIS visitor picked,
        // not the cumulative cross-visitor / cross-kind total (which would be
        // misleading on multi-validation galleries).
        $visitorPicks = $this->pickRepository->findByVisitorForGallery($visitorToken, (int) $gallery->getId());
        $countsByKind = ['favorite' => 0, 'print' => 0, 'discard' => 0];
        foreach ($visitorPicks as $pick) {
            ++$countsByKind[$pick->getKind()->value];
        }

        // CC the linked CRM contact when present so the photographer's client
        // also gets a confirmation copy of their selection.
        $clientEmail = $this->referenceResolver->summarize('crm.contact', $gallery->getClientContactId())['email'] ?? null;
        $cc = (null !== $clientEmail && '' !== $clientEmail) ? [$clientEmail] : [];

        $this->mail->send(
            $adminEmail,
            'photo.mail.gallery.subject_finalized',
            '@Photo/email/gallery_finalized.html.twig',
            [
                'gallery' => $gallery,
                'visitorPicks' => $visitorPicks,
                'countsByKind' => $countsByKind,
                'adminUrl' => $this->urlGenerator->generate('backend_photo_galleries', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'visitorName' => $visitorName,
                'visitorEmail' => $visitorEmail,
            ],
            cc: $cc,
            subjectParams: ['{title}' => $gallery->getTitle()],
        );
    }

    /**
     * Confirmation email sent to the visitor right after they finalize their
     * selection. Silently no-ops when the visitor didn't supply an email.
     */
    public function notifyVisitor(GalleryInterface $gallery, ?string $visitorName, ?string $visitorEmail): void
    {
        if (null === $visitorEmail || '' === $visitorEmail) {
            return;
        }

        $this->mail->send(
            $visitorEmail,
            'photo.mail.gallery.subject_visitor',
            '@Photo/email/visitor_finalized.html.twig',
            [
                'gallery' => $gallery,
                'visitorName' => $visitorName,
                'galleryUrl' => $this->urlGenerator->generate('frontend_gallery', ['slug' => $gallery->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            subjectParams: ['{title}' => $gallery->getTitle()],
        );
    }

    /**
     * Notifies the admin when a visitor leaves a comment on a gallery item.
     */
    public function notifyItemComment(GalleryItemCommentInterface $comment): void
    {
        $gallery = $comment->getGalleryItem()->getGallery();

        $this->mail->sendToAdmin(
            'photo.mail.gallery.subject_comment',
            '@Photo/email/gallery_item_comment.html.twig',
            [
                'comment' => $comment,
                'gallery' => $gallery,
                'adminUrl' => $this->urlGenerator->generate('backend_photo_galleries', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            subjectParams: ['{title}' => $gallery->getTitle()],
        );
    }

    /**
     * Sends the magic-link invitation email. The unique URL is built by the
     * caller (it depends on the Symfony router) and passed in.
     */
    public function notifyInvite(GalleryInviteInterface $invite, string $magicUrl): void
    {
        $gallery = $invite->getGallery();

        $this->mail->send(
            $invite->getEmail(),
            'photo.mail.gallery.subject_invite',
            '@Photo/email/gallery_invite.html.twig',
            [
                'gallery' => $gallery,
                'invite' => $invite,
                'magicUrl' => $magicUrl,
            ],
            subjectParams: ['{title}' => $gallery->getTitle()],
        );
    }
}
