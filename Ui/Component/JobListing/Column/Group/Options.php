<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Ui\Component\JobListing\Column\Group;

/**
 * Define the options available for the column "group" in the jobs listing
 * @version 1.0.0
 */
class Options implements \Magento\Framework\Data\OptionSourceInterface
{

    /**
     * @var array
     */
    protected $options = null;
    
    /**
     * @var \Magento\Cron\Model\ConfigInterface
     */
    public $cronConfig = null;

    /**
     * Class constructor
     * @param \Magento\Cron\Model\ConfigInterface $cronConfig
     */
    public function __construct(
    \Magento\Cron\Model\ConfigInterface $cronConfig
    )
    {
        $this->cronConfig = $cronConfig;
    }

    /**
     * Get all options available
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options === null) {
            $configJobs = $this->cronConfig->getJobs();
            foreach (array_keys($configJobs) as $group) {
                $this->options[] = [
                    "label" => $group, "value" => $group
                ];
            }
        }
        return $this->options;
    }

}
