<?php
/**
 * includes/generate-pdf.php
 * Fetches a submitted application's full data and renders it to a PDF
 * using Dompdf, saved into the application's storage folder (never
 * inside public/). Requires `composer require dompdf/dompdf`.
 */

require_once __DIR__ . '/pdf-template.php';
require_once __DIR__ . '/application-data.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerationFailed extends RuntimeException {}

/**
 * @return string Absolute filesystem path to the generated PDF.
 */
function generate_application_pdf(PDO $pdo, int $applicationId): string
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new PdfGenerationFailed(
            'PDF library not installed. Run "composer require dompdf/dompdf" in the project root.'
        );
    }
    require_once $autoload;

    $data = fetch_application_full($pdo, $applicationId);
    if ($data === null) {
        throw new PdfGenerationFailed("Application #{$applicationId} not found.");
    }
    $app = $data['application'];
    $employment = $data['employment'];
    $qualifications = $data['qualifications'];
    $training = $data['training'];
    $references = $data['references'];
    $photo = null;
    foreach ($data['documents'] as $doc) {
        if ($doc['doc_type'] === 'photo') {
            $photo = $doc;
            break;
        }
    }

    // ---- Render HTML ----
    $html = render_application_pdf_html($app, $employment, $qualifications, $training, $references, $photo);

    // ---- Render PDF ----
    $options = new Options();
    $options->set('isRemoteEnabled', false);   // never fetch remote URLs — closes an SSRF path
    $options->set('isPhpEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    // Restrict local file access to the application's own storage folder only.
    $storageDir = application_storage_dir($app['application_number']);
    $options->setChroot([$storageDir, APPLICATIONS_STORAGE]);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdfPath = $storageDir . '/application.pdf';
    file_put_contents($pdfPath, $dompdf->output());
    chmod($pdfPath, 0640);

    // ---- Save the path back onto the application row ----
    $stmt = $pdo->prepare('UPDATE applications SET pdf_path = :path, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['path' => $pdfPath, 'id' => $applicationId]);

    return $pdfPath;
}
