# 💳 Vinti4PayClient — Integração de Pagamentos Vinti4Net - SISP (PHP)

> **Nome:** $vinti4pay-php$
> **Versão:** $1.0.0$
> **Autor:** Eril TS Carvalho
> **Linguagem:** PHP 8.1+
> **Descrição:** Cliente standalone para integração com o gateway de pagamentos **Vinti4 (SISP Cabo Verde)**.
> Suporta **compras**, **pagamentos de serviços**, **recargas** e **reembolsos**, com **validação criptográfica (fingerprint)**.

---

## 🧭 Sumário

- [💳 Vinti4PayClient — Integração de Pagamentos Vinti4Net - SISP (PHP)](#-vinti4payclient--integração-de-pagamentos-vinti4net---sisp-php)
  - [🧭 Sumário](#-sumário)
  - [🌍 Visão Geral](#-visão-geral)
  - [⚙️ Requisitos](#️-requisitos)
  - [📦 Instalação](#-instalação)
  - [🔄 Fluxo Geral de Integração](#-fluxo-geral-de-integração)
  - [💡 Exemplo Completo](#-exemplo-completo)
    - [callback.php](#callbackphp)
  - [📘 Documentação da Classe](#-documentação-da-classe)
    - [🔹 Construtor](#-construtor)
    - [🔹 `setRequestParams()`](#-setrequestparams)
    - [🔹 `preparePurchase()`](#-preparepurchase)
      - [Estrutura de `$billing`:](#estrutura-de-billing)
    - [🔹 `prepareServicePayment()`](#-prepareservicepayment)
    - [🔹 `prepareRecharge()`](#-preparerecharge)
    - [🔹 `prepareRefund()`](#-preparerefund)
    - [🔹 `createPaymentForm()`](#-createpaymentform)
    - [🔹 `processResponse()`](#-processresponse)
  - [🧩 Constantes Importantes](#-constantes-importantes)
  - [⚠️ Erros Comuns](#️-erros-comuns)
  - [🧠 Boas Práticas](#-boas-práticas)
  - [📄 Licença](#-licença)

---

## 🌍 Visão Geral

A classe `Vinti4PayClient` encapsula o processo de comunicação com o gateway de pagamentos **Vinti4** (mantido pela SISP, Cabo Verde).
Ela permite que comerciantes criem pagamentos online de forma **segura e validada criptograficamente** com **fingerprints SHA-512**.

Suporta os seguintes tipos de operação:

| Tipo de Transação    | Constante                          | Descrição                     |
| -------------------- | ---------------------------------- | ----------------------------- |
| Compra               | `TRANSACTION_TYPE_PURCHASE`        | Pagamento comum com cartão    |
| Pagamento de Serviço | `TRANSACTION_TYPE_SERVICE_PAYMENT` | Ex: água, luz, etc.           |
| Recarga              | `TRANSACTION_TYPE_RECHARGE`        | Ex: carregamento de telemóvel |
| Reembolso            | `TRANSACTION_TYPE_REFUND`          | Devolução de valor ao cliente |

---

## ⚙️ Requisitos

* PHP **8.1+**
* Extensão `bcmath`
* Extensão `openssl`
* Servidor HTTPS (obrigatório para produção)

---

## 📦 Instalação

Basta incluir a classe no seu projeto:

```php
require_once 'Vinti4PayClient.php';
```

> 💡 Caso use Composer, você pode incluir o arquivo via autoload.

---

## 🔄 Fluxo Geral de Integração

1. **Inicie o cliente:**

   ```php
   $client = new Vinti4PayClient($posID, $posAuthCode);
   ```

2. **Prepare a transação:**

   ```php
   $client->preparePurchase(1500.00, $billingData, 'ORDER123');
   ```

3. **Gere o formulário HTML de redirecionamento:**

   ```php
   echo $client->createPaymentForm('https://seusite.com/callback.php');
   ```

4. **Receba e processe o retorno:**

   ```php
   $result = $client->processResponse($_POST);
   ```

5. **Valide o resultado:**

   ```php
   if ($result['success']) {
       // Pagamento aprovado
   } else {
       // Erro, cancelamento ou fraude
   }
   ```

---

## 💡 Exemplo Completo

```php
<?php
require_once 'Vinti4PayClient.php';

$posID = '123456';
$posAuthCode = 'SEU_AUTH_CODE';

// Inicializa o cliente
$vinti4 = new Vinti4PayClient($posID, $posAuthCode);

// Dados de faturamento
$billing = [
    'billAddrCountry' => 'CV',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Av. Cidade Lisboa',
    'billAddrPostCode' => '7600',
    'email' => 'cliente@exemplo.cv'
];

// Prepara a compra
$vinti4->preparePurchase(2500.00, $billing, 'ORDER-2025-001');

// Define parâmetros opcionais
$vinti4->setRequestParams([
    'currency' => 'CVE',
    'languageMessages' => 'pt'
]);

// Gera e envia o formulário
echo $vinti4->createPaymentForm('https://meusite.cv/retorno.php');
```

### callback.php

```php
$vinti4 = new Vinti4PayClient($posID, $posAuthCode);
$result = $vinti4->processResponse($_POST);

if ($result['success']) {
    echo "Pagamento confirmado!";
} else {
    echo "Falha: " . $result['message'];
}
```

---

## 📘 Documentação da Classe

---

### 🔹 Construtor

```php
__construct(string $posID, string $posAuthCode, ?string $endpoint = null)
```

**Descrição:**
Inicializa o cliente com as credenciais fornecidas pelo **SISP**.

| Parâmetro      | Tipo   | Descrição                                   |                                                                                   |
| -------------- | ------ | ------------------------------------------- | --------------------------------------------------------------------------------- |
| `$posID`       | string | Identificador do POS fornecido pelo Vinti4. |                                                                                   |
| `$posAuthCode` | string | Código de autenticação do POS.              |                                                                                   |
| `$endpoint`    | string | null                                        | Endpoint do gateway. Padrão: `https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment` |

---

### 🔹 `setRequestParams()`

Define parâmetros opcionais da requisição antes de criar o formulário.

```php
setRequestParams(array $params): static
```

**Parâmetros permitidos:**

| Parâmetro          | Tipo       | Descrição                                                                    |
| ------------------ | ---------- | ---------------------------------------------------------------------------- |
| `currency`         | string|int | Código ISO numérico (ex: `132` para CVE) ou alfabético (`CVE`, `USD`, `EUR`) |
| `languageMessages` | string     | Idioma das mensagens retornadas (`pt`, `en`, etc.)                           |
| `entityCode`       | string     | Código da entidade (para pagamentos de serviço ou recarga)                   |
| `referenceNumber`  | string     | Referência do pagamento                                                      |
| `merchantRef`      | string     | Código interno da transação                                                  |
| `merchantSession`  | string     | Sessão única da transação                                                    |
| `addrMatch`        | string     | `'Y'` se o endereço de entrega for igual ao de faturamento                   |
| `purchaseRequest`  | string     | JSON codificado da compra, normalmente gerado internamente                   |
| `user`             | string     | Identificador interno do utilizador                                          |

---

### 🔹 `preparePurchase()`

Prepara uma **transação de compra (Purchase)**.

```php
preparePurchase(float|string $amount, array $billing, ?string $merchantRef = null, ?string $merchantSession = null): static
```

| Parâmetro          | Tipo         | Descrição                          |                            |
| ------------------ | ------------ | ---------------------------------- | -------------------------- |
| `$amount`          | float|string | Valor da compra.                   |                            |
| `$billing`         | array        | Dados de faturamento obrigatórios. |                            |
| `$merchantRef`     | string       | null                               | Referência do comerciante. |
| `$merchantSession` | string       | null                               | Sessão da transação.       |

#### Estrutura de `$billing`:

| Campo                                                                  | Obrigatório | Descrição                                  |
| ---------------------------------------------------------------------- | ----------- | ------------------------------------------ |
| `billAddrCountry`                                                      | ✅           | País (ex: `CV`)                            |
| `billAddrCity`                                                         | ✅           | Cidade                                     |
| `billAddrLine1`                                                        | ✅           | Endereço                                   |
| `billAddrPostCode`                                                     | ✅           | Código postal                              |
| `email`                                                                | ✅           | E-mail do cliente                          |
| `addrMatch`                                                            | ❌           | `'Y'` se endereço de entrega = faturamento |
| `shipAddrCountry`, `shipAddrCity`, `shipAddrLine1`, `shipAddrPostCode` | ❌           | Endereço de entrega (opcional)             |

---

### 🔹 `prepareServicePayment()`

```php
prepareServicePayment(float|string $amount, string $entity, string $reference, ?string $merchantRef = null, ?string $merchantSession = null): static
```

Prepara um **pagamento de serviço** (Ex: contas de luz, água, etc.)

---

### 🔹 `prepareRecharge()`

```php
prepareRecharge(float|string $amount, string $entity, string $number, ?string $merchantRef = null, ?string $merchantSession = null): static
```

Prepara uma **recarga** (Ex: telemóvel).

---

### 🔹 `prepareRefund()`

```php
prepareRefund(float|string $amount, string $merchantRef, string $merchantSession, string $transactionID, string $clearingPeriod): static
```

Prepara uma operação de **reembolso**.

---

### 🔹 `createPaymentForm()`

```php
createPaymentForm(string $responseUrl, string $redirectMessage = "Processando o pagamento..."): string
```

Gera o **HTML de redirecionamento automático** para o gateway.

---

### 🔹 `processResponse()`

```php
processResponse(array $postData): array
```

Processa e valida a resposta enviada pelo **Vinti4** após o pagamento.

Verifica:

* Cancelamento pelo usuário
* Tipo de transação (pagamento/refund)
* Cálculo do fingerprint
* Verificação de DCC
* Retorno de mensagens e erros

**Retorno:**
Array com:

```php
[
  'status' => 'SUCCESS|CANCELLED|INVALID_FINGERPRINT|ERROR',
  'message' => '...',
  'success' => true|false,
  'data' => [...],
  'dcc' => [...],
  'debug' => [...],
  'detail' => '...'
]
```

---

## 🧩 Constantes Importantes

| Constante                          | Valor                                                | Descrição                     |
| ---------------------------------- | ---------------------------------------------------- | ----------------------------- |
| `DEFAULT_ENDPOINT`                 | `https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment` | Endpoint padrão do gateway    |
| `TRANSACTION_TYPE_PURCHASE`        | `'1'`                                                | Compra                        |
| `TRANSACTION_TYPE_SERVICE_PAYMENT` | `'2'`                                                | Pagamento de serviço          |
| `TRANSACTION_TYPE_RECHARGE`        | `'3'`                                                | Recarga                       |
| `TRANSACTION_TYPE_REFUND`          | `'4'`                                                | Reembolso                     |
| `CURRENCY_CVE`                     | `'132'`                                              | Código CVE                    |
| `SUCCESS_MESSAGE_TYPES`            | `['8', '10', 'P', 'M']`                              | Mensagens que indicam sucesso |

---

## ⚠️ Erros Comuns

| Situação                                            | Causa                                                  | Solução                                                                  |
| --------------------------------------------------- | ------------------------------------------------------ | ------------------------------------------------------------------------ |
| `Já existe uma transação configurada neste objeto.` | Você chamou `preparePurchase()` duas vezes sem resetar | Crie nova instância para cada transação                                  |
| `Invalid currency code`                             | Código de moeda inválido                               | Use sigla ISO (`CVE`, `EUR`, etc.)                                       |
| `INVALID_FINGERPRINT`                               | Fingerprint divergente                                 | Verifique se `$posAuthCode` é o mesmo do ambiente (produção/homologação) |

---

## 🧠 Boas Práticas

* Gere sempre novas referências (`merchantRef`, `merchantSession`) por transação.
* Use HTTPS no `responseUrl`.
* Armazene logs de retorno (`$_POST`) para auditoria.
* Nunca exponha o `posAuthCode` em JavaScript ou cliente final.

---

## 📄 Licença

Código livre para uso interno ou comercial.
Distribuído sob a licença **MIT**.
