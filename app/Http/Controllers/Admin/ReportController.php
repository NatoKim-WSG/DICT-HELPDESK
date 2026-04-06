<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\Reports\ReportPageDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportPageDataService $reportPageData,
    ) {}

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $viewData = $this->reportPageData->buildIndexViewData($request, $user);

        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('admin.reports.partials.shell', $viewData)->render(),
            ]);
        }

        return view('admin.reports.index', $viewData);
    }

    public function monthlyPdf(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $viewData = $this->reportPageData->buildMonthlyPdfViewData($request, $user);
        $pdf = Pdf::loadView('admin.reports.monthly-pdf', $viewData)
            ->setPaper('a4', 'portrait');

        return $pdf->download('ticket-monthly-report-'.$viewData['selectedMonthKey'].'.pdf');
    }
}
