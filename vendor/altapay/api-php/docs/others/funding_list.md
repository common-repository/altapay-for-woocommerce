[<](../index.md) Altapay - PHP Api - Funding list
==============================================

Used to get a list of fundings, which details when and how much money is transfered to your companys bank account.

- [Request](#request)
    + [Required](#required)
    + [Optional](#optional)
    + [Example](#example)
- [Response](#response)

# Request

```php
$request = new \Altapay\Api\Others\FundingList($auth);
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

No required options

### Optional

| Method  | Description | Type |
|---|---|---|
| setPage(int) | This method will only show 100 fundings pr. call. Setting this parameter allows you to fetch more. | integer

### Example

```php
$request = new \Altapay\Api\Others\FundingList($auth);
```

# Response

```
$response = $request->call();
```

Response is now a object of `\Altapay\Response\FundingsResponse`

| Method  | Description | Type |
|---|---|---|
| `$response->Fundings` | array of `\Altapay\Response\Embeds\Funding` objects | array

### `\Altapay\Response\Embeds\Funding` object

| Method  | Description | Type |
|---|---|---|
| `$object->Filename` | The name of the funding | string
| `$object->ContractIdentifier` | identifier | string
| `$object->Shops` | array of `\Altapay\Response\Embeds\Shop` object | array
| `$object->Acquirer` | Acquirer used | string
| `$object->FundingDate` | Date of funding | DateTime
| `$object->Amount` | Amount of funding | float
| `$object->CreatedDate` | Date created | DateTime
| `$object->DownloadLink` | Download link | string

### `\Altapay\Response\Embeds\Shop` object

| Method  | Description | Type |
|---|---|---|
| `$object->Shop` | The name of the shop | string
