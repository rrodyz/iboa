<?php

namespace App\Exports\Concerns;

use App\Models\Company;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Provides helper methods for building a compact company information block
 * in Excel export header rows (T_HEADER / T_PERIOD).
 *
 * The trait augments the company name in the left cell with address/city,
 * and the period/filter row with IFU, RCCM, phone, and e-mail.
 */
trait HasCompanyHeader
{
    /**
     * Build the left-hand content for the main header row.
     * Returns: "Company Name\nAddress, City\nPhone · Email"
     */
    protected function companyNameCell(?Company $company): string
    {
        $lines = [];
        $lines[] = $company?->name ?? '';

        $addr = array_filter([
            $company?->address,
            $company?->city ? (($company?->postal_code ? $company->postal_code . ' ' : '') . $company->city) : null,
        ]);
        if ($addr) {
            $lines[] = implode(', ', $addr);
        }

        $contact = array_filter([
            $company?->phone  ? 'Tél : ' . $company->phone  : null,
            $company?->email  ?? null,
        ]);
        if ($contact) {
            $lines[] = implode('  ·  ', $contact);
        }

        return implode("\n", array_filter($lines));
    }

    /**
     * Build the content for the period/sub-header row.
     * Returns: "RCCM : XX  ·  IFU : XX  ·  NIF : XX  ·  Capital : XX — existing subtitle"
     */
    protected function companyLegalLine(?Company $company, string $subtitle = ''): string
    {
        $parts = [];
        if ($company?->rccm)        $parts[] = 'RCCM : ' . $company->rccm;
        if ($company?->ifu)         $parts[] = 'IFU : '  . $company->ifu;
        if ($company?->nif)         $parts[] = 'NIF : '  . $company->nif;
        if ($company?->is_vat_subject && $company?->vat_number)
                                    $parts[] = 'TVA : '  . $company->vat_number;
        if ($company?->legal_form)  $parts[] = $company->legal_form;
        if ($company?->share_capital)
            $parts[] = 'Capital : ' . number_format($company->share_capital, 0, ',', ' ')
                       . ' ' . ($company->share_capital_currency ?? '');

        $legal = implode('  ·  ', $parts);

        if ($legal && $subtitle) {
            return $legal . '     |     ' . $subtitle;
        }

        return $legal ?: $subtitle;
    }

    /**
     * Apply text wrapping + proper row height to the company header cells.
     * Call this from within applyStyles() after the normal T_HEADER styling.
     */
    protected function applyCompanyHeaderWrap(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
        int $headerRow,
        int $periodRow
    ): void {
        // Wrap text in cell A of the header row so the address lines show
        $ws->getStyle("A{$headerRow}")->getAlignment()->setWrapText(true);
        $ws->getRowDimension($headerRow)->setRowHeight(46);

        // Wrap / auto-height for the period row (legal line can be long)
        $ws->getStyle("A{$periodRow}")->getAlignment()->setWrapText(true);
    }
}
