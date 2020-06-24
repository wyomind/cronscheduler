<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Cron;

/**
 * Cron Scheduler heartbeat cron (used to check the Magento main cron task configuration)
 * @version 1.0.0
 */
class HeartBeat
{
    /**
     * Cron task method
     * Simply adding a message "Cron is alive" in the task
     * @param \Magento\Cron\Model\Schedule $schedule
     */
    public function heartbeat(\Magento\Cron\Model\Schedule $schedule = null)
    {
        $schedule->setMessages(__("Cron is alive"));
        $schedule->save();
    }
}