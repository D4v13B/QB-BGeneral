<?php

require "vendor/autoload.php";
require "./Classes/Db.php";
require "./Classes/HttpClient.php";
require "./Classes/ServiceTransact.php";

$config = require "./config.php";

$clientHttp = new HttpClient();
$db = new Db();
$transact = new ServiceTransact($db, $clientHttp);

/** 
 * Revisar si hay pagos sin procesar
 * Se recuperan los pagos pendientes de ser procesados desde la base de datos.
**/
$pagosNoProcesados = $db->getPaysBg();

// Verifica si existen pagos pendientes para procesar.
if (empty($pagosNoProcesados)) {
   echo "Todos los pagos ya han sido procesados anteriormente";
   return;
} else {
   // **Autenticar y obtener el token**
   $token = $clientHttp->autenticar($config['x-ibm-clientd-id'], $config['x-ibm-client-secret']);

   // Verifica si se obtuvo un token válido.
   if ($token) {
      // **Procesar los pagos**
      // Si el token es válido, se procede a procesar los pagos pendientes.
      $transact->procesarPagos($token, $pagosNoProcesados);
   } else {
      // Si no se pudo obtener el token, se muestra un mensaje de error.
      echo "Error al obtener el token de autenticación.";
   }
}
