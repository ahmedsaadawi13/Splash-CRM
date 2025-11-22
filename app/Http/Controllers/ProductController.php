<?php

/**
 * Product Controller
 *
 * File: app/Http/Controllers/ProductController.php
 *
 * Handles all CRUD operations for products including:
 * - List with pagination, search, and filtering by category/family
 * - Create, read, update, delete operations
 * - Price management (unit, cost, list prices)
 * - Inventory tracking and stock management
 * - Audit logging for all operations
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class ProductController extends BaseController
{
    private $validator;
    private $audit;

    public function __construct(ValidationService $validator, AuditService $audit)
    {
        $this->validator = $validator;
        $this->audit = $audit;
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $user = $request->getAttribute('user');

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 20)));

        $query = Product::query()->where('tenant_id', $user->tenant_id);

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'LIKE', "%{$search}%")
                  ->orWhere('product_code', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($params['product_category'])) {
            $query->where('product_category', $params['product_category']);
        }

        if (!empty($params['product_family'])) {
            $query->where('product_family', $params['product_family']);
        }

        if (isset($params['active'])) {
            $query->where('active', (bool)$params['active']);
        }

        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = strtoupper($params['order'] ?? 'DESC');

        if (in_array($sortField, ['id', 'product_name', 'unit_price', 'created_at']) && in_array($sortOrder, ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        $total = $query->count();

        $products = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->success($response, [
            'products' => $products,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');

        $rules = [
            'product_name' => 'required|max:255',
            'product_code' => 'max:100',
            'unit_price' => 'required|numeric',
            'cost_price' => 'numeric',
            'list_price' => 'numeric',
            'quantity_in_stock' => 'numeric',
            'reorder_level' => 'numeric',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $product = new Product();
            $product->tenant_id = $user->tenant_id;
            $product->uuid = uuid();
            $product->fill($data);
            $product->save();

            $this->audit->log(
                'product',
                $product->id,
                'created',
                null,
                $product->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $product, 'Product created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create product', 500);
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $product = Product::where('tenant_id', $user->tenant_id)->find($id);

        if (!$product) {
            return $this->notFound($response, 'Product not found');
        }

        return $this->success($response, $product);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $product = Product::where('tenant_id', $user->tenant_id)->find($id);

        if (!$product) {
            return $this->notFound($response, 'Product not found');
        }

        $rules = [
            'product_name' => 'max:255',
            'product_code' => 'max:100',
            'unit_price' => 'numeric',
            'cost_price' => 'numeric',
            'list_price' => 'numeric',
            'quantity_in_stock' => 'numeric',
            'reorder_level' => 'numeric',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $oldValues = $product->toArray();
            $product->fill($data);
            $product->save();

            $this->audit->log(
                'product',
                $product->id,
                'updated',
                $oldValues,
                $product->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $product, 'Product updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update product', 500);
        }
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $product = Product::where('tenant_id', $user->tenant_id)->find($id);

        if (!$product) {
            return $this->notFound($response, 'Product not found');
        }

        try {
            DB::beginTransaction();

            $productData = $product->toArray();
            $product->delete();

            $this->audit->log(
                'product',
                $product->id,
                'deleted',
                $productData,
                null,
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, null, 'Product deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to delete product', 500);
        }
    }
}
