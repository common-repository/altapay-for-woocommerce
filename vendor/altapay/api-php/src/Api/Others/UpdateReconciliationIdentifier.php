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
namespace Altapay\Api\Others;

use Altapay\AbstractApi;
use Altapay\Exceptions\ClientException;
use Altapay\Exceptions\ResponseHeaderException;
use Altapay\Exceptions\ResponseMessageException;
use Altapay\Response\UpdateReconciliationIdentifierResponse;
use Altapay\Serializer\ResponseSerializer;
use AltaPay\vendor\GuzzleHttp\Exception\ClientException as GuzzleHttpClientException;
use AltaPay\vendor\GuzzleHttp\Psr7\Request;
use AltaPay\vendor\Psr\Http\Message\ResponseInterface;
use AltaPay\vendor\Symfony\Component\OptionsResolver\OptionsResolver;
/**
 * Used to update merchant reconciliation identifier for a given payment transaction.
 */
class UpdateReconciliationIdentifier extends AbstractApi
{
    /** @var string */
    private $paymentId;
    /**
     * Payment id for which the related transaction is to have a merchant reconciliation identifier updated.
     *
     * @param string $paymentId
     * @return void
     */
    public function setPaymentId($paymentId)
    {
        $this->paymentId = $paymentId;
    }
    /**
     * Current merchant reconciliation identifier which should be updated.
     *
     * @param string $currentMerchantReconciliationIdentifier
     *
     * @return $this
     */
    public function setCurrentMerchantReconciliationIdentifier($currentMerchantReconciliationIdentifier)
    {
        $this->unresolvedOptions['currentMerchantReconciliationIdentifier'] = $currentMerchantReconciliationIdentifier;
        return $this;
    }
    /**
     * A new value that will replace the current one.
     *
     * @param string $newMerchantReconciliationIdentifier
     *
     * @return $this
     */
    public function setNewMerchantReconciliationIdentifier($newMerchantReconciliationIdentifier)
    {
        $this->unresolvedOptions['newMerchantReconciliationIdentifier'] = $newMerchantReconciliationIdentifier;
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
        $resolver->setRequired(['currentMerchantReconciliationIdentifier', 'newMerchantReconciliationIdentifier']);
        $resolver->setAllowedTypes('currentMerchantReconciliationIdentifier', 'string');
        $resolver->setAllowedTypes('newMerchantReconciliationIdentifier', 'string');
    }
    /**
     * @param Request $request
     * @param ResponseInterface $response
     * @return UpdateReconciliationIdentifierResponse
     * @throws \Exception
     */
    protected function handleResponse(Request $request, ResponseInterface $response)
    {
        $body = (string) $response->getBody();
        $xml = new \SimpleXMLElement($body);
        return ResponseSerializer::serialize(UpdateReconciliationIdentifierResponse::class, $xml->Body, $xml->Header);
    }
    /**
     * @return array<string, string>
     */
    protected function getBasicHeaders()
    {
        $headers = parent::getBasicHeaders();
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
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
        return 'updateOrder/' . $this->paymentId . '/reconciliationIdentifier';
    }
    /**
     * @return string
     */
    protected function getHttpMethod()
    {
        return 'PATCH';
    }
    /**
     * @return \Altapay\Response\AbstractResponse|\Altapay\Response\Embeds\Transaction[]|UpdateReconciliationIdentifierResponse|string
     * @throws ResponseHeaderException
     * @throws ResponseMessageException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function doResponse()
    {
        $this->doConfigureOptions();
        $headers = $this->getBasicHeaders();
        $requestParameters = [$this->getHttpMethod(), $this->parseUrl(), $headers];
        $requestParameters[] = $this->getPostOptions();
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
