<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Controller\Adminhtml\Task;

/**
 * Tasks timeline action
 * @version 1.0.0
 */
class Timeline extends \Wyomind\CronScheduler\Controller\Adminhtml\Task
{
    /**
     * @var string
     */
    protected $_aclResource = "task_timeline";
    
    /**
     * Action to display the tasks timeline
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->setActiveMenu("Magento_Backend::system");
        $resultPage->getConfig()->getTitle()->prepend(__('Cron Scheduler > Timeline'));
        $resultPage->addBreadcrumb(__('CronScheduler'), __('CronScheduler'));
        return $resultPage;
    }
}