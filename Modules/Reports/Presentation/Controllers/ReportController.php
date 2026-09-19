<?php

namespace Modules\Reports\Presentation\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Reports\Application\ActivityReport;

/**
 * API face of the report (see ActivityReport for the rules and the
 * export-format note).
 */
class ReportController extends Controller
{
    public function summary(Request $request, ActivityReport $report): JsonResponse
    {
        return response()->json(['data' => $report->summary($request)]);
    }

    public function export(Request $request, ActivityReport $report): Response
    {
        return response($report->csv($request), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activities-report.csv"',
        ]);
    }
}
