<?php

require "vendor/autoload.php";
require "./Classes/Db.php";
require "./Classes/ServiceTransact.php";
require "./Classes/HttpClient.php";
$config = include "./config.php";
/**
 * bgeneral.php
 * 
 * Este archivo envia a la API DE banco general
 * 
 * 
 * Rutas
 * ?r=postPays
 * ?r=getPays
 */

if (isset($_GET["r"])) {
   $route = $_GET["r"];

   switch ($route) {
      case "postPays":
         /**
          * envia a la API todos los pagos
          */
         $db = new Db();
         $httpClient = new HttpClient();
         $pagoProcessor = new ServiceTransact($db, $httpClient);

         // $pagoProcessor->procesarPagos();

         break;
      case "getPays":
         /**
          * Trae todos los pagos
          */

         break;
      default:
         http_response_code(405);
      break;

   }
} else {
   http_response_code(405);
}
