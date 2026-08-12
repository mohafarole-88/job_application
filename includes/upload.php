<?php
/**
 * includes/upload.php
 * Validates and stores a single uploaded file. Never trusts the
 * client-supplied filename or the client-supplied MIME type — the
 * actual file content is sniffed with finfo before acceptance.
 */

class UploadRejected extends RuntimeException {}

/**
 * @param array  $file          A single entry from $_FILES (already isset-checked by caller)
 * @param array  $allowedMimes  Whitelist of acceptable MIME types
 * @param string $destinationDir Absolute path to save into (already created)
 * @param string $docType       'photo' | 'cv' | 'certificate' | 'other'
 * @return array{stored_filename:string, storage_path:string, original_filename:string, mime_type:string, file_size_bytes:int, doc_type:string}
 */
function handle_uploaded_file(array $file, array $allowedMimes, string $destinationDir, string $docType): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new UploadRejected('Invalid upload payload.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new UploadRejected('No file was uploaded.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new UploadRejected('The uploaded file is too large.');
        default:
            throw new UploadRejected('The file upload failed. Please try again.');
    }

    if ($file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) {
        throw new UploadRejected('The uploaded file exceeds the maximum allowed size.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UploadRejected('Invalid upload — file was not received correctly.');
    }

    // Sniff the real MIME type from file content, never trust $file['type'].
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($detectedMime, $allowedMimes, true)) {
        throw new UploadRejected('That file type is not accepted.');
    }

    $storedFilename = safe_stored_filename($file['name']);
    $destinationPath = rtrim($destinationDir, '/') . '/' . $storedFilename;

    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        throw new UploadRejected('Could not save the uploaded file.');
    }

    // Belt-and-braces: ensure uploaded files are never web-executable.
    chmod($destinationPath, 0640);

    return [
        'stored_filename'    => $storedFilename,
        'storage_path'       => $destinationPath,
        'original_filename'  => basename($file['name']), // display only, never used to build paths
        'mime_type'          => $detectedMime,
        'file_size_bytes'    => (int) $file['size'],
        'doc_type'           => $docType,
    ];
}

/**
 * Normalizes a multi-file input (e.g. certificates[]) from $_FILES'
 * grouped-array format into a list of individual per-file arrays.
 */
function normalize_multi_file_input(array $filesEntry): array
{
    $count = count($filesEntry['name']);
    $normalized = [];
    for ($i = 0; $i < $count; $i++) {
        if ($filesEntry['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $normalized[] = [
            'name'     => $filesEntry['name'][$i],
            'type'     => $filesEntry['type'][$i],
            'tmp_name' => $filesEntry['tmp_name'][$i],
            'error'    => $filesEntry['error'][$i],
            'size'     => $filesEntry['size'][$i],
        ];
    }
    return $normalized;
}
