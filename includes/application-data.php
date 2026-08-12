<?php
/**
 * includes/application-data.php
 * Shared fetch logic for a full application record — used by both
 * PDF generation and the admin detail view, so they can never drift
 * out of sync with each other.
 */

function fetch_application_full(PDO $pdo, int $applicationId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id');
    $stmt->execute(['id' => $applicationId]);
    $app = $stmt->fetch();
    if (!$app) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM employment_history WHERE application_id = :id ORDER BY sort_order');
    $stmt->execute(['id' => $applicationId]);
    $employment = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM qualifications WHERE application_id = :id ORDER BY sort_order');
    $stmt->execute(['id' => $applicationId]);
    $qualifications = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM training WHERE application_id = :id');
    $stmt->execute(['id' => $applicationId]);
    $training = [];
    foreach ($stmt->fetchAll() as $row) {
        $training[$row['course_name']] = $row;
    }

    $stmt = $pdo->prepare('SELECT * FROM `references` WHERE application_id = :id');
    $stmt->execute(['id' => $applicationId]);
    $references = [];
    foreach ($stmt->fetchAll() as $row) {
        $references[$row['ref_type']] = $row;
    }

    $stmt = $pdo->prepare('SELECT * FROM documents WHERE application_id = :id ORDER BY uploaded_at');
    $stmt->execute(['id' => $applicationId]);
    $documents = $stmt->fetchAll();

    return [
        'application'     => $app,
        'employment'      => $employment,
        'qualifications'  => $qualifications,
        'training'        => $training,
        'references'      => $references,
        'documents'       => $documents,
    ];
}
