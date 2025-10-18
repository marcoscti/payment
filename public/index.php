<?php

use App\MercadoPagoPayment;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/MercadoPagoPayment.php';

$app = AppFactory::create();

$app->post('/tokenize', function (Request $request, Response $response) {
    $mp = new MercadoPagoPayment();

    $res = $mp->setTokenizeCard([
        'card_number' => '4509953566233704',
        'expiration_month' => '11',
        'expiration_year' => '2025',
        'security_code' => '123',
        'cardholder' => [
            'name' => 'APRO',
            'identification' => [
                'type' => 'CPF',
                'number' => '19119119100'
            ]
        ]
    ]);
    $response->getBody()->write(json_encode($res));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/credit', function (Request $request, Response $response) {
    $mp = new MercadoPagoPayment();
    $res = $mp->setProcessPaymentCC(
        [
            'transaction_amount' => 1.00,
            'token' => '1d1edeeb9e560c1aaf57ad4eea4907a4',
            'description' => 'Doação HardTale',
            'installments' => 1,
            'payment_method_id' => 'visa',
            'payer' => [
                'email' => 'test_user@example.com'
            ]
        ]
    );
    $response->getBody()->write(json_encode($res));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/pix', function (Request $request, Response $response, $args) {
    $mp = new MercadoPagoPayment();
    $res = $mp->setProccessPix(
        [
            'value' => 1.00,
            'email' => 'test_user@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'cpf' => '19119119100',
        ]
    );
    $response->getBody()->write($res);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/pay/{id}', function (Request $request, Response $response, $args) {
    $mp = new MercadoPagoPayment();
    $res = $mp->getPaymentStatusDirect($args['id']);
    $response->getBody()->write($res);
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/webhook', function (Request $request, Response $response, $args) {
    $mp = new MercadoPagoPayment();

    $response->getBody()->write("OK");
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/', function (Request $request, Response $response, $args) {
    $res = json_encode(['status' => 'API is running']);
    $response->getBody()->write($res);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->run();
