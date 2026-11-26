<?php

namespace App\Http\Controllers;

use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Services\BatchProcessingService;
use App\Domain\Batch\Services\BatchTransferGuard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Batch Processing',
    description: 'Batch transaction processing and management'
)]
class BatchProcessingController extends Controller
{
    protected BatchProcessingService $batchService;

    public function __construct(BatchProcessingService $batchService)
    {
        $this->batchService = $batchService;
    }

        #[OA\Get(
            path: '/batches',
            operationId: 'batchProcessingIndex',
            tags: ['Batch Processing'],
            summary: 'List batch jobs',
            description: 'Returns the batch processing management page',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function index(Request $request)
    {
        $user = Auth::user();
        /** @var User $user */

        // Get filter parameters
        $filters = [
            'status'    => $request->get('status', 'all'),
            'type'      => $request->get('type', 'all'),
            'date_from' => $request->get('date_from'),
            'date_to'   => $request->get('date_to'),
        ];

        // Get batch jobs
        $batchJobs = $this->getBatchJobs($user, $filters);

        // Get statistics
        $statistics = $this->getBatchStatistics($user);

        // Get templates
        $templates = $this->getBatchTemplates();

        return view(
            'batch-processing.index',
            compact(
                'batchJobs',
                'statistics',
                'templates',
                'filters'
            )
        );
    }

        #[OA\Get(
            path: '/batches/create',
            operationId: 'batchProcessingCreate',
            tags: ['Batch Processing'],
            summary: 'Show create batch form',
            description: 'Shows the form to create a new batch job',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function create(Request $request)
    {
        $user = Auth::user();
        /** @var User $user */
        $accounts = $user->accounts()->with('balances.asset')->get();

        $template = null;
        if ($request->has('template')) {
            $template = $this->getBatchTemplate($request->get('template'));
        }

        return view('batch-processing.create', compact('accounts', 'template'));
    }

