<?php

namespace App\Services;

use App\Models\Clearance;
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

    public function html(Model $document): string
    {
        $isClearance = $document instanceof Clearance;
        $code = $document->qrCode?->code;

        $data = [
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
            'code' => $code,
            'verification_url' => $code
                ? config('app.frontend_url').'/verify/'.$code
                : null,
            'qr_data_uri' => $code
                ? $this->qrDataUri(config('app.frontend_url').'/verify/'.$code)
                : null,
            'generated_at' => now()->format('F j, Y g:i A'),
        ];

        return view('pdf.document', $data)->render();
    }
}
