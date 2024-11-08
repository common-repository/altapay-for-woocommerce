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
namespace Altapay\Response\Embeds;

use Altapay\Response\AbstractResponse;
class CustomerInfo extends AbstractResponse
{
    /**
     * Children of the response
     *
     * @var array<string, array<string, mixed>>
     */
    protected $childs = ['CountryOfOrigin' => ['class' => \Altapay\Response\Embeds\Country::class, 'array' => \false], 'BillingAddress' => ['class' => \Altapay\Response\Embeds\Address::class, 'array' => \false], 'ShippingAddress' => ['class' => \Altapay\Response\Embeds\Address::class, 'array' => \false], 'RegisteredAddress' => ['class' => \Altapay\Response\Embeds\Address::class, 'array' => \false]];
    /** @var string */
    public $UserAgent;
    /** @var string */
    public $IpAddress;
    /** @var string */
    public $Email;
    /** @var string */
    public $Username;
    /** @var string */
    public $CustomerPhone;
    /** @var numeric */
    public $OrganisationNumber;
    /**
     * @var Country
     */
    public $CountryOfOrigin;
    /**
     * @var Address
     */
    public $BillingAddress;
    /**
     * @var Address
     */
    public $ShippingAddress;
    /**
     * @var Address
     */
    public $RegisteredAddress;
}
