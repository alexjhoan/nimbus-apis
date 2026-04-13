<?php

$allowed_domains = [
  'https://bwtne.com',
  'https://helpers.bluewayisbetter.com',
  'https://helpers.bwtne.com',
  'https://v1.bwtne.com',
  'http://localhost:3000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Check if origin is allowed
if (in_array($origin, $allowed_domains)) {
  header("Access-Control-Allow-Origin: " . $origin);
} else {
  // Default fallback
  header("Access-Control-Allow-Origin: https://nimbus.casamedicagi.com");
}

header("Vary: Origin");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,PUT,POST,DELETE");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, X-Tenant-ID, X-Tenancy-Token, Content-Type, Accept, Access-Control-Request-Method, Access-Control-Request-Headers, Authorization");

// Handle preflight requests early
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  header("HTTP/1.1 200 OK");
  exit();
}
