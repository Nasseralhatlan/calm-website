<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Finance module (invoices, movements, Qoyod e-invoicing)
|--------------------------------------------------------------------------
| Marketplace model: Calm → Guest booking invoice, Calm → Host commission
| invoice (both official tax documents, synced to Qoyod when enabled), and an
| internal host payout settlement statement (never an invoice). All amounts
| are halalas; rates are snapshotted per booking at creation time.
*/

return [
    'vat' => [
        // Master switch for VAT lines on newly created bookings. Historical
        // bookings keep their snapshotted rates regardless.
        'enabled' => (bool) env('FINANCE_VAT_ENABLED', true),
        'rate' => (float) env('FINANCE_VAT_RATE', 15.00),
    ],

    'invoice' => [
        // Hours after the guest's checkout instant before the finance
        // finalizer issues the two official invoices.
        'issue_after_checkout_hours' => (int) env('FINANCE_INVOICE_ISSUE_HOURS', 4),
        'default_type' => 'simplified_tax_invoice',
    ],

    'qoyod' => [
        // Off until the Qoyod account + API key exist; documents are still
        // created locally so the flow works end-to-end without it.
        'enabled' => (bool) env('QOYOD_ENABLED', false),
        'api_key' => env('QOYOD_API_KEY'),
        'base_url' => env('QOYOD_BASE_URL', 'https://api.qoyod.com/2.0'),
        'timeout' => (int) env('QOYOD_TIMEOUT', 30),

        // One-time Qoyod setup ids (created in the Qoyod UI, see docs):
        // generic service products + the accounts payments are booked against.
        'product_stay_id' => env('QOYOD_PRODUCT_STAY_ID'),
        'product_commission_id' => env('QOYOD_PRODUCT_COMMISSION_ID'),
        'moyasar_account_id' => env('QOYOD_MOYASAR_ACCOUNT_ID'),      // guest payments clearing
        'settlement_account_id' => env('QOYOD_SETTLEMENT_ACCOUNT_ID'), // commission offset
        'inventory_id' => env('QOYOD_INVENTORY_ID', 1),
    ],
];
