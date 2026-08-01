<?php
require_once 'Router.php';
require_once 'cors.php';
require_once 'utils.php';
require_once 'const.php';
// Include legacy services for now until we refactor them into Controllers
require_once 'services/get.php';
require_once 'services/post.php';
require_once 'services/patch.php';
require_once 'services/delete.php';

require_once 'controllers/UserController.php';
require_once 'controllers/SwaggerController.php';
require_once 'controllers/ContactController.php';
require_once 'controllers/SaleController.php';
require_once 'controllers/PurchaseController.php';
require_once 'controllers/PaymentController.php';
require_once 'controllers/DispatchController.php';
require_once 'controllers/BalanceController.php';
require_once 'controllers/CurrencyController.php';
require_once 'controllers/AbonoController.php';

date_default_timezone_set("America/New_York");


$router = new Router();
$userController = new UserController();
$swaggerController = new SwaggerController();
$contactController = new ContactController();
$saleController = new SaleController();
$purchaseController = new PurchaseController();
$paymentController = new PaymentController();
$dispatchController = new DispatchController();
$balanceController = new BalanceController();
$currencyController = new CurrencyController();
$abonoController = new AbonoController();

$router->get('/swagger-json', function () use ($swaggerController) {
  $swaggerController->getDocs();
});

// Validate App Token for all requests
Utils::validateAppToken();

// GET Routes
$router->get('/products', function () {
  getDataFromDB('products');
});
$router->get('/product-by-id', function () {
  getDataFromDB('product-by-id');
});
$router->get('/transactions', function () {
  getDataFromDB('transactions');
});
$router->get('/transaction-by-id', function () {
  getDataFromDB('transaction-by-id');
});
$router->get('/transaction-details', function () {
  getDataFromDB('transaction-details-by-transaction');
});
$router->get('/audit-logs', function () {
  getDataFromDB('audit_logs');
});
$router->get('/system-versions', function () {
  getDataFromDB('system-versions', "nimbus");
});
$router->get('/dashboard-summary', function () {
  getDataFromDB('dashboard-summary');
});

// Contacts
$router->get('/contacts', [$contactController, 'getAll']);
$router->get('/contact-by-id', [$contactController, 'getById']);
$router->get('/contact-balance', [$balanceController, 'getContactBalance']);

// Sales
$router->get('/sales', [$saleController, 'getAll']);
$router->get('/sale-by-id', [$saleController, 'getById']);

// Purchases
$router->get('/purchases', [$purchaseController, 'getAll']);
$router->get('/purchase-by-id', [$purchaseController, 'getById']);

// Payments
$router->get('/payments', [$paymentController, 'getAll']);
$router->get('/payment-by-id', [$paymentController, 'getById']);

// Dispatches
$router->get('/dispatches', [$dispatchController, 'getAll']);
$router->get('/dispatch-by-id', [$dispatchController, 'getById']);

$router->get('/debt-directory', [$balanceController, 'getDirectory']);
$router->get('/get-rates', [$currencyController, 'getRates']);

// Abonos
$router->get('/abonos', [$abonoController, 'getAll']);

// POST Routes
$router->post('/contact-form', function () {
  include_once 'services/sendContactEmail.php';
  sendContactEmail('contact-form');
});
$router->post('/login-user', function () use ($userController) {
  $userController->login();
});
$router->post('/create-user', function () use ($userController) {
  $userController->create();
});
$router->post('/register-user', function () use ($userController) {
  $userController->create();
});
$router->post('/create-product', function () {
  postData('create-product');
});
$router->post('/create-payment', function () {
  postData('create-payment');
});
$router->post('/create-transaction', function () {
  postData('create-transaction');
});
$router->post('/create-transaction-detail', function () {
  postData('create-transaction-detail');
});
$router->post('/create-system-version', function () {
  postData('create-system-version');
});

// New Specialized CRUD POST Routes
$router->post('/create-contact', [$contactController, 'create']);
$router->post('/update-contact', [$contactController, 'update']);
$router->post('/create-sale', [$saleController, 'create']);
$router->post('/update-sale', [$saleController, 'update']);
$router->post('/create-purchase', [$purchaseController, 'create']);
$router->post('/update-purchase', [$purchaseController, 'update']);
$router->post('/create-payment', [$paymentController, 'create']);
$router->post('/create-dispatch', [$dispatchController, 'create']);

$router->post('/create-abono', [$abonoController, 'create']);
$router->post('/update-rates', [$currencyController, 'updateRates']);
$router->post('/update-user', function () {
  updateUser();
});
$router->post('/password-recovery', function () {
  recoveryPassword();
});
$router->post('/create-password', function () {
  $post = json_decode(file_get_contents("php://input"), true);
  echo password_hash($post['password'], PASSWORD_BCRYPT);
});

// PATCH Routes
$router->patch('/update-payment', function () {
  patchData('update-payment');
});
$router->patch('/update-product', function () {
  patchData('update-product');
});
$router->patch('/update-transaction', function () {
  patchData('update-transaction');
});
$router->patch('/update-transaction-detail', function () {
  patchData('update-transaction-detail');
});
$router->patch('/update-system-version', function () {
  patchData('update-system-version');
});
$router->patch('/update-level', function () {
  patchData('update-level');
});
$router->patch('/update-user-role', function () {
  patchData('update-user-role');
});

// PUT Routes
$router->put('/update-user', function () {
  updateUser();
});
$router->put('/update-product', function () {
  patchData('update-product');
});

// DELETE Routes
// Note: delete.php's deleteData function needs to be checked, assuming it accepts the item name
$router->delete('/delete-user', function () {
  deleteData('users');
});

// Soft Delete Routes for New Specialized Tables
$router->delete('/delete-contact', [$contactController, 'delete']);
$router->delete('/delete-sale', [$saleController, 'delete']);
$router->delete('/delete-purchase', [$purchaseController, 'delete']);
$router->delete('/delete-payment', [$paymentController, 'delete']);
$router->delete('/delete-dispatch', [$dispatchController, 'delete']);

$router->delete('/delete-abono', [$abonoController, 'delete']);

$router->dispatch();
