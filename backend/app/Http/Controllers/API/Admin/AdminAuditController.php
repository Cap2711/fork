<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

class AdminAuditController extends BaseAPIController
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'action' => 'nullable|string',
            'area' => 'nullable|string',
            'user_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|string|in:success,failure,error',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:performed_at,action,area,status',
            'sort_order' => 'nullable|string|in:asc,desc'
        ]);

        $query = AuditLog::query()->with('user');

        // Apply date filters
        if ($request->has('start_date')) {
            $query->where('performed_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('performed_at', '<=', $request->end_date);
        }

        // Apply other filters
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('area')) {
            $query->where('area', $request->area);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'performed_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $logs = $query->paginate($perPage);

        return $this->sendPaginatedResponse($logs);
    }

    /**
     * Display the specified audit log.
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::with(['user', 'auditable'])->findOrFail($id);
        return $this->sendResponse($log);
    }

    /**
     * Get audit logs for a specific user.
     */
    public function userActivity(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $query = AuditLog::where('user_id', $userId)->with('auditable');

        if ($request->has('start_date')) {
            $query->where('performed_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('performed_at', '<=', $request->end_date);
        }

        $logs = $query->orderBy('performed_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->sendPaginatedResponse($logs);
    }

    /**
     * Get audit history for a specific content item.
     */
    public function contentHistory(Request $request, string $type, int $id): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $modelClass = 'App\\Models\\' . ucfirst($type);
        if (!class_exists($modelClass)) {
            return $this->sendError('Invalid content type.', 404);
        }

        $query = AuditLog::where('auditable_type', $modelClass)
            ->where('auditable_id', $id)
            ->with('user');

        if ($request->has('start_date')) {
            $query->where('performed_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('performed_at', '<=', $request->end_date);
        }

        $logs = $query->orderBy('performed_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->sendPaginatedResponse($logs);
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|string|in:csv,json'
        ]);

        $query = AuditLog::with('user')
            ->whereBetween('performed_at', [
                $request->start_date,
                $request->end_date
            ]);

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('area')) {
            $query->where('area', $request->area);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->orderBy('performed_at')->get();

        $format = $request->input('format', 'csv');

        if ($format === 'json') {
            return $this->sendResponse($logs);
        }

        // Generate CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit_logs_' . 
                Carbon::now()->format('Y-m-d_His') . '.csv"'
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, [
                'ID',
                'User',
                'Action',
                'Area',
                'Status',
                'IP Address',
                'User Agent',
                'Performed At',
                'Details'
            ]);

            // Add data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->user ? $log->user->name : 'System',
                    $log->action,
                    $log->area,
                    $log->status,
                    $log->ip_address,
                    $log->user_agent,
                    $log->performed_at,
                    $log->getDescription()
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Get audit log statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $startDate = $request->input('start_date', Carbon::now()->subDays(30));
        $endDate = $request->input('end_date', Carbon::now());

        $query = AuditLog::whereBetween('performed_at', [$startDate, $endDate]);

        $stats = [
            'total_logs' => $query->count(),
            'by_status' => $query->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status'),
            'by_action' => $query->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'by_area' => $query->selectRaw('area, COUNT(*) as count')
                ->groupBy('area')
                ->orderByDesc('count')
                ->get(),
            'by_user' => $query->selectRaw('user_id, COUNT(*) as count')
                ->with('user:id,name')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'user' => $item->user ? $item->user->name : 'System',
                        'count' => $item->count
                    ];
                }),
            'timeline' => $query->selectRaw('DATE(performed_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date')
        ];

        return $this->sendResponse($stats);
    }
}