<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

require __DIR__ . '/vendor/autoload.php';
include __DIR__ . "/Classes/Db.php";
include __DIR__ . "/Classes/HttpClient.php";
$config = include __DIR__ . '/config.php';

$db = new Db();
$httpClient = new HttpClient();
$empresas = $db->getEmpresasActivas();

foreach ($empresas as $empr) {

   if (!$empr["empr_access_token"] or !$empr["accessTokenObj"]) {
      break;
   }

   //Dia que se va a leer los BillPayments

   $date = date("Y-m-d", strtotime("-1 month"));

   $realmID = $empr["empr_qb_realm_id"];
   $client = new Client();
   $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/text',
      'Authorization' => 'Bearer ' . $empr["empr_access_token"]
   ];
   $body = "SELECT * FROM BillPayment WHERE TxnDate >= '2024-01-08'"; // Consulta SQL
   $url = "https://quickbooks.api.intuit.com/v3/company/" . $empr["empr_qb_realm_id"] . "/query?minorversion=73";

   try {
      // Crear la solicitud
      $request = new Request('POST', $url, $headers, $body);

      // Enviar la solicitud
      $response = $client->send($request);

      // Obtener y procesar el cuerpo de la respuesta
      $payments = json_decode($response->getBody(), true);

      $paymentsDb = $db->getProcessedPayments($realmID); // Pagos procesados en la base de datos

      // Se extraen los IDs de los pagos ya almacenados en la base de datos
      $dbPaymentsId = array_column($paymentsDb, "trpr_qb_id");

      // Se filtran los pagos de la API para encontrar aquellos que no están en la base de datos
      $unsavePayment = array_filter($payments, function ($pay) use ($dbPaymentsId) {
         return !in_array($pay->Id, $dbPaymentsId);
      });

      /**
       * Guardar los pagos no guardados en la base de datos
       */
      if (!empty($unsavePayment)) {
         echo $db->savePayments($unsavePayment["QueryResponse"]["BillPayment"], $realmID); // Se insertan los nuevos pagos en la base de datos
      } else {
         echo "No hay pagos pendientes desde quickbooks";
      }
   } catch (RequestException $e) {
      // Manejo de errores
      if ($e->hasResponse()) {
         echo "Error en la respuesta: " . $e->getResponse()->getBody();
      } else {
         echo "Error en la solicitud: " . $e->getMessage();
      }
   }
}
