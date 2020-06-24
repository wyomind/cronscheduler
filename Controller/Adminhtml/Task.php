<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Controller\Adminhtml;

/**
 * Abstract controller for tasks
 * @version 1.0.0
 */
abstract class Task extends \Magento\Backend\App\Action
{

    /**
     * @var string
     */
    protected $_aclResource = "";
    
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
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->heartBeatHelper = $heartBeatHelper;
        parent::__construct($context);
        $this->heartBeatHelper->getLastHearBeatMessage();
    }
    
    /**
     * Is the action allowed?
     * @return boolean
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Wyomind_CronScheduler::'.$this->_aclResource);
    }
    
    /**
     * Execute the action
     */
    abstract public function execute();
}
