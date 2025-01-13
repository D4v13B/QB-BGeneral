<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class HttpClient
{
   private string $baseUrl;
   private Client $client;

   public function __construct()
   {
      $config = require "config.php";
      $this->baseUrl = $config["apiBaseUrl"];

      // Crear una instancia de Guzzle Client con la URL base
      $this->client = new Client([
         'base_uri' => $this->baseUrl,
         'timeout'  => 120.0,  // Configurar un tiempo de espera (opcional)
      ]);
   }

   /**
    * Método para autenticar en la API.
    *
    * @param string $clientId
    * @param string $clientSecret
    * @return string|null Retorna el token de autenticación o null en caso de error.
    */
   public function autenticar(string $clientId, string $clientSecret): ?string
   {
      $url = 'autenticacion/autenticar';

      try {
         $response = $this->client->get($url, [
            'headers' => [
               'Content-Type' => 'application/json',
               'accept' => 'application/json',
               'x-ibm-client-id' => $clientId,
               'x-ibm-client-secret' => $clientSecret
            ]
         ]);

         // Decodificar la respuesta JSON
         $data = json_decode($response->getBody()->getContents(), true);

         // Retornar el token de autenticación si existe
         return $data['Token'] ?? null;
      } catch (RequestException $e) {
         // Manejar el error y mostrar el mensaje de respuesta
         if ($e->hasResponse()) {
            print_r($e);
            $errorBody = $e->getResponse()->getBody()->getContents();
            echo "Error en la respuesta de la API: " . $errorBody;
         } else {
            echo "Error de solicitud: " . $e->getMessage();
         }
         return null;
      }
   }

   /**
    * Método para realizar una petición GET
    * 
    * @param string $endpoint
    * @param array $queryParams
    * @return array|null
    */
   public function get(string $endpoint, array $queryParams = []): ?array
   {
      try {
         $response = $this->client->request('GET', $endpoint, [
            'query' => $queryParams
         ]);

         return json_decode($response->getBody()->getContents(), true);
      } catch (RequestException $e) {
         // Manejo de errores
         echo 'Error en la petición GET: ' . $e->getMessage();
         return null;
      }
   }

   /**
    * Realiza una solicitud POST
    *
    * @param string $endpoint
    * @param array $data
    * @param array $headers
    * @return string
    */
   public function post(string $endpoint, array $data, array $headers = []): string
   {
      try {
         $response = $this->client->post($this->baseUrl . $endpoint, [
            'json' => $data,
            'headers' => $headers
         ]);
         return $response->getBody()->getContents();
      } catch (RequestException $e) {
         if ($e->hasResponse()) {
            return $e->getResponse()->getBody()->getContents();
         } else {
            return "Error de solicitud: " . $e->getMessage();
         }
      }
   }

   /**
    * Método para realizar una petición PUT
    * 
    * @param string $endpoint
    * @param array $data
    * @return array|null
    */
   public function put(string $endpoint, array $data = []): ?array
   {
      try {
         $response = $this->client->request('PUT', $endpoint, [
            'json' => $data
         ]);

         return json_decode($response->getBody()->getContents(), true);
      } catch (RequestException $e) {
         echo 'Error en la petición PUT: ' . $e->getMessage();
         return null;
      }
   }

   /**
    * Método para realizar una petición DELETE
    * 
    * @param string $endpoint
    * @return bool
    */
   public function delete(string $endpoint): bool
   {
      try {
         $this->client->request('DELETE', $endpoint);
         return true;
      } catch (RequestException $e) {
         echo 'Error en la petición DELETE: ' . $e->getMessage();
         return false;
      }
   }
}
