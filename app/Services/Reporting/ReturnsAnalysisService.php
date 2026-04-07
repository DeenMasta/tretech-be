<?php

namespace App\Services\Reporting;

use App\Models\Consignment;
use App\Models\ReconciliationItem;

class ReturnsAnalysisService
{
    /**
     * Returns vs Used analysis, grouped by consignment.
     *
     * Filters: from_date, to_date, client_id
     */
    public function getReport(array $filters = []): array
    {
        $query = Consignment::query()
            ->with([
                'client:id,client_name',
                'consignmentItems',
                'returnSession.returnSessionItems',
                'reconciliation.reconciliationItems',
            ])
            ->withCount('consignmentItems');

        if (!empty($filters['from_date'])) {
            $query->whereDate('consignment_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('consignment_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        $consignments = $query->orderByDesc('consignment_at')->get();

        $rows = $consignments->map(function (Consignment $c) {
            $issued   = $c->consignmentItems->count();
            $returned = $c->returnSession?->returnSessionItems()->count() ?? 0;

            // Reconciliation: items with result=`used`
            $usedCount = 0;
            if ($c->reconciliation) {
                $usedCount = $c->reconciliation->reconciliationItems->where('result', 'used')->count();
            }

            $unreconciledCount = $issued - $returned - $usedCount;
            if ($unreconciledCount < 0) {
                $unreconciledCount = 0;
            }

            return [
                'consignment_id'    => $c->id,
                'consignment_no'    => $c->consignment_no,
                'client'            => $c->client?->client_name,
                'consignment_at'    => $c->consignment_at?->format('Y-m-d'),
                'status'            => $c->status,
                'issued_count'      => $issued,
                'returned_count'    => $returned,
                'used_count'        => $usedCount,
                'unreconciled_count' => $unreconciledCount,
                'return_rate'       => $issued > 0 ? round(($returned / $issued) * 100, 2) : 0,
                'usage_rate'        => $issued > 0 ? round(($usedCount / $issued) * 100, 2) : 0,
            ];
        });

        $summary = [
            'total_consignments'     => $consignments->count(),
            'total_issued'           => $rows->sum('issued_count'),
            'total_returned'         => $rows->sum('returned_count'),
            'total_used'             => $rows->sum('used_count'),
            'total_unreconciled'     => $rows->sum('unreconciled_count'),
            'average_return_rate'    => $rows->count() > 0
                ? round($rows->avg('return_rate'), 2)
                : 0,
        ];

        return [
            'summary' => $summary,
            'data'    => $rows->values(),
        ];
    }

    public function getExportRows(array $filters = []): array
    {
        $result = $this->getReport($filters);

        return $result['data']->map(function ($row) {
            return [
                'Consignment No'     => $row['consignment_no'],
                'Client'             => $row['client'],
                'Consignment Date'   => $row['consignment_at'],
                'Status'             => $row['status'],
                'Issued'             => $row['issued_count'],
                'Returned'           => $row['returned_count'],
                'Used'               => $row['used_count'],
                'Unreconciled'       => $row['unreconciled_count'],
                'Return Rate (%)'    => $row['return_rate'],
                'Usage Rate (%)'     => $row['usage_rate'],
            ];
        })->toArray();
    }
}
