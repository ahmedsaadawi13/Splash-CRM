<?php

/**
 * Opportunity Controller
 *
 * File: app/Http/Controllers/OpportunityController.php
 *
 * Handles all CRUD operations for opportunities including:
 * - List with pagination, search, and filtering by stage/status
 * - Create, read, update, delete operations
 * - Auto-set closed date when marked won/lost
 * - Relationship management with accounts and contacts
 * - Audit logging for all operations
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class OpportunityController extends BaseController
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

        $query = Opportunity::query()->where('tenant_id', $user->tenant_id);

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where('opportunity_name', 'LIKE', "%{$search}%");
        }

        if (!empty($params['stage'])) {
            $query->where('stage', $params['stage']);
        }

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (!empty($params['account_id'])) {
            $query->where('account_id', $params['account_id']);
        }

        if (!empty($params['owner_id'])) {
            $query->where('owner_id', $params['owner_id']);
        }

        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = strtoupper($params['order'] ?? 'DESC');

        if (in_array($sortField, ['id', 'opportunity_name', 'amount', 'expected_close_date', 'created_at']) && in_array($sortOrder, ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        $total = $query->count();

        $opportunities = $query
            ->with(['account:id,account_name', 'contact:id,first_name,last_name', 'owner:id,first_name,last_name'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->success($response, [
            'opportunities' => $opportunities,
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
            'opportunity_name' => 'required|max:255',
            'amount' => 'required|numeric',
            'stage' => 'required|max:100',
            'account_id' => 'exists:accounts,id',
            'contact_id' => 'exists:contacts,id',
            'status' => 'in:open,won,lost,abandoned',
            'opportunity_type' => 'in:new_business,existing_business,renewal,upgrade',
            'probability' => 'numeric',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $opportunity = new Opportunity();
            $opportunity->tenant_id = $user->tenant_id;
            $opportunity->uuid = uuid();
            $opportunity->owner_id = $data['owner_id'] ?? $user->id;
            $opportunity->fill($data);
            $opportunity->save();

            $this->audit->log('opportunity', $opportunity->id, 'created', null, $opportunity->toArray(), $user->id, $user->tenant_id);

            DB::commit();

            return $this->success($response, $opportunity->load('account', 'contact', 'owner'), 'Opportunity created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create opportunity', 500);
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $opportunity = Opportunity::where('tenant_id', $user->tenant_id)
            ->with(['account', 'contact', 'owner'])
            ->find($id);

        if (!$opportunity) {
            return $this->notFound($response, 'Opportunity not found');
        }

        return $this->success($response, $opportunity);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $opportunity = Opportunity::where('tenant_id', $user->tenant_id)->find($id);

        if (!$opportunity) {
            return $this->notFound($response, 'Opportunity not found');
        }

        $rules = [
            'opportunity_name' => 'max:255',
            'amount' => 'numeric',
            'stage' => 'max:100',
            'account_id' => 'exists:accounts,id',
            'contact_id' => 'exists:contacts,id',
            'status' => 'in:open,won,lost,abandoned',
            'opportunity_type' => 'in:new_business,existing_business,renewal,upgrade',
            'probability' => 'numeric',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $oldValues = $opportunity->toArray();
            $opportunity->fill($data);

            // Auto-set closed_at when status changes to won/lost
            if (isset($data['status']) && in_array($data['status'], ['won', 'lost']) && !$opportunity->closed_at) {
                $opportunity->closed_at = now();
            }

            $opportunity->save();

            $this->audit->log('opportunity', $opportunity->id, 'updated', $oldValues, $opportunity->toArray(), $user->id, $user->tenant_id);

            DB::commit();

            return $this->success($response, $opportunity->load('account', 'contact', 'owner'), 'Opportunity updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update opportunity', 500);
        }
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $opportunity = Opportunity::where('tenant_id', $user->tenant_id)->find($id);

        if (!$opportunity) {
            return $this->notFound($response, 'Opportunity not found');
        }

        try {
            DB::beginTransaction();

            $opportunityData = $opportunity->toArray();
            $opportunity->delete();

            $this->audit->log('opportunity', $opportunity->id, 'deleted', $opportunityData, null, $user->id, $user->tenant_id);

            DB::commit();

            return $this->success($response, null, 'Opportunity deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to delete opportunity', 500);
        }
    }
}
