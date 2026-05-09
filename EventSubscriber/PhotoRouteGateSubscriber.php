<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\EventSubscriber;

use Aurora\Module\Photo\Service\PhotoContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gates Photo routes against the admin/front toggles.
 *
 *  - Admin (`admin_galleries*`)   → 404 when PhotoEnabled is off
 *  - Front (`frontend_gallery*`)     → 404 when PhotoPublicEnabled is off
 */
final readonly class PhotoRouteGateSubscriber implements EventSubscriberInterface
{
    private const string ADMIN_PREFIX = 'backend_galleries';

    private const string FRONT_PREFIX = 'frontend_gallery';

    public function __construct(private PhotoContext $photoContext) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 16]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if ('' === $route) {
            return;
        }

        if (str_starts_with($route, self::ADMIN_PREFIX) && !$this->photoContext->isAdminEnabled()) {
            throw new NotFoundHttpException();
        }

        if (str_starts_with($route, self::FRONT_PREFIX) && !$this->photoContext->isFrontEnabled()) {
            throw new NotFoundHttpException();
        }
    }
}
