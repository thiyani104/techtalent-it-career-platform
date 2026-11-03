<?php
/**
 * CV import: upload validation, text extraction (PDF/TXT) and rule-based section parsing.
 * No external services. Unknown fields are left empty rather than guessed.
 */

require_once __DIR__ . '/../includes/cv-functions.php';
require_once __DIR__ . '/skill-gap-analyzer.php';

/* =====================================================================
 * Upload validation & private storage
 * =================================================================== */

/** @return array{ok:bool,error?:string,ext?:string,mime?:string} */
function cv_validate_upload(?array $file): array
{
    if (!$file || !isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'No file was uploaded.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'The file is too large (max ' . (CV_MAX_UPLOAD_BYTES / 1048576) . ' MB).'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'The upload failed. Please try again.'];
    }
    if ($file['size'] <= 0 || $file['size'] > CV_MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'The file must be between 1 byte and ' . (CV_MAX_UPLOAD_BYTES / 1048576) . ' MB.'];
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'txt'], true)) {
        return ['ok' => false, 'error' => 'Only PDF and TXT files are accepted.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 1024);
    if ($ext === 'pdf') {
        if ($mime !== 'application/pdf' || !str_contains($head, '%PDF-')) {
            return ['ok' => false, 'error' => 'This file is not a valid PDF document.'];
        }
    } else {
        if (!in_array($mime, ['text/plain', 'text/x-c', 'application/octet-stream', 'text/x-asm'], true) && !str_starts_with($mime, 'text/')) {
            return ['ok' => false, 'error' => 'This file is not a plain-text document.'];
        }
        // Reject anything that looks like script/markup or binary content.
        if (preg_match('/<\?php|<script|^#!/i', $head) || str_contains($head, "\0")) {
            return ['ok' => false, 'error' => 'This text file contains content that is not allowed.'];
        }
        $mime = 'text/plain';
    }
    return ['ok' => true, 'ext' => $ext, 'mime' => $mime];
}

/** Move an uploaded file into private storage under a random name. Returns the stored name. */
function cv_store_upload(string $tmpPath): string
{
    if (!is_dir(CV_PRIVATE_STORAGE) && !mkdir(CV_PRIVATE_STORAGE, 0750, true)) {
        throw new RuntimeException('CV storage directory is not writable.');
    }
    $name = bin2hex(random_bytes(16)) . '.bin';
    if (!move_uploaded_file($tmpPath, CV_PRIVATE_STORAGE . DIRECTORY_SEPARATOR . $name)) {
        throw new RuntimeException('Could not store uploaded file.');
    }
    return $name;
}

/* =====================================================================
 * Text extraction
 * =================================================================== */

/**
 * @return array{text:string,status:string,method:string,message:string,garbled_ratio:float}
 */
function cv_extract_text(string $path, string $ext): array
{
    $data = (string)file_get_contents($path);
    if ($ext === 'txt') {
        $text = cv_to_utf8($data);
        return cv_extraction_result($text, 'text file');
    }

    $result = ['text' => '', 'status' => 'failed', 'method' => 'php-pdf', 'message' => '', 'garbled_ratio' => 0.0];
    if (preg_match('/\/Encrypt\s/', $data)) {
        $result['message'] = 'This PDF is encrypted or password-protected, so its text cannot be read. Please upload an unprotected copy or a TXT file.';
        return $result;
    }
    try {
        $text = pdf_extract_text($data);
    } catch (Throwable $e) {
        $text = '';
    }
    $result = cv_extraction_result($text, 'built-in PDF reader');

    // Optional Poppler fallback when configured and the built-in reader struggled.
    if ($result['status'] !== 'ok' && CV_PDFTOTEXT_BIN !== '' && is_file(CV_PDFTOTEXT_BIN)) {
        $out = shell_exec(escapeshellarg(CV_PDFTOTEXT_BIN) . ' -layout -enc UTF-8 ' . escapeshellarg($path) . ' -');
        if (is_string($out) && cv_alnum_count($out) > cv_alnum_count($result['text'])) {
            $result = cv_extraction_result($out, 'pdftotext');
        }
    }
    if ($result['status'] === 'no_text') {
        $scanned = (bool)preg_match('/\/Subtype\s*\/Image/', $data);
        $result['message'] = $scanned
            ? 'No selectable text was found. This PDF appears to be a scanned image; OCR is not supported. Upload a text-based PDF (exported from Word/Google Docs) or a TXT file, or start a blank CV.'
            : 'No readable text could be extracted from this PDF. Try exporting it again from your word processor, or upload a TXT file.';
    }
    return $result;
}

function cv_extraction_result(string $text, string $method): array
{
    $text = cv_tidy_text($text);
    $nonSpace = preg_match_all('/\S/u', $text);
    $bad = preg_match_all('/[\x{FFFD}\x{E000}-\x{F8FF}]|[\x00-\x08\x0E-\x1F]/u', $text);
    $ratio = $nonSpace ? $bad / $nonSpace : 0.0;
    $alnum = cv_alnum_count($text);
    $status = $alnum < 40 ? 'no_text' : ($ratio > 0.15 ? 'partial' : 'ok');
    $message = $status === 'partial'
        ? 'Some text in this file uses fonts that could not be decoded. Please check every field carefully.'
        : '';
    return ['text' => $text, 'status' => $status, 'method' => $method, 'message' => $message, 'garbled_ratio' => round($ratio, 3)];
}

function cv_alnum_count(string $s): int
{
    return (int)preg_match_all('/[\p{L}\p{N}]/u', $s);
}

function cv_to_utf8(string $s): string
{
    if (str_starts_with($s, "\xEF\xBB\xBF")) {
        $s = substr($s, 3);
    } elseif (str_starts_with($s, "\xFF\xFE")) {
        $s = mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16LE');
    } elseif (str_starts_with($s, "\xFE\xFF")) {
        $s = mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16BE');
    }
    return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
}

function cv_tidy_text(string $t): string
{
    $t = strtr($t, ["\xEF\xAC\x80" => 'ff', "\xEF\xAC\x81" => 'fi', "\xEF\xAC\x82" => 'fl', "\xEF\xAC\x83" => 'ffi', "\xEF\xAC\x84" => 'ffl', "\xC2\xA0" => ' ']);
    $t = str_replace(["\r\n", "\r", "\f"], "\n", $t);
    $t = preg_replace('/[ \t]+/u', ' ', $t) ?? $t;
    $t = preg_replace('/ *\n */', "\n", $t) ?? $t;
    $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
    return trim($t);
}

/* ---------------------------------------------------------------------
 * Minimal PDF text extractor (FlateDecode streams, object streams,
 * ToUnicode CMaps, WinAnsi fallback). Not a full PDF implementation.
 * ------------------------------------------------------------------- */

function pdf_extract_text(string $data): string
{
    $objects = pdf_parse_objects($data);
    $pdf = ['objects' => $objects, 'cmaps' => []];

    $pages = [];
    foreach ($objects as $num => $obj) {
        if (preg_match('/\/Type\s*\/Page(?![a-zA-Z])/', $obj['dict'])) {
            $pages[] = $num;
        }
    }
    // Keep document order by following the page tree when possible.
    $ordered = pdf_page_order($pdf);
    if ($ordered) {
        $pages = $ordered;
    }

    $out = '';
    foreach ($pages as $num) {
        $dict = $objects[$num]['dict'];
        $fonts = pdf_page_fonts($pdf, $num);
        $content = '';
        foreach (pdf_refs(pdf_dict_value($dict, 'Contents') ?? '') as $ref) {
            $content .= pdf_stream($pdf, $ref) . "\n";
        }
        $out .= pdf_content_text($content, $fonts) . "\n";
        $seen = [];
        $out .= pdf_forms_text($pdf, pdf_page_resources($pdf, $num), $fonts, 0, $seen) . "\n";
    }
    return $out;
}

/** Text drawn inside (possibly nested) Form XObjects referenced from a resource dictionary. */
function pdf_forms_text(array &$pdf, string $resources, array $fonts, int $depth, array &$seen): string
{
    if ($depth > 8) {
        return '';
    }
    $out = '';
    $xobj = pdf_resolve_dict($pdf, pdf_dict_value($resources, 'XObject') ?? '');
    if (!preg_match_all('/\/[^\s\/<>\[\]()]+\s+(\d+)\s+\d+\s+R/', $xobj, $xm)) {
        return '';
    }
    foreach ($xm[1] as $xref) {
        $xref = (int)$xref;
        $x = $pdf['objects'][$xref] ?? null;
        if (isset($seen[$xref]) || !$x || !preg_match('/\/Subtype\s*\/Form/', $x['dict'])) {
            continue;
        }
        $seen[$xref] = true;
        $res = pdf_resolve_dict($pdf, pdf_dict_value($x['dict'], 'Resources') ?? '');
        $formFonts = pdf_fonts_from_resources($pdf, $res) + $fonts;
        $out .= pdf_content_text(pdf_stream($pdf, $xref), $formFonts) . "\n";
        $out .= pdf_forms_text($pdf, $res, $formFonts, $depth + 1, $seen);
    }
    return $out;
}

function pdf_parse_objects(string $data): array
{
    $objects = [];
    $len = strlen($data);
    $offset = 0;
    while (preg_match('/(\d+)\s+(\d+)\s+obj\b/', $data, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $num = (int)$m[1][0];
        $start = $m[0][1] + strlen($m[0][0]);
        $endobj = strpos($data, 'endobj', $start);
        if ($endobj === false) {
            break;
        }
        $streamPos = strpos($data, 'stream', $start);
        $stream = null;
        if ($streamPos !== false && $streamPos < $endobj) {
            $dict = substr($data, $start, $streamPos - $start);
            $s = $streamPos + 6;
            if (($data[$s] ?? '') === "\r") $s++;
            if (($data[$s] ?? '') === "\n") $s++;
            $length = null;
            if (preg_match('/\/Length\s+(\d+)\b(?!\s+\d+\s+R)/', $dict, $lm)) {
                $length = (int)$lm[1];
            }
            $es = strpos($data, 'endstream', $s);
            if ($length !== null && $s + $length <= $len && ($es === false || $s + $length <= $es + 2)) {
                $stream = substr($data, $s, $length);
                $after = $s + $length;
            } else {
                if ($es === false) {
                    break;
                }
                $stream = rtrim(substr($data, $s, $es - $s), "\r\n");
                $after = $es;
            }
            $endobj = strpos($data, 'endobj', $after);
            if ($endobj === false) {
                $endobj = $len;
            }
        } else {
            $dict = substr($data, $start, $endobj - $start);
        }
        $objects[$num] = ['dict' => trim($dict), 'stream' => $stream];
        $offset = $endobj + 6;
    }

    // Expand compressed object streams (PDF 1.5+).
    foreach ($objects as $num => $obj) {
        if ($obj['stream'] === null || !preg_match('/\/Type\s*\/ObjStm/', $obj['dict'])) {
            continue;
        }
        $decoded = pdf_decode_stream($obj['dict'], $obj['stream']);
        $n = (int)(pdf_dict_value($obj['dict'], 'N') ?? 0);
        $first = (int)(pdf_dict_value($obj['dict'], 'First') ?? 0);
        if ($decoded === null || $n <= 0) {
            continue;
        }
        $nums = preg_split('/\s+/', trim(substr($decoded, 0, $first)));
        $pairs = [];
        for ($i = 0; $i + 1 < count($nums) && count($pairs) < $n; $i += 2) {
            $pairs[] = [(int)$nums[$i], (int)$nums[$i + 1]];
        }
        foreach ($pairs as $k => [$onum, $off]) {
            $end = isset($pairs[$k + 1]) ? $pairs[$k + 1][1] : strlen($decoded) - $first;
            if (!isset($objects[$onum])) {
                $objects[$onum] = ['dict' => trim(substr($decoded, $first + $off, $end - $off)), 'stream' => null];
            }
        }
    }
    return $objects;
}

function pdf_decode_stream(string $dict, string $raw): ?string
{
    $filter = pdf_dict_value($dict, 'Filter') ?? '';
    if ($filter === '') {
        return $raw;
    }
    preg_match_all('/\/(\w+)/', $filter, $fm);
    foreach ($fm[1] as $f) {
        if ($f !== 'FlateDecode' && $f !== 'Fl') {
            return null; // Images (DCT etc.) and rare filters are not needed for text.
        }
    }
    $out = @gzuncompress($raw);
    if ($out === false) $out = @gzinflate($raw);
    if ($out === false) $out = @gzinflate(substr($raw, 2));
    return $out === false ? null : $out;
}

function pdf_stream(array &$pdf, int $num): string
{
    $obj = $pdf['objects'][$num] ?? null;
    if (!$obj || $obj['stream'] === null) {
        return '';
    }
    return pdf_decode_stream($obj['dict'], $obj['stream']) ?? '';
}

/** Raw value of /Key in a dictionary string (ref, name, number, <<dict>>, [array] or (string)). */
function pdf_dict_value(string $dict, string $key): ?string
{
    if (!preg_match('/\/' . preg_quote($key, '/') . '(?=[\s\/<\[(])/', $dict, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $i = $m[0][1] + strlen($m[0][0]);
    $len = strlen($dict);
    while ($i < $len && ctype_space($dict[$i])) $i++;
    if ($i >= $len) return null;
    if (substr($dict, $i, 2) === '<<') {
        $depth = 0;
        for ($j = $i; $j < $len - 1; $j++) {
            if (substr($dict, $j, 2) === '<<') { $depth++; $j++; }
            elseif (substr($dict, $j, 2) === '>>') { $depth--; $j++; if ($depth === 0) return substr($dict, $i, $j - $i + 1); }
        }
        return substr($dict, $i);
    }
    if ($dict[$i] === '[') {
        $depth = 0;
        for ($j = $i; $j < $len; $j++) {
            if ($dict[$j] === '[') $depth++;
            elseif ($dict[$j] === ']' && --$depth === 0) return substr($dict, $i, $j - $i + 1);
        }
        return substr($dict, $i);
    }
    if (preg_match('/\G(\d+\s+\d+\s+R)/', $dict, $rm, 0, $i)) {
        return $rm[1];
    }
    if (preg_match('/\G(\/?[^\s\/<>\[\]()]+)/', $dict, $vm, 0, $i)) {
        return $vm[1];
    }
    return null;
}

function pdf_refs(string $value): array
{
    preg_match_all('/(\d+)\s+\d+\s+R/', $value, $m);
    return array_map('intval', $m[1]);
}

/** Follow an indirect reference to a dictionary; returns the dict text. */
function pdf_resolve_dict(array &$pdf, string $value): string
{
    for ($guard = 0; $guard < 5 && preg_match('/^(\d+)\s+\d+\s+R$/', trim($value), $m); $guard++) {
        $value = $pdf['objects'][(int)$m[1]]['dict'] ?? '';
    }
    return $value;
}

function pdf_page_order(array &$pdf): array
{
    $root = null;
    foreach ($pdf['objects'] as $obj) {
        if (preg_match('/\/Type\s*\/Catalog/', $obj['dict'])) {
            $root = pdf_refs(pdf_dict_value($obj['dict'], 'Pages') ?? '')[0] ?? null;
            break;
        }
    }
    if ($root === null) {
        return [];
    }
    $pages = [];
    $walk = function (int $num, int $depth) use (&$walk, &$pdf, &$pages) {
        if ($depth > 30 || !isset($pdf['objects'][$num])) return;
        $dict = $pdf['objects'][$num]['dict'];
        if (preg_match('/\/Type\s*\/Pages/', $dict)) {
            foreach (pdf_refs(pdf_dict_value($dict, 'Kids') ?? '') as $kid) $walk($kid, $depth + 1);
        } elseif (preg_match('/\/Type\s*\/Page(?![a-zA-Z])/', $dict)) {
            $pages[] = $num;
        }
    };
    $walk($root, 0);
    return $pages;
}

function pdf_page_resources(array &$pdf, int $num): string
{
    for ($guard = 0; $guard < 20 && isset($pdf['objects'][$num]); $guard++) {
        $dict = $pdf['objects'][$num]['dict'];
        $res = pdf_dict_value($dict, 'Resources');
        if ($res !== null) {
            return pdf_resolve_dict($pdf, $res);
        }
        $parent = pdf_refs(pdf_dict_value($dict, 'Parent') ?? '');
        if (!$parent) break;
        $num = $parent[0];
    }
    return '';
}

function pdf_page_fonts(array &$pdf, int $num): array
{
    return pdf_fonts_from_resources($pdf, pdf_page_resources($pdf, $num));
}

/** Map font resource name => ['cmap' => array|null, 'bytes' => int, 'type0' => bool]. */
function pdf_fonts_from_resources(array &$pdf, string $resources): array
{
    $fontDict = pdf_resolve_dict($pdf, pdf_dict_value($resources, 'Font') ?? '');
    $fonts = [];
    if (!preg_match_all('/\/([^\s\/<>\[\]()]+)\s+(\d+)\s+\d+\s+R/', $fontDict, $m, PREG_SET_ORDER)) {
        return $fonts;
    }
    foreach ($m as [$_, $name, $ref]) {
        $font = $pdf['objects'][(int)$ref]['dict'] ?? '';
        $info = ['cmap' => null, 'bytes' => 1, 'type0' => (bool)preg_match('/\/Subtype\s*\/Type0/', $font)];
        $tu = pdf_refs(pdf_dict_value($font, 'ToUnicode') ?? '');
        if ($tu) {
            $key = $tu[0];
            if (!isset($pdf['cmaps'][$key])) {
                $pdf['cmaps'][$key] = pdf_parse_cmap(pdf_stream($pdf, $key));
            }
            $info['cmap'] = $pdf['cmaps'][$key]['map'];
            $info['bytes'] = $pdf['cmaps'][$key]['bytes'];
        } elseif ($info['type0']) {
            $info['bytes'] = 2;
        }
        [$info['widths'], $info['dw']] = pdf_font_widths($pdf, $font, $info['type0']);
        $fonts[$name] = $info;
    }
    return $fonts;
}

/** Glyph advance widths (1/1000 em) keyed by character code, plus the default width. */
function pdf_font_widths(array &$pdf, string $font, bool $type0): array
{
    $widths = [];
    if ($type0) {
        $desc = pdf_refs(pdf_dict_value($font, 'DescendantFonts') ?? '');
        $cid = $desc ? pdf_resolve_dict($pdf, ($desc[0] . ' 0 R')) : '';
        $dw = (float)(pdf_dict_value($cid, 'DW') ?? 1000);
        $w = pdf_dict_value($cid, 'W') ?? '';
        if (preg_match('/^\d+\s+\d+\s+R$/', $w)) $w = pdf_resolve_dict($pdf, $w);
        // Format: c [w1 w2 ...]  or  cFirst cLast w
        $w = trim($w);
        if (str_starts_with($w, '[') && str_ends_with($w, ']')) $w = substr($w, 1, -1);
        if (preg_match_all('/(\d+)\s*\[([^\]]*)\]|(\d+)\s+(\d+)\s+([\d.]+)/', $w, $m, PREG_SET_ORDER)) {
            foreach ($m as $g) {
                if (($g[2] ?? '') !== '' || ($g[1] ?? '') !== '') {
                    $c = (int)$g[1];
                    foreach (preg_split('/\s+/', trim($g[2])) as $k => $v) if ($v !== '') $widths[$c + $k] = (float)$v;
                } else {
                    for ($c = (int)$g[3]; $c <= (int)$g[4] && $c - (int)$g[3] < 65536; $c++) $widths[$c] = (float)$g[5];
                }
            }
        }
        return [$widths, $dw];
    }
    $first = (int)(pdf_dict_value($font, 'FirstChar') ?? 0);
    $w = pdf_dict_value($font, 'Widths') ?? '';
    if (preg_match('/^\d+\s+\d+\s+R$/', $w)) $w = pdf_resolve_dict($pdf, $w);
    foreach (preg_split('/\s+/', trim($w, '[] ')) as $k => $v) {
        if (is_numeric($v)) $widths[$first + $k] = (float)$v;
    }
    return [$widths, $widths ? 0.0 : 500.0];
}

/** Width of a shown string in text-space units. */
function pdf_string_width(string $raw, ?array $font, float $size): float
{
    $step = ($font['type0'] ?? false) ? 2 : 1;
    $total = 0.0;
    for ($i = 0, $n = strlen($raw); $i < $n; $i += $step) {
        $code = $step === 2 ? (ord($raw[$i]) << 8) | ord($raw[$i + 1] ?? "\0") : ord($raw[$i]);
        $total += $font['widths'][$code] ?? ($font['dw'] ?? 500.0) ?: 500.0;
    }
    return $total / 1000 * $size;
}

function pdf_parse_cmap(string $cmap): array
{
    $map = [];
    $bytes = 1;
    if (preg_match('/begincodespacerange\s*<([0-9a-fA-F]+)>/', $cmap, $cm)) {
        $bytes = max(1, intdiv(strlen($cm[1]), 2));
    }
    $utf16 = fn(string $hex) => mb_convert_encoding((string)hex2bin(strlen($hex) % 2 ? '0' . $hex : $hex), 'UTF-8', 'UTF-16BE');
    if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocks)) {
        foreach ($blocks[1] as $block) {
            preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]*)>/', $block, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $p) {
                $map[hexdec($p[1])] = $utf16($p[2]);
            }
        }
    }
    if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks)) {
        foreach ($blocks[1] as $block) {
            preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*(<[0-9a-fA-F]+>|\[[^\]]*\])/', $block, $ranges, PREG_SET_ORDER);
            foreach ($ranges as $r) {
                $lo = hexdec($r[1]);
                $hi = min(hexdec($r[2]), $lo + 65535);
                if ($r[3][0] === '[') {
                    preg_match_all('/<([0-9a-fA-F]+)>/', $r[3], $dsts);
                    foreach ($dsts[1] as $k => $d) {
                        $map[$lo + $k] = $utf16($d);
                    }
                } else {
                    $hex = trim($r[3], '<>');
                    $base = hexdec(substr($hex, -4));
                    $prefix = substr($hex, 0, -4);
                    for ($c = $lo; $c <= $hi; $c++) {
                        $map[$c] = $utf16($prefix . str_pad(dechex($base + $c - $lo), 4, '0', STR_PAD_LEFT));
                    }
                }
            }
        }
    }
    return ['map' => $map, 'bytes' => $bytes];
}

