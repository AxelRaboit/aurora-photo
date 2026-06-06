<?php

declare(strict_types=1);

/**
 * Services config shipped inside the aurora-photo package. Loaded by
 * AuroraPhotoBundle::loadExtension when the module is a standalone package.
 */

use Aurora\Core\Dashboard\DashboardStatsProviderInterface;
use Aurora\Core\Frontend\Contract\FrontendInterface;
use Aurora\Core\Module\Contract\ModuleInterface;
use Aurora\Module\Configuration\Setting\Configuration\ConfigurationTabProviderInterface;
use Aurora\Module\Configuration\Setting\Provider\ApplicationParameterProviderInterface;
use Aurora\Module\Photo\Gallery\Service\GalleryAccessService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $moduleDir = \dirname(__DIR__);

    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    $services->instanceof(ModuleInterface::class)->tag('aurora.module');
    $services->instanceof(FrontendInterface::class)->tag('aurora.front');
    $services->instanceof(ConfigurationTabProviderInterface::class)->tag('aurora.configuration_tab_provider');
    $services->instanceof(ApplicationParameterProviderInterface::class)->tag('aurora.application_parameter_provider');
    $services->instanceof(DashboardStatsProviderInterface::class)->tag('aurora.dashboard_stats_provider');

    $services->load('Aurora\\Module\\Photo\\', $moduleDir.'/')
        ->exclude([
            $moduleDir.'/AuroraPhotoBundle.php',
            $moduleDir.'/{config,templates,translations,assets,DataFixtures}',
            $moduleDir.'/**/Entity',
            $moduleDir.'/Setting/PhotoModuleParameterEnum.php',
        ]);

    // Gallery access (signed URLs) with the app secret (was a central def).
    $services->set(GalleryAccessService::class)
        ->arg('$appSecret', '%kernel.secret%');
};
