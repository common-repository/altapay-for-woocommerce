[<](../index.md) Altapay - PHP Api - Charge subscription
=====================================================

Use the chargeSubscription method to capture a single occurrence of a recurring payment, for example a monthly subscription payment.

- [Request](#request)
    + [Required](#required)
    + [Optional](#optional)
    + [Example](#example)
- [Response](#response)

# Request

```php
$request = new \Altapay\Api\Subscription\ChargeSubscription($auth);
// Do the call
try {
    $response = $request->call();
    // See Response below
} catch (\Altapay\Exceptions\ClientException $e) {
    // Could not connect
} catch (\Altapay\Exceptions\ResponseHeaderException $e) {
    // Response error in header
    $e->getHeader()->ErrorMessage
} catch (\Altapay\Exceptions\ResponseMessageException $e) {
    // Error message
    $e->getMessage();
}
```

### Required

| Method  | Description | Type |
|---|---|---|
| setTransaction(string) | The id of the setup agreement transaction.  |

### Optional

| Method  | Description | Type |
|---|---|---|
| setAmount(float) | The amount to capture. | float|
| setTransactionInfo(array) | This is a one-dimensional associative array. This is where you put any value that you would like to bind to the payment. | array
| setOrderLines(array or OrderLine) | Order lines | array of OrderLine objects - [See OrderLine](../request/orderline.md)

### Example

```php
$request = new \Altapay\Api\Subscription\ChargeSubscription($auth);
$request->setTransaction('12345678');
```

# Response

```
$response = $request->call();
```
