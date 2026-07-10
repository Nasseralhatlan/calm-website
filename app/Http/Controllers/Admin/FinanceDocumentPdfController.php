<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialDocument;
use App\Services\Finance\QoyodSyncService;
use Illuminate\Http\RedirectResponse;

/**
 * Opens the Qoyod-hosted PDF of a tax document for admin support. Links
 * expire, so a fresh one is fetched per click and never stored. Statements
 * and unsynced documents have no external PDF — those flash an explanation.
 */
class FinanceDocumentPdfController extends Controller
{
    public function __invoke(FinancialDocument $document, QoyodSyncService $qoyod): RedirectResponse
    {
        $url = $qoyod->pdfUrl($document);

        if ($url === null) {
            return redirect()
                ->back()
                ->with('error', __('No PDF available — the document is internal or not yet synced to Qoyod.'));
        }

        return redirect()->away($url);
    }
}
