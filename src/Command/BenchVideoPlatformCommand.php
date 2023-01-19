<?php

namespace App\Command;

use App\Controller\BenchApiVideoController;
use App\Controller\BenchAWSController;
use App\Controller\BenchCloudflareController;
use App\Controller\BenchJWPlayerController;
use App\Controller\BenchMuxController;
use App\Controller\BenchVideoPlatformController;
use App\Controller\BenchVimeoController;
use App\Controller\BenchYoutubeController;
use Google\Exception;
use MuxPhp\ApiException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Vimeo\Exceptions\VimeoRequestException;

class BenchVideoPlatformCommand extends Command
{
    public function __construct(
        private BenchApiVideoController $apiVideoController,
        private BenchMuxController $muxController,
        private BenchAWSController $awsController,
        private BenchJWPlayerController $jwPlayerController,
        private BenchVimeoController $vimeoController,
        private BenchYoutubeController $youtubeController,
        private BenchCloudflareController $cloudflareController
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('bench-video-platform')
            ->setDescription('Launch a encoding benchmark test of Video Platforms.')
            ->setHelp('This command allows you to bench encoding performance of competitors vs us')
            ->addArgument('video_url', InputArgument::REQUIRED);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Benchmark video platform");

        $videoUriPath = $input->getArgument('video_url');

        if (filter_var($videoUriPath, FILTER_VALIDATE_URL) === FALSE) {
            $output->writeln('Not a valid URL, you must provide a reachable URL');
            exit(1);
        }

        $output->writeln("Benchmark in progress...");

        $this->youtubeController->authenticate();

        $response = $this->apiVideoController->benchmark($videoUriPath);
        $this->displayResult($response, $output);

        $response = $this->muxController->benchmark($videoUriPath);
        $this->displayResult($response, $output);

        $response = $this->vimeoController->benchmark($videoUriPath);
        $this->displayResult($response, $output);

        $response = $this->cloudflareController->benchmark($videoUriPath);
        $this->displayResult($response, $output);

        $response = $this->jwPlayerController->benchmark($videoUriPath);
        $this->displayResult($response, $output);

        $response = $this->awsController->benchmark($videoUriPath);
        $this->displayResult($response, $output);

        $response = $this->youtubeController->benchmark($videoUriPath);
        $this->displayResult($response, $output);

        return Command::SUCCESS;
    }

    private function displayResult(JsonResponse $response, OutputInterface $output):void
    {
        $results = json_decode($response->getContent(), true);
        $output->writeln("<info>Date: {$results['date']}</info>");
        unset($results['date']);
        foreach($results as $platform => $result){
            $output->writeln("<info>$platform</info>");
            foreach($result as $metric => $value){
                $measure = $value ?? 'N/A';
                $output->writeln("<comment>$metric: $measure seconds</comment>");
            }
            $output->writeln("================");
        }
    }
}
