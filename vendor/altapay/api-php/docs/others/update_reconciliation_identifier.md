[<](../index.md) Altapay - PHP Api - Update merchant reconciliation identifier
================================================

This method to update merchant reconciliation identifier for a given payment transaction.

- [Request](#request)
  + [Required](#required)
  + [Optional](#optional)
  + [Example](#example)
- [Response](#response)

# Request

```php
$request = new \Altapay\Api\Others\UpdateReconciliationIdentifier($auth);
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
} catch (\Exception $e) {
    // Error message
    $e->getMessage();
}
```

### Required

| Method                                             | Description                                                                                            | Type    |
|----------------------------------------------------|--------------------------------------------------------------------------------------------------------|---------|
| setPaymentId(string)                               | Payment id for which the related transaction is to have a merchant reconciliation identifier updated.  | string  |
| setCurrentMerchantReconciliationIdentifier(string) | Current merchant reconciliation identifier which should be updated.                                    | string  |
| setNewMerchantReconciliationIdentifier(string)     | A new value that will replace the current one.                                                         | string  |

### Optional

No optional options allowed

### Example

```php
$request = new \Altapay\Api\Others\UpdateReconciliationIdentifier($auth);
$request->setPaymentId('4187a57a-5377-47b3-bcff-9ac21e551910');
$request->setCurrentMerchantReconciliationIdentifier('15b3f4f1-7805-48b3-a6ae-78c997cd8aca');
$request->setNewMerchantReconciliationIdentifier('222222');
```

# Response

Object of `\Altapay\Response\UpdateReconciliationIdentifierResponse`

| Method                          | Description                            | Type   |
|---------------------------------|----------------------------------------|--------|
| `$response->Result`             | The result                             | string |

