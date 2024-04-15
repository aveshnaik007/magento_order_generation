<?php

/**
 *
 * @category  Custom Development
 * @email     contactus@learningmagento.com
 * @author    Learning Magento
 * @website   learningmagento.com
 * @Date      03-04-2024
 */

namespace Learningmagento\Ordergeneration\Model;

use Learningmagento\Ordergeneration\Api\CreateOrderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;

class CreateOrder implements CreateOrderInterface
{
    protected $cartManagementInterface;
    protected $cartRepositoryInterface;

    protected $storeManager;

    protected $productFactory;

    protected $salableQty;

    protected $regionCollection;

    protected $configuration;

    protected $messager;

    protected $invoiceService;

    protected $transaction;

    public function __construct(
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollection,
        \Learningmagento\Ordergeneration\Helper\Configuration $configuration,
        \Magento\Framework\Message\ManagerInterface $messager,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        GetSalableQuantityDataBySku  $salableQty
    ) {
        $this->cartManagementInterface = $cartManagementInterface;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->storeManager = $storeManager;
        $this->productFactory = $productFactory;
        $this->regionCollection = $regionCollection;
        $this->configuration = $configuration;
        $this->messager = $messager;
        $this->invoiceService = $invoiceService;
        $this->transection = $transaction;
        $this->salableQty = $salableQty;
    }

    /**
     * This method generate a fresh order
     *
     * @param $orderData
     * @return mixed
     */
    public function generateOrder($order)
    {
        try {
            $cart = $this->emptyCart();
            $storeId = $this->getSelectedStore();
            $store = $this->storeManager->getStore($storeId);
            $cart->setStore($store);
            $cart->setCurrency();
            // Setting a customer data
            $cart->setCustomerId(null);
            $cart->setCustomerEmail($order['email']);
            $cart->setCustomerFirstname($order['name']['firstname']);
            $cart->setCustomerLastname($order['name']['lastname']);
            $cart->setCustomerIsGuest(true);
            $cart->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
            if (!isset($order['order_items']) && empty($order['order_items'])) {
                throw new LocalizedException(__("No items added for generating an order"));
            }
            $reason = [];
            foreach ($order['order_items'] as $item) {
                if (isset($item['sku']) && !empty($item['sku'])) {
                    $qty = $item['qty'];
                    $product = $this->productFactory->create()->loadByAttribute('sku', $item['sku']);
                    if (empty($product)) {
                        $reason[] = $item['sku'] . " SKU not exist on store";
                        break;
                    }
                    if (isset($product) && !empty($product)) {
                        if ($product->getStatus() == '1') {
                            $stock = $this->salableQty->execute($item['sku']);
                            $stockStatus = ($stock[0]['qty'] > 0 && $stock[0]['qty'] >= $qty) ? true : false;
                            if ($stockStatus) {
                                $product->setSkipSaleableCheck(true);
                                $product->setData('is_salable', true);
                                $cart->setIsSuperMode(true);
                                $cart->addProduct($product, (int)$qty);
                            } else {
                                if (!isset($cancelItems[$item['sku']])) {
                                    $reason[] = $item['sku'] . "SKU out of stock";
                                }
                            }
                        } else {
                            $reason[] = $item['sku'] . " SKU not enabled on store";
                        }
                    } else {
                        $reason[] = $item['sku'] . " SKU not exist on store";
                    }
                } else {
                    $reason[] = $item['sku'] ." SKU key not exist in payload";
                }
            }

            if(count($reason)>0) {
                $txt = "";
                foreach ($reason as $rr) {
                    $txt.= " ".$rr;
                }
                throw new LocalizedException(__($txt));
            }


            $shippingRegion = $this->regionCollection->create()
                ->addFieldToFilter("code", ["eq" => $order['shipping']['state']])
                ->getFirstItem();



            $shipAddress = [
                'firstname' => $order['shipping']['firstname'],
                'lastname' => $order['shipping']['lastname'],
                'street' => $order['shipping']['street'],
                'city' => $order['shipping']['city'],
                'region_id' => $shippingRegion->getId(),
                'country_id' => $order['shipping']['country_code'],
                'region' => $shippingRegion->getData("default_name"),
                'postcode' => $order['shipping']['postcode'],
                'telephone' => $order['shipping']['telephone'],
                'fax' => '',
                'save_in_address_book' => 1
            ];

            $billingRegion = $this->regionCollection->create()
                ->addFieldToFilter("code", ["eq" => $order['billing']['state']])
                ->getFirstItem();


            $billAddress = [
                'firstname' => $order['billing']['firstname'],
                'lastname' => $order['billing']['lastname'],
                'street' => $order['billing']['street'],
                'city' => $order['billing']['city'],
                'country_id' => $order['billing']['country_code'],
                'region_id' => $billingRegion->getId(),
                'region' => $billingRegion->getData("default_name"),
                'postcode' => $order['billing']['postcode'],
                'telephone' => $order['billing']['telephone'],
                'fax' => '',
                'save_in_address_book' => 1
            ];
            $cart->getBillingAddress()->addData($billAddress);
            $cart->getShippingAddress()->addData($shipAddress);
            $shippingAddress = $cart->getShippingAddress();

            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingMethod(\Learningmagento\CustomShipping\Model\Carrier\Storeship::ORDER_CODE);

            $cart->setPaymentMethod(\Learningmagento\CustomPayment\Model\PaymentMethod::CODE);

            $cart->setInventoryProcessed(false);
            $cart->save();
            $cart->getPayment()->importData(
                [
                    'method' => \Learningmagento\CustomPayment\Model\PaymentMethod::CODE
                ]
            );

            $cart->collectTotals()->save();
            foreach ($cart->getAllItems() as $item) {
                $item->setDiscountAmount(0);
                $item->setBaseDiscountAmount(0);
                $item->setOriginalCustomPrice($item->getPrice())
                    ->setOriginalPrice($item->getPrice())
                    ->save();
            }
            try {
                /** @var \Magento\Sales\Model\Order $magentoOrder */
                $magentoOrder = $this->cartManagementInterface->submit($cart);
            } catch (\Exception $e) {
                $this->messager->addErrorMessage($e->getMessage());
            }
            if (isset($magentoOrder) && !empty($magentoOrder)) {

                $magentoOrder->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
                    ->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $magentoOrder->save();

                $this->generateInvoice($magentoOrder);
            }

        }
        catch (\Exception $exception) {
            $this->messager->addErrorMessage($exception->getMessage());
        }
    }

    protected function emptyCart()
    {
        $cartId = $this->cartManagementInterface->createEmptyCart();
        return $this->cartRepositoryInterface->get($cartId);
    }

    /**
     * Invoice generation of an order
     *
     * @param $order
     */
    protected function generateInvoice($order)
    {
        try {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->transection->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();
            $order->addStatusHistoryComment(__('Notified customer about invoice #%1.', $invoice->getId()))
                ->setIsCustomerNotified(true)->save();
            $order->setStatus('processing')->save();
        } catch (\Exception $exception) {
            $this->messager->addErrorMessage($exception->getMessage());
        }
    }

    protected function getSelectedStore()
    {
        return $this->configuration->getOrderGenerationConfig("default_store");
    }
}
