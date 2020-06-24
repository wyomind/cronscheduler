<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Setup;

class Recurring implements \Magento\Framework\Setup\InstallSchemaInterface
{
    public $output = null;
    public $magentoVersion = "";

    /**
     * Recurring constructor.
     * @param \Symfony\Component\Console\Output\ConsoleOutput $output
     * @param \Magento\Framework\App\ProductMetadata $productMetaData
     */
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
        try {
            $this->output->writeln("");
            $version = $this->magentoVersion;
            $this->output->writeln("<comment>Copying files for Magento " . $version . "</comment>");

            $explodedVersion = explode(".", $version);
            $possibleVersion = [
                $version,
                $explodedVersion[0] . "." . $explodedVersion[1],
                $explodedVersion[0]
            ];

            $path = str_replace("Setup" . DIRECTORY_SEPARATOR . "Recurring.php", "", __FILE__);

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
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function install(\Magento\Framework\Setup\SchemaSetupInterface $setup,
                            \Magento\Framework\Setup\ModuleContextInterface $context)
    {

        $files = [
            "Plugin/Cron/Observer/ProcessCronQueueObserver.php",
            "view/adminhtml/ui_component/cronscheduler_task_listing.xml",
            "view/adminhtml/ui_component/cronscheduler_job_listing.xml"
        ];
        $this->copyFilesByMagentoVersion($files);
    }
}