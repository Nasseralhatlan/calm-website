<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FinanceDocumentsIndexRequest;
use App\Http\Resources\Api\FinanceDocumentResource;
use App\Http\Responses\ApiResponse;
use App\Models\FinancialDocument;
use App\Services\Finance\FinancialDocumentService;
use App\Services\Finance\QoyodSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user's own financial documents: guests see their booking invoices and
 * credit notes, hosts their commission invoices and payout statements.
 * Strictly owner-only (admin excepted) — a document id belonging to someone
 * else 404s so existence never leaks. PDFs are Qoyod-hosted expiring links
 * fetched fresh per request, never stored.
 */
class FinanceDocumentsController extends Controller
{
    public function __construct(
        private readonly FinancialDocumentService $documents,
        private readonly QoyodSyncService $qoyod,
    ) {}

    /** The viewer's documents, newest first, paginated; ?booking_id scopes to one booking. */
    public function index(FinanceDocumentsIndexRequest $request): JsonResponse
    {
        $paginator = $this->documents->forUser($request->user(), $request->bookingId());

        return ApiResponse::success(
            data: [
                'items' => FinanceDocumentResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
            message: 'Financial documents fetched.',
        );
    }

    /** A fresh, short-lived PDF link for one of the viewer's own documents. */
    public function pdfUrl(Request $request, FinancialDocument $document): JsonResponse
    {
        $this->authorizeOwner($request, $document);

        $url = $this->qoyod->pdfUrl($document);

        if ($url === null) {
            return ApiResponse::error(
                'The PDF for this document is not available yet.',
                Response::HTTP_CONFLICT,
            );
        }

        return ApiResponse::success(
            data: ['url' => $url],
            message: 'Document link minted — it expires shortly, fetch again when needed.',
        );
    }

    /** Owner (the billed party) or admin only; 404 so existence never leaks. */
    private function authorizeOwner(Request $request, FinancialDocument $document): void
    {
        $viewer = $request->user();

        abort_unless(
            $viewer !== null && ($document->buyer_id === $viewer->id || $viewer->isAdmin()),
            Response::HTTP_NOT_FOUND,
        );
    }
}
