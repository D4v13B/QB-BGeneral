<?php

/**
 * Clase `Db`
 *
 * Esta clase se encarga de manejar la conexión a una base de datos MySQL mediante PDO.
 * Proporciona métodos para obtener y almacenar información en la base de datos.
 */
class Db
{
   private string $host;
   private string $user;
   private string $password;
   private string $database;
   private ?PDO $connection = null;

   /**
    * Constructor de la clase `Db`.
    *
    * Carga las credenciales de conexión desde un archivo de configuración `config.php` y 
    * asigna los valores a las propiedades de la clase.
    */
   function __construct()
   {
      $config = include "config.php";

      $this->host = $config["host_db"];
      $this->user = $config["user_db"];
      $this->password = $config["password_db"];
      $this->database = $config["database_db"];
   }

   /**
    * Establece una conexión a la base de datos utilizando PDO.
    *
    * Verifica si la conexión ya ha sido establecida; si no, intenta crear una nueva conexión.
    *
    * @return PDO|null Objeto PDO que representa la conexión a la base de datos
    *                  si se establece correctamente; de lo contrario, devuelve `null`.
    * @throws PDOException Si ocurre un error al intentar conectar.
    */
   public function connect(): ?PDO
   {
      if ($this->connection == null) {
         try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8";
            $this->connection = new PDO($dsn, $this->user, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
         }
      }
      return $this->connection;
   }

   /**
    * Obtiene la lista de proveedores de la base de datos para un `realmId` específico.
    *
    * @param int $realmId El ID de la empresa (company_id o realm_id de QuickBooks).
    * @return array Lista de proveedores como un arreglo asociativo o un array vacío en caso de error.
    * @throws PDOException Si ocurre algún error al ejecutar la consulta.
    */
   public function getVendors(int $realmId): array
   {
      $this->connect();

      try {
         $stmt = $this->connection->prepare("SELECT * FROM bg_proveedores WHERE bgpr_realm_id = :realmId");
         $stmt->execute([":realmId" => $realmId]);

         return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return [];
      }
   }

