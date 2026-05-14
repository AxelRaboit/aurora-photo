<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Module\Photo\Gallery\Entity\GalleryInterface;
use Aurora\Module\Photo\Gallery\Entity\GalleryInviteInterface;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Issues and verifies the per-gallery access cookie.
 *
 * Cookie value format: "<visitorToken>|<hmac>"
 *  - visitorToken: 32 random hex chars, used as the visitor identity for picks.
 *  - hmac: sha256 HMAC of "<galleryId>:<visitorToken>:<passwordHash>" with the
 *    Symfony app secret. Including the passwordHash means rotating the
 *    gallery password automatically invalidates every issued cookie.
 *
 * For galleries without a password, an empty string is substituted for
 * passwordHash so the same scheme still works.
 */
final readonly class GalleryAccessService
{
    private const string COOKIE_PREFIX = 'aurora_gallery_';

    private const int VISITOR_TOKEN_BYTES = 16;

    public function __construct(private string $appSecret) {}

    public function cookieName(GalleryInterface $gallery): string
    {
        return self::COOKIE_PREFIX.$gallery->getId();
    }

    /**
     * @return string|null the visitor token if the cookie is present and valid, otherwise null
     */
    public function readVisitorToken(Request $request, GalleryInterface $gallery): ?string
    {
        $raw = $request->cookies->get($this->cookieName($gallery));
        if (!is_string($raw) || '' === $raw) {
            return null;
        }

        $parts = explode('|', $raw, 2);
        if (2 !== count($parts)) {
            return null;
        }

        [$visitorToken, $hmac] = $parts;
        if (!hash_equals($this->computeHmac($gallery, $visitorToken), $hmac)) {
            return null;
        }

        return $visitorToken;
    }

    /**
     * Verifies the gallery password (if any) and returns a Cookie that grants
     * access. Caller is responsible for adding it to the response.
     */
    public function unlock(GalleryInterface $gallery, ?string $password): ?Cookie
    {
        if ($gallery->hasPassword()) {
            if (null === $password || '' === $password) {
                return null;
            }

            if (!password_verify($password, (string) $gallery->getPasswordHash())) {
                return null;
            }
        }

        $visitorToken = bin2hex(random_bytes(self::VISITOR_TOKEN_BYTES));
        $value = $visitorToken.'|'.$this->computeHmac($gallery, $visitorToken);

        $expires = $gallery->getExpiresAt() ?? new DateTimeImmutable('+30 days');

        return Cookie::create(
            name: $this->cookieName($gallery),
            value: $value,
            expire: $expires->getTimestamp(),
            secure: true,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    /**
     * True when the gallery is open OR the request carries a valid token.
     */
    public function isUnlocked(Request $request, GalleryInterface $gallery): bool
    {
        if (!$gallery->hasPassword()) {
            return true;
        }

        return null !== $this->readVisitorToken($request, $gallery);
    }

    /**
     * Ensures the request has a valid visitor token. For password-protected
     * galleries lacking a valid cookie, returns null so callers can branch.
     * For open galleries with no cookie yet, issues one transparently and
     * returns the new token + the cookie to attach to the response.
     *
     * @return array{0: ?string, 1: ?Cookie} [visitorToken, cookieToSet]
     */
    public function ensureVisitorToken(Request $request, GalleryInterface $gallery): array
    {
        $token = $this->readVisitorToken($request, $gallery);
        if (null !== $token) {
            return [$token, null];
        }

        if ($gallery->hasPassword()) {
            return [null, null];
        }

        $cookie = $this->unlock($gallery, null);
        if (!$cookie instanceof Cookie) {
            return [null, null];
        }

        $newToken = explode('|', (string) $cookie->getValue(), 2)[0];

        return [$newToken, $cookie];
    }

    private function computeHmac(GalleryInterface $gallery, string $visitorToken): string
    {
        $payload = sprintf('%d:%s:%s', $gallery->getId(), $visitorToken, $gallery->getPasswordHash() ?? '');

        return hash_hmac('sha256', $payload, $this->appSecret);
    }

    /**
     * Mints a deterministic visitor token tied to an invite. Same invite
     * always yields the same token across devices, so picks/finalizations
     * stay attached to the right person — and the photographer can correlate
     * activity with the named invitee.
     */
    public function visitorTokenForInviteToken(string $inviteToken): string
    {
        return mb_substr(hash_hmac('sha256', 'invite:'.$inviteToken, $this->appSecret), 0, 32);
    }

    public function unlockForInvite(GalleryInviteInterface $invite): Cookie
    {
        $visitorToken = $invite->getVisitorToken();
        $value = $visitorToken.'|'.$this->computeHmac($invite->getGallery(), $visitorToken);

        $expires = $invite->getGallery()->getExpiresAt() ?? new DateTimeImmutable('+30 days');

        return Cookie::create(
            name: $this->cookieName($invite->getGallery()),
            value: $value,
            expire: $expires->getTimestamp(),
            secure: true,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    /**
     * Builds an HMAC-signed token a visitor can share with someone (spouse,
     * family) so they get a read-only view of *this visitor's* picks. The
     * signature ties the share to the gallery + the visitor token; rotating
     * the gallery password also invalidates outstanding share links.
     */
    public function computeShareSignature(GalleryInterface $gallery, string $visitorToken): string
    {
        $payload = sprintf('share:%d:%s:%s', $gallery->getId(), $visitorToken, $gallery->getPasswordHash() ?? '');

        return mb_substr(hash_hmac('sha256', $payload, $this->appSecret), 0, 32);
    }

    public function verifyShareSignature(GalleryInterface $gallery, string $visitorToken, string $signature): bool
    {
        return hash_equals($this->computeShareSignature($gallery, $visitorToken), $signature);
    }
}
