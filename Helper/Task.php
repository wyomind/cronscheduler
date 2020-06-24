<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Helper;

/**
 * Task Helper
 * @version 1.0.0
 */
class Task extends \Magento\Framework\App\Helper\AbstractHelper
{
    const ORIGIN_CRON = 0;
    const ORIGIN_BACKEND = 1;
    const ORIGIN_CLI = 2;
    const ORIGIN_WEBAPI = 3;

    /**
     * return int 0:Cron, 1:Backend, 2:CLI, 3:WebAPI
     */
    public function setTrace(&$schedule)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $request = $objectManager->get("\Magento\Framework\App\RequestInterface");
        $state = $objectManager->get("\Magento\Framework\App\State");
        if ((isset($request->getParams()['redirect']))) { // from the backend
            $schedule->setOrigin(self::ORIGIN_BACKEND);
            $schedule->setIp($request->getClientIp());
            $auth = $objectManager->get("\Magento\Backend\Model\Auth");
            if ($auth->getUser() != null) {
                $schedule->setUser($auth->getUser()->getUsername());
            } else {
                $schedule->setUser("Unknown user");
            }
        } else {
            if (php_sapi_name() == "cli") { // bin/magento OR crontab OR separate process for the group
                $schedule->setOrigin(self::ORIGIN_CLI);
                $schedule->setUser(utf8_encode(get_current_user()));
            } else {
                if ($state->getAreaCode() == "webapi_rest") { // Web API?
                    $schedule->setOrigin(self::ORIGIN_WEBAPI);
                    $schedule->setIp($request->getClientIp());
                } else { // should never go into this case
                    $schedule->setOrigin(self::ORIGIN_CRON);
                    $schedule->setUser(utf8_encode(get_current_user()));
                }
            }
        }
    }
    
    /**
     * Get the cron task status renderer information
     * @param string $status
     * @return array first item : css class / second item : label
     */
    public function getStatusRenderer($status)
    {
        $type = "";
        $inner = "";
        switch ($status) {
            case \Magento\Cron\Model\Schedule::STATUS_ERROR;
                $type = 'major';
                $inner = __("ERROR");
                break;
            case \Magento\Cron\Model\Schedule::STATUS_MISSED;
                $type = 'major';
                $inner = __("MISSED");
                break;
            case \Magento\Cron\Model\Schedule::STATUS_RUNNING;
                $type = 'running';
                $inner = __("RUNNING");
                break;
            case \Magento\Cron\Model\Schedule::STATUS_PENDING;
                $type = 'minor';
                $inner = __("PENDING");
                break;
            case \Magento\Cron\Model\Schedule::STATUS_SUCCESS;
                $type = 'notice';
                $inner = __("SUCCESS");
                break;
        }
        return [$type, $inner];
    }

    public function getOriginToString($origin)
    {
        switch ($origin) {
            case self::ORIGIN_CRON:
                return __("Cron");
            case self::ORIGIN_BACKEND:
                return __("Backend");
            case self::ORIGIN_CLI:
                return __("CLI");
            case self::ORIGIN_WEBAPI:
                return __("WebAPI");
        }
    }
}