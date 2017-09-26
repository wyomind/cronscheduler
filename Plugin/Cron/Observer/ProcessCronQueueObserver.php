<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Plugin\Cron\Observer;

$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
$productMetaData = $objectManager->get("\Magento\Framework\App\ProductMetadata");
$explodedVersion = explode("-",$productMetaData->getVersion()); // in case of 2.2.0-dev
$version = $explodedVersion[0];

if (version_compare($version, "2.1.0") < 0) {

    /**
     * from Magento 2.0.0
     */
    class ProcessCronQueueObserver extends ProcessCronQueueObserverM20
    {
        
    }

} elseif (version_compare($version, "2.2.0") < 0) {

    /**
     * from Magento 2.1.0
     */
    class ProcessCronQueueObserver extends ProcessCronQueueObserverM21
    {
        
    }

} else {

    /**
     * from Magento 2.2.0
     */
    class ProcessCronQueueObserver extends ProcessCronQueueObserverM22
    {
         
    }

}