function pdf_decode_string(string $raw, ?array $font): string
{
    if ($font && $font['cmap'] !== null) {
        $out = '';
        $step = $font['bytes'];
        for ($i = 0, $n = strlen($raw); $i < $n; $i += $step) {
            $code = 0;
            for ($b = 0; $b < $step && $i + $b < $n; $b++) {
                $code = ($code << 8) | ord($raw[$i + $b]);
            }
            $out .= $font['cmap'][$code] ?? "\u{FFFD}";
        }
        return $out;
    }
    if ($font && $font['type0']) {
        return str_repeat("\u{FFFD}", intdiv(strlen($raw), 2)); // Glyph IDs without a ToUnicode map.
    }
    return mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
}

/** Interpret text-showing operators in a content stream. */
function pdf_content_text(string $s, array $fonts): string
{
    $out = '';
    $ops = [];
    $font = null;
    $lastY = null;
    $lastX = null;
    $runWidth = 0.0;
    $fontSize = 10.0;
    $len = strlen($s);
    $i = 0;
    $newline = function () use (&$out) {
        if ($out !== '' && substr($out, -1) !== "\n") $out .= "\n";
    };
    $space = function () use (&$out) {
        if ($out !== '' && !in_array(substr($out, -1), [' ', "\n"], true)) $out .= ' ';
    };
    while ($i < $len) {
        $c = $s[$i];
        if (ctype_space($c)) { $i++; continue; }
        if ($c === '%') { while ($i < $len && $s[$i] !== "\n" && $s[$i] !== "\r") $i++; continue; }
        if ($c === '(') {
            $depth = 0; $str = '';
            for ($i++; $i < $len; $i++) {
                $ch = $s[$i];
                if ($ch === '\\') {
                    $nx = $s[++$i] ?? '';
                    $esc = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C", '(' => '(', ')' => ')', '\\' => '\\'];
                    if (isset($esc[$nx])) { $str .= $esc[$nx]; }
                    elseif (ctype_digit($nx)) {
                        $oct = $nx;
                        while (strlen($oct) < 3 && ctype_digit($s[$i + 1] ?? 'x')) $oct .= $s[++$i];
                        $str .= chr(octdec($oct) & 0xFF);
                    } elseif ($nx === "\r") { if (($s[$i + 1] ?? '') === "\n") $i++; }
                    elseif ($nx !== "\n") { $str .= $nx; }
                    continue;
                }
                if ($ch === '(') $depth++;
                if ($ch === ')') { if ($depth === 0) { $i++; break; } $depth--; }
                $str .= $ch;
            }
            $ops[] = ['s', $str];
            continue;
        }
        if ($c === '<') {
            if (($s[$i + 1] ?? '') === '<') { $i += 2; continue; }
            $end = strpos($s, '>', $i);
            if ($end === false) break;
            $hex = preg_replace('/[^0-9a-fA-F]/', '', substr($s, $i + 1, $end - $i - 1));
            if (strlen($hex) % 2) $hex .= '0';
            $ops[] = ['s', (string)hex2bin($hex)];
            $i = $end + 1;
            continue;
        }
        if ($c === '>') { $i++; continue; }
        if ($c === '[') { $ops[] = ['[']; $i++; continue; }
        if ($c === ']') {
            $arr = [];
            while ($ops && end($ops)[0] !== '[') array_unshift($arr, array_pop($ops));
            array_pop($ops);
            $ops[] = ['a', $arr];
            $i++;
            continue;
        }
        if ($c === '/') {
            preg_match('/\G\/([^\s\/<>\[\]()%{}]*)/', $s, $m, 0, $i);
            $ops[] = ['n', $m[1]];
            $i += strlen($m[0]);
            continue;
        }
        if (preg_match('/\G[+-]?(?:\d+\.?\d*|\.\d+)/', $s, $m, 0, $i)) {
            $ops[] = ['f', (float)$m[0]];
            $i += strlen($m[0]);
            continue;
        }
        if (!preg_match('/\G[^\s\/<>\[\]()%{}]+/', $s, $m, 0, $i)) { $i++; continue; }
        $op = $m[0];
        $i += strlen($op);
        $num = fn(int $k) => (($ops[count($ops) + $k] ?? null)[0] ?? '') === 'f' ? $ops[count($ops) + $k][1] : 0.0;
        // Horizontal moves only become spaces when they exceed the (estimated) width of the
        // text just shown; generators such as Chrome/Skia position every glyph individually.
        $gapIsSpace = fn(float $dx) => $dx - $runWidth > 0.18 * $fontSize;
        switch ($op) {
            case 'Tf':
                foreach (array_reverse($ops) as $o) { if ($o[0] === 'n') { $font = $fonts[$o[1]] ?? null; break; } }
                $fontSize = abs($num(-1)) ?: 10.0;
                break;
            case 'Tj':
            case "'":
            case '"':
                if ($op !== 'Tj') { $newline(); $runWidth = 0.0; }
                if (($o = end($ops)) && $o[0] === 's') {
                    $out .= pdf_decode_string($o[1], $font);
                    $runWidth += pdf_string_width($o[1], $font, $fontSize);
                }
                break;
            case 'TJ':
                if (($o = end($ops)) && $o[0] === 'a') {
                    foreach ($o[1] as $item) {
                        if ($item[0] === 's') {
                            $out .= pdf_decode_string($item[1], $font);
                            $runWidth += pdf_string_width($item[1], $font, $fontSize);
                        } elseif ($item[0] === 'f') {
                            if ($item[1] < -200) $space();
                            $runWidth -= $item[1] / 1000 * $fontSize;
                        }
                    }
                }
                break;
            case 'Td':
            case 'TD':
                $dx = $num(-2);
                $dy = $num(-1);
                if (abs($dy) > 0.5) {
                    $newline();
                } elseif ($gapIsSpace($dx)) {
                    $space();
                }
                $lastY = ($lastY ?? 0) + $dy;
                $lastX = ($lastX ?? 0) + $dx;
                $runWidth = 0.0;
                break;
            case 'T*':
                $newline();
                $runWidth = 0.0;
                break;
            case 'Tm':
                $x = $num(-2);
                $y = $num(-1);
                if ($lastY !== null && abs($y - $lastY) > 0.5) {
                    $newline();
                } elseif ($lastX !== null && $gapIsSpace($x - $lastX)) {
                    $space();
                }
                $lastY = $y;
                $lastX = $x;
                $runWidth = 0.0;
                break;
            case 'BT':
                break;
            case 'ET':
                break;
            case 'BI':
                // Skip inline image data.
                if (preg_match('/\sEI(?=\s|$)/', $s, $em, PREG_OFFSET_CAPTURE, $i)) $i = $em[0][1] + 3; else $i = $len;
                break;
        }
        $ops = [];
    }
    return $out;
}

