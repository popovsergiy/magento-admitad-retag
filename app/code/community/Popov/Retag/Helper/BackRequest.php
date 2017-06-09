<?php

/**
 * Enter description here...
 *
 * @category Agere
 * @package Agere_<package>
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 07.06.2017 17:48
 */
class Popov_Retag_Helper_BackRequest
{
    public function sendBackRequest()
    {
        $cookie = Mage::getSingleton('core/cookie');
		if (!$cookie->get('ADMITAD_UID')) {
			return;
		}
        $order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getLastOrderId());

        $backUrl = Mage::getStoreConfig('popov_retag/postback/back_url');

        $items = $order->getAllVisibleItems();
        foreach ($items as $key => $item) {
            $post = [
                'postback_key' => Mage::getStoreConfig('popov_retag/postback/postback_key'),
                'campaign_code' => Mage::getStoreConfig('popov_retag/postback/campaign_code'),
                'postback' => 1,
                'action_code' => 1,
                'uid' => $cookie->get('ADMITAD_UID'),
                'order_id' => $order->getId(),
                'tariff_code' => 1,
                'price' => $item->getPrice(),
                'quantity' => (int) $item->getQtyOrdered(),
                'position_id' => $key + 1,
                'position_count' => $order->getTotalItemCount(),
                'product_id' => $item->getProductId(),
                'payment_type' => 'sale',

                'coupon' => (int) (bool) $order->getCouponCode(),
                'old_consumer' => $this->hasCustomerPreviousOrders(),
                'currency_code' => Mage::app()->getStore()->getCurrentCurrencyCode(),
                'country_code' => $this->getCountryCode()
            ];
			if ($customerId = Mage::getSingleton('customer/session')->getCustomer()->getId()) {
				$post['client_id'] = $customerId;
			}

            $this->send($backUrl, $post);
        }
    }

    public function send($url, $data)
    {
		$urlQuery = $url . '?' . http_build_query($data);

		$ch = curl_init();
		// Set query data here with the URL
		curl_setopt($ch, CURLOPT_URL, $urlQuery);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		#curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

		$response = curl_exec($ch);
		curl_close($ch);

    }

    /**
     * @link https://magento.stackexchange.com/a/70096
     * @link https://stackoverflow.com/a/9586889/1335142
     */
    public function hasCustomerPreviousOrders()
    {
        $order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getLastOrderId());
        #$customer = Mage::getSingleton('customer/session')->getCustomer();
        #$email = $customer->getEmail();
        $email = $order->getCustomerEmail();


        $orderCollection = Mage::getModel('sales/order')->getCollection();
        $orderCollection->addFieldToFilter('customer_email', $email);

        return (int) (bool) count($orderCollection);
    }

    /**
     * @see https://stackoverflow.com/a/6989826/1335142
     * @return string
     */
    public function getCountryCode()
    {
		$customer = Mage::getSingleton('customer/session')->getCustomer();
		$billingAddress = $customer->getDefaultBillingAddress();
		if ($billingAddress) {
			$countryCode = $billingAddress->getCountry();
		} else {
			$countryCode = Mage::getStoreConfig('general/country/default');
		}

        return $countryCode;
    }
}