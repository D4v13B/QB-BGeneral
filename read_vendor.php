<?php

/**
 * read_vendor.php
 * 
 * CRON JOB
 * 
 * Este script se ejecuta como un trabajo cron para leer y procesar la información 
 * de los proveedores de la empresa desde la API de QuickBooks. Su función principal 
 * es recuperar los datos de los proveedores y almacenarlos en la base de datos local 
 * para su posterior uso en el sistema.
 * 
 * Funcionalidades:
 * - Conexión a la base de datos para almacenar información de proveedores.
 * - Recuperación de proveedores desde la API de QuickBooks.
 * - Inserción de nuevos proveedores en la base de datos, evitando duplicados.
 * 
 * Uso:
 * Este archivo debe ser programado para ejecutarse automáticamente a intervalos 
 * regulares mediante un cron job, asegurando que la base de datos siempre tenga 
 * la información más actualizada sobre los proveedores.
 * 
 * Notas:
 * - Asegúrate de que las credenciales de la base de datos y la configuración de 
 *   la API de QuickBooks estén correctamente establecidas en el archivo de configuración.
 * - Se recomienda implementar un manejo de errores y registros para 
 *   monitorear la ejecución del script y detectar posibles problemas.
 */

include "app.php";
if (isset($_SESSION['sessionAccessToken'])) {
   include "./Classes/Db.php"; // Se incluye la clase de conexión a la base de datos

   // Se obtiene el realm ID del token de acceso
   $realmID = (int) $_SESSION['sessionAccessToken']->getRealmID();
   $db = new Db(); // Se crea una instancia de la clase Db

   /**
    * Traemos los proveedores que ya tenemos guardados en la base de datos
    */
   $vendors = $dataService->Query("SELECT * FROM vendor"); // Proveedores de la API
   $vendorsDb = $db->getVendors($realmID); // Proveedores de la base de datos

   // Se extraen los IDs de los proveedores ya almacenados en la base de datos
   $dbVendorIds = array_column($vendorsDb, "qb_vendor_id");

   // Se filtran los proveedores de la API para encontrar aquellos que no están en la base de datos
   $unsaveVendor = array_filter($vendors, function ($vendor) use ($dbVendorIds) {
      return !in_array($vendor->Id, $dbVendorIds);
   });

   /**
    * Guardar los proveedores no guardados en la base de datos
    */
   if (!empty($unsaveVendor)) {
      $db->saveVendors($unsaveVendor, $realmID); // Se insertan los nuevos proveedores en la base de datos
   }

   // Se pueden agregar más funcionalidades relacionadas con los pagos de proveedores si es necesario
   // $vendorPayments = $dataService->Query("SELECT * FROM BillPayment");
}
