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
            "descripcion" => $pago['tran_descripcion'] ?? "TRANFE PROPIA H2H",
            "monto" => (float)$pago['tran_monto'],
            "fechaInicial" => date("Y-m-d\TH:i:s.v\Z", strtotime($pago['tran_fecha_inicial'])),
            "trnPropia" => $tranPropia,
            "codigoProducto" => $pago['empr_tipo_cuenta'],
            "cuentaOrigen" => strval($pago['empr_numero_cuenta']),
            "nombreBeneficiario" => $pago['tran_nombre_beneficiario'],
            "codigoProductoBeneficiario" => $pago['tran_codigo_producto_beneficiario'],
            "numeroCuentaBeneficiario" => trim($pago['tran_numero_cuenta_beneficiario']),
            "secuencial" => $pago['trans_id'],
            "codigoBanco" => $pago['tran_codigo_banco']
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

      var_dump ($bodyHttp);

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