        #[OA\Post(
            path: '/batches',
            operationId: 'batchProcessingStore',
            tags: ['Batch Processing'],
            summary: 'Create a batch job',
            description: 'Creates and queues a new batch processing job',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'name'        => 'required|string|max:255',
                'type'        => 'required|in:transfer,payment',
                'schedule_at' => 'nullable|date|after:now',
                'items'       => 'required|array|min:1|max:' . BatchTransferGuard::MAX_ITEMS,
            ],
            ['type.in' => 'Batches can only hold transfers and payments.']
        );

        /** @var User $user */
        $user = Auth::user();

        /** @var array<mixed> $items */
        $items = $request->input('items', []);

        // The service checks every item as a single transfer is checked (403
        // for another user's source account, 422 for an invalid item) and
        // counts each item against the user's transfer limits (429).
        $batchJob = $this->batchService->createBatchJob(
            userUuid: (string) $user->uuid,
            name: $validated['name'],
            type: $validated['type'],
            items: $items,
            scheduledAt: $validated['schedule_at'] ?? null,
        );

        return redirect()
            ->route('batch-processing.show', $batchJob)
            ->with('success', 'Batch job created successfully');
    }

        #[OA\Get(
            path: '/batches/{id}',
            operationId: 'batchProcessingShow',
            tags: ['Batch Processing'],
            summary: 'Show batch job details',
            description: 'Returns details of a specific batch job',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function show(BatchJob $batchJob)
    {
        // Ensure user owns this batch job
        if ($batchJob->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        $batchJob->load('items');

        // Get processing statistics
        $stats = [
            'success_rate' => $batchJob->total_items > 0
                ? round((($batchJob->processed_items - $batchJob->failed_items) / $batchJob->total_items) * 100, 1)
                : 0,
            'avg_processing_time' => $batchJob->items()
                ->whereNotNull('processed_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at)) as avg_time')
                ->toBase()
                ->value('avg_time') ?? 0,
        ];

        return view('batch-processing.show', compact('batchJob', 'stats'));
    }

        #[OA\Post(
            path: '/batches/{id}/cancel',
            operationId: 'batchProcessingCancel',
            tags: ['Batch Processing'],
            summary: 'Cancel a batch job',
            description: 'Cancels a running or pending batch job',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function cancel(BatchJob $batchJob)
    {
        // Ensure user owns this batch job
        if ($batchJob->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        if (! in_array($batchJob->status, ['pending', 'processing'])) {
            return back()->withErrors(['error' => 'Cannot cancel this batch job']);
        }

        // Through the batch aggregate, so the workflow sees the cancellation
        // and moves no money for the items it has not reached yet. Its
        // projector marks the batch and its pending items cancelled.
        try {
            $this->batchService->cancelBatchJob((string) $batchJob->uuid, 'Cancelled by the user');
        } catch (InvalidArgumentException) {
            return back()->withErrors(['error' => 'Cannot cancel this batch job']);
        }

        return back()->with('success', 'Batch job cancelled');
    }

        #[OA\Post(
            path: '/batches/{id}/retry',
            operationId: 'batchProcessingRetry',
            tags: ['Batch Processing'],
            summary: 'Retry a failed batch job',
            description: 'Retries a failed batch processing job',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function retry(BatchJob $batchJob)
    {
        // Ensure user owns this batch job
        if ($batchJob->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        // The failed items run again as a new batch, with the same checks and
        // limits as any new batch. A failed item moved no money.
        $retry = $this->batchService->retryFailedItems((string) $batchJob->uuid);

        if ($retry === null) {
            return back()->withErrors(['error' => 'No failed items to retry']);
        }

        return redirect()
            ->route('batch-processing.show', $retry)
            ->with('success', "Retrying {$retry->total_items} failed items");
    }

        #[OA\Get(
            path: '/batches/{id}/download',
            operationId: 'batchProcessingDownload',
            tags: ['Batch Processing'],
            summary: 'Download batch results',
            description: 'Downloads the results of a completed batch job',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function download(BatchJob $batchJob)
    {
        // Ensure user owns this batch job
        if ($batchJob->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        $filename = "batch_{$batchJob->uuid}_{$batchJob->created_at->format('Y-m-d')}.csv";

        return response()->streamDownload(
            function () use ($batchJob) {
                $handle = fopen('php://output', 'w');

                // Headers
                fputcsv(
                    $handle,
                    [
                        'Sequence',
                        'Type',
                        'Status',
                        'From Account',
                        'To Account',
                        'Amount',
                        'Currency',
                        'Description',
                        'Processed At',
                        'Error Message',
                    ]
                );

                // Data
                foreach ($batchJob->items as $item) {
                    fputcsv(
                        $handle,
                        [
                            $item->sequence,
                            $item->type,
                            $item->status,
                            $item->data['from_account'] ?? '',
                            $item->data['to_account'] ?? '',
                            $item->data['amount'] ?? '',
                            $item->data['currency'] ?? '',
                            $item->data['description'] ?? '',
                            $item->processed_at,
                            $item->error_message,
                        ]
                    );
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv',
            ]
        );
    }

    /**
     * Get batch jobs for user.
     */
    private function getBatchJobs($user, $filters)
    {
        $query = BatchJob::where('user_uuid', $user->uuid);

        // Apply filters
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if ($filters['date_from']) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends($filters);
    }

    /**
     * Get batch statistics.
     */
    private function getBatchStatistics($user)
    {
        return DB::table('batch_jobs')
            ->where('user_uuid', $user->uuid)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('COUNT(*) as total_jobs'),
                DB::raw('SUM(total_items) as total_items'),
                DB::raw('SUM(processed_items) as processed_items'),
                DB::raw('SUM(failed_items) as failed_items'),
                DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_jobs'),
                DB::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_jobs')
            )
            ->first();
    }

    /**
     * Get batch templates.
     */
    private function getBatchTemplates()
    {
        return [
            [
                'id'          => 'salary_payments',
                'name'        => 'Salary Payments',
                'description' => 'Monthly salary disbursement to employees',
                'type'        => 'transfer',
                'icon'        => 'currency-dollar',
            ],
            [
                'id'          => 'vendor_payments',
                'name'        => 'Vendor Payments',
                'description' => 'Bulk payments to suppliers and vendors',
                'type'        => 'payment',
                'icon'        => 'shopping-cart',
            ],
            [
                'id'          => 'dividend_distribution',
                'name'        => 'Dividend Distribution',
                'description' => 'Distribute dividends to shareholders',
                'type'        => 'transfer',
                'icon'        => 'chart-bar',
            ],
        ];
    }

    /**
     * Get specific batch template.
     */
    private function getBatchTemplate($templateId)
    {
        $templates = collect($this->getBatchTemplates());

        return $templates->firstWhere('id', $templateId);
    }
}
