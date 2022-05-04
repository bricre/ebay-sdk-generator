<?php

namespace App\Command;

use App\Generator\EbaySDKGenerator;
use App\GitOperator;
use App\Utility;
use OpenAPI\Parser;
use OpenAPI\Schema\V2\Swagger;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

class GenerateSdkCommand extends Command
{
    protected static $defaultName = 'ebay:generate-sdk';
    protected static $defaultDescription = 'Generate SDK for eBay APIs';
    private string $downloadFolder;

    public function __construct(private GitOperator $gitOperator, protected KernelInterface $kernel)
    {
        parent::__construct();
        $this->downloadFolder = $this->kernel->getProjectDir() . '/var/temp/ebay';
    }


    protected function configure(): void
    {
        $this
            ->addOption('branch', 'b', InputOption::VALUE_OPTIONAL, 'Branch of soucecode to checkout', 'master')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Specify a particular module to generate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finder      = new Finder();
        $filePattern = $input->getOption('module') ? $input->getOption('module') : '*';
        $files       = $finder->files()->name($filePattern . '.json')
            ->in($this->downloadFolder)
            ->depth(0)
            ->sortByName()
        ;

        foreach ($files as $file) {
            $this->generate($file, $input, $output);
        }

        return Command::SUCCESS;
    }

    private function generate(SplFileInfo $file, InputInterface $input, OutputInterface $output)
    {
        /** @var Swagger $swagger */
        $swagger           = Parser::parse($file->getRealPath());
        $version           = Utility::packagistVersionFilter($swagger->info->version);
        $branch            = $input->getOption('branch');
        $isNewBranch       = false;
        $isFirstTimeCommit = false;
        $fs                = new Filesystem();
        $io                = new SymfonyStyle($input, $output);

        $io->title(sprintf('Generate SDK from %s', $file->getFilename()));

        $io->section('Checkout existing code');
        $sdkGenerator = new EbaySDKGenerator($file, $output);
        $gitOperator  = $this->gitOperator->init($file, $sdkGenerator->getDirectory());
        $repo         = $gitOperator->checkout();
        if (!in_array('origin/' . $branch, (array)$repo->getRemoteBranches())) {
            $isNewBranch = true;
        }
        if ($isNewBranch) {
            $repo->createBranch($branch, true);
        } else {
            $repo->checkout($branch);
        }

        // Clear old code to make sure files are only against current version
        foreach (Finder::create()->in($sdkGenerator->getDirectory())->ignoreDotFiles(true)->depth(0) as $fileinfo) {
            $fs->remove($fileinfo->getRealPath());
        }

        $io->section('Generate new code');
        $sdkGenerator->generate();

        if ($isNewBranch) {
            $io->writeln(sprintf('Code is new, pushing to %s now', $gitOperator->getGitUrl()));
            $repo->push(null, ['--set-upstream', 'origin', $branch]);
            $repo->push('origin', [$branch]);
        } else {
            if (in_array($version, (array)$repo->getTags())) {
                $repo->removeTag($version);
                $repo->push(null, ['--delete', 'origin', $version]);
            } else {
                $isFirstTimeCommit = true;
            }
            if ($repo->hasChanges()) {
                $repo->addAllChanges();
                $repo->commit(sprintf('Generated against OpenAPI on %s', date('c')));

                $io->writeln(sprintf('Codebase has been changed, pushing to %s now', $gitOperator->getGitUrl()));
            } else {
                $io->writeln(sprintf("Files from %s hasn't update", $gitOperator->getGitUrl()));
            }
            if ($isFirstTimeCommit) {
                $repo->push(null, ['--set-upstream', 'origin', $branch]);
            }
            $repo->push(['origin', $branch], ['--force']);

        }
        //push tag
        $repo->createTag($version);
        $repo->push(['origin', $version], ['--force']);
        $io->writeln('Done');
    }
}
