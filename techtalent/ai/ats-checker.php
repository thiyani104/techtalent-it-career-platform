<?php
/**
 * Heuristic CV quality / ATS-readiness checker.
 * Rule-based and transparent: every result states what was checked and how to fix it.
 * This is guidance only, not certification against any specific ATS product.
 */

require_once __DIR__ . '/../includes/cv-functions.php';

/**
 * @param array  $cv       Normalised CV content.
 * @param string $template Selected template key.
 * @return array ['checks' => [...], 'summary' => [...]]
 */
function ats_check_cv(array $cv, string $template = 'classic'): array
{
    $checks = [];
    $add = function (string $group, string $status, string $label, string $detail = '', string $fix = '') use (&$checks) {
        $checks[] = compact('group', 'status', 'label', 'detail', 'fix');
    };
    $p = $cv['personal'];
    $hidden = $cv['hidden'];

    // --- Contact information -------------------------------------------------
    $p['full_name'] !== ''
        ? $add('Contact', 'pass', 'Full name present')
        : $add('Contact', 'fail', 'Full name is missing', '', 'Add your full name in Personal Details.');
    if ($p['email'] === '') {
        $add('Contact', 'fail', 'Email address is missing', '', 'Add a professional email address.');
    } elseif (!filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
        $add('Contact', 'fail', 'Email address looks invalid', $p['email'], 'Check the email format (name@domain.com).');
    } else {
        $add('Contact', 'pass', 'Valid email address');
    }
    if ($p['phone'] === '') {
        $add('Contact', 'fail', 'Phone number is missing', '', 'Add a phone number with country code, e.g. +94 77 123 4567.');
    } elseif (preg_match_all('/\d/', $p['phone']) < 7) {
        $add('Contact', 'warn', 'Phone number looks incomplete', $p['phone'], 'Include the full number with country code.');
    } else {
        $add('Contact', 'pass', 'Phone number present');
    }
    $p['location'] !== ''
        ? $add('Contact', 'pass', 'Location present')
        : $add('Contact', 'warn', 'Location is missing', '', 'Add at least your city and country; many recruiters filter by location.');
    $p['linkedin'] !== ''
        ? $add('Contact', 'pass', 'LinkedIn profile included')
        : $add('Contact', 'warn', 'No LinkedIn profile', '', 'Optional, but a LinkedIn URL helps recruiters verify your profile.');
    foreach (['linkedin' => 'LinkedIn', 'github' => 'GitHub', 'portfolio' => 'Portfolio'] as $k => $name) {
        if ($p[$k] !== '' && !preg_match('~^(https?://)?[a-z0-9.-]+\.[a-z]{2,}(/\S*)?$~i', $p[$k])) {
            $add('Contact', 'warn', "$name link looks malformed", $p[$k], 'Use a plain URL such as linkedin.com/in/your-name.');
        }
    }

    // --- Sections ----------------------------------------------------------
    $p['title'] !== ''
        ? $add('Sections', 'pass', 'Professional title present')
        : $add('Sections', 'warn', 'No professional title', '', 'Add a target title (e.g. "Junior PHP Developer") under your name.');
    $summaryWords = str_word_count(strip_tags($p['summary']));
    if ($summaryWords === 0) {
        $add('Sections', 'warn', 'Professional summary is empty', '', 'Write 2–4 sentences on your focus, strongest skills and goal.');
    } elseif ($summaryWords < 20) {
        $add('Sections', 'warn', 'Summary is very short', "$summaryWords words", 'Aim for roughly 40–100 words.');
    } elseif ($summaryWords > 150) {
        $add('Sections', 'warn', 'Summary is long', "$summaryWords words", 'Keep it under ~120 words; move detail into Experience.');
    } else {
        $add('Sections', 'pass', 'Professional summary length is reasonable', "$summaryWords words");
    }
    count($cv['experience']) > 0
        ? $add('Sections', 'pass', 'Work experience section has entries', count($cv['experience']) . ' entries')
        : $add('Sections', 'warn', 'No work experience entries', '', 'Add internships, part-time, freelance or volunteer roles. If you have none, make Projects visible.');
    count($cv['education']) > 0
        ? $add('Sections', 'pass', 'Education section has entries')
        : $add('Sections', 'fail', 'Education section is empty', '', 'Add your highest qualification.');
    $skillCount = count($cv['skills']['technical']) + count($cv['skills']['soft']);
    if ($skillCount === 0) {
        $add('Sections', 'fail', 'Skills section is empty', '', 'List the technical skills that match the jobs you target.');
    } elseif (count($cv['skills']['technical']) < 4) {
        $add('Sections', 'warn', 'Few technical skills listed', count($cv['skills']['technical']) . ' technical skills', 'ATS keyword matching relies on skills; list the tools and languages you actually use.');
    } else {
        $add('Sections', 'pass', 'Skills section populated', "$skillCount skills");
    }
    foreach (CV_OPTIONAL_SECTIONS as $opt) {
        if (!$hidden[$opt] && count($cv[$opt]) === 0) {
            $add('Sections', 'warn', ucfirst($opt) . ' section is visible but empty', '', 'Add entries or hide the section so it does not print an empty heading.');
        }
    }

    // --- Consistency & completeness ---------------------------------------
    $dateFormats = [];
    $missingDates = 0;
    $badRanges = [];
    foreach (['experience', 'education', 'projects'] as $section) {
        foreach ($cv[$section] as $i => $e) {
            foreach (['start', 'end'] as $f) {
                if ($e[$f] !== '') {
                    $dateFormats[ats_date_format($e[$f])] = true;
                }
            }
            if (in_array($section, ['experience', 'education'], true) && $e['start'] === '' && $e['end'] === '') {
                $missingDates++;
            }
            $s = ats_date_value($e['start']);
            $en = ats_date_value($e['end']);
            if ($s && $en && $en < $s) {
                $badRanges[] = ucfirst($section) . ' #' . ($i + 1);
            }
        }
    }
    unset($dateFormats['other']);
    $missingDates > 0
        ? $add('Consistency', 'warn', 'Some entries have no dates', "$missingDates entries", 'Add start/end dates (e.g. "Jan 2023 – Present") to every role and qualification.')
        : $add('Consistency', 'pass', 'Experience and education entries are dated');
    count($dateFormats) > 1
        ? $add('Consistency', 'warn', 'Mixed date formats', implode(', ', array_keys($dateFormats)), 'Use one format throughout, e.g. "Mar 2024" or "03/2024".')
        : $add('Consistency', 'pass', 'Date format is consistent');
    if ($badRanges) {
        $add('Consistency', 'fail', 'End date before start date', implode(', ', $badRanges), 'Correct the date range.');
    }
    $thin = [];
    foreach ($cv['experience'] as $i => $e) {
        if ($e['position'] === '' || $e['company'] === '') {
            $thin[] = 'Experience #' . ($i + 1) . ' missing position or company';
        }
        if (str_word_count($e['responsibilities'] . ' ' . $e['achievements']) < 8) {
            $thin[] = 'Experience #' . ($i + 1) . ' has little or no description';
        }
    }
    foreach ($cv['education'] as $i => $e) {
        if ($e['institution'] === '' || $e['qualification'] === '') {
            $thin[] = 'Education #' . ($i + 1) . ' missing institution or qualification';
        }
    }
    $thin
        ? $add('Consistency', 'warn', 'Incomplete entries', implode('; ', $thin), 'Fill in titles and describe responsibilities with action verbs and results.')
        : $add('Consistency', 'pass', 'Entries are complete');
    $metrics = 0;
    foreach ($cv['experience'] as $e) {
        $metrics += preg_match_all('/\d+\s*%|\d{2,}/', $e['achievements'] . ' ' . $e['responsibilities']);
    }
    if (count($cv['experience']) > 0) {
        $metrics > 0
            ? $add('Consistency', 'pass', 'Experience includes measurable results')
            : $add('Consistency', 'warn', 'No measurable achievements', '', 'Quantify impact where true, e.g. "reduced page load time by 30%".');
    }

    // --- Readability & parsing -------------------------------------------
    $text = cv_plain_text($cv);
    $odd = [];
    if (preg_match_all('/[\x{2600}-\x{27BF}\x{1F000}-\x{1FAFF}\x{2190}-\x{21FF}\x{25A0}-\x{25FF}\x{E000}-\x{F8FF}\x{FB00}-\x{FB06}\x{FFFD}]/u', $text, $m)) {
        $odd = array_unique($m[0]);
    }
    $odd
        ? $add('Readability', 'warn', 'Unusual symbols found', implode(' ', array_slice($odd, 0, 12)), 'Replace emoji, icons, arrows and decorative glyphs with plain text; some parsers drop or garble them.')
        : $add('Readability', 'pass', 'No unusual symbols or emoji');
    if (str_contains($text, "\t") || preg_match('/ {4,}/', $text)) {
        $add('Readability', 'warn', 'Tabs or runs of spaces used for alignment', '', 'Remove manual alignment; parsers may merge or split columns incorrectly.');
    }
    $longLines = 0;
    foreach (preg_split('/\n/', $text) as $line) {
        if (str_word_count($line) > 45) {
            $longLines++;
        }
    }
    $longLines > 0
        ? $add('Readability', 'warn', 'Very long paragraphs', "$longLines paragraph(s) over 45 words", 'Break into short bullet points (one achievement per line).')
        : $add('Readability', 'pass', 'Paragraph length is readable');
    if (preg_match_all('/\b[A-Z]{4,}(?:\s+[A-Z]{4,}){3,}\b/', $text) > 0) {
        $add('Readability', 'warn', 'Long ALL-CAPS passages', '', 'Use sentence case for body text.');
    }
    $words = str_word_count($text);
    if ($words > 1300) {
        $add('Readability', 'warn', 'CV is long', "~$words words", 'For early-career roles, 1–2 pages is usually enough.');
    }

    if (!empty($cv['meta']['import'])) {
        $imp = $cv['meta']['import'];
        if (($imp['garbled_ratio'] ?? 0) > 0.05) {
            $add('Readability', 'warn', 'Original file text was partly unreadable', 'Imported file had ' . round($imp['garbled_ratio'] * 100) . '% unreadable characters', 'Review imported fields carefully; ATS software may have the same trouble reading the original file.');
        } else {
            $add('Readability', 'pass', 'Original file text could be extracted', ($imp['chars'] ?? 0) . ' characters read');
        }
    }

    // --- Layout & headings (determined by our templates) -------------------
    $add('Layout', 'pass', 'Single-column layout without tables', ucfirst($template) . ' template', 'All CV Studio templates use one reading column and real text (no images or text boxes).');
    $add('Layout', 'pass', 'Standard section headings', 'Professional Summary, Work Experience, Education, Skills…', 'Headings use common wording that parsers recognise.');
    if ($cv['order'][0] !== 'summary' && $cv['order'][0] !== 'experience') {
        $add('Layout', 'warn', 'Unusual section order', 'First section: ' . ucfirst($cv['order'][0]), 'Most parsers and recruiters expect Summary or Experience first.');
    }

    $summary = ['pass' => 0, 'warn' => 0, 'fail' => 0];
    foreach ($checks as $c) {
        $summary[$c['status']]++;
    }
    $summary['total'] = count($checks);
    $summary['score'] = (int)round(($summary['pass'] + 0.5 * $summary['warn']) / max(1, $summary['total']) * 100);
    return ['checks' => $checks, 'summary' => $summary];
}

