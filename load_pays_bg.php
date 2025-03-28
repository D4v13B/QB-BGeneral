<?php

if (!isset($_GET["a"])) {
   die("No se puede proceder con ninguna operacion");
}

include "cors.php";
require_once "vendor/autoload.php";
require_once __DIR__ . "/Classes/Db.php";
require_once __DIR__ . "/Classes/HttpClient.php";
require_once __DIR__ . "/Classes/ServiceTransact.php";
require_once __DIR__ . "/Classes/Email.php";

$config = require "./config.php";

$clientHttp = new HttpClient();
$db = new Db();
$transact = new ServiceTransact($db, $clientHttp, new Mailer());

$mailerObj = new Mailer();

$accion = $_GET["a"];

$empresas = $db->getEmpresasActivas();
// **Autenticar y obtener el token**

switch ($accion) {
   case "LoadPays":

      foreach ($empresas as $emp) {
         $bg_client_id = $emp["empr_bg_client_id"];
         $bg_client_secret = $emp["empr_bg_client_secret"];
         $empr_email_noti = $emp["empr_email"];

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

         // Verifica si existen pagos pendientes para procesar.
         if (empty($pagosNoProcesados)) {
            echo json_encode(["msg" => "Todos los pagos ya han sido procesados anteriormente", "err" => false, "pagos" => $pagosNoProcesados]);
            continue;
         }

         // **Procesar los pagos**
         // Si el token es válido, se procede a procesar los pagos pendientes.
         $transact->procesarPagos($token, $pagosNoProcesados, $bg_client_id, $empr_email_noti);
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

         //Por empresa, creamos un solo token para poder verificar el estado de los pagos
         $token = $clientHttp->autenticar($bg_client_id, $bg_client_secret);

         foreach ($pagosPendientes as $pp) {
            //Aqui vamos a implementar una funcion que me actualicé desde la API los pagos
            // Vamos a buscar la plantilla de envio
            $plantillaEnviar = $db->getTemplate('BG-NOTI-EMPRESA');

            if (!$pp["tran_codigo_pago"]) {
               continue;
            }

            // 1. Peticion a la API del pago en especifico
            $payDetail = $transact->getPayInfo($pp["tran_codigo_pago"], $token, $bg_client_id);

            $details = $payDetail["body"]["detalleTransaccionesIndividuales"][0];
            $payState = $details["descripcionEstado"];

            //2. Actualizacion en la base de datos
            $db->updatePayStatus($pp["trans_id"], $payState);

            // Vamos a verificar el estado de la transaccion y enviamos el email
            if ($payState == 'REALIZADA') {
               // Buscar el nombre del proveedor
               $vendorInfo = $db->getVendorXAccount($details["cuentaDestino"]);

               $plantillaEnviar = str_replace("[PROVEEDOR]", $vendorInfo["bgpr_proveedor"], $plantillaEnviar);
               $plantillaEnviar = str_replace("[MONTO]", $details["monto"], $plantillaEnviar);

               $mailerObj->sendMail($emp["empr_email"], "PAGO REALIZADO A " . $vendorInfo["bgpr_proveedor"], $plantillaEnviar);
            }
         }
      }

      break;
}
