<?php
require_once(__DIR__ . '/vendor/autoload.php');

use QuickBooksOnline\API\DataService\DataService;

session_start();

$config = include(__DIR__ . '/config.php');
include __DIR__ . "/Classes/Db.php";

$db = new Db();

$dataService = DataService::Configure([
   'auth_mode' => 'oauth2',
   'ClientID' => $config['client_id'],
   'ClientSecret' =>  $config['client_secret'],
   'RedirectURI' => $config['oauth_redirect_uri'],
   'scope' => $config['oauth_scope'],
   'baseUrl' => "production"
]);

$OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
$authUrl = $OAuth2LoginHelper->getAuthorizationCodeURL();

$_SESSION['authUrl'] = $authUrl;

if (isset($_SESSION['sessionAccessToken'])) {
   $accessTokenObj = $_SESSION['sessionAccessToken'];
   $dataService->updateOAuth2Token($accessTokenObj);

   $CompanyInfo = $dataService->getCompanyInfo();
   if ($CompanyInfo) {
      $realmId = $accessTokenObj->getRealmID();
      $accessToken = $accessTokenObj->getAccessToken();
      $refreshToken = $accessTokenObj->getRefreshToken();
      $refreshTokenExpiresIn = $accessTokenObj->getRefreshTokenExpiresAt();
      $accessTokenExpiresIn = $accessTokenObj->getAccessTokenExpiresAt();

      // Convierte el objeto en un array de datos accesibles
      $accessTokenData = [
         'accessToken' => $accessTokenObj->getAccessToken(),
         'tokenType' => "Bearer ",
         'refreshToken' => $accessTokenObj->getRefreshToken(),
         'accessTokenExpiresAt' => $accessTokenObj->getAccessTokenExpiresAt(),
         'refreshTokenExpiresAt' => $accessTokenObj->getRefreshTokenExpiresAt(),
         'clientID' => $accessTokenObj->getClientID(),
         'clientSecret' => $accessTokenObj->getClientSecret(),
         'realmID' => $accessTokenObj->getRealmID(),
      ];

      // Convierte el array en un JSON
      $json = json_encode($accessTokenData, JSON_PRETTY_PRINT);

      $db->saveTokens($realmId, $accessToken, $refreshToken, $refreshTokenExpiresIn, $accessTokenExpiresIn, $json);

      echo "<a href='sessionDestroy.php'>Haz clic aquí para autenticar otra empresa</a>";
   } else {
      $error = $dataService->getLastError();
      echo "Error al obtener la información de la compañía:\n";
      echo "Código: " . $error->getHttpStatusCode() . "\n";
      echo "Mensaje: " . $error->getResponseBody() . "\n";
   }
} else {
   echo "No se encontró un accessTokenObj válido en la sesión.\n";
   echo "<a href='{$authUrl}'>Haz clic aquí para autenticarte</a>";
}