/* =====================================================================
 * Section parsing
 * =================================================================== */

const CV_MONTH_RX = '(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|June?|July?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)';

function cv_date_rx(): string
{
    return '(?:' . CV_MONTH_RX . '\.?,?\s+\d{4}|\d{1,2}[\/.]\d{4}|(?:19|20)\d{2})';
}

function cv_range_rx(): string
{
    $d = cv_date_rx();
    return '/(' . $d . ')\s*(?:-|–|—|to|until)\s*(' . $d . '|present|current|now|ongoing|to date|date)/iu';
}

function cv_heading_key(string $line): ?string
{
    $l = trim($line);
    if ($l === '' || mb_strlen($l) > 45) return null;
    if (preg_match('/^(?:\p{L} ){3,}\p{L}$/u', $l)) $l = str_replace(' ', '', $l); // "S K I L L S"
    $l = mb_strtolower(trim(preg_replace('/[^\p{L}&\/ ]+/u', ' ', $l) ?? ''));
    $l = preg_replace('/\s+/', ' ', str_replace('&', 'and', $l)) ?? $l;
    if ($l === '' || str_word_count($l) > 5) return null;
    $map = [
        'summary' => ['summary', 'professional summary', 'profile', 'professional profile', 'career objective', 'objective', 'about me', 'personal statement', 'career summary', 'personal profile', 'career profile'],
        'experience' => ['experience', 'work experience', 'professional experience', 'employment history', 'work history', 'employment', 'internships', 'internship', 'internship experience', 'relevant experience', 'industry experience', 'career history'],
        'education' => ['education', 'academic qualifications', 'educational qualifications', 'academic background', 'qualifications', 'education and training', 'academic profile', 'educational background', 'academic details'],
        'skills' => ['skills', 'technical skills', 'core skills', 'key skills', 'competencies', 'core competencies', 'technical competencies', 'skills and abilities', 'tools and technologies', 'technologies', 'it skills', 'skill set', 'skillset', 'technical expertise', 'professional skills'],
        'soft' => ['soft skills', 'personal skills', 'interpersonal skills', 'transferable skills'],
        'projects' => ['projects', 'academic projects', 'personal projects', 'key projects', 'project experience', 'selected projects'],
        'certifications' => ['certifications', 'certificates', 'licenses and certifications', 'courses', 'certifications and courses', 'professional certifications', 'training', 'certification', 'courses and certifications'],
        'languages' => ['languages', 'language skills', 'language proficiency', 'language'],
        'personal' => ['personal details', 'personal information', 'contact', 'contact details', 'contact information', 'personal data'],
        'ignore' => ['references', 'referees', 'reference', 'interests', 'hobbies', 'hobbies and interests', 'declaration', 'extracurricular activities', 'extra curricular activities', 'awards', 'achievements', 'awards and achievements', 'volunteering', 'activities'],
    ];
    $compact = str_replace(' ', '', $l); // tolerate stray spaces from PDF extraction
    foreach ($map as $key => $names) {
        if (in_array($l, $names, true)) return $key;
        foreach ($names as $n) {
            if (str_replace(' ', '', $n) === $compact) return $key;
        }
    }
    return null;
}

