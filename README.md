# Vinti4Pay PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/erilshk/vinti4pay-php)](https://packagist.org/packages/erilshk/vinti4pay-php) [![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF)](https://www.php.net/) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)  [![GitHub issues](https://img.shields.io/github/issues/erilshackle/vinti4pay-php)](https://github.com/erilshackle/vinti4pay-php/issues) 

---

## 🚀 Apresentação

**Vinti4Pay PHP SDK** é uma biblioteca completa para integração com a plataforma **Vinti4Net** de Cabo Verde (SISP).  

Ela permite aos desenvolvedores:

- Criar **transações de compra**, pagamento de serviços, recargas e estornos (refunds).  
- Gerar **formulários HTML prontos para submissão**, incluindo suporte a **3DSecure** e **DCC (Dynamic Currency Conversion)**.  
- Processar e validar respostas do servidor, com **fingerprint automático** para segurança.  
- Funcionar tanto via **Composer** quanto de forma **standalone**, sem dependências externas.

O SDK é ideal para lojas, portais de serviços e sistemas financeiros que precisam integrar pagamentos com a plataforma Vinti4Net de forma segura e rápida.

---

## 📦 Instalação

### Via Composer (recomendado)

```bash
composer require erilshk/vinti4pay-php
```

### Standalone (sem composer)

Para quem não utiliza Composer, pode baixar a classe standalone **Vinti4Pay**:
> Essa classe possui mesma _interface_ da lib, tendo limitações principalmente em relacção ao _processamento da resposta_ de retorno. 

* Inclua o arquivo `Vinti4PayClient.php` e as classes dependentes manualmente.
* A estrutura e os métodos são praticamente **idênticos** aos do Composer, mantendo compatibilidade com todos os exemplos abaixo.

_Exemplo de inclusão manual:_
```php
require 'path/to/Vinti4PayClient.php';

$vinti4 = new Vinti4PayClient('SEU_POS_ID', 'SEU_POS_AUTHCODE');
```

[Download Vinti4Pay]()

---

## ⚙ Requisitos

| Requisito     | Versão / Detalhes      |
| ------------- | ---------------------- |
| PHP           | >= 8.0                 |
| Extensão JSON | (**ext-json**) Ativada |
| Extensão Hash | (**ext-hash)** Ativada |
| Composer      | Opcional (recomendado) |

---

## 🔑 Configuração

```php
use Erilshk\Vinti4Pay\Vinti4PayClient;

$vinti4 = new Vinti4PayClient(
    'SEU_POS_ID',       // POS ID fornecido pelo Vinti4Net
    'SEU_POS_AUTHCODE', // Código de autorização POS
    'https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment' // Opcional: endpoint customizado
);
```

---

## 🛒 Criando transações

### Compra (Purchase)

```php
$billing = [
    'email' => 'cliente@email.com',
    'billAddrCountry' => '132',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Av. Principal 10',
    'billAddrPostCode' => '7600',
]; // PurchaseRequest

$vinti4->preparePurchase(1500.00, $billing)
       ->setRequestParams(['currency' => 'CVE']);
       // converte CVE -> 132, assim como USD, EUR, BRL, etc.

echo $vinti4->createPaymentForm('https://meusite.cv/vinti4/callback');
```

### Pagamento de serviços

```php
$vinti4->prepareServicePayment(500.00, entity: '7', reference: '123456');

echo $vinti4->createPaymentForm('https://meusite.cv/vinti4/callback');
```

### Recarga

```php
$vinti4->prepareRecharge(300.00, entity: '7', number: '987654');

echo $vinti4->createPaymentForm('https://meusite.cv/vinti4/callback');
```

### Estorno (Refund)

```php
// É importante você salvar essas informações após uma transação 
// a fim de recuperar esses parametros e pode efectuar o estorno

$vinti4->prepareRefund(
    150.00,
    'MERCHANT_REF',
    'MERCHANT_SESSION',
    'TRANSACTION_ID',
    'CLEARING_PERIOD'
);

echo $vinti4->createPaymentForm('https://meusite.cv/vinti4/callback');
```

---

## 📥 Processando respostas

```php
$response = $vinti4->processResponse($_POST);

$response->onSuccess(function($r) {
    echo "Transação aprovada!";
});

$response->onError(function($r) {
    echo "Erro: " . $r->message;
});

$response->onCancel(function($r) {
    echo "Pagamento cancelado pelo usuário.";
});
```

O objeto `$response` inclui:

* **`success`**: `bool` se houve sucesso válido ou não na transação.
* **`status`**: `SUCCESS`, `ERROR`, `CANCELLED`, `INVALID_FINGERPRINT`
* **`message`**: mensagem detalhada
* **`data`**: dados originais da transação `$_POST`
* **`dcc`**: dados de conversão de moeda (quando aplicável)

> **obs:** Para a classe standalone, `processResponse()` não retorna um <s>_object_</s>, mas sim um **array** com as mesmas chaves da _$response_. Use uma condicional sobre o `status` ou `success` para verificar a transação.
> Ou use uma funcão auxiliar acoplada à classe: `onVinti4TransactionResult(success, error)`

---

## 🔐 Segurança

* Fingerprint automático para cada transação e estorno
* Validação contra fraudes
* Suporte a 3DSecure para pagamentos online

---

## 📝 Documentação

* A biblioteca possui **PHPDoc completo** para todos os métodos.
* Consulte os exemplos na pasta `examples/` do repositório para integração rápida.
* Suporte à linguagem de mensagens em **Português (`pt`)** ou **Inglês (`en`)**.

---

## 🛠 Testes

```bash
# run test
composer install
vendor/bin/phpunit
```

### Confiabilidade
 ![CI](https://github.com/erilshackle/vinti4pay-php/actions/workflows/ci.yml/badge.svg) [![Coverage](https://codecov.io/gh/erilshackle/vinti4pay-php/branch/main/graph/badge.svg)](https://codecov.io/gh/erilshackle/vinti4pay-php)  ![Packagist Downloads](https://img.shields.io/packagist/dt/erilshk/vinti4pay-php)

---

## 📄 Licença

MIT License – [LICENSE](LICENSE)

---

## 🔗 Links

* Página do projeto: [GitHub](https://github.com/erilshackle/vinti4pay-php)
* Packagist: [erilshk/vinti4pay-php](https://packagist.org/packages/erilshk/vinti4pay-php)
* Issues / suporte: [GitHub Issues](https://github.com/erilshackle/vinti4pay-php/issues)

---
