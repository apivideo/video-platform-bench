<?php


namespace App\Service;


use Aws\CloudFormation\CloudFormationClient;
use Aws\MediaConvert\MediaConvertClient;
use Aws\S3\S3Client;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BenchAWSService
{
    private string $vodS3BucketDestination;
    private string $vodArnRoleIamMediaConvert;
    private string $vodCloudfrontEndpoint;
    private string $liveTemplateUrl;

    public function __construct(private MediaConvertClient $awsMediaConvert, private S3Client $s3, private CloudFormationClient $cloudFormation)
    {
    }

    #[ArrayShape(['TimeToEncodeFirstQuality' => "", 'FullTimeToEncode' => "", 'TimeToPlayback' => ""])]
    public function performVod(string $videoUriPath): array
    {
        $filename = basename($videoUriPath);
        $filenameWithoutExt = explode('.', $filename)[0];

        preg_match('/s3:\/\/(.*)\//', $this->vodS3BucketDestination, $bucketMatches);
        if(!array_key_exists(1, $bucketMatches)){
            throw new Exception('Unable to extract bucket name');
        }

        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);

        // AWS MediaConvert config job
        $jobSetting = [
            'Inputs' => [
                0 => [
                    'TimecodeSource' => 'ZEROBASED',
                    'VideoSelector' => [
                    ],
                    'AudioSelectors' => [
                        'Audio Selector 1' => [
                            'DefaultSelection' => 'DEFAULT',
                        ],
                    ],
                    'FileInput' => $videoUriPath,
                ],
            ],
            'OutputGroups' => [
                0 => [
                    'Name' => 'Apple HLS',
                    'OutputGroupSettings' => [
                        'Type' => 'HLS_GROUP_SETTINGS',
                        'HlsGroupSettings' => [
                            'SegmentLength' => 4,
                            'MinSegmentLength' => 0,
                            'Destination' => $this->vodS3BucketDestination,
                            'TargetDurationCompatibilityMode' => 'LEGACY',
                            'DirectoryStructure' => 'SINGLE_DIRECTORY',
                        ],
                    ],
                    'Outputs' => [
                        0 => [
                            'VideoDescription' => [
                                'CodecSettings' => [
                                    'Codec' => 'H_264',
                                    'H264Settings' => [
                                        'RateControlMode' => 'QVBR',
                                        'QualityTuningLevel' => 'MULTI_PASS_HQ',
                                        'FramerateControl' => 'INITIALIZE_FROM_SOURCE',
                                    ],
                                ],
                            ],
                            'AudioDescriptions' => [
                                0 => [
                                    'CodecSettings' => [
                                        'Codec' => 'AAC',
                                        'AacSettings' => [
                                            'Bitrate' => 96000,
                                            'CodingMode' => 'CODING_MODE_2_0',
                                            'SampleRate' => 48000,
                                        ],
                                    ],
                                ],
                            ],
                            'OutputSettings' => [
                                'HlsSettings' => [
                                ],
                            ],
                            'ContainerSettings' => [
                                'Container' => 'M3U8',
                                'M3u8Settings' => [
                                ],
                            ],
                        ],
                    ],
                    'CustomName' => 'video-bench',
                    'AutomatedEncodingSettings' => [
                        'AbrSettings' => [
                        ],
                    ],
                ],
            ],
            'TimecodeConfig' => [
                'Source' => 'ZEROBASED',
            ],
        ];

        // Start EncodingTime measure
        $startEncodingTime = microtime(true);

        // Upload/Download video file
        $job = $this->awsMediaConvert->createJob([
            "Role" => $this->vodArnRoleIamMediaConvert,
            "Settings" => $jobSetting,
        ]);

        $jobId = $job->get('Job')['Id'];

        // Waiting for Ready status
        do {
            $jobStatus = $this->awsMediaConvert->getJob([
                'Id' => $jobId,
            ]);
            sleep(1);
        } while ($jobStatus->get('Job')['Status'] !== 'COMPLETE');

        // Compute EncodingTime measure
        $encodingTime = microtime(true) - $startEncodingTime;

        // Download master hls manifest
        do {
            $masterManifestHls = file_get_contents("$this->vodCloudfrontEndpoint/$filenameWithoutExt.m3u8");
            usleep(500);
        } while (empty($masterManifestHls));

        // Extract url of the first playlist manifest
        preg_match('/.*.m3u8/', $masterManifestHls, $matchMasterHls);

        // Download first playlist hls manifest
        $firstQualityHls = file_get_contents("$this->vodCloudfrontEndpoint/$matchMasterHls[0]");

        // Extract url of the first fragment
        preg_match("/.*.ts/", $firstQualityHls, $matchPlaylistHls);
        $fragmentUrl = "$this->vodCloudfrontEndpoint/$matchPlaylistHls[0]";

        // Download first video fragment
        file_get_contents($fragmentUrl);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        $keys =$this->s3->listObjects([
            'Bucket' => $bucketMatches[1]
        ]);

        if(!empty($keys['Contents'])) {
            foreach ($keys['Contents'] as $key) {
                $this->s3->deleteObjects([
                    'Bucket' => $bucketMatches[1],
                    'Delete' => [
                        'Objects' => [
                            [
                                'Key' => $key['Key']
                            ]
                        ]
                    ]
                ]);
            }
        }

        return [
            'TimeToEncodeFirstQuality' => null,
            'FullTimeToEncode' => $encodingTime,
            'TimeToPlayback' => $timeToPlayback,
        ];
    }

    #[ArrayShape(['TimeToPlayback' => ""])]
    public function performLive(string $videoUriPath): array
    {
        $id = random_int(1, 100);
        $createdStack = $this->cloudFormation->createStack([
            'Capabilities' => ['CAPABILITY_IAM'],
            'EnableTerminationProtection' => false,
            'OnFailure' => 'DELETE',
            'StackName' => "bench-live-platform-stack-$id",
            'TemplateURL' => $this->liveTemplateUrl,
        ]);

        do{
            $stackDescribe = $this->cloudFormation->describeStacks([
                'StackName' => $createdStack->get('StackId'),
            ]);
            $stack = $stackDescribe->get('Stacks')[0];
            sleep(5);
        }while($stack['StackStatus'] !== 'CREATE_COMPLETE');

        $outputs = $stack['Outputs'];

        $cloudFrontHlsEndpoint = array_values(array_filter($outputs, function ($outpout){
            return  $outpout['OutputKey'] === 'CloudFrontHlsEndpoint';
        }));

        $rtmpEndpoint = array_values(array_filter($outputs, function ($outpout){
            return  $outpout['OutputKey'] === 'MediaLivePrimaryEndpoint';
        }));

        $logBucketS3 = array_values(array_filter($outputs, function ($outpout){
            return  $outpout['OutputKey'] === 'LogsBucket';
        }));

        $consoleDemoBucketS3 = array_values(array_filter($outputs, function ($outpout){
            return  $outpout['OutputKey'] === 'DemoConsole';
        }));

        $tmpBaseUriHlsEndpoint = explode('/', $cloudFrontHlsEndpoint[0]['OutputValue']);
        array_pop($tmpBaseUriHlsEndpoint);
        $baseUriHlsEndpoint =  implode('/', $tmpBaseUriHlsEndpoint);
        $executableFinder = new ExecutableFinder();
        $ffmpegPath = $executableFinder->find('ffmpeg');
        $process = new Process([
            $ffmpegPath,
            '-re',
            '-stream_loop',
            '-1',
            '-i',
            $videoUriPath,
            '-profile:v',
            'main',
            '-pix_fmt',
            'yuv420p',
            '-c:v',
            'libx264',
            '-r',
            '30',
            '-preset',
            'ultrafast',
            '-tune',
            'zerolatency',
            '-c:a',
            'aac',
            '-ar',
            '48000',
            '-ac',
            '2',
            '-f',
            'flv',
            $rtmpEndpoint[0]['OutputValue']
        ]);

        // Start TimeToPlayback measure
        $startTimeToPlayback = microtime(true);

        $process->start();

        // Download master hls manifest
        do {
            $file_headers = get_headers($cloudFrontHlsEndpoint[0]['OutputValue']);
            sleep(1);
        } while (strpos($file_headers[0], '404') !== false);

        $masterManifestHls = file_get_contents($cloudFrontHlsEndpoint[0]['OutputValue']);

        // Extract url of the first playlist manifest
        preg_match('/.*.m3u8/', $masterManifestHls, $matchMasterHls);

        // Download first playlist hls manifest
        $firstQualityHls = file_get_contents("$baseUriHlsEndpoint/$matchMasterHls[0]");

        // Extract url of the first fragment
        preg_match("/.*.ts/", $firstQualityHls, $matchPlaylistHls);
        $fragmentUrl = "$baseUriHlsEndpoint/$matchPlaylistHls[0]";

        // Download first video fragment
        file_get_contents($fragmentUrl);

        // Compute TimeToPlayback measure
        $timeToPlayback = microtime(true) - $startTimeToPlayback;

        $process->stop(5, SIGINT);

        $this->cloudFormation->deleteStack([
            'StackName' => $createdStack->get('StackId')
        ]);

        return [
            'TimeToPlayback' => $timeToPlayback,
        ];
    }

    public function setVodS3BucketDestination(string $vodS3BucketDestination): void
    {
        $this->vodS3BucketDestination = $vodS3BucketDestination;
    }

    public function setVodArnRoleIamMediaConvert(string $vodArnRoleIamMediaConvert): void
    {
        $this->vodArnRoleIamMediaConvert = $vodArnRoleIamMediaConvert;
    }

    public function setVodCloudfrontEndpoint(string $vodCloudfrontEndpoint): void
    {
        $this->vodCloudfrontEndpoint = $vodCloudfrontEndpoint;
    }

    public function setLiveTemplateUrl(string $liveTemplateUrl): void
    {
        $this->liveTemplateUrl = $liveTemplateUrl;
    }

}