function cv_is_bullet(string $line): bool
{
    return (bool)preg_match('/^\s*(?:[•\-\*▪●◦‣·○■□➢➤►✓✔]|o\s)\s*/u', $line) && !preg_match('/^\s*-?\d/', $line);
}

function cv_strip_bullet(string $line): string
{
    return trim(preg_replace('/^\s*(?:[•\-\*▪●◦‣·○■□➢➤►✓✔]|o(?=\s))\s*/u', '', $line) ?? $line);
}

/**
 * Parse extracted text into the CV structure.
 * @return array{content:array,report:array}
 */
function cv_parse_text(PDO $pdo, string $text): array
{
    $c = cv_empty_content();
    $report = ['found' => [], 'missing' => [], 'unassigned' => []];
    $lines = explode("\n", $text);

    // Split into sections.
    $sections = ['_header' => []];
    $current = '_header';
    foreach ($lines as $line) {
        $key = cv_heading_key($line);
        if ($key !== null) {
            $current = $key;
            $sections[$current] = $sections[$current] ?? [];
            continue;
        }
        // Inline headings such as "Skills: PHP, MySQL".
        // (Inside a skills block, labels like "Languages:" are skill categories, not sections.)
        if (!in_array($current, ['skills', 'soft'], true)
            && preg_match('/^([A-Za-z &]{4,30}):\s*(.+)$/', trim($line), $im) && ($k = cv_heading_key($im[1])) && !in_array($k, ['personal', 'ignore'], true)) {
            $sections[$k][] = $im[2];
            continue;
        }
        $sections[$current][] = $line;
    }

    // Personal details: header block + explicit personal section, contact regexes over the whole text.
    $headerLines = array_values(array_filter(array_map('trim', array_merge($sections['_header'], $sections['personal'] ?? [])), 'strlen'));
    $p = &$c['personal'];
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $m)) $p['email'] = $m[0];
    if (preg_match('~(?:https?://)?(?:[a-z]{2,3}\.)?linkedin\.com/[^\s|,;)]+~i', $text, $m)) $p['linkedin'] = rtrim($m[0], '.');
    if (preg_match('~(?:https?://)?(?:www\.)?github\.com/[^\s|,;)]+~i', $text, $m)) $p['github'] = rtrim($m[0], '.');
    if (preg_match_all('/(?<![\w\/])(\+?\(?\d[\d\s().-]{7,}\d)(?![\w\/])/', implode("\n", $headerLines) ?: $text, $pm)) {
        foreach ($pm[1] as $cand) {
            if (preg_match_all('/\d/', $cand) >= 9 && !preg_match('/^(19|20)\d{2}\s*[-–]\s*(19|20)\d{2}$/', trim($cand))) {
                $p['phone'] = trim($cand);
                break;
            }
        }
    }
    foreach ($headerLines as $hl) {
        if (preg_match('/^(?:address|location|city)\s*[:\-]\s*(.+)$/i', $hl, $m)) $p['location'] = trim($m[1]);
        if (preg_match('/^(?:name|full name)\s*[:\-]\s*(.+)$/i', $hl, $m)) $p['full_name'] = trim($m[1]);
        if ($p['portfolio'] === '' && preg_match('~(?:https?://|www\.)[^\s|,;]+~i', $hl, $m) && !preg_match('/linkedin|github/i', $m[0])) $p['portfolio'] = $m[0];
    }
    $isContact = fn(string $l) => (bool)preg_match('/@|https?:|www\.|linkedin|github|\d{3}/i', $l);
    $nameIdx = null;
    if ($p['full_name'] === '') {
        foreach (array_slice($headerLines, 0, 6) as $idx => $hl) {
            $words = preg_split('/\s+/', $hl);
            if (!$isContact($hl) && count($words) >= 2 && count($words) <= 5 && preg_match('/^[\p{L}][\p{L} .\'-]+$/u', $hl)) {
                // Convert "JOHN SILVA" to "John Silva"; keep mixed case as written.
                $p['full_name'] = mb_strtoupper($hl) === $hl ? mb_convert_case(mb_strtolower($hl), MB_CASE_TITLE) : $hl;
                $nameIdx = $idx;
                break;
            }
        }
    }
    if ($nameIdx !== null && isset($headerLines[$nameIdx + 1])) {
        $next = $headerLines[$nameIdx + 1];
        if (!$isContact($next) && str_word_count($next) <= 10 && !str_contains($next, ',')) {
            $p['title'] = $next;
        }
    }
    if ($p['location'] === '') {
        foreach ($headerLines as $idx => $hl) {
            if ($idx === $nameIdx || $hl === $p['title']) continue;
            foreach (preg_split('/\s*[|•·]\s*/u', $hl) as $seg) {
                $seg = trim($seg);
                if ($seg !== '' && str_contains($seg, ',') && !$isContact($seg) && str_word_count($seg) <= 10 && !preg_match('/@/', $seg)) {
                    $p['location'] = $seg;
                    break 2;
                }
            }
        }
    }
    unset($p);

    // Summary.
    if (!empty($sections['summary'])) {
        $c['personal']['summary'] = cv_join_paragraphs($sections['summary']);
    }

    // Experience.
    foreach (cv_split_entries($sections['experience'] ?? []) as $e) {
        [$start, $end, $heads] = cv_entry_dates($e['header']);
        [$position, $company, $location] = cv_split_role($heads);
        $resp = [];
        $ach = [];
        foreach ($e['body'] as $b) {
            if (preg_match('/\d+\s*%|\b(achiev|award|won|increas|reduc|improv|sav)\w*/i', $b)) {
                $ach[] = $b;
            } else {
                $resp[] = $b;
            }
        }
        $c['experience'][] = ['company' => $company, 'position' => $position, 'location' => $location, 'start' => $start, 'end' => $end,
            'responsibilities' => implode("\n", $resp), 'achievements' => implode("\n", $ach)];
    }

    // Education.
    foreach (cv_split_entries($sections['education'] ?? []) as $e) {
        [$start, $end, $heads] = cv_entry_dates($e['header']);
        $qual = $inst = '';
        $rest = [];
        foreach ($heads as $h) {
            foreach (preg_split('/\s+[|–—]\s+|\s+-\s+|,\s+(?=[A-Z])/u', $h) as $part) {
                $part = trim($part, " ,");
                if ($part === '') continue;
                if ($qual === '' && preg_match('/\b(B\.?Sc|M\.?Sc|B\.?A|M\.?A|B\.?Eng|M\.?Eng|BEng|MEng|BIT|Bachelor|Master|Diploma|HND|Ph\.?D|Degree|Certificate|Foundation|A\/L|O\/L|Advanced Level|Ordinary Level|G\.?C\.?E|MBA|BBA|Associate)\b/i', $part)) $qual = $part;
                elseif ($inst === '' && preg_match('/\b(University|College|Institute|School|Academy|Campus|Faculty|SLIIT|NSBM|IIT|NIBM|ESOFT|APIIT)\b/i', $part)) $inst = $part;
                else $rest[] = $part;
            }
        }
        if ($inst === '' && $qual !== '' && $rest) $inst = array_shift($rest);
        if ($qual === '' && $inst !== '' && $rest) $qual = array_shift($rest);
        if ($qual === '' && $inst === '' && $rest) $qual = array_shift($rest);
        $field = '';
        if (preg_match('/^(.*?)\s+(?:in|of)\s+(.+)$/i', $qual, $m) && preg_match('/bachelor|master|diploma|degree|sc|ba|eng|hnd|phd|certificate/i', $m[1])) {
            $qual = trim($m[1]);
            $field = trim($m[2]);
        }
        $c['education'][] = ['institution' => $inst, 'qualification' => $qual, 'field' => $field, 'start' => $start, 'end' => $end,
            'description' => implode("\n", array_merge($rest, $e['body']))];
    }

    // Projects.
    foreach (cv_split_entries($sections['projects'] ?? []) as $e) {
        [$start, $end, $heads] = cv_entry_dates($e['header']);
        $name = array_shift($heads) ?? '';
        $all = implode(' ', array_merge($e['header'], $e['body']));
        $link = preg_match('~(?:https?://|www\.|github\.com/)[^\s|,;)]+~i', $all, $m) ? $m[0] : '';
        $c['projects'][] = ['name' => $name, 'role' => '', 'link' => $link, 'start' => $start, 'end' => $end,
            'description' => implode("\n", array_merge($heads, $e['body']))];
    }

    // Certifications: one entry per line.
    foreach ($sections['certifications'] ?? [] as $line) {
        $line = cv_strip_bullet($line);
        if ($line === '') continue;
        $date = preg_match('/' . cv_date_rx() . '/iu', $line, $m) ? $m[0] : '';
        $rest = trim(preg_replace('/[(\[]?\s*' . preg_quote($date, '/') . '\s*[)\]]?/', '', $line) ?? $line, " -–|,");
        $parts = preg_split('/\s+[|–—-]\s+|\s*,\s+/u', $rest, 2);
        $c['certifications'][] = ['name' => trim($parts[0]), 'issuer' => trim($parts[1] ?? ''), 'date' => $date, 'link' => ''];
    }

    // Languages.
    foreach ($sections['languages'] ?? [] as $line) {
        foreach (preg_split('/\s*[,|•;]\s*/u', cv_strip_bullet($line)) as $item) {
            $item = trim($item);
            if ($item === '' || str_word_count($item) > 6) continue;
            if (preg_match('/^([\p{L} ]+?)\s*(?:[(:\-–]\s*([\p{L} ]+)\)?)?$/u', $item, $m)) {
                $c['languages'][] = ['name' => trim($m[1]), 'level' => trim($m[2] ?? '')];
            }
        }
    }

    // Skills: listed items, then taxonomy terms detected anywhere in the document.
    $softList = ['communication', 'teamwork', 'team work', 'leadership', 'problem solving', 'problem-solving', 'time management', 'critical thinking', 'adaptability', 'collaboration', 'creativity', 'attention to detail', 'presentation', 'presentation skills', 'interpersonal skills', 'decision making', 'analytical thinking', 'self-motivated', 'work ethic', 'negotiation', 'conflict resolution', 'mentoring'];
    $seen = [];
    $addSkill = function (string $name, string $type) use (&$c, &$seen) {
        $k = mb_strtolower($name);
        if ($name === '' || isset($seen[$k])) return;
        $seen[$k] = true;
        $c['skills'][$type][] = ['name' => $name, 'level' => ''];
    };
    foreach (['skills' => 'technical', 'soft' => 'soft'] as $sec => $type) {
        foreach ($sections[$sec] ?? [] as $line) {
            $line = cv_strip_bullet($line);
            $line = preg_replace('/^[\p{L} &\/]{2,30}:\s*/u', '', $line) ?? $line; // "Languages: PHP, JS"
            foreach (preg_split('/\s*[,|•;·]\s*/u', $line) as $item) {
                $item = trim($item, " .\t");
                if ($item === '' || str_word_count($item) > 4 || mb_strlen($item) > 40) continue;
                $isSoft = $type === 'soft' || in_array(mb_strtolower($item), $softList, true);
                $addSkill($item, $isSoft ? 'soft' : 'technical');
            }
        }
    }
    $tax = skill_taxonomy($pdo);
    $detected = detect_taxonomy_skills($pdo, $text);
    $existingCanon = [];
    foreach ($c['skills']['technical'] as $s) $existingCanon[skill_norm(skill_canonical($s['name'], $tax))] = true;
    foreach ($detected as $d) {
        if (!isset($existingCanon[skill_norm($d)])) $addSkill($d, 'technical');
    }
    foreach ($softList as $soft) {
        if (preg_match('/\b' . preg_quote($soft, '/') . '\b/i', $text) && count($c['skills']['soft']) < 10) {
            $addSkill(ucfirst($soft), 'soft');
        }
    }
    $report['taxonomy_skills'] = $detected;

    // Show optional sections only when something was found.
    foreach (CV_OPTIONAL_SECTIONS as $opt) {
        $c['hidden'][$opt] = empty($c[$opt]);
    }

    $labels = ['full_name' => 'Full name', 'email' => 'Email', 'phone' => 'Phone', 'location' => 'Location', 'title' => 'Professional title', 'summary' => 'Summary', 'linkedin' => 'LinkedIn', 'github' => 'GitHub'];
    foreach ($labels as $k => $label) {
        $report[$c['personal'][$k] !== '' ? 'found' : 'missing'][] = $label;
    }
    foreach (['experience' => 'Work experience', 'education' => 'Education', 'projects' => 'Projects', 'certifications' => 'Certifications', 'languages' => 'Languages'] as $k => $label) {
        if ($c[$k]) {
            $report['found'][] = $label . ' (' . count($c[$k]) . ')';
        } else {
            $report['missing'][] = $label;
        }
    }
    $nSkills = count($c['skills']['technical']) + count($c['skills']['soft']);
    if ($nSkills) {
        $report['found'][] = "Skills ($nSkills)";
    } else {
        $report['missing'][] = 'Skills';
    }
    $report['unassigned'] = array_values(array_filter(array_map('trim', $sections['ignore'] ?? []), 'strlen'));

    return ['content' => cv_normalize_content($c), 'report' => $report];
}

