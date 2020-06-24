<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Controller\Adminhtml\Task;

/**
 * Tasks listing action
 * @version 1.0.0
 */
class Listing extends \Wyomind\CronScheduler\Controller\Adminhtml\Task
{
    /**
     * @var string
     */
    protected $_aclResource = "task_listing";

    /**
     * Action to display the tasks listing
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->setActiveMenu("Magento_Backend::system");
        $resultPage->getConfig()->getTitle()->prepend(__('Cron Scheduler > Tasks List'));
        $resultPage->addBreadcrumb(__('Cron Scheduler'), __('Cron Scheduler'));
        return $resultPage;
    }
}