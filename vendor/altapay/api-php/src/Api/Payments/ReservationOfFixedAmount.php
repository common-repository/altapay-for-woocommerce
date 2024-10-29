<?php

/**
 * Copyright (c) 2016 Martin Aarhof
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace Altapay\Api\Payments;

use Altapay\AbstractApi;
use Altapay\Exceptions\ClientException;
use Altapay\Exceptions\ResponseHeaderException;
use Altapay\Exceptions\ResponseMessageException;
use Altapay\Request\Card;
use Altapay\Response\ReservationOfFixedAmountResponse;
use Altapay\Response\PaymentRequestResponse;
use Altapay\Serializer\ResponseSerializer;
use Altapay\Traits;
use Altapay\Types;
use AltaPay\vendor\GuzzleHttp\Exception\ClientException as GuzzleHttpClientException;
use AltaPay\vendor\GuzzleHttp\Exception\GuzzleException;
use AltaPay\vendor\GuzzleHttp\Psr7\Request;
use AltaPay\vendor\Psr\Http\Message\ResponseInterface;
use AltaPay\vendor\Symfony\Component\OptionsResolver\Options;
use AltaPay\vendor\Symfony\Component\OptionsResolver\OptionsResolver;
use Altapay\Request\Config;
/**
 * This will create a MO/TO payment. The payment can be made with a credit card, or a credit card token and the CVV.
 */
