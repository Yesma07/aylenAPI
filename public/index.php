<?php

use App\Core\Router;
use App\Core\Request;
use App\Core\Exceptions\HttpException;
use Dotenv\Dotenv;

ob_start(); // Iniciar el buffer de salida

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$request = new Request();

// Detectar el path: /create, /search, etc.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$endpoint = trim($path, '/'); // ejemplo: "create"

try {
    $response = Router::handle($request, $endpoint); // Le pasamos el endpoint como acción
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $response]);
} catch (HttpException $e) {
    http_response_code($e->getStatusCode());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error interno del servidor',
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
