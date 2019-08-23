<?php

/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */
class AdyenValidate3dModuleFrontController extends \ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->context = \Context::getContext();
        $adyenHelperFactory = new \Adyen\PrestaShop\service\Adyen\Helper\DataFactory();
        $this->helper_data = $adyenHelperFactory->createAdyenHelperData(
            \Configuration::get('ADYEN_MODE'),
            _COOKIE_KEY_
        );
    }

    public function postProcess()
    {
        // retrieve cart from temp value and restore the cart to approve payment
        $cart = new Cart((int)$this->context->cookie->__get("id_cart_temp"));
        $client = $this->helper_data->initializeAdyenClient();

        $requestMD = $_REQUEST['MD'];
        $requestPaRes = $_REQUEST['PaRes'];
        $paymentData = $_REQUEST['paymentData'];
        $this->helper_data->adyenLogger()->logDebug("md: " . $requestMD);
        $this->helper_data->adyenLogger()->logDebug("PaRes: " . $requestPaRes);
        $this->helper_data->adyenLogger()->logDebug("request" . json_encode($_REQUEST));
        $request = [
            "paymentData" => $paymentData,
            "details" => [
                "MD" => $requestMD,
                "PaRes" => $requestPaRes
            ]
        ];

        $client->setAdyenPaymentSource(\Adyen::MODULE_NAME, \Adyen::VERSION);

        try {
            $client = $this->helper_data->initializeAdyenClient();
            // call lib
            $service = new \Adyen\Service\Checkout($client);
            $response = $service->paymentsDetails($request);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
            $this->helper_data->adyenLogger()->logError("exception: " . $e->getMessage());
        }
        $this->helper_data->adyenLogger()->logDebug("result: " . json_encode($response));
        $currency = $this->context->currency;
        $customer = new \Customer($cart->id_customer);
        $total = (float)$cart->getOrderTotal(true, \Cart::BOTH);
        $resultCode = $response['resultCode'];
        $extra_vars = array();
        if (!empty($response['pspReference'])) {
            $extra_vars['transaction_id'] = $response['pspReference'];
        }
        switch ($resultCode) {
            case 'Authorised':
                $this->module->validateOrder($cart->id, 2, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $new_order = new \Order((int)$this->module->currentOrder);
                if (\Validate::isLoadedObject($new_order)) {
                    $payment = $new_order->getOrderPaymentCollection();
                    if (isset($payment[0])) {
                        //todo add !empty
                        $payment[0]->card_number = pSQL($response['additionalData']['cardBin'] . " *** " . $response['additionalData']['cardSummary']);
                        $payment[0]->card_brand = pSQL($response['additionalData']['paymentMethod']);
                        $payment[0]->card_expiration = pSQL($response['additionalData']['expiryDate']);
                        $payment[0]->card_holder = pSQL($response['additionalData']['cardHolderName']);
                        $payment[0]->save();
                    }
                }
                \Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
                break;
            case 'Refused':
                $this->helper_data->cloneCurrentCart($this->context);
                $this->helper_data->adyenLogger()->logError("The payment was refused, id:  " . $cart->id);
                if ($this->helper_data->isPrestashop16()) {
                    return $this->setTemplate('error.tpl');
                } else {
                    return $this->setTemplate('module:adyen/views/templates/front/error.tpl');
                }
                break;
            default:
                //6_PS_OS_CANCELED_ : order canceled
                $this->module->validateOrder($cart->id, 6, $total, $this->module->displayName, null, $extra_vars,
                    (int)$currency->id, false, $customer->secure_key);
                $this->helper_data->adyenLogger()->logError("The payment was cancelled, id:  " . $cart->id);
                if ($this->helper_data->isPrestashop16()) {
                    return $this->setTemplate('error.tpl');
                } else {
                    return $this->setTemplate('module:adyen/views/templates/front/error.tpl');
                }
                break;
        }
    }
}