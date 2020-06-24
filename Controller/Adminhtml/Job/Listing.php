<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Controller\Adminhtml\Job;

/**
 * Jobs listing action
 * @version 1.0.0
 */
class Listing extends \Magento\Backend\App\Action
{
    /**
     * @var string
     */
    protected $_aclResource = "job_listing";
    
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory = null;

    /**
     * @var \Wyomind\CronScheduler\Helper\HeartBeat
     */
    public $heartBeatHelper = null;

    /**
     * Class constructor
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Wyomind\CronScheduler\Helper\HeartBeat $heartBeatHelper
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Wyomind\CronScheduler\Helper\HeartBeat $heartBeatHelper
    )
    {
        $this->_resultPageFactory = $resultPageFactory;
        $this->heartBeatHelper = $heartBeatHelper;
        parent::__construct($context);
    }

    /**
     * Execute action
     */
    public function execute()
    {
        $this->heartBeatHelper->getLastHearBeatMessage();
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->setActiveMenu("Magento_Backend::system");
        $resultPage->getConfig()->getTitle()->prepend(__('Cron Scheduler > Jobs Configuration'));
        $resultPage->addBreadcrumb(__('Cron Scheduler'), __('Cron Scheduler'));
        return $resultPage;
    }

    /**
     * Is the action allowed?
     * @return boolean
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Wyomind_CronScheduler::'.$this->_aclResource);
    }
}