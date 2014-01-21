<?php

/*
 * This file is part of Bowerphp.
 *
 * (c) Mauro D'Alatri <mauro.dalatri@bee-lab.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bowerphp\Command;

use Bowerphp\Bowerphp;
use Bowerphp\Config\Config;
use Bowerphp\Installer\Installer;
use Bowerphp\Output\BowerphpConsoleOutput;
use Bowerphp\Package\Package;
use Bowerphp\Repository\GithubRepository;
use Bowerphp\Util\ZipArchive;
use Doctrine\Common\Cache\FilesystemCache;
use Gaufrette\Adapter\Local as LocalAdapter;
use Gaufrette\Filesystem;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Http\Client;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\DefaultCacheStorage;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Search
 */
class SearchCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('search')
            ->setDescription('Search for a package by name')
            ->addArgument('name', InputArgument::REQUIRED, 'Name to search for.')
            ->setHelp(<<<EOT
The <info>search</info> command searches for a package by name.
EOT
            )
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $adapter = new LocalAdapter('/');
        $filesystem = new Filesystem($adapter);
        $httpClient = new Client();
        $config = new Config($filesystem);

        $this->logHttp($httpClient, $output);

        // http cache
        $cachePlugin = new CachePlugin(array(
            'storage' => new DefaultCacheStorage(
                new DoctrineCacheAdapter(
                    new FilesystemCache($config->getCacheDir())
                )
            )
        ));
        $httpClient->addSubscriber($cachePlugin);

        $name = $input->getArgument('name');

        $bowerphp      = new Bowerphp($config);
        $consoleOutput = new BowerphpConsoleOutput($output);
        $installer     = new Installer($filesystem, $httpClient, new GithubRepository(), new ZipArchive(), $config, $consoleOutput);
        $packageNames  =  $bowerphp->searchPackages($httpClient, $name);

        if (count($packageNames) === 0) {
            $output->writeln('No results.');
        } else {
            $output->writeln('Search results:');
            $output->writeln('');
            foreach ($packageNames as $packageName) {
                $package = new Package($packageName);
                $bower = $bowerphp->getPackageInfo($package, $installer, 'original_url');

                $consoleOutput->writelnSearchOrLookup($bower['name'], $bower['url'], 4);
            }
        }
    }
}