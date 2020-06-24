<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Controller\Adminhtml\Job;

/**
 * Run tasks and generate schedule
 * @version 1.0.0
 */
class GenerateSchedule extends \Magento\Backend\App\Action
{
    /**
     * @var string
     */
    protected $_aclResource = "generate_schedule";
    
    /**
     * @var \Magento\Cron\Observer\ProcessCronQueueObserver
     */
    public $cron = null;
    
    /**
     * @var \Magento\Framework\Event\Observer
     */
    public $observer = null;
    
    /**
     * @var \Magento\Backend\Model\View\Result\ForwardFactory
     */
    public $resultForwardFactory = null;

    /**
     * GenerateSchedule class constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Cron\Observer\ProcessCronQueueObserver $cron
     * @param \Magento\Framework\Event\Observer $observer
     * @param \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Cron\Observer\ProcessCronQueueObserver $cron,
        \Magento\Framework\Event\Observer $observer,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
    )
    {
        $this->cron = $cron;
        $this->observer = $observer;
        $this->resultForwardFactory = $resultForwardFactory;
        parent::__construct($context);
    }

    /**
     * Execute action
     */
    public function execute()
    {
        $this->cron->execute($this->observer);
        $params = $this->getRequest()->getParams();
        if (isset($params['redirect'])) {
            return $this->resultRedirectFactory->create()->setPath(str_replace("_","/",$params['redirect']), []);
        }
        return $this->resultRedirectFactory->create()->setPath(\Wyomind\CronScheduler\Helper\Url::JOB_CONFIG, []);
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