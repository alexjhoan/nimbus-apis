<?php

class Router
{
  private $routes = [];

  public function get($uri, $callback)
  {
    $this->addRoute('GET', $uri, $callback);
  }

  public function post($uri, $callback)
  {
    $this->addRoute('POST', $uri, $callback);
  }

  public function put($uri, $callback)
  {
    $this->addRoute('PUT', $uri, $callback);
  }

  public function patch($uri, $callback)
  {
    $this->addRoute('PATCH', $uri, $callback);
  }

  public function delete($uri, $callback)
  {
    $this->addRoute('DELETE', $uri, $callback);
  }

  private function addRoute($method, $uri, $callback)
  {
    $this->routes[] = [
      'method' => $method,
      'uri' => $uri,
      'callback' => $callback
    ];
  }

  public function dispatch()
  {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Remove query string and trim trailing slashes
    $uri = rtrim($uri, '/');

    // Basic handling for local development where project might be in a subdirectory
    // Ad-hoc adjustment: remove project folder prefix if it exists
    // In a real environment, DocumentRoot should point to public/ or .htaccess rewrite rules handle this.
    // For now, we assume the URI matches the registered route directly or logic needs to adapt.

    // Let's iterate and try to match
    foreach ($this->routes as $route) {
      if ($route['method'] === $method) {
        // Simple exact match for now. Regex can be added later for parameters like /users/{id}
        // Check if the registered URI coincides with the end of the Request URI to support subfolders
        if ($this->matches($route['uri'], $uri)) {
          call_user_func($route['callback']);
          return;
        }
      }
    }

    $this->sendNotFound();
  }

  private function matches($routeUri, $requestUri)
  {
    // If route is /users, we check if requestUri ends with /users
    // This is a naive approach for subfolder support without robust rewrite rules
    if ($routeUri === '/' && $requestUri === '') return true; // root

    // Exact match
    if ($routeUri === $requestUri) return true;

    // Suffix match (careful with collisions)
    $length = strlen($routeUri);
    if ($length == 0) {
      return true;
    }

    return (substr($requestUri, -$length) === $routeUri);
  }

  private function sendNotFound()
  {
    header("HTTP/1.0 404 Not Found");
    echo json_encode([
      'status' => 404,
      'statusText' => 'Not Found'
    ]);
  }
}
