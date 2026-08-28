<?php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generate a PDF document asynchronously and store it in storage/app/documents/.
 *
 * Usage:
 *   GenerateDocumentPdfJob::dispatch('invoice', $invoice, 'ventes.pdf.invoice', ['invoice' => $invoice])
 *       ->afterResponse();
 *
 * [P6 — Phase 15] UNUSED / NOT IN ACTIVE FLOW : aucun ->dispatch() réel
 * trouvé dans le code (seul l'exemple ci-dessus, en docblock). Si ce job
 * est un jour câblé pour l'un des 7 templates à pagination
 * (App\Support\Pdf\PageNumberStamp), appeler
 * PageNumberStamp::apply($pdf) avant $pdf->output() dans handle().
 */
class GenerateDocumentPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly string $documentType,   // 'invoice', 'quote', 'delivery_note', etc.
        public readonly int    $documentId,
        public readonly string $view,           // blade view path
        public readonly array  $viewData,       // data passed to the view
        public readonly string $filename,       // e.g. 'facture-F2025-001.pdf'
        public readonly string $disk = 'local', // storage disk
    ) {}

    public function handle(): void
    {
        $pdf  = Pdf::loadView($this->view, $this->viewData)
            ->setPaper('a4', 'portrait');

        $path = 'documents/' . $this->documentType . '/' . $this->filename;

        Storage::disk($this->disk)->put($path, $pdf->output());

        Log::info("PDF generated", [
            'type'     => $this->documentType,
            'id'       => $this->documentId,
            'filename' => $this->filename,
            'disk'     => $this->disk,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("PDF generation failed", [
            'type'  => $this->documentType,
            'id'    => $this->documentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
