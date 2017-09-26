<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Ui\Component\TaskListing\Column\Status;

/**
 * Define the options available for the column "status" in the tasks listing
 * @version 1.0.0
 */
class Options implements \Magento\Framework\Data\OptionSourceInterface
{

    /**
     * @var array
     */
    protected $options = null;
    
    /**
     * @var \Wyomind\CronScheduler\Model\ResourceModel\Task\Collection
     */
    public $taskCollection = null;

    /**
     * Class constructor
     * @param \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $taskCollectionFactory
     */
    public function __construct(
    \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $taskCollectionFactory)
    {
        $this->taskCollection = $taskCollectionFactory->create();
    }

    /**
     * Get all options available
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options === null) {
            $this->options = [];
            $taskStatuses = $this->taskCollection->getTaskStatuses();
            foreach ($taskStatuses as $taskStatus) {
                $this->options[] = [
                    "label" => $taskStatus->getStatus(), "value" => $taskStatus->getStatus()
                ];
            }
        }
        return $this->options;
    }

}
