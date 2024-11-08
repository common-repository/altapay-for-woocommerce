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
class Address extends AbstractResponse
{
    /**
     * @var string|null
     */
    public $Email;
    /**
     * @var string|null
     */
    public $Firstname;
    /**
     * @var string|null
     */
    public $Lastname;
    /**
     * @var string|null
     */
    public $Address;
    /**
     * @var string|null
     */
    public $City;
    /**
     * @var string|null
     */
    public $PostalCode;
    /**
     * @var string
     */
    public $Region;
    /**
     * @var string|null
     */
    public $Country;
    /**
     * @var string
     */
    public $billingAddress;
    /**
     * @var string
     */
    public $paymentMethod;
    /**
     * @var string
     */
    public $currency;
    /**
     * @var string
     */
    public $orderAmount;
    /**
     * @param string $email
     *
     * @return $this
     */
    public function setEmail($email)
    {
        $this->Email = $email;
        return $this;
    }
    /**
     * @param string $FirstName
     *
     * @return $this
     */
    public function setFirstName($FirstName)
    {
        $this->Firstname = $FirstName;
        return $this;
    }
    /**
     * @param string $LastName
     *
     * @return $this
     */
    public function setLastName($LastName)
    {
        $this->Lastname = $LastName;
        return $this;
    }
    /**
     * @param string $address
     *
     * @return $this
     */
    public function setAddress($address)
    {
        $this->Address = $address;
        return $this;
    }
    /**
     * @param string $city
     *
     * @return $this
     */
    public function setCity($city)
    {
        $this->City = $city;
        return $this;
    }
    /**
     * @param string $postalCode
     *
     * @return $this
     */
    public function setPostalCode($postalCode)
    {
        $this->PostalCode = $postalCode;
        return $this;
    }
    /**
     * @param string $region
     *
     * @return $this
     */
    public function setRegion($region)
    {
        $this->Region = $region;
        return $this;
    }
    /**
     * @param string $country
     *
     * @return $this
     */
    public function setCountry($country)
    {
        $this->Country = $country;
        return $this;
    }
}
