<?php
/**
 * Copyright © 2019 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Wyomind\CronScheduler\Controller\Adminhtml\Task;

/**
 * Mass delete action from the tasks listing
 * @version 1.0.0
 */
class MassDelete extends \Magento\Backend\App\Action
{
    /**
     * @var string
     */
    protected $_aclResource = "task_massdelete";

    /**
     * @var string
     */
    protected $redirectUrl = \Wyomind\CronScheduler\Helper\Url::TASK_LISTING;

    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    protected $filter;

    /**
     * @var object
     */
    protected $collectionFactory;

    /**
     * MassDelete class constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Ui\Component\MassAction\Filter $filter
     * @param \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $taskCollectionFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Ui\Component\MassAction\Filter $filter,
        \Wyomind\CronScheduler\Model\ResourceModel\Task\CollectionFactory $taskCollectionFactory
    )
    {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $taskCollectionFactory;
    }

    /**
     * Execute the action
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            return $this->massAction($collection);
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath($this->redirectUrl);
        }
    }

    /**
     * Execute the mass delete action
     * @param type $collection
     * @return type
     */
    public function massAction($collection)
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($this->redirectUrl);

        if (!$this->_authorization->isAllowed('Wyomind_CronScheduler::' . $this->_aclResource)) {
            $this->messageManager->addError(__("You are not allowed to delete tasks"));
        } else {

            $counter = 0;
            foreach ($collection as $item) {
                $item->delete();
                $counter ++;
            }
            if ($counter >= 2) {
                $this->messageManager->addSuccess(__("%1 tasks have been deleted", $counter));
            } else {
                $this->messageManager->addSuccess(__("%1 task has been deleted", $counter));
            }
        }

        return $resultRedirect;
    }

    /**
     * Is the action allowed?
     * @return boolean
     */
    protected function _isAllowed()
    {
        return true;
    }
}