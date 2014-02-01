<?php
require_once('../vendor/autoload.php');

$classes = glob('../class/*.php');
foreach ($classes as $class) {
    require_once($class);   
}

ini_set('error_log', '../data/error.log');
$app = new \Slim\Slim();
$app->get('/:version/:uri+', 'main');
$app->run();

function response($httpResponseCode, $result)
{
    http_response_code($httpResponseCode);
    die(json_encode(array(
        'result' => $result,
    )));
}

function main($version, $uri)
{
    global $app;
    $action = strtolower($app->request->getMethod());

    if (!file_exists("../api/$version")) {
        response(SLHTTPResponseCodes::BAD_REQUEST, SLErrorMessages::INVALID_VERSION . " ($version)");
    }

    if (count($uri) % 2 === 0) {
        $action .= ucfirst(array_pop($uri));
    }

    $resourcePath = "../api/$version";
    $constructParameters = array();
    while (count($uri) !== 1) {
        $resourceName = ucfirst(array_shift($uri));
        $resourcePath .= "/SL$resourceName";
        $resourceId = array_shift($uri);
        $constructParameters[] = $resourceId;

        if (!file_exists($resourcePath)) {
            response(SLHTTPResponseCodes::BAD_REQUEST, SLErrorMessages::INVALID_RESOURCE . " ($resourceName)");
        }

        if (!is_numeric($resourceId)) {
            response(SLHTTPResponseCodes::BAD_REQUEST, SLErrorMessages::INVALID_RESOURCE_ID . " ($resourceId)");
        }
    }

    $resourceName = ucfirst(array_shift($uri));
    $resourceClass = "SL$resourceName";
    $resourcePath .= "/SL$resourceName.php";
    if (!file_exists($resourcePath)) {
        response(SLHTTPResponseCodes::BAD_REQUEST, SLErrorMessages::INVALID_RESOURCE . " ($resourceName)");
    }

    require_once($resourcePath);

    if (!method_exists($resourceClass, $action)) {
        response(SLHTTPResponseCodes::BAD_REQUEST, SLErrorMessages::INVALID_ACTION . " ($action)");
    }

    try {
        $resourceKlass = new ReflectionClass($resourceClass);
        $resource = $resourceKlass->newInstanceArgs($constructParameters);
        $result = $resource->run($action, $_GET);
        
        response(SLHTTPResponseCodes::OK, $result);
    } catch (Exception $error) {
        if ($error instanceof SLException) {
            response($error->getHttpResponseCode(), $error->getMessage());
        }

        error_log($error);
        response(SLHTTPResponseCodes::INTERNAL_SERVER_ERROR, SLErrorMessages::UNKNOWN_ERROR);
    }
}