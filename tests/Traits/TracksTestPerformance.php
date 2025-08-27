<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Log;
use ReflectionClass;

trait TracksTestPerformance
{
    /**
     * Track slow test threshold in seconds.
     */
    protected float $slowTestThreshold = 1.0;

    /**
     * Start time of the test.
     */
    protected float $testStartTime;

    /**
     * Set up performance tracking.
     */
    protected function setUpPerformanceTracking(): void
    {
        $this->testStartTime = microtime(true);
    }

    /**
     * Track test performance after completion.
     */
    protected function trackTestPerformance(): void
    {
        $executionTime = microtime(true) - $this->testStartTime;

        // Get test name - compatible with both PHPUnit and Pest
        $testName = $this->getTestName();

        // Log slow tests
        if ($executionTime > $this->slowTestThreshold) {
            $this->logSlowTest($testName, $executionTime);
        }

        // Store metrics for reporting
        $this->storeTestMetrics($testName, $executionTime);
    }

    /**
     * Get the current test name in a way that works with both PHPUnit and Pest.
     */
    protected function getTestName(): string
    {
        // For Pest tests, we need to get the test name differently
        if (defined('PEST_VERSION')) {
            // In Pest, we can use the test description from the global test function
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach ($backtrace as $frame) {
                if (isset($frame['function']) && $frame['function'] === 'test') {
                    // Try to extract test description from the test closure
                    return 'Pest Test';
                }
            }
            // Fallback: use the test file and line number
            $reflection = new ReflectionClass($this);

            return basename($reflection->getFileName()) . ':' . __LINE__;
        }

        // For PHPUnit tests, use the standard getName() method
        if (method_exists($this, 'getName')) {
            return $this->getName();
        }

        // Fallback: use class name
        return get_class($this);
    }

    /**
     * Log slow test execution.
     */
    protected function logSlowTest(string $testName, float $executionTime): void
    {
        $message = sprintf(
            'Slow test detected: %s took %.2f seconds (threshold: %.2f seconds)',
            $testName,
            $executionTime,
            $this->slowTestThreshold
        );

        Log::warning($message);

        // Also write to a dedicated slow test log file
        $logFile = storage_path('logs/slow-tests.log');
        $logEntry = sprintf(
            "[%s] %s - %.2f seconds\n",
            date('Y-m-d H:i:s'),
            $testName,
            $executionTime
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Store test metrics for analysis.
     */
    protected function storeTestMetrics(string $testName, float $executionTime): void
    {
        $metricsFile = storage_path('logs/test-metrics.json');

        $metrics = [];
        if (file_exists($metricsFile)) {
            $content = file_get_contents($metricsFile);
            $metrics = json_decode($content, true) ?? [];
        }

        $metrics[] = [
            'test'           => $testName,
            'execution_time' => $executionTime,
            'timestamp'      => now()->toIso8601String(),
            'memory_peak'    => memory_get_peak_usage(true) / 1048576, // MB
        ];

        // Keep only last 1000 entries to prevent file from growing too large
        if (count($metrics) > 1000) {
            $metrics = array_slice($metrics, -1000);
        }

        file_put_contents(
            $metricsFile,
            json_encode($metrics, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /**
     * Get slow test threshold.
     */
    public function getSlowTestThreshold(): float
    {
        return $this->slowTestThreshold;
    }

    /**
     * Set slow test threshold.
     */
    public function setSlowTestThreshold(float $threshold): void
    {
        $this->slowTestThreshold = $threshold;
    }
}
