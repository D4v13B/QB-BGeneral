<?php
ini_set('serialize_precision', 10);

// use oasis\names\specification\ubl\schema\xsd\CommonBasicComponents_2\Date;
class ServiceTransact
{
   private Db $db;
   private HttpClient $httpClient;
   private array $config;
   private Mailer $mailer;

   public function __construct(Db $db, HttpClient $httpClient, Mailer $mailer)
   {
      $this->config = require "./config.php";
      $this->db = $db;
      $this->httpClient = $httpClient;
      $this->mailer = $mailer;
   }

   public function quitarCaracteresEspeciales($string)
   {
      // Convertir caracteres especiales (tildes, ñ) a ASCII
      $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
      // Eliminar todo lo que no sea una letra (a-z o A-Z)
      $string = preg_replace('/[^a-zA-Z\s]/', '', $string);
      return $string;
   }

   /**
    * Procesa los pagos no procesados y los envía a la API de destino
    */
   public function procesarPagos($token, $pagosNoProcesados, $clientId, $emprNotiEmail)
   {
      $transacciones = [];
      $plantillaOriginal = $this->db->getTemplate('BG-NOTI-EMPRESA');

      foreach ($pagosNoProcesados as $pago) {
         $montoPago = (float)number_format($pago["tran_monto"], 2, ".", "");

         $tranPropia = $pago["tran_propia"] == 0 ? false : true;;
         $transacciones[] = [
            "descripcion" => $this->quitarCaracteresEspeciales(strval($pago['tran_descripcion'] ?? "TRANFE PROPIA H2H")),
            // "monto" => number_format($pago['tran_monto'], 2, ".", ""), // Forzar formato de 2 decimales
            "monto" => $montoPago, // Forzar formato de 2 decimales
            "fechaInicial" => date("Y-m-d\TH:i:s.v\Z", strtotime($pago['tran_fecha_inicial'])),
            "trnPropia" => (bool)$tranPropia,
            "codigoProducto" => (float)number_format((float)$pago['empr_tipo_cuenta'], 2, '.', ''), // Forzar formato de 2 decimales
            "cuentaOrigen" => strval($pago['empr_numero_cuenta']), // Mantener como cadena
            "nombreBeneficiario" => $this->quitarCaracteresEspeciales(strval($pago['tran_nombre_beneficiario'])),
            "codigoBanco" => (int)$pago['tran_codigo_banco'], // Convertir a entero
            "codigoProductoBeneficiario" => (int)$pago['tran_codigo_producto_beneficiario'], // Convertir a entero
            "numeroCuentaBeneficiario" => strval(trim($pago['tran_numero_cuenta_beneficiario'])), // Mantener como cadena
            "secuencial" => (int)$pago['trans_id'], // Convertir a entero
         ];
      }

      var_dump($transacciones);

      // Configura los encabezados con el token de autenticación
      $headers = [
         'Content-Type' => 'application/json',
         'authorization' => 'Bearer ' . $token,
         'x-ibm-client-id' => $clientId
      ];

      $bodyHttp = [
         "transacciones" => $transacciones
      ];

      try {
         // Utilizamos la clase HttpClient para enviar los datos
         $response = $this->httpClient->post('h2h/transaccion/individuales', $bodyHttp, $headers);

         //Vamos a buscar en la base de datos y actualizar las respuestas
         $response = json_decode($response, true);

         if (isset($response["httpCode"]) and ($response["httpCode"] == 400 or $response["httpCode"] == 422)) {
            throw new Exception($response["moreInformation"]);
         }
         // Se actualizan los pagos en base a la respuesta de a API
         foreach ($response["body"]["resultadosTransaccion"] as $res) {
            $estadoTransaccion = $res["estadoTransaccion"];

            if ($estadoTransaccion == "EE") { //Esto es un error de respuesta de la API
               $this->db->updatePayResponse($res["secuencial"], 1, $estadoTransaccion);
            } else {
               $this->db->updatePayResponse($res["secuencial"], 1, $estadoTransaccion, $res["codigoPago"]);

               //Vamos a buscar la info de la transaccion para notificar a quien se pago y el monto que se hizo
               // $payInfo = $this->db->getPayInfo($res["secuencial"]);

               //Vamos a buscar la plantilla
               // $plantillaEnviar = str_replace("[PROVEEDOR]", $payInfo["tran_nombre_beneficiario"], $plantillaOriginal);
               // $plantillaEnviar = str_replace("[PROVEEDOR]", $payInfo["trans_monto"], $plantillaEnviar);

               // $this->mailer->sendMail($payInfo["empr_email"], "PAGO EFECTUADO A ". $payInfo["tran_nombre_beneficiario"] , $plantillaEnviar);
            }
         }
      } catch (Exception $e) {
         echo json_encode(["msg" => $e->getMessage()]);
         //Enviar EMAIL DE ERROR

         //Plantilla para enviar correos de error
         $plantillaEmail = $this->db->getTemplate("BG-NOTIFICACION");
         $users = $this->db->getUserEmailNotifications();

         $plantillaEmail = str_replace("[MENSAJE_ERROR]", $e->getMessage(), $plantillaEmail);

         $this->mailer->sendMail($users, "ERRORES EN BANCO GENERAL", $plantillaEmail);
      }
   }

   public function getPayInfo(int $id, string $token, string $clientId)
   {

      try {
         $params = [
            "query" => [
               "codigoAutorizacion" => $id,
               "fecha" => (new DateTime())->format('Y-m-d\TH:i:s.100')
            ],
            "headers" => [
               'Content-Type' => 'application/json',
               'Authorization' => 'Bearer '. $token,
               'x-ibm-client-id' => $clientId
            ]
         ];

         $response = $this->httpClient->get('h2h/transaccion/individuales', $params["query"], $params["headers"]);

         // $estado = $response["body"]["detalleTransaccionesIndividuales"]["descripcionEstado"];


         // if($estado == ""){

         // }

         return $response;
      } catch (Exception $th) {
         echo json_encode(["msg" => $th->getMessage()]);
      }
   }
}
