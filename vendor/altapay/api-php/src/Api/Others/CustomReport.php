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
use Altapay\Traits\CsvToArrayTrait;
use AltaPay\vendor\GuzzleHttp\Psr7\Request;
use AltaPay\vendor\GuzzleHttp\Psr7\Response;
use AltaPay\vendor\Psr\Http\Message\ResponseInterface;
use AltaPay\vendor\Symfony\Component\OptionsResolver\OptionsResolver;
/**
 * Used to get a comma separated value file containing the custom report.
 * Find the id and see the optional parameters that can be passed to each custom report on the
 * custom report details page in the merchant interface (this is only visible if you have api credentials).
 *
 * Custom reports can be enabled by AltaPay support.
 */
class CustomReport extends AbstractApi
{
    use CsvToArrayTrait;
    /**
     * Report id - find the id in the url when viewing the custom report in the merchant interface.
     *
     * @param string $id
     *
     * @return $this
     */
    public function setCustomReportId($id)
    {
        $this->unresolvedOptions['id'] = $id;
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
        $resolver->setRequired(['id']);
        $resolver->setAllowedTypes('id', 'string');
    }
    /**
     * Handle response
     *
     * @param Request           $request
     * @param ResponseInterface $response
     *
     * @return string
     */
    protected function handleResponse(Request $request, ResponseInterface $response)
    {
        return (string) $response->getBody();
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
        return \sprintf('getCustomReport?%s', $query);
    }
}
