<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Core\Setting\Enum\ApplicationParameterEnum;
use Aurora\Core\Setting\Repository\SettingRepository;
use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryInvite;
use Aurora\Module\Photo\Gallery\Repository\GalleryPickRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class GalleryNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private SettingRepository $settingRepository,
        private GalleryPickRepository $pickRepository,
        private UrlGeneratorInterface $urlGenerator,
        private string $mailerFrom,
    ) {}

    public function notifyFinalized(Gallery $gallery, string $visitorToken, ?string $visitorName = null, ?string $visitorEmail = null): void
    {
        $adminEmail = $this->settingRepository->get(ApplicationParameterEnum::AdminEmail->value);
        if (null === $adminEmail || '' === $adminEmail) {
            return;
        }

        $siteName = $this->settingRepository->getOrDefault(ApplicationParameterEnum::SiteName);
        // Per-visitor counts: a finalize event reports what THIS visitor picked,
        // not the cumulative cross-visitor / cross-kind total (which would be
        // misleading on multi-validation galleries).
        $visitorPicks = $this->pickRepository->findByVisitorForGallery($visitorToken, (int) $gallery->getId());
        $countsByKind = ['favorite' => 0, 'print' => 0, 'discard' => 0];
        foreach ($visitorPicks as $pick) {
            ++$countsByKind[$pick->getKind()->value];
        }

        $adminUrl = $this->urlGenerator->generate('admin_galleries', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $body = $this->twig->render('@Photo/email/gallery_finalized.html.twig', [
            'gallery' => $gallery,
            'visitorPicks' => $visitorPicks,
            'countsByKind' => $countsByKind,
            'siteName' => $siteName,
            'adminUrl' => $adminUrl,
            'visitorName' => $visitorName,
            'visitorEmail' => $visitorEmail,
        ]);

        $email = new Email()
            ->from($this->mailerFrom)
            ->to($adminEmail)
            ->subject(sprintf('[%s] %s — %s', $siteName, $gallery->getTitle(), 'Sélection terminée'))
            ->html($body);

        // CC the linked CRM contact when present so the photographer's client
        // also gets a confirmation copy of their selection.
        $clientEmail = $gallery->getClientContact()?->getEmail();
        if (null !== $clientEmail && '' !== $clientEmail && $clientEmail !== $adminEmail) {
            $email->cc($clientEmail);
        }

        $this->mailer->send($email);
    }

    /**
     * Confirmation email sent to the visitor right after they finalize their
     * selection. Silently no-ops when the visitor didn't supply an email.
     */
    public function notifyVisitor(Gallery $gallery, ?string $visitorName, ?string $visitorEmail): void
    {
        if (null === $visitorEmail || '' === $visitorEmail) {
            return;
        }

        $siteName = $this->settingRepository->getOrDefault(ApplicationParameterEnum::SiteName);
        $galleryUrl = $this->urlGenerator->generate('front_gallery', ['slug' => $gallery->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        $body = $this->twig->render('@Photo/email/visitor_finalized.html.twig', [
            'gallery' => $gallery,
            'visitorName' => $visitorName,
            'siteName' => $siteName,
            'galleryUrl' => $galleryUrl,
        ]);

        $email = new Email()
            ->from($this->mailerFrom)
            ->to($visitorEmail)
            ->subject(sprintf('[%s] Confirmation de votre sélection — %s', $siteName, $gallery->getTitle()))
            ->html($body);

        $this->mailer->send($email);
    }

    /**
     * Sends the magic-link invitation email. The unique URL is built by the
     * caller (it depends on the Symfony router) and passed in.
     */
    public function notifyInvite(GalleryInvite $invite, string $magicUrl): void
    {
        $gallery = $invite->getGallery();
        $siteName = $this->settingRepository->getOrDefault(ApplicationParameterEnum::SiteName);

        $body = $this->twig->render('@Photo/email/gallery_invite.html.twig', [
            'gallery' => $gallery,
            'invite' => $invite,
            'magicUrl' => $magicUrl,
            'siteName' => $siteName,
        ]);

        $email = new Email()
            ->from($this->mailerFrom)
            ->to($invite->getEmail())
            ->subject(sprintf('[%s] %s — Vos photos sont prêtes', $siteName, $gallery->getTitle()))
            ->html($body);

        $this->mailer->send($email);
    }
}
