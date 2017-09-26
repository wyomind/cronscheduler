<?php

/**
 * Copyright © 2017 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Block\Adminhtml\Task\Timeline;

/**
 * Toolbar block for the view System > Cron Scheduler > Tasks Timeline (button : Generate Schedule)
 * @version 1.0.0
 */
class Actions extends \Magento\Backend\Block\Template
{

    /**
     * @var \Magento\Framework\Authorization
     */
    protected $_authorization = null;

    /**
     * @var string
     */
    protected $_aclResource = "generate_schedule";

    /**
     * Class constructor
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     */
    public function __construct(
    \Magento\Backend\Block\Template\Context $context,
            array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_authorization = $context->getAuthorization();
        $this->setTemplate('task/timeline/actions.phtml');
    }

    /**
     * Is allowed to generate schedule
     * @return boolean
     */
    public function isAllowed()
    {
        return $this->_authorization->isAllowed('Wyomind_CronScheduler::' . $this->_aclResource);
    }

    /**
     * Get the url to generate schedule
     * @return string the url
     */
    public function getGenerateScheduleUrl()
    {
        return $this->getUrl("*/job/generateSchedule", ["redirect" => "cronscheduler_task_timeline"]);
    }

}
