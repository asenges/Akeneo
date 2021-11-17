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
class ShopifyMigrationCommand extends Command
{
    protected static $defaultName = 'shopify:migration';

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
        $this->setDescription('migrate the mapping')
            ->setHelp('setups shopify bundle mapping');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info> migration process start </info>');

        $oroConfigRepository        = $this->entityMangar->getRepository('OroConfigBundle:ConfigValue');
        $shopifyConfigRepository    = $this->entityMangar->getRepository('ShopifyBundle:SettingConfiguration');
        $oldMappingData             = $oroConfigRepository->createQueryBuilder('shopify')
                                        ->select()
                                        ->where('shopify.section like :section')
                                        ->setParameter('section', '%shopify_connector%')
                                        ->getQuery()->getResult();

        if (empty($oldMappingData)) {
            $output->writeln('<error> No exising mapping found </error>');
            return 0;
        }
       

        foreach ($oldMappingData as $key => $oldMapping) {
            $shopifyConfig = $shopifyConfigRepository->findBy(
                ["section" => $oldMapping->getSection(), "name" => $oldMapping->getName()]
            );

            if (!empty($shopifyConfig)) {
                $output->writeln('<error>' . $key . ' Mapping Already Migrated</error>');

                continue;
            }

            $shopifyConfig = new SettingConfiguration();
            $shopifyConfig->setName($oldMapping->getName());
            $shopifyConfig->setSection($oldMapping->getSection());
            $shopifyConfig->setValue($oldMapping->getValue());
            $this->entityMangar->persist($shopifyConfig);
            $this->entityMangar->flush();
            $output->writeln('<info>' . $key . ' Mapping Migrated successfully</info>');
        }

        return 0;
    }
}
