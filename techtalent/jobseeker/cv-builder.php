<?php
ob_start();
session_start();

// ---------------------------------------------------------------------
// 1. AUTH GUARD & DB CONNECTION
// ---------------------------------------------------------------------
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'job_seeker') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ---------------------------------------------------------------------
// 2. FETCH DATA
// ---------------------------------------------------------------------
$profile   = [];
$skills    = [];
$vacancies = [];

try {
    // User profile (users + job_seeker_profiles)
    $stmt = $pdo->prepare(
        "SELECT u.full_name, u.email, p.headline, p.phone, p.location, p.bio AS summary " .
        "FROM users u LEFT JOIN job_seeker_profiles p ON u.user_id = p.user_id " .
        "WHERE u.user_id = :uid LIMIT 1"
    );
    $stmt->execute([':uid' => $user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Master skills list
    $stmt = $pdo->query("SELECT skill_id, skill_name AS name FROM skills ORDER BY skill_name ASC");
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Open vacancies (for ATS target)
    $stmt = $pdo->query(
        "SELECT job_id, job_title AS title, required_skills FROM jobs " .
        "WHERE LOWER(status) IN ('published','active','open') " .
        "ORDER BY created_at DESC"
    );
    $vacancies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('CV Builder data load error: ' . $e->getMessage());
    // Continue with empty arrays – UI will handle missing data.
}

// ---------------------------------------------------------------------
// 3. PAGE SETUP
// ---------------------------------------------------------------------
$page_title = 'AI‑Powered CV Builder – TechTalent';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<style>
/* ---------- Layout ---------- */
.cv-studio {
    height: calc(100vh - 65px);
    overflow: hidden;
    background: #eef2f6;
}
.cv-studio-topbar {
    background:#fff;
    border-bottom:1px solid #e2e8f0;
    padding:8px 12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.left-pane {
    width:45%;
    background:#fff;
    border-right:1px solid #e2e8f0;
    padding:24px;
    overflow-y:auto;
}
.right-pane {
    width:55%;
    background:#334155;
    display:flex;
    justify-content:center;
    align-items:flex-start;
    padding:30px;
    overflow-y:auto;
    position:relative;
}
#cv-preview {
    width:794px;                /* A4 @96dpi */
    min-height:1123px;
    background:#fff;
    box-shadow:0 15px 35px rgba(0,0,0,0.3);
    border-radius:4px;
    padding:var(--page-margin,20mm);
    position:relative;
    --primary-color:#2563EB;   /* default navy */
    --font-family:'Arial,Helvetica,sans-serif';
    --line-height:1.4;
    --entry-spacing:12px;
    --section-spacing:24px;
    --page-margin:20mm;
    color:#111;
}
/* ---------- Floating Dock (glassmorphic) ---------- */
#floating-dock {
    position:absolute;
    bottom:0; left:0; right:0;
    backdrop-filter:blur(8px);
    background:rgba(255,255,255,0.35);
    border-top:1px solid rgba(0,0,0,0.1);
    padding:8px 12px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
    z-index:10;
}
/* ---------- Template placeholders ---------- */
.template-circular   #cv-preview {}
.template-twocolumn  #cv-preview {}
.template-minimal    #cv-preview {}
.template-executive  #cv-preview {}
/* ---------- Print Styles ---------- */
@media print {
    @page { size:A4 portrait; margin:0; }
    html, body { width:210mm !important; height:297mm !important; margin:0 !important; padding:0 !important; background:#fff !important; -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
    header, nav, .navbar, footer, .left-pane, #floating-dock, .d-print-none, .btn, .modal, .cv-studio-topbar { display:none !important; visibility:hidden !important; }
    .cv-studio, .right-pane { display:block !important; width:100% !important; height:auto !important; margin:0 !important; padding:0 !important; background:transparent !important; overflow:visible !important; }
    #cv-preview { width:210mm !important; min-height:297mm !important; max-height:297mm !important; box-shadow:none !important; border:none !important; margin:0 !important; padding:var(--page-margin,20mm) !important; page-break-after:avoid !important; page-break-inside:avoid !important; }
}
</style>

<div class="container-fluid cv-studio d-flex flex-column">
    <!-- TOP BAR -->
    <div class="cv-studio-topbar">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary me-2" aria-label="Back to Dashboard">
                <i class="bi bi-arrow-left"></i> CVs
            </a>
            <h5 class="mb-0 d-flex align-items-center">
                <span id="cv-title" contenteditable="true" class="border-bottom border-dark" style="outline:none;" aria-label="CV Title">Untitled CV</span>
                <i class="bi bi-pencil ms-2" title="Edit title"></i>
            </h5>
        </div>
        <div class="badge bg-success" id="ats-score-badge">ATS Score: 0%</div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyModal">
                <i class="bi bi-send"></i> Apply with CV
            </button>
            <button class="btn btn-outline-primary" onclick="window.print();">
                <i class="bi bi-printer"></i> Download PDF
            </button>
        </div>
    </div>

    <!-- MAIN SPLIT SCREEN -->
    <div class="d-flex flex-grow-1 overflow-hidden">
        <!-- LEFT EDITOR PANEL -->
        <div class="left-pane">
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body d-grid gap-2">
                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="bi bi-cloud-arrow-up"></i> Upload Existing CV
                            </button>
                            <button class="btn btn-outline-primary" id="importProfileBtn">
                                <i class="bi bi-person-fill"></i> Import Profile
                            </button>
                            <button class="btn btn-outline-info" id="exampleBtn">
                                <i class="bi bi-file-earmark-text"></i> Start from Example
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accordion Form Sections -->
            <div class="accordion" id="cvFormAccordion">
                <!-- Personal Details -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPersonal">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePersonal" aria-expanded="true" aria-controls="collapsePersonal">
                            Personal Details
                        </button>
                    </h2>
                    <div id="collapsePersonal" class="accordion-collapse collapse show" aria-labelledby="headingPersonal" data-bs-parent="#cvFormAccordion">
                        <div class="accordion-body">
                            <div class="mb-3">
                                <label class="form-label small">Photo</label>
                                <div class="border rounded d-flex align-items-center justify-content-center" style="height:120px; background:#f8f9fa;">
                                    <i class="bi bi-person-square text-muted" style="font-size:2rem;"></i>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small">First Name</label>
                                    <input type="text" class="form-control form-control-sm" id="firstName" value="<?= htmlspecialchars($profile['full_name'] ?? '', ENT_QUOTES) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Last Name</label>
                                    <input type="text" class="form-control form-control-sm" id="lastName">
                                </div>
                            </div>
                            <div class="mb-3 mt-2">
                                <label class="form-label small">Desired Position / Headline</label>
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm" id="headline" value="<?= htmlspecialchars($profile['headline'] ?? '', ENT_QUOTES) ?>">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" id="toggleHeadlineBtn" title="Toggle Headline Visibility"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Email</label>
                                <input type="email" class="form-control form-control-sm" id="email" value="<?= htmlspecialchars($profile['email'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Phone</label>
                                <input type="tel" class="form-control form-control-sm" id="phone" value="<?= htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Address / City</label>
                                <input type="text" class="form-control form-control-sm" id="location" value="<?= htmlspecialchars($profile['location'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">LinkedIn URL</label>
                                <input type="url" class="form-control form-control-sm" id="linkedin">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Portfolio / Website</label>
                                <input type="url" class="form-control form-control-sm" id="portfolio">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Professional Summary -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSummary">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSummary" aria-expanded="false" aria-controls="collapseSummary">
                            Professional Summary
                        </button>
                    </h2>
                    <div id="collapseSummary" class="accordion-collapse collapse" aria-labelledby="headingSummary" data-bs-parent="#cvFormAccordion">
                        <div class="accordion-body">
                            <textarea class="form-control form-control-sm" id="summary" rows="4" placeholder="Brief impact‑driven summary..."><?= htmlspecialchars($profile['summary'] ?? '', ENT_QUOTES) ?></textarea>
                            <div class="mt-2 d-flex gap-2">
                                <button class="btn btn-outline-success btn-sm" data-action="add-verb" data-verb="Spearheaded"><i class="bi bi-plus"></i> Spearheaded</button>
                                <button class="btn btn-outline-success btn-sm" data-action="add-verb" data-verb="Architected"><i class="bi bi-plus"></i> Architected</button>
                                <button class="btn btn-outline-success btn-sm" data-action="add-verb" data-verb="Optimized"><i class="bi bi-plus"></i> Optimized</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Technical Skills & ATS Analyzer -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSkills">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSkills" aria-expanded="false" aria-controls="collapseSkills">
                            Technical Skills & ATS Analyzer
                        </button>
                    </h2>
                    <div id="collapseSkills" class="accordion-collapse collapse" aria-labelledby="headingSkills" data-bs-parent="#cvFormAccordion">
                        <div class="accordion-body">
                            <div class="mb-3">
                                <label class="form-label small">Target Vacancy (for ATS match)</label>
                                <select class="form-select form-select-sm" id="targetVacancy">
                                    <option value="">— Select Vacancy —</option>
                                    <?php foreach ($vacancies as $v): ?>
                                        <option value="<?= (int)$v['job_id'] ?>" data-skills="<?= htmlspecialchars($v['required_skills'] ?? '', ENT_QUOTES) ?>">
                                            <?= htmlspecialchars($v['title'], ENT_QUOTES) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Select Skills (click to toggle)</label>
                                <div class="d-flex flex-wrap gap-2" id="skillCloud">
                                    <?php foreach ($skills as $s): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>" id="skill_<?= (int)$s['skill_id'] ?>">
                                            <label class="form-check-label small" for="skill_<?= (int)$s['skill_id'] ?>">
                                                <?= htmlspecialchars($s['name'], ENT_QUOTES) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="mt-3">
                                <h6 class="small mb-2">Matched Skills</h6>
                                <div id="matchedSkills" class="d-flex flex-wrap gap-1"></div>
                                <h6 class="small mt-3 mb-2">Missing High‑Demand Skills</h6>
                                <div id="missingSkills" class="d-flex flex-wrap gap-1"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Experience Repeater -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingExp">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExp" aria-expanded="false" aria-controls="collapseExp">
                            Experience
                        </button>
                    </h2>
                    <div id="collapseExp" class="accordion-collapse collapse" aria-labelledby="headingExp" data-bs-parent="#cvFormAccordion">
                        <div class="accordion-body">
                            <div id="experienceContainer"></div>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" id="addExperienceBtn"><i class="bi bi-plus"></i> Add Position</button>
                        </div>
                    </div>
                </div>

                <!-- Education Repeater -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingEdu">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEdu" aria-expanded="false" aria-controls="collapseEdu">
                            Education
                        </button>
                    </h2>
                    <div id="collapseEdu" class="accordion-collapse collapse" aria-labelledby="headingEdu" data-bs-parent="#cvFormAccordion">
                        <div class="accordion-body">
                            <div id="educationContainer"></div>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" id="addEducationBtn"><i class="bi bi-plus"></i> Add Education</button>
                        </div>
                    </div>
                </div>

                <!-- Additional Optional Blocks -->
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOptional">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptional" aria-expanded="false" aria-controls="collapseOptional">
                            Additional Sections
                        </button>
                    </h2>
                    <div id="collapseOptional" class="accordion-collapse collapse" aria-labelledby="headingOptional" data-bs-parent="#cvFormAccordion">
                        <div class="accordion-body">
                            <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="addLanguageBtn"><i class="bi bi-plus"></i> Languages</button>
                            <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="addProjectBtn"><i class="bi bi-plus"></i> Projects</button>
                            <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="addCertBtn"><i class="bi bi-plus"></i> Certifications</button>
                            <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="addCustomBtn"><i class="bi bi-plus"></i> Custom Section</button>
                        </div>
                    </div>
                </div>
            </div> <!-- /accordion -->
        </div> <!-- /left-pane -->

        <!-- RIGHT PREVIEW PANEL -->
        <div class="right-pane">
            <div id="cv-preview" data-template="circular">
                <div id="cv-content"></div>
                <!-- Floating Dock -->
                <div id="floating-dock" class="d-print-none">
                    <!-- Template Selector -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-layout-text-window-reverse"></i> Template
                        </button>
                        <ul class="dropdown-menu p-2" style="min-width:250px;">
                            <li><button class="dropdown-item template-select" data-template="circular">Circular</button></li>
                            <li><button class="dropdown-item template-select" data-template="twocolumn">Two‑Column Tech</button></li>
                            <li><button class="dropdown-item template-select" data-template="minimal">Minimalist Slate</button></li>
                            <li><button class="dropdown-item template-select" data-template="executive">Classic Executive</button></li>
                        </ul>
                    </div>
                    <!-- Font Picker -->
                    <select class="form-select form-select-sm w-auto" id="fontPicker">
                        <option value="'Arial,Helvetica,sans-serif'">Arial</option>
                        <option value="'Calibri,Arial,sans-serif'">Calibri</option>
                        <option value="'Lato,Helvetica,sans-serif'">Lato</option>
                        <option value="'Helvetica,Arial,sans-serif'">Helvetica</option>
                        <option value="'Poppins,Helvetica,sans-serif'">Poppins</option>
                        <option value="'Georgia,serif'">Georgia</option>
                        <option value="'Garamond,serif'">Garamond</option>
                        <option value="'Trebuchet MS,Helvetica,sans-serif'">Trebuchet</option>
                        <option value="'Courier New,monospace'">Courier New</option>
                    </select>
                    <!-- Typography & Spacing -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-arrows-angle-expand"></i> Spacing
                        </button>
                        <ul class="dropdown-menu p-3">
                            <li class="mb-2"><label class="form-label small mb-0">Line Height</label><input type="range" min="1.1" max="1.8" step="0.1" id="lineHeightRange" class="form-range"></li>
                            <li class="mb-2"><label class="form-label small mb-0">Entry Spacing</label><input type="range" min="6" max="24" step="2" id="entrySpacingRange" class="form-range"></li>
                            <li class="mb-2"><label class="form-label small mb-0">Section Spacing</label><input type="range" min="12" max="36" step="2" id="sectionSpacingRange" class="form-range"></li>
                            <li><label class="form-label small mb-0">Page Margin</label><input type="range" min="15" max="45" step="5" id="pageMarginRange" class="form-range"></li>
                        </ul>
                    </div>
                    <!-- Color Palette -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-palette"></i> Colours
                        </button>
                        <ul class="dropdown-menu p-2">
                            <li><button class="dropdown-item color-swatch" data-color="#1e3a5f">CVwizard Navy</button></li>
                            <li><button class="dropdown-item color-swatch" data-color="#06b6d4">Cyber Cyan</button></li>
                            <li><button class="dropdown-item color-swatch" data-color="#059669">Tech Emerald</button></li>
                            <li><button class="dropdown-item color-swatch" data-color="#881337">Burgundy</button></li>
                            <li><button class="dropdown-item color-swatch" data-color="#d97706">Warm Amber</button></li>
                            <li><button class="dropdown-item color-swatch" data-color="#1e293b">Dark Slate</button></li>
                            <li class="dropdown-divider"></li>
                            <li><input type="color" id="customColor" class="form-control form-control-sm"></li>
                        </ul>
                    </div>
                    <!-- Zoom Toggle -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-zoom-in"></i> Zoom
                        </button>
                        <ul class="dropdown-menu p-2">
                            <li><button class="dropdown-item zoom-select" data-zoom="0.8">80%</button></li>
                            <li><button class="dropdown-item zoom-select" data-zoom="0.9">90%</button></li>
                            <li><button class="dropdown-item zoom-select" data-zoom="1.0">100%</button></li>
                        </ul>
                    </div>
                </div> <!-- /floating-dock -->
            </div> <!-- /cv-preview -->
        </div> <!-- /right-pane -->
    </div> <!-- /main split -->
</div> <!-- /cv-studio -->

<!-- MODALS -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="uploadForm">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel"><i class="bi bi-cloud-arrow-up"></i> Upload Existing CV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Supported: .txt, .pdf (text extraction only).</p>
                <input type="file" class="form-control" id="cvFile" accept=".txt,.pdf" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Parse & Populate</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="applyModal" tabindex="-1" aria-labelledby="applyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" id="applyForm">
            <div class="modal-header">
                <h5 class="modal-title" id="applyModalLabel"><i class="bi bi-send"></i> Apply with this CV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($vacancies)): ?>
                    <div class="mb-3">
                        <label for="applyVacancySelect" class="form-label">Select Vacancy</label>
                        <select class="form-select" id="applyVacancySelect" name="apply_job_id" required>
                            <option value="">— Choose Vacancy —</option>
                            <?php foreach ($vacancies as $v): ?>
                                <option value="<?= (int)$v['job_id'] ?>"><?= htmlspecialchars($v['title'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">No open vacancies available.</div>
                <?php endif; ?>
                <input type="hidden" name="cv_payload" id="cvPayloadHidden">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
<script>
/* Rendering Engine */
let experienceIndex = 0;
let educationIndex   = 0;
function getSelectedSkills(){
    const boxes = document.querySelectorAll('#skillCloud input[type=checkbox]:checked');
    return Array.from(boxes).map(cb=>cb.value);
}
function renderCV(){
    const preview = document.getElementById('cv-preview');
    const content = document.getElementById('cv-content');
    const tmpl    = preview.getAttribute('data-template');
    const name = `${document.getElementById('firstName').value} ${document.getElementById('lastName').value}`.trim();
    const headline = document.getElementById('headline').value;
    const email = document.getElementById('email').value;
    const phone = document.getElementById('phone').value;
    const location = document.getElementById('location').value;
    const summary = document.getElementById('summary').value;
    const skills = getSelectedSkills();

    // Experience
    const expCards = document.querySelectorAll('#experienceContainer .exp-card');
    let expHTML = '';
    expCards.forEach(card=>{
        const title = card.querySelector('[name$="[title]"]').value;
        const employer = card.querySelector('[name$="[employer]"]').value;
        const city = card.querySelector('[name$="[city]"]').value;
        const start = card.querySelector('[name$="[start]"]').value;
        const end = card.querySelector('[name$="[end]"]').value;
        const details = card.querySelector('[name$="[details]"]').value.split('\n').filter(l=>l.trim());
        expHTML += `<div class="mb-2"><strong>${title}</strong> – ${employer}, ${city} (${start} – ${end || 'Present'})</div>`;
        if(details.length){
            expHTML += '<ul>'+details.map(l=>`<li>${l}</li>`).join('')+'</ul>';
        }
    });

    // Education
    const eduCards = document.querySelectorAll('#educationContainer .edu-card');
    let eduHTML = '';
    eduCards.forEach(card=>{
        const degree = card.querySelector('[name$="[degree]"]').value;
        const institution = card.querySelector('[name$="[institution]"]').value;
        const year = card.querySelector('[name$="[year]"]').value;
        const details = card.querySelector('[name$="[details]"]').value;
        eduHTML += `<div class="mb-2"><strong>${degree}</strong>, ${institution} (${year})</div>`;
        if(details.trim()) eduHTML += `<p>${details}</p>`;
    });

    let html='';
    if(tmpl==='circular'){
        html = `<h2>${name}</h2><h5 class="text-muted">${headline}</h5><p><i class="bi bi-envelope"></i> ${email} | <i class="bi bi-telephone"></i> ${phone} | <i class="bi bi-geo-alt"></i> ${location}</p><h5 class="mt-4 mb-2">Professional Summary</h5><p>${summary}</p><h5 class="mt-4 mb-2">Technical Skills</h5><p>${skills.join(', ')}</p>${expHTML?`<h5 class="mt-4 mb-2">Experience</h5>${expHTML}`:''}${eduHTML?`<h5 class="mt-4 mb-2">Education</h5>${eduHTML}`:''};
    } else if(tmpl==='twocolumn'){
        html = `<div style="display:flex;">
            <div style="flex:0 0 30%; padding-right:12px; border-right:1px solid #e2e8f0;">
                <h2>${name}</h2><h5 class="text-muted">${headline}</h5><p><i class="bi bi-envelope"></i> ${email}<br><i class="bi bi-telephone"></i> ${phone}<br><i class="bi bi-geo-alt"></i> ${location}</p><h6 class="mt-3">Technical Skills</h6><p>${skills.join(', ')}</p>
            </div>
            <div style="flex:1; padding-left:12px;">
                <h5 class="mt-0 mb-2">Professional Summary</h5><p>${summary}</p>${expHTML?`<h5 class="mt-4 mb-2">Experience</h5>${expHTML}`:''}${eduHTML?`<h5 class="mt-4 mb-2">Education</h5>${eduHTML}`:''}
            </div>
        </div>`;
    } else if(tmpl==='minimal'){
        html = `<h2>${name}</h2><p class="text-muted">${headline}</p><p><i class="bi bi-envelope"></i> ${email} | <i class="bi bi-telephone"></i> ${phone}</p><h5 class="mt-4 mb-2">Summary</h5><p>${summary}</p><h5 class="mt-4 mb-2">Skills</h5><p>${skills.join(', ')}</p>${expHTML?`<h5 class="mt-4 mb-2">Experience</h5>${expHTML}`:''}${eduHTML?`<h5 class="mt-4 mb-2">Education</h5>${eduHTML}`:''};
    } else { // executive
        html = `<h1 class="text-center mb-1">${name}</h1><h4 class="text-center text-muted mb-3">${headline}</h4><p class="text-center"><i class="bi bi-envelope"></i> ${email} | <i class="bi bi-telephone"></i> ${phone} | ${location}</p><hr><h5 class="mt-4 mb-2">Professional Summary</h5><p>${summary}</p><h5 class="mt-4 mb-2">Technical Skills</h5><p>${skills.join(', ')}</p>${expHTML?`<h5 class="mt-4 mb-2">Experience</h5>${expHTML}`:''}${eduHTML?`<h5 class="mt-4 mb-2">Education</h5>${eduHTML}`:''};
    }
    content.innerHTML = html;
}
/* Repeater Helpers */
function addExperienceItem(){
    experienceIndex++;
    const container=document.getElementById('experienceContainer');
    const div=document.createElement('div');
    div.className='exp-card border p-2 mb-2';
    div.innerHTML=`
        <div class="mb-2"><input type="text" class="form-control form-control-sm" placeholder="Job Title" name="exp[${experienceIndex}][title]"></div>
        <div class="mb-2"><input type="text" class="form-control form-control-sm" placeholder="Employer" name="exp[${experienceIndex}][employer]"></div>
        <div class="mb-2"><input type="text" class="form-control form-control-sm" placeholder="City" name="exp[${experienceIndex}][city]"></div>
        <div class="row g-2 mb-2"><div class="col-6"><input type="month" class="form-control form-control-sm" name="exp[${experienceIndex}][start]"></div><div class="col-6"><input type="month" class="form-control form-control-sm" name="exp[${experienceIndex}][end]"></div></div>
        <div class="mb-2"><textarea class="form-control form-control-sm" rows="3" placeholder="Bullet points (one per line)" name="exp[${experienceIndex}][details]"></textarea></div>
    `;
    container.appendChild(div);
}
function addEducationItem(){
    educationIndex++;
    const container=document.getElementById('educationContainer');
    const div=document.createElement('div');
    div.className='edu-card border p-2 mb-2';
    div.innerHTML=`
        <div class="mb-2"><input type="text" class="form-control form-control-sm" placeholder="Degree / Course" name="edu[${educationIndex}][degree]"></div>
        <div class="mb-2"><input type="text" class="form-control form-control-sm" placeholder="Institution" name="edu[${educationIndex}][institution]"></div>
        <div class="mb-2"><input type="number" min="1900" max="2100" class="form-control form-control-sm" placeholder="Year" name="edu[${educationIndex}][year]"></div>
        <div class="mb-2"><textarea class="form-control form-control-sm" rows="2" placeholder="Highlights / Details" name="edu[${educationIndex}][details]"></textarea></div>
    `;
    container.appendChild(div);
}
/* ATS Analyzer */
function runATS(){
    const vacancySelect=document.getElementById('targetVacancy');
    const vacancyId=vacancySelect.value;
    if(!vacancyId){
        document.getElementById('matchedSkills').innerHTML='';
        document.getElementById('missingSkills').innerHTML='';
        document.getElementById('ats-score-badge').textContent='ATS Score: 0%';
        return;
    }
    const option=vacancySelect.selectedOptions[0];
    const raw=option.getAttribute('data-skills')||'';
    const required=raw.split(',').map(s=>s.trim()).filter(Boolean);
    const selected=getSelectedSkills();
    const matched=required.filter(s=>selected.includes(s));
    const missing=required.filter(s=>!selected.includes(s));
    document.getElementById('matchedSkills').innerHTML=matched.map(s=>`<span class="badge bg-success me-1">${s}</span>`).join('');
    document.getElementById('missingSkills').innerHTML=missing.map(s=>`<button class="btn btn-sm btn-outline-warning me-1" onclick="addSkill('${s}')">${s}</button>`).join('');
    const score=Math.round((matched.length/(required.length||1))*100);
    document.getElementById('ats-score-badge').textContent=`ATS Score: ${score}%`;
}
function addSkill(name){
    const cb=Array.from(document.querySelectorAll('#skillCloud input[type=checkbox]')).find(i=>i.value===name);
    if(cb){cb.checked=true;runATS();renderCV();}
}
/* Toolbar Interaction */
document.querySelectorAll('.template-select').forEach(btn=>{btn.addEventListener('click',()=>{document.getElementById('cv-preview').setAttribute('data-template',btn.dataset.template);renderCV();});});
document.getElementById('fontPicker').addEventListener('change',e=>{const p=document.getElementById('cv-preview');p.style.setProperty('--font-family',e.target.value);p.style.fontFamily=e.target.value.replace(/^'/,'').replace(/'$/,'');renderCV();});
document.getElementById('lineHeightRange').addEventListener('input',e=>{document.getElementById('cv-preview').style.setProperty('--line-height',e.target.value);renderCV();});
document.getElementById('entrySpacingRange').addEventListener('input',e=>{document.getElementById('cv-preview').style.setProperty('--entry-spacing',e.target.value+'px');renderCV();});
document.getElementById('sectionSpacingRange').addEventListener('input',e=>{document.getElementById('cv-preview').style.setProperty('--section-spacing',e.target.value+'px');renderCV();});
document.getElementById('pageMarginRange').addEventListener('input',e=>{document.getElementById('cv-preview').style.setProperty('--page-margin',e.target.value+'mm');renderCV();});
document.querySelectorAll('.color-swatch').forEach(btn=>{btn.addEventListener('click',()=>{document.getElementById('cv-preview').style.setProperty('--primary-color',btn.dataset.color);renderCV();});});
document.getElementById('customColor').addEventListener('input',e=>{document.getElementById('cv-preview').style.setProperty('--primary-color',e.target.value);renderCV();});
document.querySelectorAll('.zoom-select').forEach(btn=>{btn.addEventListener('click',()=>{const z=btn.dataset.zoom;document.getElementById('cv-preview').style.transform=`scale(${z})`;});});
/* Action Tile Buttons */
document.getElementById('importProfileBtn').addEventListener('click',()=>{renderCV();});
document.getElementById('exampleBtn').addEventListener('click',()=>{document.getElementById('firstName').value='Jane';document.getElementById('lastName').value='Doe';document.getElementById('headline').value='Senior Full‑Stack Engineer';document.getElementById('summary').value='Passionate developer with 10+ years building SaaS platforms.';const boxes=document.querySelectorAll('#skillCloud input[type=checkbox]');boxes.forEach(cb=>cb.checked=false);['PHP','JavaScript','Docker','MySQL','AWS'].forEach(s=>{const cb=Array.from(boxes).find(i=>i.value===s);if(cb)cb.checked=true;});renderCV();});
/* Upload CV Placeholder */
document.getElementById('uploadForm').addEventListener('submit',e=>{e.preventDefault();const file=document.getElementById('cvFile').files[0];if(!file)return;const reader=new FileReader();reader.onload=ev=>{const txt=ev.target.result;document.getElementById('summary').value=txt.slice(0,400)+'...';renderCV();bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();};reader.readAsText(file);});
/* Apply Modal */
document.getElementById('applyForm').addEventListener('submit',()=>{const payload={title:document.getElementById('cv-title').innerText.trim(),name:`${document.getElementById('firstName').value} ${document.getElementById('lastName').value}`.trim(),headline:document.getElementById('headline').value,email:document.getElementById('email').value,phone:document.getElementById('phone').value,location:document.getElementById('location').value,summary:document.getElementById('summary').value,skills:getSelectedSkills()};document.getElementById('cvPayloadHidden').value=JSON.stringify(payload);});
/* Global Input Listeners */
['firstName','lastName','headline','email','phone','location','summary','targetVacancy'].forEach(id=>{const el=document.getElementById(id);if(el)el.addEventListener('input',()=>{renderCV();if(id==='targetVacancy')runATS();});});
document.querySelectorAll('#skillCloud input[type=checkbox]').forEach(cb=>{cb.addEventListener('change',()=>{renderCV();runATS();});});
/* Init */
document.getElementById('addExperienceBtn').addEventListener('click',addExperienceItem);
document.getElementById('addEducationBtn').addEventListener('click',addEducationItem);
runATS();
renderCV();
</script>