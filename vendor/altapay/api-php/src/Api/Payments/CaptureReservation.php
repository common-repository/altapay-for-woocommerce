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
use Altapay\Response\CaptureReservationResponse;
use Altapay\Serializer\ResponseSerializer;
use Altapay\Traits\AmountTrait;
use Altapay\Traits\OrderlinesTrait;
use Altapay\Traits\TransactionsTrait;
use AltaPay\vendor\GuzzleHttp\Exception\ClientException as GuzzleHttpClientException;
use AltaPay\vendor\GuzzleHttp\Exception\GuzzleException;
use AltaPay\vendor\GuzzleHttp\Psr7\Request;
use AltaPay\vendor\Psr\Http\Message\ResponseInterface;
use AltaPay\vendor\Symfony\Component\OptionsResolver\OptionsResolver;
/**
 * When the funds of a payment has been reserved and the goods are ready for delivery
 * your system should capture the payment.
 *
 * By default, auto reauth is enabled for all terminals (but is only supported by a few acquirers),
 * which means if the capture fails the system will automatically try to reauth the payment and then capture again.
 * Reauthed payments, however, do not have cvv or 3d-secure protection, which means the
 * protection against chargebacks is not as good.
 * If you wish to disable auto reauth for one or more of your terminals please contact Altapay.
 */
class CaptureReservation extends AbstractApi
{
    use TransactionsTrait;
    use OrderlinesTrait;
    use AmountTrait;
    /**
     * If you wish to define the reconciliation identifier used in the reconciliation csv files
     *
     * @param string $identifier
     *
     * @return $this
     */
    public function setReconciliationIdentifier($identifier)
    {
        $this->unresolvedOptions['reconciliation_identifier'] = $identifier;
        return $this;
    }
    /**
     * If you wish to decide what the invoice number is on a Arvato invoice, set it here.
     * Note that the invoice number is used as an OCR Number in regard to Klarna captures.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setInvoiceNumber($number)
    {
        $this->unresolvedOptions['invoice_number'] = $number;
        return $this;
    }
    /**
     * The sales tax amount is used if you wish to indicate how much of the gross amount was sales tax
     *
     * @param numeric $salesTax
     *
     * @return $this
     */
    public function setSalesTax($salesTax)
    {
        $this->unresolvedOptions['sales_tax'] = $salesTax;
        return $this;
    }
    /**
     * The shipping tracking info is used if you want to send the shipping tracking info
     * with invoice.
     *
     * @param string $shippingTrackingInfo
     *
     * @return $this
     */
    public function setTrackingInfo($shippingTrackingInfo)
    {
        $this->unresolvedOptions['shippingTrackingInfo'] = $shippingTrackingInfo;
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
        $resolver->setRequired('transaction_id');
        $resolver->setDefined(['amount', 'reconciliation_identifier', 'invoice_number', 'sales_tax', 'orderLines', 'shippingTrackingInfo']);
        $resolver->addAllowedTypes('reconciliation_identifier', 'string');
        $resolver->addAllowedTypes('invoice_number', 'string');
        $resolver->addAllowedTypes('sales_tax', ['string', 'int', 'float']);
    }
    /**
     * Handle response
     *
     * @param Request $request
     * @param ResponseInterface $response
     *
     * @return CaptureReservationResponse
     * @throws \Exception
     */
    protected function handleResponse(Request $request, ResponseInterface $response)
    {
        $body = (string) $response->getBody();
        $xml = new \SimpleXMLElement($body);
        if ((string) $xml->Body->Result === 'Error') {
            throw new \Exception($xml->Body->MerchantErrorMessage);
        }
        try {
            return ResponseSerializer::serialize(CaptureReservationResponse::class, $xml->Body, $xml->Header);
        } catch (\Exception $e) {
            throw $e;
        }
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
        $url = 'captureReservation';
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
     * Generate the response
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
