<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Helper;

/**
 * Job Helper
 */
class Job extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var \Magento\Cron\Model\ConfigInterface
     */
    protected $_cronConfig = null;

    /**
     * Class constructor
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Cron\Model\ConfigInterface $cronConfig
     */
    public function __construct(
    \Magento\Framework\App\Helper\Context $context,
            \Magento\Cron\Model\ConfigInterface $cronConfig
    )
    {
        $this->_cronConfig = $cronConfig;
        parent::__construct($context);
    }

    /**
     * Get the job data
     * (independant method in order to be able to plugin it in the Pro version)
     * @return array
     */
    public function getJobData()
    {
        $data = [];
        $configJobs = $this->_cronConfig->getJobs();
        foreach ($configJobs as $group => $jobs) {
            foreach ($jobs as $code => $job) {
                $job['code'] = $code;
                $job['group'] = $group;
                if (!isset($job['config_schedule'])) {
                    if (isset($job['schedule'])) {
                        $job['config_schedule'] = $job['schedule'];
                    } else {
                        $job['config_schedule'] = "";
                    }
                }
                $data[$code] = $job;
            }
        }
        return $data;
    }

}
