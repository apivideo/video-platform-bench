<?php
declare(strict_types=1);
namespace Symfony\Component\DependencyInjection\Loader\Configurator;
use ApiVideo\Client\Client;
use App\Service\BenchAWSService;
use Jwplayer\JwplatformAPI;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Api\LiveStreamsApi;
use MuxPhp\Configuration;
use Vimeo\Vimeo;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services
        ->load(
            'App\\Controller\\',
            '../src/Controller'
        )
        ->tag('controller.service_arguments');
    $services
        ->load(
            'App\\Service\\',
            '../src/Service'
        );
    $services
        ->load(
            'App\\Command\\',
            '../src/Command'
        )
        ->tag('console.command');

    $services->set(Vimeo::class)
        ->arg('$client_id', env('VIMEO_CLIENT_ID')->string())
        ->arg('$client_secret', env('VIMEO_CLIENT_SECRET')->string())
        ->arg('$access_token', env('VIMEO_ACCESS_TOKEN')->string());

    $services->set(AssetsApi::class)
        ->arg('$config',
            inline_service(Configuration::class)
                ->call('setUsername', [env('MUX_USERNAME')])
                ->call('setPassword', [env('MUX_PASSWORD')])
        );

    $services->set(LiveStreamsApi::class)
        ->arg('$config',
            inline_service(Configuration::class)
                ->call('setUsername', [env('MUX_USERNAME')])
                ->call('setPassword', [env('MUX_PASSWORD')])
        );

    $services->set(Client::class)
        ->arg('$baseUri', 'https://ws.api.video')
        ->arg('$apiKey', env('API_VIDEO_APIKEY'));

    $services->set(JwplatformAPI::class)
        ->arg('$key', env('JW_PLAYER_API_KEY'))
        ->arg('$secret', env('JW_PLAYER_API_SECRET'))
        ->arg('$reportingAPIKey', env('JW_PLAYER_REPORTING_API_KEY'));

    $services->set(BenchAWSService::class, BenchAWSService::class)
        ->call('setVodS3BucketDestination', ['%env(AWS_VOD_S3_BUCKET_DESTINATION)%'])
        ->call('setVodArnRoleIamMediaConvert', ['%env(AWS_VOD_ARN_ROLE_IAM_MEDIA_CONVERT)%'])
        ->call('setVodCloudfrontEndpoint', ['%env(AWS_VOD_CLOUDFRONT_ENDPOINT)%'])
        ->call('setLiveTemplateUrl', ['%env(AWS_LIVE_TEMPLATE_URL)%']);

};
