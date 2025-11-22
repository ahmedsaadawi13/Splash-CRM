<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\AuthMiddleware;
use Slim\Routing\RouteCollectorProxy;

/**
 * API Routes - Version 1
 *
 * All routes are prefixed with /api/v1
 */

$app->group('/api/v1', function (RouteCollectorProxy $group) {

    // Public routes (no authentication required)
    $group->post('/auth/login', [AuthController::class, 'login']);
    $group->post('/auth/register', [AuthController::class, 'register']);
    $group->post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    $group->post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    // Protected routes (authentication required)
    $group->group('', function (RouteCollectorProxy $protected) {

        // Auth routes
        $protected->get('/auth/me', [AuthController::class, 'me']);
        $protected->post('/auth/refresh', [AuthController::class, 'refresh']);
        $protected->post('/auth/logout', [AuthController::class, 'logout']);

        // Leads
        $protected->get('/leads', 'App\Http\Controllers\LeadController:index');
        $protected->post('/leads', 'App\Http\Controllers\LeadController:store');
        $protected->get('/leads/{id}', 'App\Http\Controllers\LeadController:show');
        $protected->put('/leads/{id}', 'App\Http\Controllers\LeadController:update');
        $protected->delete('/leads/{id}', 'App\Http\Controllers\LeadController:destroy');
        $protected->post('/leads/{id}/convert', 'App\Http\Controllers\LeadController:convert');

        // Contacts
        $protected->get('/contacts', 'App\Http\Controllers\ContactController:index');
        $protected->post('/contacts', 'App\Http\Controllers\ContactController:store');
        $protected->get('/contacts/{id}', 'App\Http\Controllers\ContactController:show');
        $protected->put('/contacts/{id}', 'App\Http\Controllers\ContactController:update');
        $protected->delete('/contacts/{id}', 'App\Http\Controllers\ContactController:destroy');

        // Accounts
        $protected->get('/accounts', 'App\Http\Controllers\AccountController:index');
        $protected->post('/accounts', 'App\Http\Controllers\AccountController:store');
        $protected->get('/accounts/{id}', 'App\Http\Controllers\AccountController:show');
        $protected->put('/accounts/{id}', 'App\Http\Controllers\AccountController:update');
        $protected->delete('/accounts/{id}', 'App\Http\Controllers\AccountController:destroy');

        // Opportunities
        $protected->get('/opportunities', 'App\Http\Controllers\OpportunityController:index');
        $protected->post('/opportunities', 'App\Http\Controllers\OpportunityController:store');
        $protected->get('/opportunities/{id}', 'App\Http\Controllers\OpportunityController:show');
        $protected->put('/opportunities/{id}', 'App\Http\Controllers\OpportunityController:update');
        $protected->delete('/opportunities/{id}', 'App\Http\Controllers\OpportunityController:destroy');

        // Activities
        $protected->get('/activities', 'App\Http\Controllers\ActivityController:index');
        $protected->post('/activities', 'App\Http\Controllers\ActivityController:store');
        $protected->get('/activities/{id}', 'App\Http\Controllers\ActivityController:show');
        $protected->put('/activities/{id}', 'App\Http\Controllers\ActivityController:update');
        $protected->delete('/activities/{id}', 'App\Http\Controllers\ActivityController:destroy');

        // Products
        $protected->get('/products', 'App\Http\Controllers\ProductController:index');
        $protected->post('/products', 'App\Http\Controllers\ProductController:store');
        $protected->get('/products/{id}', 'App\Http\Controllers\ProductController:show');
        $protected->put('/products/{id}', 'App\Http\Controllers\ProductController:update');
        $protected->delete('/products/{id}', 'App\Http\Controllers\ProductController:destroy');

        // Reports
        $protected->get('/reports', 'App\Http\Controllers\ReportController:index');
        $protected->post('/reports', 'App\Http\Controllers\ReportController:store');
        $protected->post('/reports/run', 'App\Http\Controllers\ReportController:run');
        $protected->get('/reports/{id}', 'App\Http\Controllers\ReportController:show');
        $protected->get('/reports/{id}/export', 'App\Http\Controllers\ReportController:export');

        // Workflows
        $protected->get('/workflows', 'App\Http\Controllers\WorkflowController:index');
        $protected->post('/workflows', 'App\Http\Controllers\WorkflowController:store');
        $protected->post('/workflows/execute', 'App\Http\Controllers\WorkflowController:execute');
        $protected->get('/workflows/{id}', 'App\Http\Controllers\WorkflowController:show');

        // Users
        $protected->get('/users', 'App\Http\Controllers\UserController:index');
        $protected->post('/users', 'App\Http\Controllers\UserController:store');
        $protected->get('/users/{id}', 'App\Http\Controllers\UserController:show');
        $protected->put('/users/{id}', 'App\Http\Controllers\UserController:update');
        $protected->delete('/users/{id}', 'App\Http\Controllers\UserController:destroy');

        // Roles & Permissions
        $protected->get('/roles', 'App\Http\Controllers\RoleController:index');
        $protected->post('/roles', 'App\Http\Controllers\RoleController:store');
        $protected->get('/roles/{id}', 'App\Http\Controllers\RoleController:show');
        $protected->put('/roles/{id}', 'App\Http\Controllers\RoleController:update');
        $protected->delete('/roles/{id}', 'App\Http\Controllers\RoleController:destroy');
        $protected->get('/permissions', 'App\Http\Controllers\RoleController:permissions');

        // Quotes
        $protected->get('/quotes', 'App\Http\Controllers\QuoteController:index');
        $protected->post('/quotes', 'App\Http\Controllers\QuoteController:store');
        $protected->get('/quotes/{id}', 'App\Http\Controllers\QuoteController:show');
        $protected->put('/quotes/{id}', 'App\Http\Controllers\QuoteController:update');
        $protected->delete('/quotes/{id}', 'App\Http\Controllers\QuoteController:destroy');
        $protected->post('/quotes/{id}/accept', 'App\Http\Controllers\QuoteController:accept');
        $protected->post('/quotes/{id}/decline', 'App\Http\Controllers\QuoteController:decline');

        // Invoices
        $protected->get('/invoices', 'App\Http\Controllers\InvoiceController:index');
        $protected->post('/invoices', 'App\Http\Controllers\InvoiceController:store');
        $protected->get('/invoices/{id}', 'App\Http\Controllers\InvoiceController:show');
        $protected->put('/invoices/{id}', 'App\Http\Controllers\InvoiceController:update');
        $protected->delete('/invoices/{id}', 'App\Http\Controllers\InvoiceController:destroy');
        $protected->post('/invoices/{id}/mark-paid', 'App\Http\Controllers\InvoiceController:markPaid');

    })->add(AuthMiddleware::class);
});
