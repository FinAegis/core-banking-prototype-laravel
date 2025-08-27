<?php

namespace App\Http\Controllers;

use App\Domain\Batch\Models\BatchItem;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Services\BatchProcessingService;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BatchProcessingController extends Controller
{
    protected BatchProcessingService $batchService;

    public function __construct(BatchProcessingService $batchService)
    {
        $this->batchService = $batchService;
    }

    /**
     * Display batch processing interface.
     */
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

    /**
     * Show create batch job form.
     */
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

    /**
     * Store a new batch job.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'name'                 => 'required|string|max:255',
                'type'                 => 'required|in:transfer,payment,conversion',
                'schedule_at'          => 'nullable|date|after:now',
                'items'                => 'required|array|min:1',
                'items.*.from_account' => 'required_if:type,transfer|uuid',
                'items.*.to_account'   => 'required_if:type,transfer|uuid',
                'items.*.amount'       => 'required|numeric|min:0.01',
                'items.*.currency'     => 'required|string|size:3',
                'items.*.description'  => 'nullable|string|max:255',
            ]
        );

        DB::beginTransaction();
        try {
            // Create batch job
            $batchJob = BatchJob::create(
                [
                    'uuid'            => Str::uuid(),
                    'user_uuid'       => Auth::user()->uuid,
                    'name'            => $validated['name'],
                    'type'            => $validated['type'],
                    'status'          => 'pending',
                    'total_items'     => count($validated['items']),
                    'processed_items' => 0,
                    'failed_items'    => 0,
                    'scheduled_at'    => $validated['schedule_at'] ?? now(),
                ]
            );

            // Create batch items
            foreach ($validated['items'] as $index => $item) {
                BatchItem::create(
                    [
                        'batch_job_id' => $batchJob->id,
                        'sequence'     => $index + 1,
                        'type'         => $validated['type'],
                        'status'       => 'pending',
                        'data'         => $item,
                    ]
                );
            }

            DB::commit();

            // Queue for processing if not scheduled
            if (! isset($validated['schedule_at'])) {
                $this->batchService->processBatch($batchJob);
            }

            return redirect()
                ->route('batch-processing.show', $batchJob)
                ->with('success', 'Batch job created successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create batch job: ' . $e->getMessage()]);
        }
    }

    /**
     * Show batch job details.
     */
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
                ->value('avg_time') ?? 0,
        ];

        return view('batch-processing.show', compact('batchJob', 'stats'));
    }

    /**
     * Cancel a pending batch job.
     */
    public function cancel(BatchJob $batchJob)
    {
        // Ensure user owns this batch job
        if ($batchJob->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        if (! in_array($batchJob->status, ['pending', 'processing'])) {
            return back()->withErrors(['error' => 'Cannot cancel this batch job']);
        }

        $batchJob->update(['status' => 'cancelled']);

        // Cancel all pending items
        $batchJob->items()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return back()->with('success', 'Batch job cancelled');
    }

    /**
     * Retry failed items in a batch job.
     */
    public function retry(BatchJob $batchJob)
    {
        // Ensure user owns this batch job
        if ($batchJob->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        $failedItems = $batchJob->items()->where('status', 'failed')->count();

        if ($failedItems === 0) {
            return back()->withErrors(['error' => 'No failed items to retry']);
        }

        // Reset failed items
        $batchJob->items()
            ->where('status', 'failed')
            ->update(
                [
                    'status'        => 'pending',
                    'error_message' => null,
                    'processed_at'  => null,
                ]
            );

        // Update batch job
        $batchJob->update(
            [
                'status'       => 'processing',
                'failed_items' => 0,
            ]
        );

        // Queue for reprocessing
        $this->batchService->processBatch($batchJob);

        return back()->with('success', "Retrying {$failedItems} failed items");
    }

    /**
     * Download batch job report.
     */
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
                'id'          => 'currency_conversion',
                'name'        => 'Currency Conversion',
                'description' => 'Bulk currency conversion operations',
                'type'        => 'conversion',
                'icon'        => 'refresh',
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