function cv_join_paragraphs(array $lines): string
{
    $paras = [];
    $buf = '';
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l === '') {
            if ($buf !== '') { $paras[] = $buf; $buf = ''; }
            continue;
        }
        $buf = $buf === '' ? cv_strip_bullet($l) : $buf . ' ' . cv_strip_bullet($l);
    }
    if ($buf !== '') $paras[] = $buf;
    return implode("\n\n", $paras);
}

/** Group section lines into entries: non-bullet header lines followed by body lines. */
function cv_split_entries(array $lines): array
{
    $entries = [];
    $cur = null;
    $gap = false;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') { $gap = true; continue; }
        if (cv_is_bullet($line)) {
            $cur ??= ['header' => [], 'body' => [], 'dated' => false];
            $cur['body'][] = cv_strip_bullet($line);
            $gap = false;
            continue;
        }
        // Wrapped continuation of the previous body line (URL-only or starting lower-case).
        if ($cur && $cur['body'] && !$gap && (preg_match('~^(?:https?://|www\.)?[\w.-]+\.[a-z]{2,}/\S*$~i', $line) || preg_match('/^\p{Ll}/u', $line))) {
            $cur['body'][count($cur['body']) - 1] .= ' ' . $line;
            continue;
        }
        $hasDate = (bool)preg_match(cv_range_rx(), $line) || (bool)preg_match('/^\(?' . cv_date_rx() . '\)?$/iu', $line);
        $looksHeader = str_word_count($line) <= 12 && !preg_match('/[.!?]$/', $line);
        $startNew = $cur === null
            || ($cur['body'] && ($looksHeader || $hasDate))
            || ($gap && $cur['header'] && $cur['dated'] && $looksHeader)
            || ($hasDate && $cur['dated'] && !$cur['body'] && count($cur['header']) >= 2);
        if ($startNew) {
            if ($cur) $entries[] = $cur;
            $cur = ['header' => [], 'body' => [], 'dated' => false];
        }
        if (!$cur['body'] && count($cur['header']) < 3 && $looksHeader) {
            $cur['header'][] = $line;
            $cur['dated'] = $cur['dated'] || $hasDate;
        } else {
            $cur['body'][] = $line;
        }
        $gap = false;
    }
    if ($cur) $entries[] = $cur;
    return $entries;
}

