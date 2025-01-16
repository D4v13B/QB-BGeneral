<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use oasis\names\specification\ubl\schema\xsd\CommonBasicComponents_2\Date;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . "/Classes/Db.php";
$config = include_once __DIR__ . '/config.php';

$clientId = $config['client_id'];
$clientSecret = $config['client_secret'];

$db = new Db();
$empresas = $db->getEmpresasActivas();

foreach ($empresas as $empr) {

    $refreshToken = $empr["empr_refresh_token"]; // Obtén el refresh token actual de tu base de datos
    try {
        // Crear el cliente HTTP
        $client = new Client();

        // Endpoint de Intuit para refrescar tokens
        $url = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';

        // Encabezados de la solicitud
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret)
        ];

        // Datos del cuerpo de la solicitud
        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ];

        // Realizar la solicitud POST
        $response = $client->post($url, [
            'headers' => $headers,
            'form_params' => $body
        ]);

        // Decodificar la respuesta JSON
        $data = json_decode($response->getBody(), true);

        // Fecha y hora actual
        $current_datetime = new DateTime();

        // Calcular fechas de expiración
        $access_token_expiration = (clone $current_datetime)->add(new DateInterval('PT' . $data["expires_in"] . 'S'))->format('Y-m-d H:i:s');
        $refresh_token_expiration = (clone $current_datetime)->add(new DateInterval('PT' . $data["x_refresh_token_expires_in"] . 'S'))->format('Y-m-d H:i:s');

        // Extraer los nuevos tokens
        $newAccessToken = $data['access_token'];
        $newRefreshToken = $data['refresh_token'];

        $accessTokenData = [
            'accessToken' => $newAccessToken,
            'tokenType' => "Bearer ",
            'refreshToken' => $refreshToken,
            'accessTokenExpiresAt' => $access_token_expiration,
            'refreshTokenExpiresAt' => $refresh_token_expiration,
            'clientID' => $config["client_id"],
            'clientSecret' => $config["client_secret"],
            'realmID' => $empr["empr_qb_realm_id"],
        ];

        // Convierte el array en un JSON
        $json = json_encode($accessTokenData, JSON_PRETTY_PRINT);

        $db->saveTokens($empr["empr_qb_realm_id"], $newAccessToken, $newRefreshToken, $refresh_token_expiration, $access_token_expiration, $json);

    } catch (RequestException $e) {
        // Manejo de errores
        if ($e->hasResponse()) {
            echo "Error en la respuesta: " . $e->getResponse()->getBody();
        } else {
            echo "Error en la solicitud: " . $e->getMessage();
        }
    }
}
