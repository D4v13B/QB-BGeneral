<?php

use oasis\names\specification\ubl\schema\xsd\CommonBasicComponents_2\Date;

class ServiceTransact
{
   private Db $db;
   private HttpClient $httpClient;
   private array $config;

   public function __construct(Db $db, HttpClient $httpClient)
   {
      $this->config = require "config.php";
      $this->db = $db;
      $this->httpClient = $httpClient;
   }

   /**
    * Procesa los pagos no procesados y los envía a la API de destino
    */
   public function procesarPagos($token, $pagosNoProcesados, $clientId)
   {
      $transacciones = [];

      foreach ($pagosNoProcesados as $pago) {
         $tranPropia = $pago["tran_propia"] == 0 ? false : true;;
         $transacciones[] = [
            "descripcion" => strval($pago['tran_descripcion'] ?? "TRANFE PROPIA H2H"),
            "monto" => (float)number_format((float)$pago['tran_monto'], 0, '.', ''), // Forzar formato de 2 decimales
            "fechaInicial" => date("Y-m-d\TH:i:s.v\Z", strtotime($pago['tran_fecha_inicial'])),
            "trnPropia" => (bool)$tranPropia,
            "codigoProducto" => (float)number_format((float)$pago['empr_tipo_cuenta'], 2, '.', ''), // Forzar formato de 2 decimales
            "cuentaOrigen" => strval($pago['empr_numero_cuenta']), // Mantener como cadena
            "nombreBeneficiario" => strval($pago['tran_nombre_beneficiario']),
            "codigoBanco" => (int)$pago['tran_codigo_banco'], // Convertir a entero
            "codigoProductoBeneficiario" => (int)$pago['tran_codigo_producto_beneficiario'], // Convertir a entero
            "numeroCuentaBeneficiario" => strval(trim($pago['tran_numero_cuenta_beneficiario'])), // Mantener como cadena
            "secuencial" => (int)$pago['trans_id'], // Convertir a entero
         ];
      }

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
            }
         }
      } catch (Exception $e) {
         echo json_encode(["msg" => $e->getMessage()]);
      }
   }

   public function getPayInfo(int $id, string $token)
   {

      try {
         $headers = [
            "query" => [
               "codigoAutorizacion" => $id,
               "fecha" => new Date()
            ],
            'Content-Type' => 'application/json',
            'authorization' => 'Bearer ' . $token,
            'x-ibm-client-id' => $this->config['x-ibm-clientd-id']
         ];

         $response = $this->httpClient->get('h2h/transaccion/individuales', $headers);

         return $response;
      } catch (Exception $th) {
         echo json_encode(["msg" => $th->getMessage()]);
      }
   }
}
