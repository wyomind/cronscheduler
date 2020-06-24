<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Controller\Adminhtml\Task;

/**
 * Get the task details to be displayed in a modal window
 */
class View extends \Magento\Backend\App\Action
{
    /**
     * @var string
     */
    protected $_acl_resource = "task_view";

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $_jsonHelper = null;

    /**
     * @var \Magento\Cron\Model\ScheduleFactory
     */
    protected $_scheduleFactory = null;

    /**
     * @var \Wyomind\CronScheduler\Helper\HeartBeat
     */
    protected $_taskHelper = null;

    /**
     * Class constructor
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\Cron\Model\ScheduleFactory $scheduleFactory
     * @param \Wyomind\CronScheduler\Helper\Task $taskHelper
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Cron\Model\ScheduleFactory $scheduleFactory,
        \Wyomind\CronScheduler\Helper\Task $taskHelper
    )
    {
        $this->_jsonHelper = $jsonHelper;
        $this->_scheduleFactory = $scheduleFactory;
        $this->_taskHelper = $taskHelper;
        parent::__construct($context);
    }

    /**
     * Action to view the details of a task
     */
    public function execute()
    {
        $scheduleId = $this->getRequest()->getParam("schedule_id");
        $schedule = $this->_scheduleFactory->create()->load($scheduleId);
        $data = $schedule->getData();
        if (!empty($data)) {
            $data['messages'] = nl2br($data['messages']);
            $data['status'] = $this->_taskHelper->getStatusRenderer($data['status']);
            $data['origin'] = $this->_taskHelper->getOriginToString($data['origin']);
        } else {
            $data['error'] = __("This task doesn't exist anymore");
        }
        $this->getResponse()->representJson($this->_jsonHelper->jsonEncode($data));
    }
}