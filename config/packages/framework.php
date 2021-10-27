<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $config, ContainerConfigurator $container): void {
    $config
        ->secret(env('APP_SECRET')->string())
        ->phpErrors()
            ->log(true);

    $config
        ->cache();

    $config
        ->router()
        ->utf8(true)
        ->strictRequirements('prod' === $container->env() ? null : true);


    $config
        ->session()
        ->enabled(false);

    if ('test' === $container->env()) {
        $config
            ->test(true);
    }
};
