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
use Altapay\Response\TerminalsResponse;
use Altapay\Serializer\ResponseSerializer;
use AltaPay\vendor\GuzzleHttp\Psr7\Request;
use AltaPay\vendor\GuzzleHttp\Psr7\Response;
use AltaPay\vendor\Psr\Http\Message\ResponseInterface;
use AltaPay\vendor\Symfony\Component\OptionsResolver\OptionsResolver;
/**
 * This method will allow you to extract a list of terminals that you have access to.
 * The list will contain some details about the terminals
 */
class Terminals extends AbstractApi
{
    /**
     * Configure options
     *
     * @param OptionsResolver $resolver
     *
     * @return void
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
    }
    /**
     * Handle response
     *
     * @param Request $request
     * @param ResponseInterface $response
     *
     * @return TerminalsResponse
     * @throws \Exception
     */
    protected function handleResponse(Request $request, ResponseInterface $response)
    {
        $body = (string) $response->getBody();
        $xml = new \SimpleXMLElement($body);
        return ResponseSerializer::serialize(TerminalsResponse::class, $xml->Body, $xml->Header);
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
        $query = $this->buildUrl($options);
        return \sprintf('getTerminals?%s', $query);
    }
}
