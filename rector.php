<?php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Rector\Symfony\Rector\Class_\ChangeFileLoaderInExtensionAndKernelRector;

return function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(ChangeFileLoaderInExtensionAndKernelRector::class)
        ->call('configure', [[
            ChangeFileLoaderInExtensionAndKernelRector::FROM => 'yaml',
            ChangeFileLoaderInExtensionAndKernelRector::TO => 'php',
        ]]);
};
