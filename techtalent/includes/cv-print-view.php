<?php
/**
 * Standalone A4 print/PDF view for a CV (no site navigation).
 * Expects: $print_title, $print_content (normalised CV array), $print_template, $print_accent,
 *          $print_back_url, optional $print_note (plain text), $print_autoprint (bool).
 */
cv_private_headers();
$print_payload = [
    'data' => $print_content,
    'template' => in_array($print_template, CV_TEMPLATES, true) ? $print_template : 'classic',
    'accent' => cv_valid_color($print_accent),
    'autoprint' => !empty($print_autoprint),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= cv_e($print_title) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/cv-studio.css">
    <style>
        body { background: #e9edf2; }
        .print-bar { background: #0f2137; color: #fff; }
        @media screen and (max-width: 900px) { #cvPages { zoom: .5; } }
    </style>
</head>
<body>
<div class="print-bar no-print py-2 px-3 d-flex flex-wrap gap-2 align-items-center justify-content-between sticky-top">
    <div class="small">
        <a href="<?= cv_e($print_back_url) ?>" class="text-white text-decoration-none me-3"><i class="bi bi-arrow-left"></i> Back</a>
        <strong><?= cv_e($print_title) ?></strong>
        <?php if (!empty($print_note)): ?><span class="text-white-50 ms-2"><?= cv_e($print_note) ?></span><?php endif; ?>
    </div>
    <button type="button" class="btn btn-sm btn-light fw-semibold" onclick="window.print()"><i class="bi bi-file-earmark-pdf me-1"></i>Print / Save as PDF</button>
</div>
<p class="no-print text-center small text-muted mt-2 mb-0">In the print dialog choose <strong>Save as PDF</strong>, paper size <strong>A4</strong>, margins <strong>None</strong> (or Default), and turn off headers/footers.</p>
<div class="py-3 cv-print-root">
    <div id="cvPages" class="cv-pages"></div>
</div>
<script id="cvPrintData" type="application/json"><?= json_encode($print_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="../assets/js/cv-render.js"></script>
<script>
(function () {
    var p = JSON.parse(document.getElementById('cvPrintData').textContent);
    var target = document.getElementById('cvPages');
    function draw() { CVRender.render(target, p.data, { template: p.template, accent: p.accent }); }
    draw();
    var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
    ready.then(function () {
        draw();
        if (p.autoprint) setTimeout(function () { window.print(); }, 300);
    });
})();
</script>
</body>
</html>
