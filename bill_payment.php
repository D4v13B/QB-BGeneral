<?php

/**
 * read_payment.php
 * 
 * Este script gestiona la sincronización de los pagos procesados 
 * entre la API de QuickBooks y la base de datos local. Se ejecuta 
 * en el contexto de una sesión activa y verifica si hay nuevos 
 * pagos en la API que aún no están almacenados en la base de datos.
 * 
 * Funcionalidades:
 * - Comprueba si existe un token de acceso válido en la sesión.
 * - Recupera el ID del reino (realm ID) del token de acceso de la sesión.
 * - Crea una instancia de la clase de conexión a la base de datos.
 * - Obtiene la lista de pagos desde la API de QuickBooks.
 * - Obtiene la lista de pagos ya procesados almacenados en la base de datos local.
 * - Filtra los pagos de la API para identificar aquellos que no 
 *   están guardados en la base de datos.
 * - Almacena los nuevos pagos en la base de datos.
 * 
 * Notas:
 * - Se requiere que la clase Db esté correctamente implementada 
 *   y configurada para establecer una conexión con la base de datos.
 * - Asegúrate de que la variable $dataService esté inicializada 
 *   y configurada adecuadamente para realizar consultas a la API de QuickBooks.
 * - Este script puede ser parte de un cron job o ejecutarse manualmente 
 *   según las necesidades de actualización de los datos de pagos.
 */

include "app.php"; // Se incluye el archivo de configuración y manejo de sesiones

if (isset($_SESSION['sessionAccessToken'])) {
   include "./Classes/Db.php"; // Se incluye la clase de conexión a la base de datos

   // Se obtiene el realm ID del token de acceso
   $realmID = (int) $_SESSION['sessionAccessToken']->getRealmID();
   $db = new Db(); // Se crea una instancia de la clase Db

   /**
    * Traemos los pagos procesados que ya tenemos guardados en la base de datos
    */
   $payments = $dataService->Query("SELECT * FROM BillPayment"); // Pagos desde la API
   $paymentsDb = $db->getProcessedPayments($realmID); // Pagos procesados en la base de datos

   // print_r($payments);
   // Se extraen los IDs de los pagos ya almacenados en la base de datos
   $dbPaymentsId = array_column($paymentsDb, "trpr_qb_id");

   // Se filtran los pagos de la API para encontrar aquellos que no están en la base de datos
   $unsavePayment = array_filter($payments, function ($pay) use ($dbPaymentsId) {
      return !in_array($pay->Id, $dbPaymentsId);
   });

   /**
    * Guardar los pagos no guardados en la base de datos
    */
   if (!empty($unsavePayment)) {
      echo $db->savePayments($unsavePayment, $realmID); // Se insertan los nuevos pagos en la base de datos
   }else{
      echo "No hay pagos pendientes desde quickbooks";
   }

   // Se pueden agregar más funcionalidades relacionadas con los pagos de proveedores si es necesario
   // Por ejemplo, se pueden realizar operaciones adicionales sobre los pagos procesados.
}
