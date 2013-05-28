<?php
use Symfony\Component\HttpFoundation\Request;

$rootDir = __DIR__.'/..';
require_once $rootDir.'/app/bootstrap.php.cache';
require_once $rootDir.'/app/AppKernel.php';

$kernel = new AppKernel('temp', true);
$kernel->loadClassCache();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
