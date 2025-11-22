<?php

/**
 * Quote Controller
 *
 * File: app/Http/Controllers/QuoteController.php
 *
 * Handles all quote operations including:
 * - List quotes with pagination, search, and filtering
 * - Create quotes with line items
 * - Update quotes and line items
 * - Delete quotes
 * - Accept/Decline quote actions
 * - Automatic calculation of totals
 * - Audit logging for all operations
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class QuoteController extends BaseController
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

        $query = Quote::query()->where('tenant_id', $user->tenant_id);

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'LIKE', "%{$search}%")
                  ->orWhere('quote_name', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (!empty($params['account_id'])) {
            $query->where('account_id', $params['account_id']);
        }

        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = strtoupper($params['order'] ?? 'DESC');

        if (in_array($sortField, ['id', 'quote_number', 'total', 'quote_date', 'created_at']) && in_array($sortOrder, ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        $total = $query->count();

        $quotes = $query
            ->with(['account:id,account_name', 'contact:id,first_name,last_name', 'owner:id,first_name,last_name'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->success($response, [
            'quotes' => $quotes,
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
            'quote_name' => 'required|max:255',
            'account_id' => 'exists:accounts,id',
            'contact_id' => 'exists:contacts,id',
            'quote_date' => 'required|date',
            'status' => 'in:draft,sent,accepted,declined,expired',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            // Generate quote number
            $lastQuote = Quote::where('tenant_id', $user->tenant_id)
                ->orderBy('id', 'desc')
                ->first();

            $quoteNumber = 'Q-' . date('Y') . '-' . str_pad(($lastQuote ? $lastQuote->id + 1 : 1), 5, '0', STR_PAD_LEFT);

            $quote = new Quote();
            $quote->tenant_id = $user->tenant_id;
            $quote->uuid = uuid();
            $quote->quote_number = $quoteNumber;
            $quote->owner_id = $data['owner_id'] ?? $user->id;
            $quote->fill($data);

            // Calculate totals from items
            $subtotal = 0;
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $lineTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
                    $lineTotal -= ($item['discount'] ?? 0);
                    $lineTotal += ($item['tax'] ?? 0);
                    $subtotal += $lineTotal;
                }
            }

            $quote->subtotal = $subtotal;
            $quote->total = $subtotal + ($data['tax'] ?? 0) + ($data['shipping'] ?? 0) - ($data['discount'] ?? 0);

            $quote->save();

            // Create quote items
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $itemData) {
                    $item = new QuoteItem();
                    $item->quote_id = $quote->id;
                    $item->line_number = $index + 1;
                    $item->product_id = $itemData['product_id'] ?? null;
                    $item->product_name = $itemData['product_name'];
                    $item->description = $itemData['description'] ?? null;
                    $item->quantity = $itemData['quantity'] ?? 1;
                    $item->unit_price = $itemData['unit_price'];
                    $item->discount = $itemData['discount'] ?? 0;
                    $item->tax = $itemData['tax'] ?? 0;
                    $item->line_total = ($item->quantity * $item->unit_price) - $item->discount + $item->tax;
                    $item->save();
                }
            }

            $this->audit->log(
                'quote',
                $quote->id,
                'created',
                null,
                $quote->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $quote->load('items', 'account', 'contact', 'owner'), 'Quote created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Quote creation failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to create quote: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $quote = Quote::where('tenant_id', $user->tenant_id)
            ->with(['items.product', 'account', 'contact', 'opportunity', 'owner'])
            ->find($id);

        if (!$quote) {
            return $this->notFound($response, 'Quote not found');
        }

        return $this->success($response, $quote);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $quote = Quote::where('tenant_id', $user->tenant_id)->find($id);

        if (!$quote) {
            return $this->notFound($response, 'Quote not found');
        }

        try {
            DB::beginTransaction();

            $oldValues = $quote->toArray();

            $quote->fill($data);

            // Recalculate totals if items are provided
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete old items
                QuoteItem::where('quote_id', $quote->id)->delete();

                // Create new items and calculate total
                $subtotal = 0;
                foreach ($data['items'] as $index => $itemData) {
                    $item = new QuoteItem();
                    $item->quote_id = $quote->id;
                    $item->line_number = $index + 1;
                    $item->product_id = $itemData['product_id'] ?? null;
                    $item->product_name = $itemData['product_name'];
                    $item->description = $itemData['description'] ?? null;
                    $item->quantity = $itemData['quantity'] ?? 1;
                    $item->unit_price = $itemData['unit_price'];
                    $item->discount = $itemData['discount'] ?? 0;
                    $item->tax = $itemData['tax'] ?? 0;
                    $item->line_total = ($item->quantity * $item->unit_price) - $item->discount + $item->tax;
                    $item->save();

                    $subtotal += $item->line_total;
                }

                $quote->subtotal = $subtotal;
                $quote->total = $subtotal + ($data['tax'] ?? 0) + ($data['shipping'] ?? 0) - ($data['discount'] ?? 0);
            }

            $quote->save();

            $this->audit->log(
                'quote',
                $quote->id,
                'updated',
                $oldValues,
                $quote->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $quote->load('items', 'account', 'contact', 'owner'), 'Quote updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Quote update failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to update quote', 500);
        }
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $quote = Quote::where('tenant_id', $user->tenant_id)->find($id);

        if (!$quote) {
            return $this->notFound($response, 'Quote not found');
        }

        try {
            DB::beginTransaction();

            $quoteData = $quote->toArray();
            $quote->delete();

            $this->audit->log(
                'quote',
                $quote->id,
                'deleted',
                $quoteData,
                null,
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, null, 'Quote deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Quote deletion failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to delete quote', 500);
        }
    }

    public function accept(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $quote = Quote::where('tenant_id', $user->tenant_id)->find($id);

        if (!$quote) {
            return $this->notFound($response, 'Quote not found');
        }

        if ($quote->isExpired()) {
            return $this->error($response, 'Cannot accept an expired quote', 400);
        }

        try {
            DB::beginTransaction();

            $oldValues = $quote->toArray();

            $quote->status = 'accepted';
            $quote->save();

            $this->audit->log(
                'quote',
                $quote->id,
                'accepted',
                $oldValues,
                $quote->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $quote, 'Quote accepted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to accept quote', 500);
        }
    }

    public function decline(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $quote = Quote::where('tenant_id', $user->tenant_id)->find($id);

        if (!$quote) {
            return $this->notFound($response, 'Quote not found');
        }

        try {
            DB::beginTransaction();

            $oldValues = $quote->toArray();

            $quote->status = 'declined';
            $quote->save();

            $this->audit->log(
                'quote',
                $quote->id,
                'declined',
                $oldValues,
                $quote->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $quote, 'Quote declined');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to decline quote', 500);
        }
    }
}
