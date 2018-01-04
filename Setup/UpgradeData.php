<?php

/*
 * Copyright © 2015 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class UpgradeData implements UpgradeDataInterface
{

    public $output = null;
    public $magentoVersion = "";

    public function __construct(
    \Symfony\Component\Console\Output\ConsoleOutput $output,
            \Magento\Framework\App\ProductMetadata $productMetaData
    )
    {
        $this->output = $output;
        $explodedVersion = explode("-", $productMetaData->getVersion());
        $this->magentoVersion = $explodedVersion[0];
    }

    public function copyFilesByMagentoVersion($files)
    {
        $this->output->writeln("");
        $version = $this->magentoVersion;
        $this->output->writeln("<comment>Copying files for Magento " . $version . "</comment>");

        $explodedVersion = explode(".", $version);
        $possibleVersion = [
            $version,
            $explodedVersion[0] . "." . $explodedVersion[1],
            $explodedVersion[0]
        ];

        $path = str_replace("Setup" . DIRECTORY_SEPARATOR . "UpgradeData.php", "", __FILE__);


        foreach ($files as $file) {
            $fullFile = $path . str_replace("/", DIRECTORY_SEPARATOR, $file);
            $ext = pathinfo($fullFile, PATHINFO_EXTENSION);

            foreach ($possibleVersion as $v) {
                $newFile = str_replace("." . $ext, "_" . $v . "." . $ext, $fullFile);
                if (file_exists($newFile)) {
                    copy($newFile, $fullFile);
                    break;
                }
            }
        }
    }

    public function upgrade(ModuleDataSetupInterface $setup,
            ModuleContextInterface $context)
    {

        $setup->startSetup();

        $files = [
            "Plugin/Cron/Observer/ProcessCronQueueObserver.php",
            "view/adminhtml/ui_component/cronscheduler_task_listing.xml",
            "view/adminhtml/ui_component/cronscheduler_job_listing.xml"
        ];
        $this->copyFilesByMagentoVersion($files);

        $setup->endSetup();
    }

}
