[<](../index.md) Altapay - PHP Api - Transaction
================================================

# `\Altapay\Response\Embeds\Transaction` object

| Method  | Description | Type |
|---|---|---|
| `$object->TransactionId` | | string
| `$object->PaymentId` | | string
| `$object->CardStatus` | | array
| `$object->CreditCardExpiry` | | `\Altapay\Response\Embeds\CreditCard`
| `$object->CreditCardToken` | | string
| `$object->CreditCardMaskedPan` | | string
| `$object->IsTokenized` | | boolean
| `$object->ThreeDSecureResult` | | string
| `$object->LiableForChargeback` | | string
| `$object->CVVCheckResult` | | string
| `$object->BlacklistToken` | | string
| `$object->ShopOrderId` | | string
| `$object->Shop` | | string
| `$object->Terminal` | | string
| `$object->TransactionStatus` | | string
| `$object->ReasonCode` | | string
| `$object->MerchantCurrency` | | string
| `$object->MerchantCurrencyAlpha` | | string
| `$object->CardHolderCurrency` | | string
| `$object->CardHolderCurrencyAlpha` | | string
| `$object->AuthType` | | string
| `$object->ReservedAmount` | | float
| `$object->CapturedAmount` | | float
| `$object->RefundedAmount` | | float
| `$object->RecurringDefaultAmount` | | float
| `$object->CreditedAmount` | | float
| `$object->SurchargeAmount` | | float
| `$object->CreatedDate` | | DateTime
| `$object->UpdatedDate` | | DateTime
| `$object->PaymentNature` | | string
| `$object->PaymentSchemeName` | | string
| `$object->PaymentSource` | | string
| `$object->PaymentNatureService` | array of `\Altapay\Response\Embeds\PaymentNatureService` objects | array
| `$object->FraudRiskScore` | | float
| `$object->FraudExplanation` | | string
| `$object->FraudRecommendation` | | string
| `$object->ChargebackEvents` | array of `\Altapay\Response\Embeds\ChargebackEvent` objects | array
| `$object->PaymentInfos` | array of `\Altapay\Response\Embeds\PaymentInfo` objects | array
| `$object->CustomerInfo` | | `\Altapay\Response\Embeds\CustomerInfo`
| `$object->ReconciliationIdentifiers` | array of `\Altapay\Response\Embeds\ReconciliationIdentifier` objects | array

### `\Altapay\Response\Embeds\CreditCard`

@todo

### `\Altapay\Response\Embeds\PaymentNatureService`

| Method  | Description | Type |
|---|---|---|
| `$object->Name` | | string
| `$object->SupportsRefunds` | | boolean
| `$object->SupportsRelease` | | boolean
| `$object->SupportsMultipleCaptures` | | boolean
| `$object->SupportsMultipleRefunds` | | boolean

### `\Altapay\Response\Embeds\ChargebackEvent`

@todo

### `\Altapay\Response\Embeds\PaymentInfo`

| Method  | Description | Type |
|---|---|---|
| `$object->name` | | string
| `$object->PaymentInfo` | | string

### `\Altapay\Response\Embeds\CustomerInfo`

| Method  | Description | Type |
|---|---|---|
| `$object->UserAgent` | | string
| `$object->IpAddress` | | string
| `$object->Email` | | string
| `$object->Username` | | string
| `$object->CustomerPhone` | | string
| `$object->OrganisationNumber` | | string
| `$object->CountryOfOrigin` | | `\Altapay\Response\Embeds\Country` object
| `$object->BillingAddress` | | `\Altapay\Response\Embeds\Address` object
| `$object->ShippingAddress` | | `\Altapay\Response\Embeds\Address` object
| `$object->RegisteredAddress` | | `\Altapay\Response\Embeds\Address` object

### `\Altapay\Response\Embeds\ReconciliationIdentifier`

| Method  | Description | Type |
|---|---|---|
| `$object->Id` | | string
| `$object->Amount` | | float
| `$object->currency` | | string
| `$object->AmountCurrency` | | string
| `$object->Type` | | string
| `$object->Date` | | DateTime

### `\Altapay\Response\Embeds\Country`

| Method  | Description | Type |
|---|---|---|
| `$object->Country` | | string
| `$object->Source` | | string

### `\Altapay\Response\Embeds\Address`

| Method  | Description | Type |
|---|---|---|
| `$object->Firstname` | | string
| `$object->Lastname` | | string
| `$object->Address` | | string
| `$object->City` | | string
| `$object->PostalCode` | | string
| `$object->Region` | | string
| `$object->Country` | | string
