<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension(
        'aws',
        [
            'version' => 'latest',
            'region' => 'eu-west-3',
            'profil' => 'default',
            'MediaConvert' => [
                'endpoint' => '%env(AWS_MEDIA_CONVERT_ENDPOINT)%',
            ],
            'credentials' => [
                'key' => '%env(AWS_KEY)%', 'secret' => '%env(AWS_SECRET)%'
            ]
        ]
    );
};
