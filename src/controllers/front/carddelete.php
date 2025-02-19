<?php

use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use Adyen\Core\BusinessLogic\CheckoutAPI\CheckoutConfig\Request\DisableStoredDetailsRequest;
use Adyen\Core\Infrastructure\ORM\Exceptions\RepositoryClassException;
use AdyenPayment\Classes\Bootstrap;
use AdyenPayment\Classes\Utility\AdyenPrestaShopUtility;

/**
 * Class AdyenOfficialCardDeleteModuleFrontController
 */
class AdyenOfficialCardDeleteModuleFrontController extends ModuleFrontController
{
    /**
     * @throws RepositoryClassException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * @return void
     *
     * @throws \Adyen\Core\BusinessLogic\Domain\Connection\Exceptions\ConnectionSettingsNotFountException
     */
    public function postProcess(): void
    {
        $customerId = \Tools::getValue('customerId');
        $cardId = \Tools::getValue('cardId');
        if ($cardId === '') {
            AdyenPrestaShopUtility::die404(
                [
                    'message' => 'Disable action could not be processed, invalid request.'
                ]
            );
        }

        if ($customerId === '') {
            AdyenPrestaShopUtility::die404(
                [
                    'message' => 'Disable action could not be processed, customer not found.'
                ]
            );
        }

        $shop = \Shop::getShop(\Context::getContext()->shop->id);
        $disableRequest = new DisableStoredDetailsRequest(
            $shop['domain'] . '_' . \Context::getContext()->shop->id . '_' . $customerId,
            $cardId
        );

        $result = CheckoutAPI::get()->checkoutConfig(\Context::getContext()->shop->id)->disableStoredDetails(
            $disableRequest
        );

        if (!$result->isSuccessful()) {
            AdyenPrestaShopUtility::die400(
                [
                    'message' => 'Disable action could not be processed.'
                ]
            );
        }

        AdyenPrestaShopUtility::dieJson($result);
    }
}
