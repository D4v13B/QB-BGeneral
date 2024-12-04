<?php
require_once(__DIR__ . '/vendor/autoload.php');

use QuickBooksOnline\API\DataService\DataService;

$config = include('config.php');

session_start();

$dataService = DataService::Configure(array(
   'auth_mode' => 'oauth2',
   'ClientID' => $config['client_id'],
   'ClientSecret' =>  $config['client_secret'],
   'RedirectURI' => $config['oauth_redirect_uri'],
   'scope' => $config['oauth_scope'],
   'baseUrl' => "production"
));

$OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
$authUrl = $OAuth2LoginHelper->getAuthorizationCodeURL();

// Store the url in PHP Session Object;
$_SESSION['authUrl'] = $authUrl;

//set the access token using the auth object
if (isset($_SESSION['sessionAccessToken'])) {

   $accessToken = $_SESSION['sessionAccessToken'];
   $accessTokenJson = [
      'token_type' => 'bearer',
      'access_token' => $accessToken->getAccessToken(),
      'refresh_token' => $accessToken->getRefreshToken(),
      'x_refresh_token_expires_in' => $accessToken->getRefreshTokenExpiresAt(),
      'expires_in' => $accessToken->getAccessTokenExpiresAt()
   ];

   $dataService->updateOAuth2Token($accessToken);
   $oauthLoginHelper = $dataService->getOAuth2LoginHelper();
   $CompanyInfo = $dataService->getCompanyInfo();

   // print_r($CompanyInfo);
} else {
   echo "No se pudo acceder";
}
