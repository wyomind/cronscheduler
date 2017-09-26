<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Helper;

/**
 * Heartbeat helper to check the configuration of the Magento main cron task
 * @version 1.0.0
 */
class HeartBeat extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory
     */
    public $scheduleCollectionFactory = null;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    public $dataTime = null;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    public $messageManager = null;
    
    /**
     * @var string
     */
    private $_magentoVersion = "";

    /**
     * Class constructor
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $scheduleCollectionFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $datetime
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
    \Magento\Framework\App\Helper\Context $context,
            \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $scheduleCollectionFactory,
            \Magento\Framework\Stdlib\DateTime\DateTime $datetime,
            \Magento\Framework\Message\ManagerInterface $messageManager,
            \Magento\Framework\App\ProductMetadata $productMetaData
    )
    {
        $this->scheduleCollectionFactory = $scheduleCollectionFactory;
        $this->datetime = $datetime;
        $this->messageManager = $messageManager;
        $explodedVersion = explode("-", $productMetaData->getVersion()); // in case of 2.2.0-dev
        $this->_magentoVersion = $explodedVersion[0];
        parent::__construct($context);
    }

    /**
     * Check the existence of the last heartbeat, and when it has been ticked for the last time
     * Adds a message in the message manager with the result
     */
    public function getLastHearBeatMessage()
    {
        if (version_compare($this->_magentoVersion, "2.2.0") >= 0) {
            $currentTime = $this->datetime->date('U');
        } else {
            $currentTime = $this->datetime->date('U') + $this->datetime->getGmtOffset('hours') * 60 * 60;
        }
        $lastHeartBeat = strtotime($this->scheduleCollectionFactory->create()->getLastHeartBeat());
        if ($lastHeartBeat != null) {
            $diff = floor(($currentTime - $lastHeartBeat) / 60); // in minutes
            if ($diff > 5) {
                if ($diff >= 60) {
                    $diff = floor($diff / 60);
                    $this->messageManager->addError(__("Last heartbeat is older than %1 hour%2", $diff, ($diff > 1) ? "s" : ""));
                } else {
                    $this->messageManager->addError(__("Last heartbeat is older than %1 minute%2", $diff, ($diff > 1) ? "s" : ""));
                }
            } else {
                $this->messageManager->addSuccess(__("Last heartbeat was %1 minute%2 ago", $diff, ($diff > 1) ? "s" : ""));
            }
        } else {
            $this->messageManager->addError(__("No heartbeat found"));
        }
    }

}
