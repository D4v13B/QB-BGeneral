<?php

include "cors.php";

date_default_timezone_set("America/Panama");
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

require_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . "/Classes/Db.php";
include_once __DIR__ . "/Classes/HttpClient.php";
include_once __DIR__ . "/Classes/Email.php";
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
   $pagosCheques = [];

   $realmID = $empr["empr_qb_realm_id"];
   $client = new Client();
   $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/text',
      'Authorization' => 'Bearer ' . $empr["empr_access_token"]
   ]; 
   $body = "SELECT * FROM BillPayment WHERE MetaData.CreateTime >= '$formattedDate' maxresults 1000"; // Cnsulta SQL
   $url = "https://quickbooks.api.intuit.com/v3/company/" . $empr["empr_qb_realm_id"] . "/query?minorversion=75";

   try {
      // Crear la solicitud
      $request = new Request('POST', $url, $headers, $body);

      // Enviar la solicitud
      $response = $client->send($request);

      if(!isset(json_decode($response->getBody(), true)["QueryResponse"]["BillPayment"])){
         continue;
      }

      // Obtener y procesar el cuerpo de la respuesta
      $payments = json_decode($response->getBody(), true)["QueryResponse"]["BillPayment"];

      $paymentsDb = $db->getProcessedPayments($realmID); // Pagos procesados en la base de datos
      
      // Se extraen los IDs de los pagos ya almacenados en la base de datos
      $dbPaymentsId = array_column($paymentsDb, "trpr_qb_id");

      // Se filtran los pagos de la API para encontrar aquellos que no están en la base de datos
      $unsavePayment = array_filter($payments, function ($pay) use ($dbPaymentsId) {
         return !in_array($pay["Id"], $dbPaymentsId);
      });

      // Filtrar los pagos de tipo cheque
      foreach($unsavePayment as $up){

         if(($up["PayType"]) == "Check" and $up["TotalAmt"] != 0){ //Solo pasa lo que esta marcado como cheque
            $pagosCheques[] = $up;
         }
      }
      /**
       * Guardar los pagos no guardados en la base de datos
       */
      if (!empty($pagosCheques)) {
         $db->savePayments($pagosCheques, $realmID); // Se insertan los nuevos pagos en la base de datos
      } else {
         echo json_encode(["msg" => "No hay pagos pendientes desde quickbooks"]);
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
