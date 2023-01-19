<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(Google_Client::class, Google_Client::class)
        ->call('setApplicationName', ['video-platform-bench'])
        ->call('setDeveloperKey', ['%env(GOOGLE_API_KEY)%'])
        ->call('setClientId', ['%env(GOOGLE_CLIENT_ID)%'])
        ->call('setClientSecret', ['%env(GOOGLE_CLIENT_SECRET)%'])
        ->call('setAuthConfig', ['%env(GOOGLE_CLIENT_AUTH_CONFIG)%'])
        ->call('setRedirectUri', ['%env(GOOGLE_CLIENT_REDIRECT_URI)%']);
};
