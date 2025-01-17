<?php
date_default_timezone_set("America/Panama");
// echo date_default_timezone_get();
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

require_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . "/Classes/Db.php";
include_once __DIR__ . "/Classes/HttpClient.php";
$config = include_once __DIR__ . '/config.php';

$db = new Db();
$httpClient = new HttpClient();
$empresas = $db->getEmpresasActivas();

foreach ($empresas as $empr) {

   if (!$empr["empr_access_token"] or !$empr["accessTokenObj"]) {
      break;
   }

   //Dia que se va a leer los BillPayments
   $datetime = new DateTime();
   $datetime->modify($config["payments_time_interval"]);
   $formattedDate = $datetime->format('c');

   $realmID = $empr["empr_qb_realm_id"];
   $client = new Client();
   $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/text',
      'Authorization' => 'Bearer ' . $empr["empr_access_token"]
   ]; 
   echo $body = "SELECT * FROM BillPayment WHERE MetaData.CreateTime >= '$formattedDate'"; // Consulta SQL
   // echo $body = "SELECT * FROM BillPayment"; // Consulta SQL
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

      print_r($payments);
      /**
       * Guardar los pagos no guardados en la base de datos
       */
      die();
      if (!empty($unsavePayment["QueryResponse"]["BillPayment"])) {
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
