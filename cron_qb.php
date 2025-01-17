<?php 

/**
 * 1. Primero vamos a refrescar los tokens de acceso de las empresas activas
 * 2. Buscamos e insertamos los proveedores en los diferentes REALMS
 * 3. Buscamos, filtramos e insertamos los pagos de quickbooks que se subieron hace 1 hora a partir del momento que ejecutamos
 * 4. Insertamos en la API de banco general los pagos no procesados de 50 en 50
 * 5. Vamos a actualizar el estado de los pagos desde la API de banco general
 */

 include_once "./refreshToken.php";
 include_once "./read_vendor.php";
 include_once "./bill_payment.php";
?>