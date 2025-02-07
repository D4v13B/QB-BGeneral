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
            $id = (int) $vend["Id"];
            // $name = $this->quitarCaracteresEspeciales($vend["DisplayName"]); // Escapar caracteres problemáticos
            $name = $vend["DisplayName"]; // Escapar caracteres problemáticos

            $realmId = addslashes($realmId);

            $values[] = "($id, '$name', '$realmId', '0', '0', '10')";
         }

         $sql = "INSERT INTO bg_proveedores(qb_vendor_id, bgpr_proveedor, bgpr_realm_id, bgpr_numero_cuenta, bgpr_tipo_cuenta, bgpr_banco) VALUES " . implode(', ', $values);

         $this->connection->query($sql);

         //Buscar todos los proveedores y actualizarlos
         $sql = "UPDATE bg_proveedores p
         INNER JOIN bg_proveedores_cuentas dp ON p.bgpr_proveedor = dp.nombre
         SET p.bgpr_numero_cuenta = dp.cuenta_bancaria,
            p.bgpr_banco = dp.bg_codigo_banco,
            p.bgpr_tipo_cuenta = dp.tipo_cuenta
         WHERE p.bgpr_numero_cuenta IS NULL OR p.bgpr_banco IS NULL OR p.bgpr_tipo_cuenta IS NULL OR p.bgpr_banco IS NULL OR bgpr_numero_cuenta = 0 OR bgpr_tipo_cuenta = 0";

         $this->connection->query($sql);

         // Buscar proveedores dentro de la tabla y actualizarlo
         $sql = "UPDATE bg_proveedores p1
         JOIN bg_proveedores p2
         ON p1.bgpr_proveedor = p2.bgpr_proveedor
               AND p2.bgpr_numero_cuenta <> '0'
               AND p2.bgpr_numero_cuenta IS NOT NULL
         SET p1.bgpr_numero_cuenta = p2.bgpr_numero_cuenta, p1.bgpr_tipo_cuenta = p2.bgpr_tipo_cuenta, p1.bgpr_banco = p2.bgpr_banco
         WHERE p1.bgpr_numero_cuenta = '0' OR (p1.bgpr_tipo_cuenta = 0 AND p1.bgpr_banco = 10);";

         $this->connection->query($sql);

         return true;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
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

      print_r($payments);

      try {
         $empresaInfo = $this->getEmpresaInfo($realmId);
         $empresaId = $empresaInfo["empr_id"];
         $empresaNumeroCuenta = $empresaInfo["empr_numero_cuenta"];
         $empresaTipoCuenta = $empresaInfo["empr_tipo_cuenta"];

         $stmt = $this->connection->prepare("INSERT INTO bg_transacciones(
               qb_vendor_id,
                tran_descripcion, 
                tran_monto, 
                tran_fecha_inicial, 
                tran_propia,
                tran_codigo_producto, 
                tran_cuenta_origen,
                tran_nombre_beneficiario,
                tran_codigo_banco, 
                tran_codigo_producto_beneficiario,
                tran_numero_cuenta_beneficiario,
                tran_estado,
                empr_id
            ) 
            VALUES
            (:qb_vendor,:descripcion, :monto, NOW(), '0', :codigo_producto, :cuenta_origen, :nombre_beneficiario, :codigo_banco, :codigo_producto_beneficiario, :numero_cuenta_beneficiario, '0', :empresa_id)");

         $this->connection->beginTransaction();

         foreach ($payments as $pay) {
            $id = $pay["Id"] ?? null;
            $vendorRef = $pay["VendorRef"] ?? null;
            $monto = $pay["TotalAmt"] ?? null;

            $vendorInfo = $this->getVendorInfo($realmId, $vendorRef["value"]);

            if (empty($vendorInfo)) {
               error_log("Vendor Info no encontrada para pago ID $id. Proveedor " . $vendorRef["name"]);
               continue;
            }

            $vendorName = $vendorInfo["bgpr_proveedor"];
            $vendorTipoCuenta = $vendorInfo["bgpr_tipo_cuenta"];
            $vendorBanco = $vendorInfo["bgpr_banco"];
            $vendorNumeroCuenta = $vendorInfo["bgpr_numero_cuenta"];

            if ($vendorTipoCuenta == 0 or $vendorNumeroCuenta == 0 or $vendorNumeroCuenta == null or $vendorTipoCuenta == null) {
               error_log("{$vendorName} no tiene DATOS BANCARIOS registrados para la empresa de quickbooks con ID {$realmId}");
               continue;
            }

            $valuesArray = [
               ":qb_vendor" => $vendorRef["value"],
               ":descripcion" => "Pago a $vendorName",
               ":monto" => $monto,
               ":codigo_producto" => $empresaTipoCuenta,
               ":cuenta_origen" => $empresaNumeroCuenta,
               ":nombre_beneficiario" => $vendorName,
               ":codigo_banco" => $vendorBanco,
               ":codigo_producto_beneficiario" => $vendorTipoCuenta,
               ":numero_cuenta_beneficiario" => $vendorNumeroCuenta,
               ":empresa_id" => $empresaId
            ];

            $stmt->execute($valuesArray);
            $this->savePaysProceseed($id, $realmId, $vendorName);
         }

         $this->connection->commit();
         return true;
      } catch (PDOException $e) {
         $this->connection->rollBack();
         error_log("Error al guardar pagos: " . $e->getMessage());
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

   public function getEmpresasActivas(): array
   {
      $this->connect();

      try {
         $stmt = $this->connection->prepare("SELECT * FROM empresas WHERE empr_qb_activa = '1'");
         $stmt->execute();

         return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return [];
      }
   }

   public function getEmpresas(): array
   {
      $this->connect();

      try {
         $stmt = $this->connection->prepare("SELECT * FROM empresas WHERE empr_qb_realm_id IS NOT NULL OR empr_qb_realm_id != ''");
         $stmt->execute();

         return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
   public function getVendorInfo(int $realmId, $qbVendorId): array
   {
      $this->connect();


      try {
         // echo "VendorID $qbVendorId" . " " . "Realm ID $realmId" . "<br>";s

         $sql = "SELECT * FROM bg_proveedores WHERE bgpr_realm_id = '$realmId' AND qb_vendor_id = '$qbVendorId'";
         $stmt = $this->connection->query($sql);

         $result = $stmt->fetch(PDO::FETCH_ASSOC);

         if ($result === false) {
            error_log("Error en getVendorInfo: Vendor Info vacio");
            echo "VendorID $qbVendorId" . " con Realm ID $realmId VACIO" . "<br>";
            return [];
         }

         return $result;
      } catch (PDOException $e) {
         error_log("Error en getVendorInfo: " . $e->getMessage());
         return ["error" => $e->getMessage()];
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
   public function getPaysBg(int $empr_id): array
   {
      $this->connect();

      try {
         // Prepara la consulta SQL para obtener las transacciones y loRMs detalles de la empresa y que no han sido cargados a BGeneral
         $stmt = $this->connection->prepare("SELECT
            c.qb_vendor_id,
            a.qb_vendor_id,
            b.empr_id,
            trans_id,
            tran_descripcion,
            tran_monto,
            tran_fecha_inicial,
            tran_propia,
            c.bgpr_numero_cuenta as tran_numero_cuenta_beneficiario,
            c.bgpr_tipo_cuenta as tran_codigo_producto_beneficiario,
            tran_nombre_beneficiario,
            tran_codigo_banco,
            b.empr_numero_cuenta,
            b.empr_tipo_cuenta
         FROM bg_transacciones a, empresas b, bg_proveedores c
         WHERE
            a.empr_id = b.empr_id AND
            a.qb_vendor_id = c.qb_vendor_id AND
            c.bgpr_realm_id = b.empr_qb_realm_id AND
            (tran_estado = 0 OR tran_estado IS NULL) AND
            a.qb_vendor_id IS NOT NULL AND b.empr_id = :empr_id LIMIT 50");

         // Ejecuta la consulta
         $stmt->execute([
            ":empr_id" => $empr_id
         ]);

         // Retorna los resultados de la consulta como un array asociativo
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         // Muestra el mensaje de error en caso de excepción y retorna false
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   public function updatePayResponse(int $payId, int $status, string $res, string $codigoPago = null): bool
   {
      $this->connect();

      try {

         $stmt = $this->connection->prepare("UPDATE bg_transacciones SET tran_estado = :status, tran_res = :res_api, tran_codigo_pago = :codigoPago WHERE trans_id = :id");

         // Ejecuta la consulta
         $stmt->execute([
            ":status" => $status,
            ":id" => $payId,
            ":res_api" => $res,
            ":codigoPago" => $codigoPago
         ]);

         return true;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   public function updatePayStatus(int $payId, string $res)
   {
      $this->connect();

      try {

         $stmt = $this->connection->prepare("UPDATE bg_transacciones SET tran_status_detail = :status WHERE trans_id = :id");

         // Ejecuta la consulta
         $stmt->execute([
            ":status" => $res,
            ":id" => $payId
         ]);

         return true;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   public function getPendingPaysAutorization(int $empr_id)
   {
      $this->connect();

      try {

         $stmt = $this->connection->prepare("SELECT trans_id FROM bg_transacciones WHERE tran_status_detail IS NULL or tran_status_detail = 'AUTORIZACION PENDIENTE' AND empr_id = :empr_id");

         // Ejecuta la consulta
         $stmt->execute([
            ":empr_id" => $empr_id
         ]);

         return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   public function getTokens($empr_id)
   {
      $this->connect();

      try {

         $stmt = $this->connection->prepare("SELECT empr_access_token, empr_refresh_token FROM empresas WHERE empr_id = :empr_id AND empr_access_token IS NOT NULL AND empr_refresh_token IS NOT NULL");
         $stmt->execute([":empr_id" => $empr_id]);

         // Ejecuta la consulta
         $stmt->execute();

         $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

         if (!$res) {
            return false;
         }

         return $res;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   public function updateTokens() {}

   public function saveTokens($realm_id, $access_token, $refresh_token, $refresh_expires_in, $access_expires_in, $access_token_obj)
   {

      $this->connect();

      try {

         $stmt = $this->connection->prepare("UPDATE empresas SET empr_access_token = :access_token, 
         empr_refresh_token = :refresh_token,
         empr_refresh_token_expiration_date = :refresh_expiration_date,
         empr_access_token_expiration_date = :access_expiration_date,
         accessTokenObj = :access_token_obj
         WHERE empr_qb_realm_id = :id");

         // Ejecuta la consulta
         $stmt->execute([
            ":access_token" => $access_token,
            ":refresh_token" => $refresh_token,
            ":refresh_expiration_date" => $refresh_expires_in,
            ":access_expiration_date" => $access_expires_in,
            ":access_token_obj" => $access_token_obj,
            ":id" => $realm_id
         ]);

         return true;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   public function getTemplate(string $plantilla)
   {
      $this->connect();

      try {

         $stmt = $this->connection->prepare("SELECT plan_contenido FROM plantillas WHERE plan_nombre = :nombre");

         // Ejecuta la consulta
         $stmt->execute([":nombre" => $plantilla]);

         $res = $stmt->fetch(PDO::FETCH_ASSOC);

         if (!$res) {
            return false;
         }

         return $res["plan_contenido"];
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }

   public function getUserEmailNotifications()
   {
      $this->connect();

      try {

         $stmt = $this->connection->prepare("SELECT usua_email FROM usuarios WHERE usua_notificacion_banco = 1");

         // Ejecuta la consulta
         $stmt->execute();

         $res = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

         if (!$res) {
            return false;
         }

         return $res;
      } catch (PDOException $e) {
         echo "Error: " . $e->getMessage();
         return false;
      }
   }
}
