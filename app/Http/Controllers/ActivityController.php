<?php

/**
 * Activity Controller
 *
 * File: app/Http/Controllers/ActivityController.php
 *
 * Handles all CRUD operations for activities including:
 * - List with pagination, search, and filtering by type/status/priority
 * - Support for tasks, calls, meetings, emails, events, notes
 * - Polymorphic relationships to leads, contacts, accounts, opportunities
 * - Create, read, update, delete operations
 * - Audit logging for all operations
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class ActivityController extends BaseController
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

        $query = Activity::query()->where('tenant_id', $user->tenant_id);

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where('subject', 'LIKE', "%{$search}%");
        }

        if (!empty($params['activity_type'])) {
            $query->where('activity_type', $params['activity_type']);
        }

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (!empty($params['priority'])) {
            $query->where('priority', $params['priority']);
        }

        if (!empty($params['related_to_type']) && !empty($params['related_to_id'])) {
            $query->where('related_to_type', $params['related_to_type'])
                  ->where('related_to_id', $params['related_to_id']);
        }

        if (!empty($params['owner_id'])) {
            $query->where('owner_id', $params['owner_id']);
        }

        if (!empty($params['assigned_to_id'])) {
            $query->where('assigned_to_id', $params['assigned_to_id']);
        }

        $sortField = $params['sort'] ?? 'due_date';
        $sortOrder = strtoupper($params['order'] ?? 'ASC');

        if (in_array($sortField, ['id', 'subject', 'due_date', 'created_at']) && in_array($sortOrder, ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        $total = $query->count();

        $activities = $query
            ->with(['owner:id,first_name,last_name', 'assignedTo:id,first_name,last_name'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->success($response, [
            'activities' => $activities,
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
            'subject' => 'required|max:255',
            'activity_type' => 'required|in:task,call,meeting,email,event,note',
            'status' => 'in:planned,in_progress,completed,cancelled,deferred',
            'priority' => 'in:low,medium,high,urgent',
            'related_to_type' => 'max:50',
            'related_to_id' => 'numeric',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $activity = new Activity();
            $activity->tenant_id = $user->tenant_id;
            $activity->uuid = uuid();
            $activity->owner_id = $data['owner_id'] ?? $user->id;
            $activity->fill($data);
            $activity->save();

            $this->audit->log('activity', $activity->id, 'created', null, $activity->toArray(), $user->id, $user->tenant_id);

            DB::commit();

            return $this->success($response, $activity->load('owner', 'assignedTo'), 'Activity created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create activity', 500);
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $activity = Activity::where('tenant_id', $user->tenant_id)
            ->with(['owner', 'assignedTo'])
            ->find($id);

        if (!$activity) {
            return $this->notFound($response, 'Activity not found');
        }

        return $this->success($response, $activity);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $activity = Activity::where('tenant_id', $user->tenant_id)->find($id);

        if (!$activity) {
            return $this->notFound($response, 'Activity not found');
        }

        $rules = [
            'subject' => 'max:255',
            'activity_type' => 'in:task,call,meeting,email,event,note',
            'status' => 'in:planned,in_progress,completed,cancelled,deferred',
            'priority' => 'in:low,medium,high,urgent',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $oldValues = $activity->toArray();
            $activity->fill($data);
            $activity->save();

            $this->audit->log('activity', $activity->id, 'updated', $oldValues, $activity->toArray(), $user->id, $user->tenant_id);

            DB::commit();

            return $this->success($response, $activity->load('owner', 'assignedTo'), 'Activity updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update activity', 500);
        }
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = $args['id'];

        $activity = Activity::where('tenant_id', $user->tenant_id)->find($id);

        if (!$activity) {
            return $this->notFound($response, 'Activity not found');
        }

        try {
            DB::beginTransaction();

            $activityData = $activity->toArray();
            $activity->delete();

            $this->audit->log('activity', $activity->id, 'deleted', $activityData, null, $user->id, $user->tenant_id);

            DB::commit();

            return $this->success($response, null, 'Activity deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to delete activity', 500);
        }
    }
}
