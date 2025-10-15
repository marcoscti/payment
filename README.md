# Payment API (MercadoPago) - Projeto local

Este projeto fornece uma API minimalista para realizar operações de pagamentos usando o SDK oficial do MercadoPago e chamadas diretas HTTP quando necessário. Está estruturado em PHP usando Slim Framework (v4).

## Visão geral

- Rotas principais (definidas em `index.php`):
  - `POST /tokenize` — tokeniza um cartão de crédito (usa SDK MercadoPago).
  - `POST /credit` — processa pagamento com cartão de crédito usando token.
  - `POST /pix` — cria pagamento via PIX (chamada direta à API do MercadoPago).
  - `GET /pay/{id}` — consulta status de um pagamento pelo `id`.
  - `GET /` — verificação simples (retorna `{ "status": "API is running" }`).

## Estrutura relevante

- `index.php`: arquivo de entrada; registra rotas e inicia a aplicação Slim.
- `app/MercadoPagoPayment.php`: classe `App\MercadoPagoPayment` que encapsula a integração com o MercadoPago (tokenização, pagamento por cartão, PIX e consulta de status).
- `env.php` / `env-model.php`: pequenas helpers que chamam `putenv()` com variáveis de exemplo.
- `composer.json`: dependências do projeto (`slim/slim`, `slim/psr7`, `mercadopago/dx-php`).

## Variáveis de ambiente

O projeto lê variáveis de ambiente com `getenv()`; as principais são:

- `ACCESS_TOKEN` — token de acesso da sua conta MercadoPago (string). Obrigatório para chamadas autenticadas.
- `BASE_PATH` — base path usado pelo Slim (por padrão `payment` nos exemplos). Configure conforme seu ambiente (por exemplo, quando rodar em um subdiretório).

Exemplo local rápido (opcional): criar um arquivo `env.php` com:

```php
<?php
putenv("ACCESS_TOKEN=APP_USR-xxxxxxxxxxxxxxxxxxxxxxxx");
putenv("BASE_PATH=payment");
```

> Observação: não deixe tokens reais em repositórios públicos. Use `.env` não comitado ou variáveis de ambiente do sistema.

## Como rodar localmente (Laragon / PHP)

1. Instale dependências via Composer (se ainda não instaladas):

```powershell
composer install
```

2. Configure suas variáveis de ambiente (via `env.php` ou exportando no sistema).

3. Inicie o servidor embutido do PHP (exemplo com base path `payment`):

```powershell
php -S localhost:8000 -t public
```

No cenário do Laragon, a aplicação pode ficar disponível em `http://localhost/payment/` se configurado como virtual host ou via base path.

Observação: Este repositório presume que `index.php` está no root e que as requisições chegam diretamente a ele. Se usar um diretório `public/`, ajuste o `-t` e o `BASE_PATH` conforme necessário.

## Endpoints e exemplos

1) Tokenizar cartão

- Endpoint: `POST /tokenize`
- Body (JSON):

```json
{
  "card_number": "4509953566233704",
  "expiration_month": "11",
  "expiration_year": "2025",
  "security_code": "123",
  "cardholder": {
    "name": "APRO",
    "identification": { "type": "CPF", "number": "19119119100" }
  }
}
```

Resposta (exemplo): JSON com `success`, `token_id` ou `error`.

2) Pagamento com cartão (crédito)

- Endpoint: `POST /credit`
- Body (JSON):

```json
{
  "transaction_amount": 1.00,
  "token": "<token_id_retornado>",
  "description": "Doação HardTale",
  "installments": 1,
  "payment_method_id": "visa",
  "payer": { "email": "test_user@example.com" }
}
```

Resposta: JSON com `success`, `payment_id`, `status` e `status_detail` ou `error`.

3) Criar pagamento PIX

- Endpoint: `POST /pix`
- Body (JSON):

```json
{
  "value": 1.00,
  "email": "test_user@example.com",
  "first_name": "Test",
  "last_name": "User",
  "cpf": "19119119100"
}
```

Resposta: JSON com `success`, `payment_id`, `status`, `qr_code` e `qr_code_base64` quando disponível.

4) Consultar status de pagamento

- Endpoint: `GET /pay/{id}`
- Exemplo: `GET /pay/123456789`
- Resposta: JSON com `success`, `payment_id`, `status` e `status_detail`.

## Comportamento interno importante

- A classe `MercadoPagoPayment` usa o SDK oficial (`mercadopago/dx-php`) para tokenização e pagamentos com cartão via `PaymentClient` e `CardTokenClient`.
- Para PIX e verificações de status ela faz chamadas HTTP diretas (`curl`) para a API do MercadoPago.
- Erros são capturados e retornados em JSON com campos `success: false` e `error` (e, às vezes, `debug`).

## Segurança e produção

- Nunca exponha `ACCESS_TOKEN` em repositórios públicos.
- Use HTTPS em produção.
- Considere validar e sanitizar mais fortemente os dados de entrada antes de enviar ao MercadoPago.
- Use idempotency keys (já gerado para PIX via `X-Idempotency-Key`) para evitar cobranças duplicadas.

## Testes rápidos

- Use `curl` ou um cliente como Postman/Insomnia para enviar requisições aos endpoints acima.
- Verifique os logs do PHP e respostas JSON para depurar.

## Próximos passos sugeridos

- Adicionar autenticação às rotas internas (API key ou JWT).
- Mover configuração para um `.env` (ex.: usar `vlucas/phpdotenv`).
- Adicionar testes automatizados (PHPUnit) cobrindo tokenização e criação de pagamentos (mockando API externa).

---

Se quiser, posso ajustar o README com instruções específicas do Laragon, exemplos curl formatados, ou adicionar um `.env.example` e um pequeno script de testes. Diga o que prefere.
