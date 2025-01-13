<?php

if (!isset($_GET["a"])) {
   die("No se puede proceder con ninguna operacion");
}

require "vendor/autoload.php";
require "./Classes/Db.php";
require "./Classes/HttpClient.php";
require "./Classes/ServiceTransact.php";

$config = require "./config.php";

$clientHttp = new HttpClient();
$db = new Db();
$transact = new ServiceTransact($db, $clientHttp);

$accion = $_GET["a"];

$empresas = $db->getEmpresasActivas();
// **Autenticar y obtener el token**

switch ($accion) {
   case "LoadPays":

      foreach ($empresas as $emp) {
         $bg_client_id = $emp["empr_bg_client_id"];
         $bg_client_secret = $emp["empr_bg_client_secret"];

         $token = $clientHttp->autenticar($bg_client_id, $bg_client_secret);

         if (!$token) {
            // Si no se pudo obtener el token, se muestra un mensaje de error.
            echo json_encode(["msg" => "Error al obtener el token de autenticación", "err" => true]);
            break;
         }
         /** 
          * Revisar si hay pagos sin procesar
          * Se recuperan los pagos pendientes de ser procesados desde la base de datos.
          **/
         $pagosNoProcesados = $db->getPaysBg($emp["empr_id"]);

         // print_r($pagosNoProcesados);

         // Verifica si existen pagos pendientes para procesar.
         if (empty($pagosNoProcesados)) {
            echo json_encode(["msg" => "Todos los pagos ya han sido procesados anteriormente", "err" => false]);
            break;
         }

         // **Procesar los pagos**
         // Si el token es válido, se procede a procesar los pagos pendientes.
         $transact->procesarPagos($token, $pagosNoProcesados, $bg_client_id);
      }

      break;
   case "UpdatePaysAPI":

      foreach ($empresas as $emp) {
         $pagosPendientes = $db->getPendingPaysAutorization($emp["empr_id"]);

         if (empty($pagosPendientes)) {
            echo json_encode(["msg" => "No hay pagos pendientes por revisar", "err" => false]);
            break;
         }

         $bg_client_id = $emp["empr_bg_client_id"];
         $bg_client_secret = $emp["empr_bg_client_secret"];

         foreach ($pagosPendientes as $pp) {
            //Aqui vamos a implementar una funcion que me actualicé desde la API los pagos
            $token = $clientHttp->autenticar($bg_client_id, $bg_client_secret);

            // 1. Peticion a la API del pago en especifico
            $payDetail = $transact->getPayInfo($pp["tran_codigo_pago"], $token);
            //2. Actualizacion en la base de datos
            $db->updatePayStatus($pp["trans_id"], $payDetail["body"]["detalleTransaccionesIndividuales"]["descripcionEstado"]);
         }
      }

      break;
}
