[<](../index.md) Altapay - PHP Api - Terminal
=============================================

# `\Altapay\Response\Embeds\Terminal` object

| Method                | Description                                      | Type |
|-----------------------|--------------------------------------------------|---|
| `$object->Title`      | The title of the terminal                        | string
| `$object->Country`    | The country of the terminal                      | string
| `$object->Natures`    | array of `\Altapay\Response\Embeds\Nature` objects | array
| `$object->Currencies` | array of `\Altapay\Response\Embeds\Currency` objects | array
| `$object->Methods`    | array of `\Altapay\Response\Embeds\Method` objects | array
| `$object->Products`   | array of `\Altapay\Response\Embeds\Product` objects | array
| `$object->PrimaryMethod`   |  | `\Altapay\Response\Embeds\PrimaryMethod`
| `$object->CanUseCredit`   |                                               | boolean
| `$object->CanIssueNewCredit`   |                                          | boolean
| `$object->LogoUrl`   |                                                    | string
| `$object->Description`   |                                                | string

### `\Altapay\Response\Embeds\Nature`

| Method  | Description | Type |
|---|---|---|
| `$object->Nature` | | string

### `\Altapay\Response\Embeds\Method`

| Method           | Description | Type |
|------------------|---|---|
| `$object->Method` | | string

### `\Altapay\Response\Embeds\Product`

| Method            | Description | Type |
|-------------------|---|---|
| `$object->Product` | | string

### `\Altapay\Response\Embeds\PrimaryMethod`

| Method  | Description | Type |
|---|---|---|
| `$object->Nature` | | string
| `$object->Identifier` | | string
| `$object->SupportedAgreementTypes` | | array of `\Altapay\Response\Embeds\AgreementType` objects

### `\Altapay\Response\Embeds\AgreementType`

| Method            | Description | Type |
|-------------------|---|---|
| `$object->AgreementType` | | string

### `\Altapay\Response\Embeds\Currency`

| Method  | Description | Type |
|---|---|---|
| `$object->Currency` | | string
