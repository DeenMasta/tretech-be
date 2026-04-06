<?php

namespace App\Services\Audit;

use App\Models\ErrorLog;
use Illuminate\Pagination\LengthAwarePaginator;

class ErrorLogService
{
    /**
     * Persist an error log entry.
     *
     * @param array<string, mixed>|null $details  Arbitrary context (URL, user, inputs, etc.)
     */
    public function log(
        string $source,
        string $message,
        ?int $sourceId = null,
        ?array $details = null
    ): ErrorLog {
        return ErrorLog::query()->create([
            'source'    => $source,
            'source_id' => $sourceId,
            'message'   => $message,
            'details'   => $details,
        ]);
    }

    /**
     * Log a Throwable with full context captured from request/environment.
     */
    public function logException(
        \Throwable $e,
        string $source = 'app',
        ?int $sourceId = null,
        ?array $extraContext = null
    ): ErrorLog {
        $details = array_filter(array_merge([
            'exception'  => get_class($e),
            'file'       => $e->getFile(),
            'line'       => $e->getLine(),
            'trace'      => array_map(
                fn(array $frame) => sprintf(
                    '%s:%d %s%s%s()',
                    $frame['file'] ?? '',
                    $frame['line'] ?? 0,
                    $frame['class'] ?? '',
                    $frame['type'] ?? '',
                    $frame['function'],
                ),
                array_slice($e->getTrace(), 0, 10)
            ),
        ], $extraContext ?? []));

        return $this->log(
            source:    $source,
            message:   $e->getMessage() ?: class_basename($e),
            sourceId:  $sourceId,
            details:   $details,
        );
    }

    /**
     * Paginated list of error logs with optional filters.
     *
     * @param array<string, mixed> $filters  Supported keys:
     *   source, source_id, from_date (Y-m-d), to_date (Y-m-d)
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ErrorLog::query()->latest('created_at');

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['source_id'])) {
            $query->where('source_id', $filters['source_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date'] . ' 00:00:00');
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date'] . ' 23:59:59');
        }

        return $query->paginate($perPage);
    }

    /**
     * Find a single error log entry.
     */
    public function find(int $id): ?ErrorLog
    {
        return ErrorLog::query()->find($id);
    }
}
