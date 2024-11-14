<?php
class ServiceTransact
{
   private Db $db;
   private HttpClient $httpClient;
   private array $config;

   public function __construct(Db $db, HttpClient $httpClient)
   {
      $this->config = require "../config.php";
      $this->db = $db;
      $this->httpClient = $httpClient;
   }

   /**
    * Procesa los pagos no procesados y los envía a la API de destino
    */
   public function procesarPagos()
   {
      // Autenticar y obtener el token
      $token = $this->httpClient->autenticar($this->config['clientId'], $this->config['clientSecret']);

      if (!$token) {
         echo "No se pudo obtener el token de autenticación.";
         return;
      }

      // Obtiene los pagos no procesados desde la base de datos
      $pagosNoProcesados = $this->db->getPaysBg();
      $pagosFormateados = [];

      foreach ($pagosNoProcesados as $pago) {
         $pagosFormateados[] = [
            "descripcion" => $pago['tran_descripcion'] ?? "TRANFE PROPIA H2H",
            "monto" => (float)$pago['tran_monto'],
            "fechaInicial" => date("Y-m-d\TH:i:s.v\Z", strtotime($pago['tran_fecha_inicial'])),
            "trnPropia" => (bool)$pago['tran_propia'],
            "codigoProducto" => $pago['tran_codigo_producto'],
            "cuentaOrigen" => strval($pago['tran_cuenta_origen']),
            "correo" => $pago['tran_correo'] ? "correo@prueba.com" : null,
            "nombreBeneficiario" => $pago['tran_nombre_beneficiario'],
            "codigoProductoBeneficiario" => $pago['tran_codigo_producto_beneficiario'],
            "numeroCuentaBeneficiario" => $pago['tran_numero_cuenta_beneficiario'],
            "secuencial" => $pago['tran_secuencia_detalle'],
            "codigoBanco" => $pago['tran_codigo_banco']
         ];
      }

      // Configura los encabezados con el token de autenticación
      $headers = [
         'Content-Type' => 'application/json',
         'authorization' => 'Bearer ' . $token,
         'x-ibm-client-id' => $this->config['x-ibm-clientd-id']
      ];

      // Utilizamos la clase HttpClient para enviar los datos
      $response = $this->httpClient->post('h2h/transacciones', $pagosFormateados, $headers);
      echo "Respuesta de la API: " . $response;
      
   }
}
