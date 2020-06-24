<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Model\ResourceModel\Task;

/**
 * Tasks collection
 * @version 1.0.0
 */
class Collection extends \Magento\Cron\Model\ResourceModel\Schedule\Collection
{
    /**
     * @var string
     */
    protected $_idFieldName = "schedule_id";

    public function sortByScheduledAtDesc() {
        $this->getSelect()->order('scheduled_at DESC');
        return $this;
    }
    
    /**
     * Get distinct job codes based on the tasks scheduled
     * @return \Wyomind\CronScheduler\Model\ResourceModel\Task\Collection
     */
    public function getJobCodes()
    {
        $this->getSelect()->reset('columns')
                ->columns('DISTINCT(job_code) as job_code')
                ->order('job_code ASC');

        return $this;
    }

    /**
     * Get distinct status based on the tasks scheduled
     * @link http://www.wyomind.com wyomind.com
     * @see \Wyomind\CronScheduler\Block\Adminhtml\Task\Timeline
     * @return \Wyomind\CronScheduler\Model\ResourceModel\Task\Collection
     */
    public function getTaskStatuses()
    {
        $this->getSelect()->reset('columns')
                ->columns('DISTINCT(status) as status')
                ->order('status ASC');

        return $this;
    }

    /**
     * Get the last heart beat found execution date time
     * @return string | null
     */
    public function getLastHeartbeat()
    {
        $this->getSelect()->reset('columns')
                ->columns(['executed_at'])
                ->where('executed_at is not null and job_code ="cronscheduler_heartbeat"')
                ->order('finished_at desc');

        $last = $this->getFirstItem();
        if ($last) {
            return $last->getExecutedAt();
        } else {
            return null;
        }
    }
}