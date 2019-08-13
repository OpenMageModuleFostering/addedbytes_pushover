<?php
class Addedbytes_Pushover_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Observer method called on sales_order_place_after event
     */
    public function newOrderNotification($observer)
    {
        // Get order
        $order = $observer->getEvent()->getOrder();

        // Check there is an order ID in case this was another event triggering the observer
        $incrementId = $order->getIncrementId();
        if (!$incrementId) {
            return true;
        }

        $messageTitle = 'New Order ' . $incrementId();
        $messageText = 'A new order was placed on your Magento store by "' . $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname() . '" for ' . Mage::helper('checkout')->formatPrice($order->getGrandTotal());
        $orderUrl = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view', array('order_id' => $order->getId()));

        self::sendToPushover($messageTitle, $messageText, $orderUrl);
    }

    /**
     * Observer method called on config save. Check info and send notification.
     */
    public function confirmConfiguration($observer)
    {
        $messageTitle = 'Configuration Passed';
        $messageText = 'This message is confirmation that the configuration provided for the Added Bytes Pushover extension is working correctly.';

        self::sendToPushover($messageTitle, $messageText);
    }

    /**
     * Send notification to linked pushover accounts
     */
    public static function sendToPushover($strTitle, $strMessage, $strUrl = '')
    {

        // Check module is enabled
        $isEnabled = Mage::getStoreConfig('pushover/pushover_options/pushover_active', Mage::app()->getStore());
        if ($isEnabled != 1) {
            return false;
        }

        // Check curl is available
        if (!function_exists('curl_version')) {
            Mage::log('[Pushover] Curl is not available.', Zend_Log::INFO, 'addedbytes.log');
            return false;
        }

        // Check token
        $token = Mage::getStoreConfig('pushover/pushover_settings/pushover_api_token', Mage::app()->getStore());
        if ($token == '') {
            // No token set
            Mage::log('[Pushover] Application token not set.', Zend_Log::INFO, 'addedbytes.log');
            return false;
        }

        $userKeysString = Mage::getStoreConfig('pushover/pushover_settings/pushover_user_keys', Mage::app()->getStore());
        if ($userKeysString == '') {
            // No user key set
            Mage::log('[Pushover] No user keys set.', Zend_Log::INFO, 'addedbytes.log');
            return false;
        }

        // Set curl options
        $curlh = curl_init();
        curl_setopt($curlh, CURLOPT_URL, 'https://api.pushover.net/1/messages.json');
        curl_setopt($curlh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlh, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curlh, CURLOPT_TIMEOUT, 3);

        $success = true;

        $userKeys = explode(',', $userKeysString);
        foreach ($userKeys as $userKey) {
            curl_setopt(
                $curlh,
                CURLOPT_POSTFIELDS,
                array(
                    'token' => $token,
                    'user' => trim($userKey),
                    'title' => $strTitle,
                    'message' => $strMessage,
                    'url' => $strUrl,
                )
            );
            // Send notification
            $result = curl_exec($curlh);
            $resultArray = json_decode($result, true);
            if ($resultArray['status'] != 1) {
                // Check for token error
                if ((isset($resultArray['token'])) && ($resultArray['token'] == 'invalid')) {
                    $success = false;
                    Mage::getSingleton('core/session')->addError('Unable to send confirmation message with given application token: "' . $token . '".');
                    break;
                }
                $success = false;
                Mage::getSingleton('core/session')->addError('Unable to send confirmation message to device with key: "' . $userKey . '".');
            }
        }
        curl_close($curlh);

        if ($success) {
            Mage::getSingleton('core/session')->addSuccess('Pushover configuration was saved successfully. You should receive confirmation messages on linked devices.');
        }

        return true;
    }
}
