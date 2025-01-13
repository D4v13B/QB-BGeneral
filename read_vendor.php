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

   $realmID = $empr["empr_qb_realm_id"];
   $client = new Client();
   $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/text',
      'Authorization' => 'Bearer ' . $empr["empr_access_token"]
   ];
   $body = 'SELECT * FROM vendor'; // Consulta SQL
   $url = "https://quickbooks.api.intuit.com/v3/company/" . $empr["empr_qb_realm_id"] . "/query?minorversion=73";

   try {
      // Crear la solicitud
      $request = new Request('POST', $url, $headers, $body);

      // Enviar la solicitud
      $response = $client->send($request);

      // Obtener y procesar el cuerpo de la respuesta
      $vendorsApi = json_decode($response->getBody(), true)["QueryResponse"]["Vendor"];


      $vendorsDb = $db->getVendors($realmID); // Proveedores de la base de datos

      // Se extraen los IDs de los proveedores ya almacenados en la base de datos
      $dbVendorIds = array_column($vendorsDb, "qb_vendor_id");
      
      // // Se filtran los proveedores de la API para encontrar aquellos que no están en la base de datos
      $unsaveVendor = array_filter($vendorsApi, function ($vendor) use ($dbVendorIds) {
         return !in_array($vendor["Id"], $dbVendorIds);
      });
      
      // /**
      //  * Guardar los proveedores no guardados en la base de datos
      //  */
      if (!empty($unsaveVendor)) {
         $db->saveVendors($unsaveVendor, $realmID); // Se insertan los nuevos proveedores en la base de datos
      }else{
         echo "Todos los proveedores de la empresa " . $empr["empr_nombre"] . " han sido cargados\n";
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
