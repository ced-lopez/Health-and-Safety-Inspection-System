<?php

namespace App\Services;

use App\Models\Clearance;
use App\Models\Inspection;
use App\Models\Payment;
use App\Models\Violation;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Model;

class DocumentPdfService
{
    public function qrDataUri(string $content): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 8,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($content);
    }

    public function pdf(Model $document): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadHTML($this->html($document))
            ->setPaper('a4', 'portrait');
    }

    /**
     * Produce the compact proof-of-clearance card. The QR always resolves to
     * the public verifier; no status or personal clearance data is embedded.
     */
    public function qrCardPdf(Clearance $clearance, string $format = 'wallet'): \Barryvdh\DomPDF\PDF
    {
        $format = $format === 'signage' ? 'signage' : 'wallet';
        $code = $clearance->qrCode?->code;
        $verificationUrl = $code ? config('app.frontend_url').'/verify/'.$code : null;

        $pdf = Pdf::loadHTML(view('pdf.clearance-qr-card', array_merge($this->branding(), [
            'clearance_number' => $clearance->clearance_number,
            'format' => $format,
            'logo_data_uri' => $this->logoDataUri(),
            'qr_data_uri' => $verificationUrl ? $this->qrDataUri($verificationUrl) : null,
        ]))->render());

        // Dompdf uses points. Wallet is the ISO/IEC 7810 ID-1 size: 85 x 54 mm.
        return $format === 'wallet'
            ? $pdf->setPaper([0, 0, 240.94, 153.07])
            : $pdf->setPaper('a6', 'portrait');
    }

    public function html(Model $document): string
    {
        $isClearance = $document instanceof Clearance;
        $code = $document->qrCode?->code;

        $data = array_merge($this->branding(), [
            'title' => $isClearance
                ? 'Health and Safety Clearance'
                : 'Certificate of Compliance',
            'number' => $isClearance
                ? $document->clearance_number
                : $document->certificate_number,
            'type' => $isClearance
                ? $document->clearance_type
                : $document->certificate_type,
            'purpose' => $document->purpose,
            'status' => $document->status,
            'issue_date' => $document->issue_date?->format('F j, Y'),
            'expiration_date' => $document->expiration_date
                ? $document->expiration_date->format('F j, Y')
                : 'N/A',
            'establishment' => $document->establishment,
            'issuer' => $document->issuer?->name,
            'inspection' => $document->inspection,
            'logo_data_uri' => $this->logoDataUri(),
            'code' => $code,
            'verification_url' => $code
                ? config('app.frontend_url').'/verify/'.$code
                : null,
            'qr_data_uri' => $code
                ? $this->qrDataUri(config('app.frontend_url').'/verify/'.$code)
                : null,
            'generated_at' => now()->format('F j, Y g:i A'),
        ]);

        return view('pdf.document', $data)->render();
    }

    public function receiptPdf(Payment $payment): \Barryvdh\DomPDF\PDF
    {
        $request = $payment->inspectionRequest;
        $typeLabel = $payment->type === 'application_fee' ? 'Application Fee' : 'Clearance Fee';

        return Pdf::loadHTML(view('pdf.receipt', array_merge($this->branding(), [
            'title' => 'Official Payment Receipt',
            'payment' => $payment,
            'request' => $request,
            'type_label' => $typeLabel,
            'amount' => number_format((float) $payment->amount, 2),
            'paid_at' => $payment->paid_at?->format('F j, Y g:i A'),
            'generated_at' => now()->format('F j, Y g:i A'),
        ]))->render())->setPaper('a4', 'portrait');
    }

    public function inspectionReportPdf(Inspection $inspection): \Barryvdh\DomPDF\PDF
    {
        $results = $inspection->results ?? collect();

        return Pdf::loadHTML(view('pdf.inspection-report', array_merge($this->branding(), [
            'title' => 'Inspection Report',
            'inspection' => $inspection,
            'establishment' => $inspection->establishment,
            'inspector' => $inspection->inspector,
            'results' => $results,
            'violations' => $inspection->violations ?? collect(),
            'summary' => [
                'total' => $results->count(),
                'compliant' => $results->where('compliance_status', 'compliant')->count(),
                'non_compliant' => $results->where('compliance_status', 'non_compliant')->count(),
                'needs_correction' => $results->where('compliance_status', 'needs_correction')->count(),
            ],
            'generated_at' => now()->format('F j, Y g:i A'),
        ]))->render())->setPaper('a4', 'portrait');
    }

    public function violationNoticePdf(Violation $violation): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadHTML(view('pdf.violation-notice', array_merge($this->branding(), [
            'title' => 'Violation Notice',
            'violation' => $violation,
            'establishment' => $violation->establishment,
            'inspection' => $violation->inspection,
            'deadline' => $violation->correction_deadline?->format('F j, Y') ?? '7 days from notice',
            'generated_at' => now()->format('F j, Y g:i A'),
        ]))->render())->setPaper('a4', 'portrait');
    }

    private function branding(): array
    {
        return [
            'barangay_name' => config('barangay.name'),
            'barangay_city' => config('barangay.city'),
            'barangay_office' => config('barangay.office'),
            'captain_name' => config('barangay.captain.name'),
            'captain_title' => config('barangay.captain.title'),
            'health_officer_name' => config('barangay.health_officer.name'),
            'health_officer_title' => config('barangay.health_officer.title'),
        ];
    }

    private function logoDataUri(): ?string
    {
        // Deployments may override this with a backend-managed branding asset.
        // The fallback reuses the logo already bundled by the frontend.
        $configuredPath = config('barangay.logo_path');
        $paths = array_filter([
            $configuredPath,
            base_path('../frontend/src/assets/brgy178logo.jpg'),
        ]);

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return null;
    }
}
