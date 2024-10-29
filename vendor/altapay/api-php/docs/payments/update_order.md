[<](../index.md) Altapay - PHP Api - Update Order
================================================================

If you need to modify an existing order in your system, the updateOrder API can help you accomplish that. With this API, you can update the order amount and also add, remove, or update order lines as needed. 

- [Request](#request)
    + [Required](#required)
    + [Optional](#optional)
    + [Example](#example)
- [Response](#response)

# Request

```php
$request = new \Altapay\Api\Payments\UpdateOrder($auth);
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
| setPaymentId(string) | The id of a specific payment. | string
| setAmount(float) | The amount of the payment in english notation (ex. 89.95)<br />For a subscription the amount is the default amount for each capture.<br />Amount is limited to 2 decimals, an error will be returned if more decimals is supplied. | int, float

### Optional

| Method  | Description | Type |
|---|---|---|
| setOrderLines(string) | This method allows you to update the order lines associated with a particular order. With setOrderlines, you can add new order lines, remove existing ones, or update the quantity or price of existing order lines. | array

### Example

```php
$request = new \Altapay\Api\Payments\UpdateOrder($auth);
$request->setPaymentId('12345678-12345678');
$request->setAmount('123');
$request->setOrderLines(new OrderLine('White sugar', 'productid', 1.5, 5.75));
```

# Response

Object of `\Altapay\Response\UpdateOrderResponse`

| Method                          | Description                            | Type   |
|---------------------------------|----------------------------------------|--------|
| `$response->Result`             | The result                             | string |
