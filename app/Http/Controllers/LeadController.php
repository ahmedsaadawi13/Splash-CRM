<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class LeadController extends BaseController
{
    private $validator;
    private $audit;

    public function __construct(ValidationService $validator, AuditService $audit)
    {
        $this->validator = $validator;
        $this->audit = $audit;
    }

    /**
     * List all leads
     *
     * GET /api/v1/leads
     * Query params: page, per_page, search, status, rating, lead_source, sort, order
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $user = $request->getAttribute('user');

        // Pagination
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 20)));

        // Build query
        $query = Lead::query()->where('tenant_id', $user->tenant_id);

        // Search
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('company', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Filters
        if (!empty($params['status'])) {
            $query->where('lead_status', $params['status']);
        }

        if (!empty($params['rating'])) {
            $query->where('rating', $params['rating']);
        }

        if (!empty($params['lead_source'])) {
            $query->where('lead_source', $params['lead_source']);
        }

        if (!empty($params['owner_id'])) {
            $query->where('owner_id', $params['owner_id']);
        }

        if (isset($params['converted'])) {
            $query->where('converted', (bool)$params['converted']);
        }

        // Sorting
        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = strtoupper($params['order'] ?? 'DESC');
        $allowedSorts = ['id', 'first_name', 'last_name', 'company', 'created_at', 'updated_at', 'score'];

        if (in_array($sortField, $allowedSorts) && in_array($sortOrder, ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        // Get total count
        $total = $query->count();

        // Get paginated results
        $leads = $query
            ->with('owner:id,first_name,last_name,email')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->success($response, [
            'leads' => $leads,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Create a new lead
     *
     * POST /api/v1/leads
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');

        // Validation rules
        $rules = [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'company' => 'required|max:255',
            'email' => 'email|max:255',
            'phone' => 'max:50',
            'mobile' => 'max:50',
            'website' => 'url|max:500',
            'lead_status' => 'in:new,contacted,qualified,unqualified,converted,lost',
            'rating' => 'in:hot,warm,cold',
            'annual_revenue' => 'numeric',
            'employees' => 'numeric',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            // Create lead
            $lead = new Lead();
            $lead->tenant_id = $user->tenant_id;
            $lead->uuid = uuid();
            $lead->owner_id = $data['owner_id'] ?? $user->id;
            $lead->fill($data);
            $lead->save();

            // Audit log
            $this->audit->log(
                'lead',
                $lead->id,
                'created',
                null,
                $lead->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $lead->load('owner'), 'Lead created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Lead creation failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to create lead', 500);
        }
    }

    /**
     * Get a specific lead
     *
     * GET /api/v1/leads/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $lead = Lead::where('tenant_id', $user->tenant_id)
            ->with([
                'owner:id,first_name,last_name,email',
                'convertedAccount:id,account_name',
                'convertedContact:id,first_name,last_name,email',
                'convertedOpportunity:id,opportunity_name,amount'
            ])
            ->find($id);

        if (!$lead) {
            return $this->notFound($response, 'Lead not found');
        }

        return $this->success($response, $lead);
    }

    /**
     * Update a lead
     *
     * PUT /api/v1/leads/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $lead = Lead::where('tenant_id', $user->tenant_id)->find($id);

        if (!$lead) {
            return $this->notFound($response, 'Lead not found');
        }

        // Validation rules
        $rules = [
            'first_name' => 'max:100',
            'last_name' => 'max:100',
            'company' => 'max:255',
            'email' => 'email|max:255',
            'phone' => 'max:50',
            'mobile' => 'max:50',
            'website' => 'url|max:500',
            'lead_status' => 'in:new,contacted,qualified,unqualified,converted,lost',
            'rating' => 'in:hot,warm,cold',
            'annual_revenue' => 'numeric',
            'employees' => 'numeric',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $oldValues = $lead->toArray();

            $lead->fill($data);
            $lead->save();

            // Audit log
            $this->audit->log(
                'lead',
                $lead->id,
                'updated',
                $oldValues,
                $lead->toArray(),
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, $lead->load('owner'), 'Lead updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Lead update failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to update lead', 500);
        }
    }

    /**
     * Delete a lead
     *
     * DELETE /api/v1/leads/{id}
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $lead = Lead::where('tenant_id', $user->tenant_id)->find($id);

        if (!$lead) {
            return $this->notFound($response, 'Lead not found');
        }

        try {
            DB::beginTransaction();

            $leadData = $lead->toArray();

            $lead->delete();

            // Audit log
            $this->audit->log(
                'lead',
                $lead->id,
                'deleted',
                $leadData,
                null,
                $user->id,
                $user->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, null, 'Lead deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Lead deletion failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to delete lead', 500);
        }
    }

    /**
     * Convert lead to contact/account/opportunity
     *
     * POST /api/v1/leads/{id}/convert
     */
    public function convert(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $lead = Lead::where('tenant_id', $user->tenant_id)->find($id);

        if (!$lead) {
            return $this->notFound($response, 'Lead not found');
        }

        if ($lead->converted) {
            return $this->error($response, 'Lead already converted', 400);
        }

        try {
            DB::beginTransaction();

            // Create account
            $account = new \App\Models\Account();
            $account->tenant_id = $user->tenant_id;
            $account->uuid = uuid();
            $account->account_name = $lead->company;
            $account->website = $lead->website;
            $account->phone = $lead->phone;
            $account->email = $lead->email;
            $account->industry = $lead->industry;
            $account->annual_revenue = $lead->annual_revenue;
            $account->employees = $lead->employees;
            $account->owner_id = $lead->owner_id;
            $account->save();

            // Create contact
            $contact = new \App\Models\Contact();
            $contact->tenant_id = $user->tenant_id;
            $contact->uuid = uuid();
            $contact->account_id = $account->id;
            $contact->first_name = $lead->first_name;
            $contact->last_name = $lead->last_name;
            $contact->title = $lead->title;
            $contact->email = $lead->email;
            $contact->phone = $lead->phone;
            $contact->mobile = $lead->mobile;
            $contact->lead_source = $lead->lead_source;
            $contact->owner_id = $lead->owner_id;
            $contact->save();

            // Create opportunity if requested
            $opportunity = null;
            if (!empty($data['create_opportunity'])) {
                $opportunity = new \App\Models\Opportunity();
                $opportunity->tenant_id = $user->tenant_id;
                $opportunity->uuid = uuid();
                $opportunity->account_id = $account->id;
                $opportunity->contact_id = $contact->id;
                $opportunity->opportunity_name = $data['opportunity_name'] ?? "Opportunity - {$lead->company}";
                $opportunity->amount = $data['amount'] ?? 0;
                $opportunity->stage = $data['stage'] ?? 'Prospecting';
                $opportunity->probability = $data['probability'] ?? 10;
                $opportunity->lead_source = $lead->lead_source;
                $opportunity->owner_id = $lead->owner_id;
                $opportunity->save();
            }

            // Mark lead as converted
            $lead->converted = true;
            $lead->converted_at = now();
            $lead->converted_account_id = $account->id;
            $lead->converted_contact_id = $contact->id;
            $lead->converted_opportunity_id = $opportunity ? $opportunity->id : null;
            $lead->lead_status = 'converted';
            $lead->save();

            // Audit logs
            $this->audit->log('lead', $lead->id, 'converted', null, $lead->toArray(), $user->id, $user->tenant_id);
            $this->audit->log('account', $account->id, 'created', null, $account->toArray(), $user->id, $user->tenant_id);
            $this->audit->log('contact', $contact->id, 'created', null, $contact->toArray(), $user->id, $user->tenant_id);

            if ($opportunity) {
                $this->audit->log('opportunity', $opportunity->id, 'created', null, $opportunity->toArray(), $user->id, $user->tenant_id);
            }

            DB::commit();

            return $this->success($response, [
                'lead' => $lead,
                'account' => $account,
                'contact' => $contact,
                'opportunity' => $opportunity,
            ], 'Lead converted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Lead conversion failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to convert lead: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get client IP address
     */
    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['HTTP_X_FORWARDED_FOR'] ?? $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
