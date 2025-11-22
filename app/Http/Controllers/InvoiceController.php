<?php

/**
 * Invoice Controller
 *
 * File: app/Http/Controllers/InvoiceController.php
 *
 * Handles all invoice operations including:
 * - List invoices with pagination, search, and filtering
 * - Create invoices from quotes or standalone with line items
 * - Update invoices and line items
 * - Delete invoices
 * - Record payments and update invoice status
 * - Automatic calculation of totals and balances
 * - Audit logging for all operations
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Quote;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class InvoiceController extends BaseController
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

        $query = Invoice::query()->where('tenant_id', $user->tenant_id);

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhere('invoice_name', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (!empty($params['account_id'])) {
            $query->where('account_id', $params['account_id']);
        }

        if (!empty($params['overdue']) && $params['overdue'] === 'true') {
            $query->where('due_date', '<', date('Y-m-d'))
                  ->whereNotIn('status', ['paid', 'cancelled']);
        }

        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = strtoupper($params['order'] ?? 'DESC');

        if (in_array($sortField, ['id', 'invoice_number', 'total', 'balance', 'invoice_date', 'due_date', 'created_at']) && in_array($sortOrder, ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        $total = $query->count();

        $invoices = $query
            ->with(['account:id,account_name', 'contact:id,first_name,last_name', 'owner:id,first_name,last_name', 'quote:id,quote_number'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->success($response, [
            'invoices' => $invoices,
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
            'invoice_name' => 'required|max:255',
            'account_id' => 'exists:accounts,id',
            'contact_id' => 'exists:contacts,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'status' => 'in:draft,sent,partial,paid,overdue,cancelled',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            // Generate invoice number
            $lastInvoice = Invoice::where('tenant_id', $user->tenant_id)
                ->orderBy('id', 'desc')
                ->first();

            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad(($lastInvoice ? $lastInvoice->id + 1 : 1), 5, '0', STR_PAD_LEFT);

            $invoice = new Invoice();
            $invoice->tenant_id = $user->tenant_id;
            $invoice->uuid = uuid();
            $invoice->invoice_number = $invoiceNumber;
            $invoice->owner_id = $data['owner_id'] ?? $user->id;
            $invoice->fill($data);

            // If created from quote, copy quote items
            if (!empty($data['quote_id'])) {
                $quote = Quote::where('tenant_id', $user->tenant_id)->find($data['quote_id']);
                if ($quote) {
                    $quote->load('items');
                    $data['items'] = $quote->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'discount' => $item->discount,
                            'tax' => $item->tax,
                        ];
                    })->toArray();
                }
            }

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

            $invoice->subtotal = $subtotal;
            $invoice->total = $subtotal + ($data['tax'] ?? 0) + ($data['shipping'] ?? 0) - ($data['discount'] ?? 0);
            $invoice->amount_paid = 0;
            $invoice->balance = $invoice->total;

            $invoice->save();

            // Create invoice items
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $itemData) {
                    $item = new InvoiceItem();
                    $item->invoice_id = $invoice->id;
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
                'invoice',
                $invoice->id,
                'created',
                null,
                $invoice->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $invoice->load('items', 'account', 'contact', 'owner'), 'Invoice created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Invoice creation failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to create invoice: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $invoice = Invoice::where('tenant_id', $user->tenant_id)
            ->with(['items.product', 'account', 'contact', 'quote', 'owner', 'payments'])
            ->find($id);

        if (!$invoice) {
            return $this->notFound($response, 'Invoice not found');
        }

        return $this->success($response, $invoice);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $invoice = Invoice::where('tenant_id', $user->tenant_id)->find($id);

        if (!$invoice) {
            return $this->notFound($response, 'Invoice not found');
        }

        // Prevent editing paid invoices
        if ($invoice->status === 'paid') {
            return $this->error($response, 'Cannot modify a paid invoice', 400);
        }

        try {
            DB::beginTransaction();

            $oldValues = $invoice->toArray();

            $invoice->fill($data);

            // Recalculate totals if items are provided
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete old items
                InvoiceItem::where('invoice_id', $invoice->id)->delete();

                // Create new items and calculate total
                $subtotal = 0;
                foreach ($data['items'] as $index => $itemData) {
                    $item = new InvoiceItem();
                    $item->invoice_id = $invoice->id;
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

                $invoice->subtotal = $subtotal;
                $invoice->total = $subtotal + ($data['tax'] ?? 0) + ($data['shipping'] ?? 0) - ($data['discount'] ?? 0);
                $invoice->balance = $invoice->total - $invoice->amount_paid;
            }

            $invoice->save();

            $this->audit->log(
                'invoice',
                $invoice->id,
                'updated',
                $oldValues,
                $invoice->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $invoice->load('items', 'account', 'contact', 'owner'), 'Invoice updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Invoice update failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to update invoice', 500);
        }
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $invoice = Invoice::where('tenant_id', $user->tenant_id)->find($id);

        if (!$invoice) {
            return $this->notFound($response, 'Invoice not found');
        }

        // Prevent deleting paid invoices with payments
        if ($invoice->amount_paid > 0) {
            return $this->error($response, 'Cannot delete an invoice with payments. Cancel it instead.', 400);
        }

        try {
            DB::beginTransaction();

            $invoiceData = $invoice->toArray();
            $invoice->delete();

            $this->audit->log(
                'invoice',
                $invoice->id,
                'deleted',
                $invoiceData,
                null,
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, null, 'Invoice deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Invoice deletion failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to delete invoice', 500);
        }
    }

    public function markPaid(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $invoice = Invoice::where('tenant_id', $user->tenant_id)->find($id);

        if (!$invoice) {
            return $this->notFound($response, 'Invoice not found');
        }

        if ($invoice->status === 'paid') {
            return $this->error($response, 'Invoice is already paid', 400);
        }

        $rules = [
            'amount' => 'required|numeric',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,check,credit_card,bank_transfer,paypal,other',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            return $this->error($response, 'Payment amount must be greater than zero', 400);
        }

        if ($amount > $invoice->balance) {
            return $this->error($response, 'Payment amount cannot exceed the remaining balance', 400);
        }

        try {
            DB::beginTransaction();

            $oldValues = $invoice->toArray();

            // Generate payment number
            $lastPayment = Payment::where('tenant_id', $user->tenant_id)
                ->orderBy('id', 'desc')
                ->first();

            $paymentNumber = 'PAY-' . date('Y') . '-' . str_pad(($lastPayment ? $lastPayment->id + 1 : 1), 5, '0', STR_PAD_LEFT);

            // Create payment record
            $payment = new Payment();
            $payment->tenant_id = $user->tenant_id;
            $payment->invoice_id = $invoice->id;
            $payment->payment_number = $paymentNumber;
            $payment->amount = $amount;
            $payment->payment_date = $data['payment_date'];
            $payment->payment_method = $data['payment_method'];
            $payment->reference_number = $data['reference_number'] ?? null;
            $payment->notes = $data['notes'] ?? null;
            $payment->save();

            // Update invoice
            $invoice->amount_paid += $amount;
            $invoice->balance = $invoice->total - $invoice->amount_paid;

            // Update status
            if ($invoice->balance <= 0.01) { // Account for floating point precision
                $invoice->status = 'paid';
                $invoice->paid_date = $data['payment_date'];
            } else if ($invoice->amount_paid > 0) {
                $invoice->status = 'partial';
            }

            $invoice->save();

            $this->audit->log(
                'invoice',
                $invoice->id,
                'payment_recorded',
                $oldValues,
                $invoice->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );
            $this->audit->log(
                'payment',
                $payment->id,
                'created',
                null,
                $payment->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, [
                'invoice' => $invoice->load('payments'),
                'payment' => $payment,
            ], 'Payment recorded successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Payment recording failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to record payment', 500);
        }
    }
}
