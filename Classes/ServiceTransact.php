<?php
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
   public function procesarPagos($token, $pagosNoProcesados)
   {
      $transacciones = [];

      foreach ($pagosNoProcesados as $pago) {
         $transacciones[] = [
            "descripcion" => $pago['tran_descripcion'] ?? "TRANFE PROPIA H2H",
            "monto" => (float)$pago['tran_monto'],
            "fechaInicial" => date("Y-m-d\TH:i:s.v\Z", strtotime($pago['tran_fecha_inicial'])),
            "trnPropia" => (bool)$pago['tran_propia'],
            "codigoProducto" => $pago['empr_tipo_cuenta'],
            "cuentaOrigen" => strval($pago['empr_numero_cuenta']),
            "correo" => $pago['tran_correo'],
            "nombreBeneficiario" => $pago['tran_nombre_beneficiario'],
            "codigoProductoBeneficiario" => $pago['tran_codigo_producto_beneficiario'],
            "numeroCuentaBeneficiario" => $pago['tran_numero_cuenta_beneficiario'],
            "secuencial" => $pago['trans_id'],
            "codigoBanco" => $pago['tran_codigo_banco']
         ];

      }

      // Configura los encabezados con el token de autenticación
      $headers = [
         'Content-Type' => 'application/json',
         'authorization' => 'Bearer ' . $token,
         'x-ibm-client-id' => $this->config['x-ibm-clientd-id']
      ];

      $bodyHttp = [
         "transacciones" => $transacciones
      ];

      // Utilizamos la clase HttpClient para enviar los datos
      $response = $this->httpClient->post('h2h/transaccion/individuales', $bodyHttp, $headers);

      //Vamos a buscar en la base de datos y actualizar las respuestas
      $response = json_decode($response, true);

      // Se actualizan los pagos en base a la respuesta de a API
      foreach($response["body"]["transacciones"] as $res){
         $estadoTransaccion = $res["estadoTransaccion"];

         if($estadoTransaccion == "EE"){ //Esto es un error de respuesta de la API
            $this->db->updatePayStatus($res["secuencial"], 1, $estadoTransaccion);
         }else{
            $this->db->updatePayStatus($res["secuencial"], 1, $estadoTransaccion, $res["codigoPago"]);
         }

      }
   }
}
