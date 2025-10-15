<?php
namespace App;
require_once __DIR__ . '/../env.php';
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\CardToken\CardTokenClient as CardTokenCardTokenClient;
use MercadoPago\Client\Payment\PaymentClient;


class MercadoPagoPayment
{
    private $accessToken;

    public function __construct()
    {
        $this->accessToken = getenv('ACCESS_TOKEN', true) ?: getenv('ACCESS_TOKEN');
        // Configurar timezone e locale para Brasil
        date_default_timezone_set('America/Sao_Paulo');

        // Configurações adicionais para evitar warnings
        if (!defined('MP_SKIP_VALIDATION')) {
            define('MP_SKIP_VALIDATION', true);
        }
    }

    public function setProcessPaymentCC($paymentData)
    {
        try {
            // Configura token de acesso
            MercadoPagoConfig::setAccessToken($this->accessToken);
            // Validação básica
            if (empty($paymentData['token'])) {
                return [
                    'success' => false,
                    'error' => 'Token do cartão é obrigatório'
                ];
            }
            // Criar cliente de pagamento
            $clientPayment = new PaymentClient();

            // Criar o pagamento
            $payment = $clientPayment->create([
                "transaction_amount" => (float)$paymentData['transaction_amount'],
                "token" => is_array($paymentData['token'])
                    ? ($paymentData['token']['id'] ?? '')
                    : $paymentData['token'],
                "description" => "Pagamento HardTale",
                "installments" => (int)($paymentData['installments'] ?? 1),
                "payment_method_id" => $paymentData['payment_method_id'] ?? 'visa',
                "payer" => [
                    "email" => $paymentData['payer']['email'] ?? '',
                    "first_name" => $paymentData['payer']['first_name'] ?? '',
                    "last_name" => $paymentData['payer']['last_name'] ?? '',
                    "identification" => [
                        "type" => "CPF",
                        "number" => $paymentData['payer']['identification']['number'] ?? ''
                    ],
                    "phone" => [
                        "area_code" => "11",
                        "number" => preg_replace('/\D/', '', $paymentData['payer']['phone']['number'] ?? '')
                    ]
                ],
                "external_reference" => 'Donate',
                "statement_descriptor" => "HARDTALE",
                "notification_url" => $_ENV['MERCADOPAGO_WEBHOOK_URL'] ?? 'https://hardtale.com.br/api/payment/webhook',
                "binary_mode" => false,
                "capture" => true,
                "additional_info" => [
                    "items" => [[
                        "id" => "hardtale_donate",
                        "title" => "Doação HardTale",
                        "description" => "Pagamento via cartão de crédito",
                        "quantity" => 1,
                        "unit_price" => (float)$paymentData['transaction_amount']
                    ]]
                ]
            ]);

            // Retorna resposta final
            return [
                'success' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status ?? 'unknown',
                'status_detail' => $payment->status_detail ?? '',
                'external_reference' => $payment->external_reference ?? '',
                'message' => 'Pagamento processado com sucesso via SDK oficial'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao processar pagamento: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'method' => 'setProcessPaymentCC',
                    'payment_data' => $paymentData ?? 'N/A',
                ]
            ];
        }
    }
   
    public function setTokenizeCard($cardData)
    {
        try {
            // Configurar token de acesso
            MercadoPagoConfig::setAccessToken($this->accessToken);

            // Validar dados obrigatórios
            $requiredFields = ['card_number', 'expiration_month', 'expiration_year', 'security_code'];
            foreach ($requiredFields as $field) {
                if (empty($cardData[$field])) {
                    return [
                        'success' => false,
                        'error' => "Campo obrigatório não fornecido: {$field}"
                    ];
                }
            }

            // Validar dados do titular
            if (
                empty($cardData['cardholder']['name']) ||
                empty($cardData['cardholder']['identification']['number'])
            ) {
                return [
                    'success' => false,
                    'error' => 'Dados do titular do cartão são obrigatórios'
                ];
            }

            // Criar cliente de tokenização
            $client = new CardTokenCardTokenClient();

            // Criar token via SDK oficial
            $token = $client->create([
                "card_number" => preg_replace('/\D/', '', $cardData['card_number']),
                "expiration_month" => (int)$cardData['expiration_month'],
                "expiration_year" => (int)$cardData['expiration_year'],
                "security_code" => $cardData['security_code'],
                "cardholder" => [
                    "name" => trim($cardData['cardholder']['name']),
                    "identification" => [
                        "type" => $cardData['cardholder']['identification']['type'] ?? "CPF",
                        "number" => preg_replace('/\D/', '', $cardData['cardholder']['identification']['number'])
                    ]
                ]
            ]);

            // Retornar resposta formatada
            return [
                'success' => true,
                'token_id' => $token->id,
                'message' => 'Token do cartão criado com sucesso',
                'debug_info' => [
                    'card_last_digits' => substr($cardData['card_number'], -4),
                    'expiration' => sprintf('%02d/%d', $cardData['expiration_month'], $cardData['expiration_year']),
                    'cardholder_name' => $cardData['cardholder']['name']
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao criar token: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'method' => 'setTokenizeCard',
                    'card_data' => $cardData ?? 'N/A'
                ]
            ];
        }
    }

    public function setProccessPix($dados)
    {
        try {
            // Preparar dados do pagamento PIX
            $paymentData = [
                'transaction_amount' => (float)$dados['value'],
                'description' => 'Doação PIX Hardtale',
                'payment_method_id' => 'pix',
                'external_reference' => "Donate",
                'notification_url' => 'https://hardtale.com.br/api/payment/webhook',
                'statement_descriptor' => 'Hardtale',
                'binary_mode' => true
            ];
            // Configurar pagador
            if ($dados) {
                $paymentData['payer'] = [
                    'email' => $dados['email'],
                    'first_name' => $dados['first_name'],
                    'last_name' => $dados['last_name'],
                    'identification' => [
                        'type' => 'CPF',
                        'number' => $dados['cpf']
                    ]
                ];
            }

            // Fazer requisição direta para a API do MercadoPago
            $url = 'https://api.mercadopago.com/v1/payments';
            $headers = [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'User-Agent: *',
                'X-Idempotency-Key: ' . uniqid('pix_', true)
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("Erro cURL: " . $error);
            }

            $responseData = json_decode($response, true);

            if ($httpCode !== 201 && $httpCode !== 200) {
                $errorMsg = isset($responseData['message']) ? $responseData['message'] : 'Erro desconhecido';
                $errorDetail = isset($responseData['error']) ? $responseData['error'] : '';
                throw new \Exception("API retornou HTTP $httpCode: $errorMsg $errorDetail");
            }

            if (!$responseData || !isset($responseData['id'])) {
                throw new \Exception("Resposta inválida da API: " . $response);
            }

            // Pagamento PIX criado com sucesso
            $paymentId = $responseData['id'];
            $status = $responseData['status'];


            // Extrair dados do PIX
            $qrCode = '';
            $qrCodeBase64 = '';

            if (
                isset($responseData['point_of_interaction']) &&
                isset($responseData['point_of_interaction']['transaction_data'])
            ) {
                $qrCode = $responseData['point_of_interaction']['transaction_data']['qr_code'] ?? '';
                $qrCodeBase64 = $responseData['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';
            }

            return json_encode([
                'success' => true,
                'payment_id' => $paymentId,
                'status' => $status,
                'status_detail' => $responseData['status_detail'] ?? 'pending',
                'qr_code' => $qrCode,
                'qr_code_base64' => $qrCodeBase64,
                'external_reference' => $responseData['external_reference'],
                'payer_info' => [
                    'email' => $responseData['binary_mode']['payer']['email'] ?? '',
                    'name' => (string)($responseData['binary_mode']['payer']['first_name'] ?? '') . ' ' . (string)($responseData['payer']['last_name'] ?? '')
                ]
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'method' => 'setProccessPix'
                ]
            ]);
        }
    }
    
    public function getPaymentStatusDirect($paymentId)
    {
        try {
            // Fazer requisição direta para a API do MercadoPago
            $url = "https://api.mercadopago.com/v1/payments/$paymentId";
            $headers = [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'User-Agent: *'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("Erro cURL: " . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception("API retornou HTTP $httpCode");
            }

            $responseData = json_decode($response, true);

            if (!$responseData || !isset($responseData['id'])) {
                throw new \Exception("Resposta inválida da API");
            }

            return json_encode([
                'success' => true,
                'payment_id' => $responseData['id'],
                'status' => $responseData['status'],
                'value'=>(float)($responseData['transaction_amount'] ?? 0),
                'status_detail' => $responseData['status_detail'] ?? '',
                'external_reference' => $responseData['external_reference'] ?? ''
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
