<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Server-side validation for customer identity uploads.
 *
 * The browser's filename, extension and Content-Type are all attacker
 * controlled.  This guard verifies the temporary file itself before the
 * CodeIgniter upload library is allowed to persist it.
 */
class Identity_upload_guard
{
    const MAX_BYTES        = 4194304; // 4 MiB per uploaded side
    const MAX_IMAGE_PIXELS = 25000000;
    const MAX_DIMENSION    = 10000;

    /** Return TRUE only for an actual submitted file (not an empty slot). */
    public function is_present($file)
    {
        return is_array($file)
            && isset($file['error'])
            && is_scalar($file['error'])
            && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Validate one normalized PHP upload entry.
     *
     * @param array $file                 name/type/tmp_name/error/size
     * @param bool  $allow_pdf            PDFs are allowed only in side 1
     * @param bool  $require_http_upload  Disable only in CLI unit tests
     * @return string|null Human-readable error, or NULL when valid/empty
     */
    public function validate($file, $allow_pdf = TRUE, $require_http_upload = TRUE)
    {
        if ( ! is_array($file)) {
            return 'The uploaded document has an invalid request format.';
        }

        foreach (array('name', 'tmp_name', 'error', 'size') as $key) {
            if ( ! array_key_exists($key, $file) || ! is_scalar($file[$key])) {
                return 'The uploaded document has an invalid request format.';
            }
        }

        $error = (int) $file['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return NULL;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $messages = array(
                UPLOAD_ERR_INI_SIZE   => 'The document is larger than the server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The document is larger than the allowed upload limit.',
                UPLOAD_ERR_PARTIAL    => 'The document upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server upload folder is unavailable.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded document.',
                UPLOAD_ERR_EXTENSION  => 'The server rejected the uploaded document.',
            );
            return isset($messages[$error]) ? $messages[$error] : 'The document upload failed.';
        }

        $tmp = (string) $file['tmp_name'];
        if ($tmp === '' || ! is_file($tmp)) {
            return 'The uploaded document is missing or unreadable.';
        }
        if ($require_http_upload && ! is_uploaded_file($tmp)) {
            return 'The document was not received through a valid upload request.';
        }

        $reported_size = (int) $file['size'];
        $actual_size   = @filesize($tmp);
        if ($reported_size <= 0 || $actual_size === FALSE || $actual_size <= 0) {
            return 'Empty documents cannot be uploaded.';
        }
        if ($reported_size > self::MAX_BYTES || $actual_size > self::MAX_BYTES) {
            return 'Each document image or PDF must be 4 MB or smaller.';
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowed = $allow_pdf
            ? array('jpg', 'jpeg', 'png', 'pdf')
            : array('jpg', 'jpeg', 'png');
        if ( ! in_array($extension, $allowed, TRUE)) {
            return $allow_pdf
                ? 'Only JPG, PNG or PDF documents are allowed.'
                : 'Image 2 must be a JPG or PNG image.';
        }

        if ( ! function_exists('finfo_open')) {
            return 'Secure file inspection is unavailable on the server.';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $tmp) : FALSE;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($extension === 'pdf') {
            $head = @file_get_contents($tmp, FALSE, NULL, 0, 5);
            if ($mime !== 'application/pdf' || $head !== '%PDF-') {
                return 'The selected PDF is not a valid PDF document.';
            }
            return NULL;
        }

        $expected_mime = in_array($extension, array('jpg', 'jpeg'), TRUE)
            ? 'image/jpeg'
            : 'image/png';
        if ($mime !== $expected_mime) {
            return 'The selected image content does not match its file type.';
        }

        $image = @getimagesize($tmp);
        $expected_type = $expected_mime === 'image/jpeg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
        if (
            ! is_array($image)
            || empty($image[0])
            || empty($image[1])
            || (isset($image[2]) && (int) $image[2] !== $expected_type)
        ) {
            return 'The selected file is not a valid JPG or PNG image.';
        }

        $width  = (int) $image[0];
        $height = (int) $image[1];
        if (
            $width > self::MAX_DIMENSION
            || $height > self::MAX_DIMENSION
            || ($width * $height) > self::MAX_IMAGE_PIXELS
        ) {
            return 'The image dimensions are too large. Crop or resize it before uploading.';
        }

        return NULL;
    }
}
