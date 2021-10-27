<?php

namespace App\Command;

use App\Controller\BenchLivePlatformController;
use MuxPhp\ApiException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BenchLivePlatformCommand extends Command
{
    public function __construct(private BenchLivePlatformController $benchLivePlatform)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('bench-live-platform')
            ->setDescription('Launch a playback benchmark test of Live Platforms.')
            ->setHelp('This command allows you to bench performance of competitors vs us')
            ->addArgument('video_url', InputArgument::REQUIRED);
    }

    /**
     * @throws ApiException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Benchmark live platform");

        $videoUriPath = $input->getArgument('video_url');

        if (filter_var($videoUriPath, FILTER_VALIDATE_URL) === FALSE) {
            $output->writeln('Not a valid URL, you must provide a reachable URL');
            exit(1);
        }

        $output->writeln("Benchmark in progress...");

        $response = $this->benchLivePlatform->benchmark($videoUriPath);
        $results = json_decode($response->getContent(), true);

        $output->writeln("<info>Date: {$results['date']}</info>");
        unset($results['date']);

        foreach ($results as $platform => $result) {
            $output->writeln("<info>$platform</info>");
            foreach ($result as $metric => $value) {
                $measure = $value ?? 'N/A';
                $output->writeln("<comment>$metric: $measure seconds</comment>");
            }
            $output->writeln("================");
        }
        return Command::SUCCESS;
    }
}