/** Pull a date range (or single date) out of header lines; returns [start, end, remainingHeaderTexts]. */
function cv_entry_dates(array $header): array
{
    $start = $end = '';
    $rest = [];
    foreach ($header as $h) {
        if ($start === '' && preg_match(cv_range_rx(), $h, $m)) {
            $start = trim($m[1]);
            $end = ucfirst(strtolower(trim($m[2]))) === 'Date' ? 'Present' : trim($m[2]);
            if (preg_match('/^(present|current|now|ongoing|to date)$/i', $end)) $end = 'Present';
            $h = str_replace($m[0], '', $h);
        } elseif ($start === '' && preg_match('/(?:^|[\s(|,])(' . cv_date_rx() . ')\)?\s*$/iu', $h, $m)) {
            $end = trim($m[1]);
            $h = substr($h, 0, strlen($h) - strlen($m[0]));
        }
        $h = trim($h, " \t|,()–—-");
        if ($h !== '') $rest[] = $h;
    }
    return [$start, $end, $rest];
}

/** Interpret "Position at Company", "Position | Company | Location" or two header lines. */
function cv_split_role(array $heads): array
{
    $position = $company = $location = '';
    if (!$heads) return [$position, $company, $location];
    $first = $heads[0];
    if (preg_match('/^(.+?)\s+(?:at|@)\s+(.+)$/i', $first, $m)) {
        [$position, $company] = [trim($m[1]), trim($m[2])];
    } else {
        $parts = preg_split('/\s+[|–—]\s+|\s+-\s+/u', $first);
        $position = trim($parts[0]);
        $company = trim($parts[1] ?? '');
        $location = trim($parts[2] ?? '');
    }
    if ($company === '' && isset($heads[1])) {
        $parts = preg_split('/\s+[|–—]\s+|\s+-\s+/u', $heads[1]);
        $company = trim($parts[0]);
        $location = $location ?: trim($parts[1] ?? '');
    } elseif ($location === '' && isset($heads[1]) && str_word_count($heads[1]) <= 5) {
        $location = $heads[1];
    }
    return [$position, $company, $location];
}
