<?php
/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Bruno Elisei <brunoelisei@o2ti.com>
 * @license     See LICENSE for license details.
 */

namespace MercadoPago\PaymentMagento\Controller;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Notification\NotifierInterface as NotifierPool;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Result\PageFactory;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\OrderRepository;
use MercadoPago\PaymentMagento\Model\Console\Command\Notification\FetchStatus;

/**
 * Class Mercado Pago Index.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class MpIndex extends Action
{
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteria;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transaction;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var FetchStatus
     */
    protected $fetchStatus;

    /**
     * @var NotifierPool
     */
    protected $notifierPool;

    /**
     * @param Context                        $context
     * @param Json                           $json
     * @param SearchCriteriaBuilder          $searchCriteria
     * @param TransactionRepositoryInterface $transaction
     * @param OrderRepository                $orderRepository
     * @param PageFactory                    $pageFactory
     * @param JsonFactory                    $resultJsonFactory
     * @param Logger                         $logger
     * @param FetchStatus                    $fetchStatus
     * @param NotifierPool                   $notifierPool
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Json $json,
        SearchCriteriaBuilder $searchCriteria,
        TransactionRepositoryInterface $transaction,
        OrderRepository $orderRepository,
        PageFactory $pageFactory,
        JsonFactory $resultJsonFactory,
        Logger $logger,
        FetchStatus $fetchStatus,
        NotifierPool $notifierPool
    ) {
        parent::__construct($context);
        $this->json = $json;
        $this->searchCriteria = $searchCriteria;
        $this->transaction = $transaction;
        $this->orderRepository = $orderRepository;
        $this->pageFactory = $pageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->fetchStatus = $fetchStatus;
        $this->notifierPool = $notifierPool;
    }

    /**
     * Get Order Data.
     *
     * @param string $orderId
     *
     * @return OrderRepository|ResultInterface
     */
    public function getOrderData($orderId)
    {
        try {
            /** @var OrderRepository $order */
            $order = $this->orderRepository->get($orderId);
        } catch (Exception $exc) {
            return $this->createResult(
                500,
                [
                    'error'   => 500,
                    'message' => $exc->getMessage(),
                ]
            );
        }

        return $order;
    }

    /**
     * Create Result.
     *
     * @param int   $statusCode
     * @param array $data
     *
     * @return ResultInterface
     */
    public function createResult($statusCode, $data)
    {
        /** @var JsonFactory $resultPage */
        $resultPage = $this->resultJsonFactory->create();
        $resultPage->setHttpResponseCode($statusCode);
        $resultPage->setData($data);

        return $resultPage;
    }

    /**
     * Filter Invalid Notification.
     *
     * @param string          $mpStatus
     * @param OrderRepository $order
     *
     * @return array
     */
    public function filterInvalidNotification(
        $mpStatus,
        $order
    ) {
        $result = [];

        if (!$order->getEntityId()) {

            $result = [
                'isInvalid' => true,
                'code'      => 406,
                'msg'       => __('Order not found.'),
            ];

            return $result;
        }

        if ($mpStatus === 'refunded') {

            if ($order->getState() !== \Magento\Sales\Model\Order::STATE_CLOSED) {
                $header = __('Mercado Pago, refund notification');

                $description = __(
                    'The order %1, was refunded directly on Mercado Pago, you need to create an offline refund.',
                    $order->getIncrementId()
                );

                $this->notifierPool->addCritical($header, $description);
            }

            $result = [
                'isInvalid' => true,
                'code'      => 412,
                'msg'       => __('Unavailable.'),
            ];

            return $result;
        }

        if ($order->getState() === \Magento\Sales\Model\Order::STATE_CLOSED) {

            $result = [
                'isInvalid' => true,
                'code'      => 412,
                'msg'       => [
                    'error'   => 412,
                    'message' => __('Unavailable.'),
                    'state'   => $order->getState(),
                ],
            ];

            return $result;
        }

        $result = [
            'isInvalid' => false,
        ];

        return $result;
    }
}
