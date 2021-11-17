<?php
namespace Webkul\ShopifyBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Webkul\ShopifyBundle\Entity\SettingConfiguration;

// ContainerAwareCommand
class ShopifyInstallationCommand extends Command
{
    protected static $defaultName = 'shopify:setup:install';

    /**
     * @var $entityMangar
     */
    private $entityMangar;

    public function __construct($entityMangar)
    {
        parent::__construct();
        $this->entityMangar = $entityMangar;
    }
    protected function configure()
    {
        $this
            ->setName('shopify:setup:install')
            ->setDescription('Install Shopify Akeneo connector setup')
            ->setHelp('setups shopify bundle installation');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $errorFlag = false;

        /* version wise loading */
        if (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            // version 2
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        } elseif (class_exists('Akeneo\Platform\CommunityVersion')) {
            // version 3 or later
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        }

        $version = $versionClass::VERSION;
        if ($version >= "5.0") {
            $this->runAkeneo5ModuleInstallationCommand($input, $output);
        } elseif ($version >= "4.0") {
            $this->runAkeneo4ModuleInstallationCommand($input, $output);
        } else {
            $this->runAkeneoModuleInstallationCommand($input, $output);
        }

        return 0;
    }

    protected function runAkeneo4ModuleInstallationCommand(InputInterface $input, OutputInterface $output)
    {
        $this->runAkeneoCommanInstallation($input, $output);
    }

    protected function runAkeneo5ModuleInstallationCommand(InputInterface $input, OutputInterface $output)
    {
        $this->runAkeneoCommanInstallation($input, $output);

        $yarn_pkg = preg_replace("/\r|\n/", "", shell_exec('which yarnpkg || which yarn || echo "yarn"'));
        shell_exec($yarn_pkg . ' run update-extensions');
    }
 
    protected function runAkeneoCommanInstallation(InputInterface $input, OutputInterface $output)
    {
        shell_exec('rm -rf public/bundles public/js');

        $this->runCommand(
            'pim:installer:assets',
            [
                '--clean' => null,
                '--symlink'  => null,
            ],
            $output
        );

        $this->runCommand(
            'doctrine:schema:update',
            [
                '--force' => null,
            ],
            $output
        );

        $yarn_pkg = preg_replace("/\r|\n/", "", shell_exec('which yarnpkg || which yarn || echo "yarn"'));

        shell_exec('rm -rf public/css');
        shell_exec($yarn_pkg . ' run less');

        shell_exec('rm -rf public/dist-dev');
        shell_exec($yarn_pkg . ' run webpack-dev');

        shell_exec('rm -rf public/dist');
        shell_exec($yarn_pkg . ' run webpack');
    }

    protected function runAkeneoModuleInstallationCommand(InputInterface $input, OutputInterface $output)
    {
        $this->runCommand(
            'pim:install:asset',
            [],
            $output
        );

        $this->runCommand(
            'assets:install',
            [
                '--symlink' => null,
                '--relative' => null,
            ],
            $output
        );

        $this->runCommand(
            'doctrine:schema:update',
            [
                '--force' => null,
            ],
            $output
        );

        $yarn_pkg = preg_replace("/\r|\n/", "", shell_exec('which yarnpkg || which yarn || echo "yarn"'));

        foreach (['node', 'npm', $yarn_pkg] as $program) {
            $result = shell_exec($program . ' --version');
            if (strpos($result, 'not installed') || strpos($result, 'Ask your administrator')) {
                $output->writeln('<error>' . $result . '</error>');
                $errorFlag = true;
            }
        }

        // run yarn webpack
        $result = shell_exec($yarn_pkg . ' run webpack');

        // success
        if (strpos($result, 'Done in') !== false) {
            $output->writeln('<info>' . $result. '</info>');
        // failure
        } else {
            $output->writeln($result);
            $errorFlag = true;
        }

        if (!exec('grep -r '.escapeshellarg('resource: "@ShopifyBundle/Resources/config/routing.yml"').' ./app/config/routing.yml')) {
            $output->writeln('<comment>Check app/config/routing.yml, maybe shopify entry is not done in this file. Add entry then re run command</comment>');
        }

        $output->writeln("if akeneo doesn't load on server (ex. bitnami) run command <info>sudo chmod -R 777 ./var/cache/**</info>");
        $check = shell_exec('php bin/console --version --env=prod');
    }

    protected function runCommand($name, array $args, $output)
    {
        $command = $this->getApplication()->find($name);
        $commandInput = new ArrayInput(
            $args
        );
        $command->run($commandInput, $output);
    }
}
