<?php
/**
* Copyright © 2018 Cointopay. All rights reserved.
* See COPYING.txt for license details.
*/

namespace Cointopay\Paymentgateway\Controller\Order;

use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $_context;
    protected $_pageFactory;
    protected $_jsonEncoder;
    protected $orderManagement;
    protected $resultJsonFactory;
	
	/**
	* @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
	*/
    protected $invoiceSender;

    /**
   * @var \Magento\Framework\App\Config\ScopeConfigInterface
   */
   protected $scopeConfig;

    /**
    * @var \Magento\Framework\HTTP\Client\Curl
    */
    protected $_curl;

    /**
    * @var $merchantId
    **/
    protected $merchantId;

    /**
    * @var $merchantKey
    **/
    protected $merchantKey;

    /**
    * @var $coinId
    **/
    protected $coinId;

    /**
    * @var $type
    **/
    protected $type;

    /**
    * @var $orderTotal
    **/
    protected $orderTotal;

    /**
    * @var $_curlUrl
    **/
    protected $_curlUrl;

    /**
    * @var currencyCode
    **/
    protected $currencyCode;

    /**
    * @var $_storeManager
    **/
    protected $_storeManager;
    
    /**
    * @var $securityKey
    **/
    protected $securityKey;
	
	/**
    * @var $paidnotenoughStatus
    **/
    protected $paidnotenoughStatus;
	
	/**
    * @var $paidnotenoughStatus
    **/
    protected $paidStatus;
	
	/**
    * @var $failedStatus
    **/
    protected $failedStatus;

    /**
    * Merchant ID
    */
    const XML_PATH_MERCHANT_ID = 'payment/cointopay_gateway/merchant_gateway_id';

    /**
    * Merchant COINTOPAY API Key
    */
    const XML_PATH_MERCHANT_KEY = 'payment/cointopay_gateway/merchant_gateway_key';

    /**
    * Merchant COINTOPAY SECURITY Key
    */
    const XML_PATH_MERCHANT_SECURITY = 'payment/cointopay_gateway/merchant_gateway_security';
	
	/**
    * Merchant COINTOPAY SECURITY Key
    */
    const XML_PATH_PAID_NOTENOUGH_ORDER_STATUS = 'payment/cointopay_gateway/order_status_paid_notenough';
	
	/**
    * Merchant COINTOPAY SECURITY Key
    */
    const XML_PATH_PAID_ORDER_STATUS = 'payment/cointopay_gateway/order_status_paid';
	
	/**
    * Merchant FAILED Order Status
    */
    const XML_PATH_ORDER_STATUS_FAILED = 'payment/cointopay_gateway/order_status_failed';

    /**
    * API URL
    **/
    const COIN_TO_PAY_API = 'https://cointopay.com/MerchantAPI';

    /**
    * @var $response
    **/
    protected $response = [] ;

    /*
    * @param \Magento\Framework\App\Action\Context $context
    * @param \Magento\Framework\Json\EncoderInterface $encoder
    * @param \Magento\Framework\HTTP\Client\Curl $curl
    * @param \Magento\Framework\App\Config\ScopeConfigInterface    $scopeConfig
    * @param \Magento\Store\Model\StoreManagerInterface $storeManager
    * @param \Magento\Framework\View\Result\PageFactory $pageFactory
    * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
	* @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    */
    public function __construct (
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Json\EncoderInterface $encoder,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
		\Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) {
        $this->_context = $context;
        $this->_jsonEncoder = $encoder;
        $this->_curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_pageFactory = $pageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderManagement = $orderManagement;
		$this->invoiceSender = $invoiceSender;
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $customerReferenceNr = $this->getRequest()->getParam('CustomerReferenceNr');
            $status = $this->getRequest()->getParam('status');
            $ConfirmCode = $this->getRequest()->getParam('ConfirmCode');
            $SecurityCode = $this->getRequest()->getParam('SecurityCode');
            $notenough = $this->getRequest()->getParam('notenough');
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $this->securityKey = $this->scopeConfig->getValue(self::XML_PATH_MERCHANT_SECURITY, $storeScope);
			$this->paidnotenoughStatus = $this->scopeConfig->getValue(self::XML_PATH_PAID_NOTENOUGH_ORDER_STATUS, $storeScope);
			$this->paidStatus = $this->scopeConfig->getValue(self::XML_PATH_PAID_ORDER_STATUS, $storeScope);
			$this->failedStatus = $this->scopeConfig->getValue(self::XML_PATH_ORDER_STATUS_FAILED, $storeScope);
            if ($this->securityKey == $SecurityCode) {
				$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
				$order = $objectManager->create('\Magento\Sales\Model\Order')
					->loadByIncrementId($customerReferenceNr);
				if (count($order->getData()) > 0) {
					if ($status == 'paid' && $notenough == 1) {
						$order->setState($this->paidnotenoughStatus)->setStatus($this->paidnotenoughStatus);
						$order->save();
					} else if ($status == 'paid') {
						if ($order->canInvoice()) {
							$invoice = $order->prepareInvoice();
							$invoice->getOrder()->setIsInProcess(true);
							$invoice->register()->pay();
							$invoice->save();
						}

						$order->setState($this->paidStatus)->setStatus($this->paidStatus);
						$order->save();
						if ($order->canInvoice()) {
							$this->invoiceSender->send($invoice);
						}
						
					} else if ($status == 'failed') {
						if ($order->getStatus() == 'complete') {
							/** @var \Magento\Framework\Controller\Result\Json $result */
							$result = $this->resultJsonFactory->create();
							return $result->setData([
								'CustomerReferenceNr' => $customerReferenceNr,
								'status' => 'error',
								'message' => 'Order cannot be cancel now, because it is completed now.'
							]);
						} else {
							//$this->orderManagement->cancel($order->getId());
							$order->setState($this->failedStatus)->setStatus($this->failedStatus);
						    $order->save();
						}
					} else {
						/** @var \Magento\Framework\Controller\Result\Json $result */
						$result = $this->resultJsonFactory->create();
						return $result->setData([
							'CustomerReferenceNr' => $customerReferenceNr,
							'status' => 'error',
							'message' => 'Order status should have valid value.'
						]);
					}
					/** @var \Magento\Framework\Controller\Result\Json $result */
					$result = $this->resultJsonFactory->create();
					return $result->setData([
						'CustomerReferenceNr' => $customerReferenceNr,
						'status' => 'success',
						'message' => 'Order status successfully updated.'
					]);
				} else {
					/** @var \Magento\Framework\Controller\Result\Json $result */
					$result = $this->resultJsonFactory->create();
					return $result->setData([
						'CustomerReferenceNr' => $customerReferenceNr,
						'status' => 'error',
						'message' => 'No order found.'
					]);
				}
			} else {
				/** @var \Magento\Framework\Controller\Result\Json $result */
				$result = $this->resultJsonFactory->create();
				return $result->setData([
					'CustomerReferenceNr' => $customerReferenceNr,
					'status' => 'error',
					'message' => 'Security key is not valid.'
				]);
			}
        } catch (\Exception $e) {
            /** @var \Magento\Framework\Controller\Result\Json $result */
            $result = $this->resultJsonFactory->create();
            return $result->setData([
                'CustomerReferenceNr' => $customerReferenceNr,
                'status' => 'error',
                'message' => 'General error:'.$e->getMessage()
            ]);
        }
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();
        return $result->setData([
            'status' => 'error'
        ]);
    }
}