class ReservationOfFixedAmount extends AbstractApi
{
    use Traits\AmountTrait;
    use Traits\TerminalTrait;
    use Traits\CurrencyTrait;
    use Traits\ShopOrderIdTrait;
    use Traits\TransactionInfoTrait;
    use Traits\CustomerInfoTrait;
    use Traits\OrderlinesTrait;
    use Traits\AgreementTrait;
    /**
     * The id of the order in your web shop
     *
     * @param string $shopOrderId
     *
     * @return $this
     */
    public function setShopOrderId($shopOrderId)
    {
        $this->unresolvedOptions['shop_orderid'] = $shopOrderId;
        return $this;
    }
    /**
     * Set the credit card
     *
     * @param Card $card
     *
     * @return $this
     */
    public function setCard(Card $card)
    {
        $this->unresolvedOptions['cardnum'] = $card->getCardNumber();
        $this->unresolvedOptions['emonth'] = $card->getExpiryMonth();
        $this->unresolvedOptions['eyear'] = $card->getExpiryYear();
        $this->unresolvedOptions['cvc'] = $card->getCvc();
        return $this;
    }
    /**
     * @param array<string> $agreement
     *
     * @return $this
     */
    public function setAgreement($agreement)
    {
        $this->unresolvedOptions['agreement'] = $agreement;
        return $this;
    }
    /**
     * A credit card token previously received from an eCommerce payment or an other MO/TO payment.
     *
     * @param string $token A credit card token previously received from an eCommerce payment or an other MO/TO payment.
     * @param string $cvc   The CVC/CVV/CVV2/Security Code
     *
     * @return $this
     */
    public function setCreditCardToken($token, $cvc = null)
    {
        $this->unresolvedOptions['credit_card_token'] = $token;
        if ($cvc) {
            $this->unresolvedOptions['cvc'] = $cvc;
        }
        return $this;
    }
    /**
     * This sets the invoice number to be used on capture
     *
     * @param string $number
     *
     * @return $this
     */
    public function setSaleInvoiceNumber($number)
    {
        $this->unresolvedOptions['sale_invoice_number'] = $number;
        return $this;
    }
    /**
     * The type of payment
     *
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->unresolvedOptions['type'] = $type;
        return $this;
    }
    /**
     * If you wish to define the reconciliation identifier used in the reconciliation csv files
     *
     * @param string $identifier
     *
     * @return $this
     */
    public function setSaleReconciliationIdentifier($identifier)
    {
        $this->unresolvedOptions['sale_reconciliation_identifier'] = $identifier;
        return $this;
    }
    /**
     * The source of the payment.
     *
     * @param string $paymentSource
     *
     * @return $this
     */
    public function setPaymentSource($paymentSource)
    {
        $this->unresolvedOptions['payment_source'] = $paymentSource;
        return $this;
    }
    /**
     * Set config
     *
     * @param Config $config
     *
     * @return $this
     */
    public function setConfig(Config $config)
    {
        $this->unresolvedOptions['config'] = $config;
        return $this;
    }
    /**
     * If you wish to decide which fraud detection service to use
     *
     * @param string $fraudService
     *
     * @return $this
     */
    public function setFraudService($fraudService)
    {
        $this->unresolvedOptions['fraud_service'] = $fraudService;
        return $this;
    }
    /**
     * The cookie to be sent to your callback urls
     *
     * @param string $cookie
     *
     * @return $this
     */
    public function setCookie($cookie)
    {
        $this->unresolvedOptions['cookie'] = $cookie;
        return $this;
    }
    /**
     * The surcharge amount to apply to the payment.
     *
     * @param float $surcharge
     *
     * @return $this
     */
    public function setSurcharge($surcharge)
    {
        $this->unresolvedOptions['surcharge'] = $surcharge;
        return $this;
    }
    /**
     * Fraud detection services can use this parameter in the fraud detection calculations
     *
     * @param string $shippingMethod
     *
     * @return $this
     */
    public function setShippingMethod($shippingMethod)
    {
        $this->unresolvedOptions['shipping_method'] = $shippingMethod;
        return $this;
    }
    /**
     * Configure options
     *
     * @param OptionsResolver $resolver
     *
     * @return void
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['terminal', 'shop_orderid', 'amount', 'currency', 'type', 'payment_source']);
        $resolver->setDefault('type', 'payment');
        $resolver->setAllowedValues('type', Types\PaymentTypes::getAllowed());
        $resolver->setAllowedValues('payment_source', Types\PaymentSources::getAllowed());
        $resolver->setDefault('payment_source', 'eCommerce');
        $resolver->setDefined(['cardnum', 'emonth', 'eyear', 'cvc', 'credit_card_token', 'sale_reconciliation_identifier', 'sale_invoice_number', 'config', 'transaction_info', 'agreement', 'fraud_service', 'cookie', 'surcharge', 'customer_info', 'shipping_method', 'customer_created_date', 'orderLines']);
        $resolver->setAllowedTypes('config', Config::class);
        /** @noinspection PhpUnusedParameterInspection */
        $resolver->setNormalizer('config', function (Options $options, Config $value) {
            return $value->serialize();
        });
        $resolver->setAllowedTypes('surcharge', ['int', 'float']);
        $resolver->setAllowedValues('sale_invoice_number', function ($value) {
            return \mb_strlen($value) <= 100;
        });
        $resolver->setNormalizer('cardnum', function (Options $options, $value) {
            if (isset($options['credit_card_token'])) {
                throw new \InvalidArgumentException(\sprintf('You can not set both a credit card and a credit card token'));
            }
            return $value;
        });
        $resolver->setNormalizer('credit_card_token', function (Options $options, $value) {
            $fields = ['cardnum', 'emonth', 'eyear'];
            foreach ($fields as $field) {
                if (isset($options[$field])) {
                    throw new \InvalidArgumentException(\sprintf('You can not set both a credit card token and a credit card'));
                }
            }
            return $value;
        });
    }
    /**
     * Handle response
     *
     * @param Request $request
     * @param ResponseInterface $response
     *
     * @return ReservationOfFixedAmountResponse
     * @throws \Exception
     */
    protected function handleResponse(Request $request, ResponseInterface $response)
    {
        $body = (string) $response->getBody();
        $xml = new \SimpleXMLElement($body);
        return ResponseSerializer::serialize(ReservationOfFixedAmountResponse::class, $xml->Body, $xml->Header);
    }
    /**
     * @return array<string, string>
     */
    protected function getBasicHeaders()
    {
        $headers = parent::getBasicHeaders();
        if (\mb_strtolower($this->getHttpMethod()) === 'post') {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        return $headers;
    }
    /**
     * Url to api call
     *
     * @param array<string, mixed> $options Resolved options
     *
     * @return string
     */
    protected function getUrl(array $options)
    {
        $url = 'reservation';
        if (\mb_strtolower($this->getHttpMethod()) === 'get') {
            $query = $this->buildUrl($options);
            $url = \sprintf('%s/?%s', $url, $query);
        }
        return $url;
    }
    /**
     * @return string
     */
    protected function getHttpMethod()
    {
        return 'POST';
    }
    /**
     * @return \Altapay\Response\AbstractResponse|PaymentRequestResponse|bool|void
     *
     * @throws \Exception
     * @throws ClientException
     * @throws GuzzleException
     * @throws ResponseHeaderException
     * @throws ResponseMessageException
     */
    protected function doResponse()
    {
        $this->doConfigureOptions();
        $headers = $this->getBasicHeaders();
        $requestParameters = [$this->getHttpMethod(), $this->parseUrl(), $headers];
        if (\mb_strtolower($this->getHttpMethod()) === 'post') {
            $requestParameters[] = $this->getPostOptions();
        }
        $request = new Request(...$requestParameters);
        $this->request = $request;
        try {
            $response = $this->getClient()->send($request);
            $this->response = $response;
            $output = $this->handleResponse($request, $response);
            $this->validateResponse($output);
            return $output;
        } catch (GuzzleHttpClientException $e) {
            throw new ClientException($e->getMessage(), $e->getRequest(), $e->getResponse(), $e);
        }
    }
    /**
     * @return string
     */
    protected function getPostOptions()
    {
        $options = $this->options;
        return \http_build_query($options, '', '&');
    }
}