   /**
    * Guarda una lista de proveedores en la base de datos.
    *
    * @param array $vendors Un array de objetos que representan los proveedores.
    *                       Cada objeto debe tener `Id` y `DisplayName`.
    * @param int $realmId ID de la empresa que se asocia a los proveedores.
    * @return bool `true` si la inserción fue exitosa; `false` si ocurrió un error.
    * @throws PDOException Si ocurre un error al ejecutar la consulta.
    */
   public function saveVendors(array $vendors, int $realmId): bool
   {
      $this->connect();

      try {
         
         foreach ($vendors as $vend) {
            $id = $vend->Id;
            $name = $vend->DisplayName;
            
            $values[] = "($id, '$name', '$realmId')";
         }
         $stmt = $this->connection->query("INSERT INTO bg_proveedores(qb_vendor_id, bgpr_proveedor, bgpr_realm_id) VALUES " . implode(', ', $values));

         // $stmt->execute([":VAL" => ]);

         return true;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   /**
    * Obtiene la lista de pagos procesados de la base de datos para un `realmId` específico.
    *
    * @param int $realmId El ID de la empresa.
    * @return array Lista de pagos procesados como un arreglo asociativo o un array vacío en caso de error.
    * @throws PDOException Si ocurre algún error al ejecutar la consulta.
    */
   public function getProcessedPayments(int $realmId): array
   {
      $this->connect();

      try {
         $stmt = $this->connection->prepare("SELECT * FROM bg_qb_transaccion_procesada WHERE qb_realm_id = :realmId");
         $stmt->execute([":realmId" => $realmId]);

         return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return [];
      }
   }

   /**
    * Guarda una lista de pagos en la base de datos.
    *
    * @param array $payments Un array de objetos que representan los pagos.
    *                        Cada objeto debe tener propiedades como `Id`, `TotalAmt`, y `VendorRef`.
    * @param int $realmId ID de la empresa que se asocia a los pagos.
    * @return bool `true` si la inserción fue exitosa; `false` si ocurrió un error.
    * @throws PDOException Si ocurre un error al ejecutar la consulta.
    */
   public function savePayments(array $payments, int $realmId): bool
   {
      $this->connect();
      $valuesArray = [];

      try {
         $empresaInfo = $this->getEmpresaInfo($realmId);
         $empresaNumeroCuenta = $empresaInfo["empr_numero_cuenta"];
         $empresaTipoCuenta = $empresaInfo["empr_tipo_cuenta"];

         $stmt = $this->connection->prepare("INSERT INTO bg_transacciones(
            tran_descripcion, 
            tran_monto, 
            tran_fecha_inicial, 
            tran_propia,
            tran_codigo_producto, 
            tran_cuenta_origen, 
            tran_nombre_beneficiario, 
            tran_codigo_banco, 
            tran_codigo_producto_beneficiario,
            tran
         ) 
         VALUES :VAL");

         foreach ($payments as $pay) {
            $id = $pay->Id;
            $monto = $pay->TotalAmt;
            $vendorInfo = $this->getVendorInfo($pay->VendorRef, $realmId);
            $vendorName = $vendorInfo["bgpr_proveedor"];
            $vendorTipoCuenta = $vendorInfo["bgpr_tipo_cuenta"];
            $vendorBanco = $vendorInfo["bgpr_banco"];
            $vendorNumeroCuenta = $vendorInfo["bgpr_numero_cuenta"];

            $values = "(
               'Pago a $vendorName', 
               '$monto', 
               NOW(), 
               'true', 
               '$empresaTipoCuenta', 
               '$empresaNumeroCuenta', 
               '$vendorName', 
               '$vendorBanco', 
               '$vendorTipoCuenta')";

            $valuesArray[] = $values;

            $this->savePaysProceseed($id, $realmId, $vendorName);
         }

         $stmt->execute([":VAL" => implode(",", $valuesArray)]);

         return true;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   /**
    * Obtiene la información de una empresa en función de su `realmId`.
    *
    * @param int $realmId El ID de la empresa.
    * @return array Un array asociativo con la información de la empresa, o un array vacío en caso de error.
    * @throws PDOException Si ocurre algún error al ejecutar la consulta.
    */
   public function getEmpresaInfo(int $realmId): array
   {
      $this->connect();

      try {
         $stmt = $this->connection->prepare("SELECT * FROM empresas WHERE empr_qb_realm_id = :realmId");
         $stmt->execute([":realmId" => $realmId]);

         return $stmt->fetch(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return [];
      }
   }

   /**
    * Obtiene la información de un proveedor específico.
    *
    * @param int $realmId ID de la empresa.
    * @param int $qbVendorId ID del proveedor en QuickBooks.
    * @return array Un array asociativo con la información del proveedor o un array vacío en caso de error.
    * @throws PDOException Si ocurre algún error al ejecutar la consulta.
    */
   public function getVendorInfo(int $realmId, int $qbVendorId): array
   {
      $this->connect();

      try {
         $stmt = $this->connection->prepare("SELECT * FROM bg_proveedores WHERE bgpr_realm_id = :realmId AND qb_vendor_id = :vendorId");
         $stmt->execute([
            ":realmId" => $realmId,
            ":vendorId" => $qbVendorId
         ]);

         return $stmt->fetch(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return [];
      }
   }

   /**
    * Guarda un pago procesado en la base de datos.
    *
    * @param int $payId ID del pago.
    * @param int $realmId ID de la empresa.
    * @param string $nameProveedor Nombre del proveedor asociado al pago.
    * @return bool `true` si la inserción fue exitosa; `false` si ocurrió un error.
    * @throws PDOException Si ocurre un error al ejecutar la consulta.
    */
   public function savePaysProceseed(int $payId, int $realmId, string $nameProveedor): bool
   {
      $this->connect();

      try {
         $stmt = $this->connection->prepare("INSERT bg_qb_transaccion_procesada 
            (
               trpr_proveedor,
               trpr_qb_id,
               qb_realm_id
            )
         VALUES ('$nameProveedor', '$payId', '$realmId');");

         $stmt->execute();

         return true;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   /**
    * Obtiene todas las transacciones de la tabla `bg_transacciones` junto con los detalles de la empresa asociada de la tabla `empresas`.
    * La consulta utiliza un `INNER JOIN` entre ambas tablas utilizando el campo `empr_id` como clave de relación.
    *
    * @return array|false Devuelve un array asociativo con todos los resultados de la consulta. Si ocurre un error, retorna `false` y muestra el mensaje de error de la excepción.
    *
    * @throws PDOException Si ocurre un error en la ejecución de la consulta SQL, se lanza una excepción con el mensaje de error correspondiente.
    */
   public function getPaysBg(): array
   {
      $this->connect();

      try {
         // Prepara la consulta SQL para obtener las transacciones y los detalles de la empresa
         $stmt = $this->connection->prepare("SELECT * FROM bg_transacciones a
        INNER JOIN empresas ON empresas.empr_id = a.empr_id");

         // Ejecuta la consulta
         $stmt->execute();

         // Retorna los resultados de la consulta como un array asociativo
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         // Muestra el mensaje de error en caso de excepción y retorna false
         echo "Error: " . $e->getMessage();
         return false;
      }
   }
}
