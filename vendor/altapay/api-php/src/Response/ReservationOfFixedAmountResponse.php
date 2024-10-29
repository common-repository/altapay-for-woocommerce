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
namespace Altapay\Response;

use Altapay\Response\Embeds\Transaction;
use Altapay\Response\Embeds\RedirectResponse;
class ReservationOfFixedAmountResponse extends \Altapay\Response\AbstractResponse
{
    /**
     * Children of the response
     *
     * @var array<string, array<string, mixed>>
     */
    protected $childs = ['Transactions' => ['class' => Transaction::class, 'array' => 'Transaction'], 'RedirectResponse' => ['class' => RedirectResponse::class, 'array' => \false]];
    /**
     * Result
     *
     * @var string
     */
    public $Result;
    /** @var string */
    public $MerchantErrorMessage;
    /** @var string */
    public $CardHolderErrorMessage;
    /** @var bool */
    public $CardHolderMessageMustBeShown;
    /**
     * Transactions
     *
     * @var Transaction[]
     */
    public $Transactions;
    /** @var string */
    public $PaymentRequestId;
    /** @var string */
    public $RedirectUrl;
    /** @var string */
    public $Url;
    /** @var string */
    public $DynamicJavascriptUrl;
    /** @var string */
    public $AppUrl;
    /** @var string */
    public $MerchantErrorCode;
    /** @var string */
    public $RedirectResponse;
}
