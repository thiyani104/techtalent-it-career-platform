<?php
/**
 * Shared CV editor UI (form panel + A4 preview + ATS + skill gap).
 * Expects: $pdo, $editor_config (array passed to assets/js/cv-editor.js), $editor_mode ('edit'|'import').
 */
$gap_jobs = $pdo->query("SELECT job_id, job_title, company_name FROM jobs WHERE status = 'Published' ORDER BY created_at DESC")->fetchAll();
$skill_names = $pdo->query('SELECT skill_name FROM skills ORDER BY skill_name')->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="card border-0 shadow-sm rounded-4 mb-3 no-print">
    <div class="card-body py-3 d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1" style="min-width:220px;max-width:420px;">
            <label for="cvTitle" class="visually-hidden">CV name</label>
            <input id="cvTitle" type="text" class="form-control fw-semibold" maxlength="150" placeholder="CV name, e.g. CV – Junior PHP Developer">
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Template">
            <button type="button" class="btn btn-outline-secondary cv-tpl-btn" data-tpl="classic"><i class="bi bi-file-text me-1"></i>Classic</button>
            <button type="button" class="btn btn-outline-secondary cv-tpl-btn" data-tpl="modern"><i class="bi bi-layout-text-sidebar me-1"></i>Modern</button>
            <button type="button" class="btn btn-outline-secondary cv-tpl-btn" data-tpl="minimal"><i class="bi bi-list me-1"></i>Minimal</button>
        </div>
        <div class="d-flex align-items-center gap-1" title="Accent colour (Modern template)">
            <label for="cvAccent" class="small text-muted mb-0">Accent</label>
            <input id="cvAccent" type="color" class="form-control form-control-color form-control-sm" value="#0f2137">
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span id="saveStatus" class="small text-muted" aria-live="polite"></span>
            <button id="saveBtn" type="button" class="btn btn-primary btn-sm fw-semibold">
                <i class="bi bi-floppy me-1"></i><?= $editor_mode === 'import' ? 'Save as new CV' : 'Save' ?>
            </button>
            <button id="printBtn" type="button" class="btn btn-success btn-sm fw-semibold"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
        </div>
    </div>
</div>

<div class="row g-4 cv-print-root">
    <div class="col-lg-5 no-print">
        <div id="cvForm" class="cv-editor-panel pe-1"></div>
        <datalist id="cvSkillList">
            <?php foreach ($skill_names as $s): ?><option value="<?= cv_e($s) ?>"><?php endforeach; ?>
        </datalist>
    </div>
    <div class="col-lg-7 cv-print-root">
        <div class="cv-sticky cv-print-root">
            <ul class="nav nav-pills mb-2 no-print" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active py-1" data-bs-toggle="pill" data-bs-target="#tabPreview" type="button" role="tab"><i class="bi bi-eye me-1"></i>A4 Preview</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link py-1" data-bs-toggle="pill" data-bs-target="#tabAts" type="button" role="tab"><i class="bi bi-clipboard-check me-1"></i>ATS Check</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link py-1" data-bs-toggle="pill" data-bs-target="#tabGap" type="button" role="tab"><i class="bi bi-bullseye me-1"></i>Skill Gap</button></li>
            </ul>
            <div class="tab-content cv-print-root">
                <div class="tab-pane fade show active cv-print-root" id="tabPreview" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-2 no-print">
                        <span class="small text-muted"><i class="bi bi-file-earmark me-1"></i>A4 · <span id="cvPageCount">1 page</span></span>
                        <select id="cvZoom" class="form-select form-select-sm w-auto" aria-label="Zoom">
                            <option value="0">Fit width</option>
                            <option value="0.5">50%</option>
                            <option value="0.75">75%</option>
                            <option value="1">100%</option>
                        </select>
                    </div>
                    <div id="cvWrap" class="cv-preview-wrap cv-print-root" style="max-height: calc(100vh - 200px);">
                        <div id="cvScale" class="cv-scale cv-print-root"><div id="cvPages" class="cv-pages"></div></div>
                    </div>
                </div>
                <div class="tab-pane fade no-print" id="tabAts" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <h6 class="fw-bold mb-1">ATS compatibility check</h6>
                                    <p class="small text-muted mb-0">Rule-based guidance on contact details, sections, consistency, readability and layout. Results are heuristic, not an official certification, and individual ATS products behave differently.</p>
                                </div>
                                <button id="atsRun" type="button" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-play-fill me-1"></i>Run check</button>
                            </div>
                            <div id="atsResults" class="mt-3" style="max-height: calc(100vh - 330px); overflow:auto;">
                                <p class="small text-muted mb-0">Click <strong>Run check</strong> to analyse the CV as it is currently shown (unsaved changes included).</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade no-print" id="tabGap" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-1">Job skill gap</h6>
                            <p class="small text-muted">Compare this CV with the skills a vacancy asks for. Informational only; candidates are never ranked or rejected automatically.</p>
                            <div class="input-group input-group-sm mb-3">
                                <select id="gapJob" class="form-select" aria-label="Vacancy">
                                    <option value="">Select a vacancy…</option>
                                    <?php foreach ($gap_jobs as $j): ?>
                                        <option value="<?= (int)$j['job_id'] ?>"><?= cv_e($j['job_title'] . ' — ' . $j['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button id="gapRun" type="button" class="btn btn-primary"><i class="bi bi-bullseye me-1"></i>Compare</button>
                            </div>
                            <div id="gapResults" style="max-height: calc(100vh - 360px); overflow:auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script id="cvConfig" type="application/json"><?= json_encode($editor_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="../assets/js/cv-render.js"></script>
<script src="../assets/js/cv-editor.js"></script>
