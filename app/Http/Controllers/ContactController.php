<?php

/**
 * Contact Controller
 *
 * File: app/Http/Controllers/ContactController.php
 *
 * Handles all CRUD operations for contacts including:
 * - List with pagination, search, and filtering by account
 * - Create, read, update, delete operations
 * - Relationship management with accounts and users
 * - Audit logging for all operations
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class ContactController extends BaseController
{
    private $validator;
    private $audit;

    public function __construct(ValidationService $validator, AuditService $audit)
    {
        $this->validator = $validator;
        $this->audit = $audit;
    }

    /**
     * List all contacts
     * GET /api/v1/contacts
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $user = $request->getAttribute('user');

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 20)));

        $query = Contact::query()->where('tenant_id', $user->tenant_id);

        // Search
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        // Filters
        if (!empty($params['account_id'])) {
            $query->where('account_id', $params['account_id']);
        }

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (!empty($params['owner_id'])) {
            $query->where('owner_id', $params['owner_id']);
        }

        // Sorting
        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = strtoupper($params['order'] ?? 'DESC');
        $allowedSorts = ['id', 'first_name', 'last_name', 'email', 'created_at', 'updated_at'];

        if (in_array($sortField, $allowedSorts) && in_array($sortOrder, ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        $total = $query->count();

        $contacts = $query
            ->with([
                'account:id,account_name',
                'owner:id,first_name,last_name,email'
            ])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->success($response, [
            'contacts' => $contacts,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Create a new contact
     * POST /api/v1/contacts
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');

        $rules = [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'email|max:255',
            'phone' => 'max:50',
            'mobile' => 'max:50',
            'account_id' => 'exists:accounts,id',
            'status' => 'in:active,inactive,do_not_contact',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $contact = new Contact();
            $contact->tenant_id = $user->tenant_id;
            $contact->uuid = uuid();
            $contact->owner_id = $data['owner_id'] ?? $user->id;
            $contact->fill($data);
            $contact->save();

            $this->audit->log(
                'contact',
                $contact->id,
                'created',
                null,
                $contact->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $contact->load('account', 'owner'), 'Contact created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Contact creation failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to create contact', 500);
        }
    }

    /**
     * Get a specific contact
     * GET /api/v1/contacts/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $contact = Contact::where('tenant_id', $user->tenant_id)
            ->with(['account', 'owner', 'reportsTo'])
            ->find($id);

        if (!$contact) {
            return $this->notFound($response, 'Contact not found');
        }

        return $this->success($response, $contact);
    }

    /**
     * Update a contact
     * PUT /api/v1/contacts/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $contact = Contact::where('tenant_id', $user->tenant_id)->find($id);

        if (!$contact) {
            return $this->notFound($response, 'Contact not found');
        }

        $rules = [
            'first_name' => 'max:100',
            'last_name' => 'max:100',
            'email' => 'email|max:255',
            'phone' => 'max:50',
            'mobile' => 'max:50',
            'account_id' => 'exists:accounts,id',
            'status' => 'in:active,inactive,do_not_contact',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $oldValues = $contact->toArray();
            $contact->fill($data);
            $contact->save();

            $this->audit->log(
                'contact',
                $contact->id,
                'updated',
                $oldValues,
                $contact->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $contact->load('account', 'owner'), 'Contact updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Contact update failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to update contact', 500);
        }
    }

    /**
     * Delete a contact
     * DELETE /api/v1/contacts/{id}
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $contact = Contact::where('tenant_id', $user->tenant_id)->find($id);

        if (!$contact) {
            return $this->notFound($response, 'Contact not found');
        }

        try {
            DB::beginTransaction();

            $contactData = $contact->toArray();
            $contact->delete();

            $this->audit->log(
                'contact',
                $contact->id,
                'deleted',
                $contactData,
                null,
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, null, 'Contact deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Contact deletion failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to delete contact', 500);
        }
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['HTTP_X_FORWARDED_FOR'] ?? $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