function ats_date_format(string $d): string
{
    $d = trim($d);
    if (preg_match('/^(present|current|now|ongoing)$/i', $d)) return 'other';
    if (preg_match('/^\d{4}$/', $d)) return 'YYYY';
    if (preg_match('/^[A-Za-z]{3}\.? \d{4}$/', $d)) return 'Mon YYYY';
    if (preg_match('/^[A-Za-z]{4,} \d{4}$/', $d)) return 'Month YYYY';
    if (preg_match('/^\d{1,2}[\/.-]\d{4}$/', $d)) return 'MM/YYYY';
    if (preg_match('/^\d{4}[\/.-]\d{1,2}$/', $d)) return 'YYYY-MM';
    return 'free text';
}

/** Comparable YYYYMM integer, or null when the date cannot be read. */
function ats_date_value(string $d): ?int
{
    $d = trim($d);
    if ($d === '' || preg_match('/^(present|current|now|ongoing)$/i', $d)) return null;
    if (preg_match('/^(\d{4})[\/.-](\d{1,2})$/', $d, $m)) return (int)$m[1] * 100 + (int)$m[2];
    if (preg_match('/^(\d{1,2})[\/.-](\d{4})$/', $d, $m)) return (int)$m[2] * 100 + (int)$m[1];
    if (preg_match('/^(\d{4})$/', $d, $m)) return (int)$m[1] * 100 + 1;
    if (preg_match('/^[A-Za-z]{3,}\.? \d{4}$/', $d)) {
        $ts = strtotime('1 ' . str_replace('.', '', $d));
        return $ts ? (int)date('Ym', $ts) : null;
    }
    return null;
}
