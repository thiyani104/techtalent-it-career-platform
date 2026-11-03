<?php
/**
 * AI Skill Gap Analyzer Utility
 * Provides functions to tokenize skills, calculate job match scores, and generate learning recommendations.
 */

/**
 * Normalizes a skill list string or array into a clean array of lower‑cased skill tokens.
 *
 * @param string|array $string_or_array CSV string or array of skill names.
 * @return array Array of trimmed, lower‑cased skill strings.
 */
function tokenize_skills($string_or_array): array
{
    if (is_string($string_or_array)) {
        $items = explode(',', $string_or_array);
    } elseif (is_array($string_or_array)) {
        $items = $string_or_array;
    } else {
        return [];
    }
    $tokens = [];
    foreach ($items as $item) {
        $token = strtolower(trim($item));
        if ($token !== '') {
            $tokens[] = $token;
        }
    }
    return $tokens;
}

/**
 * Calculates the match score between a candidate's skills and the required skills for a job.
 * Returns an associative array with the percentage score, matched skills and missing gaps.
 *
 * @param array  $candidate_skills    Array of candidate skill names (strings).
 * @param string $job_required_skills_csv CSV string of required skill names.
 * @return array ['score'=>int,'matched'=>array,'gaps'=>array]
 */
function calculate_job_match(array $candidate_skills, string $job_required_skills_csv): array
{
    $required = tokenize_skills($job_required_skills_csv);
    $candidate = array_map('strtolower', array_map('trim', $candidate_skills));
    $matched = array_values(array_intersect($required, $candidate));
    $gaps = array_values(array_diff($required, $candidate));
    $score = 0;
    if (count($required) > 0) {
        $score = round((count($matched) / count($required)) * 100);
    }
    return [
        'score' => $score,
        'matched' => $matched,
        'gaps' => $gaps,
    ];
}

/**
 * Generates placeholder learning recommendations for each missing skill.
 * In a production system this could be hooked into a content service or external API.
 *
 * @param array $gaps List of missing skill names.
 * @return array List of recommendation entries, each with 'title' and 'link'.
 */
function generate_recommendations(array $gaps): array
{
    $recs = [];
    foreach ($gaps as $gap) {
        $recs[] = [
            'title' => "Learn {$gap} (e.g., via online courses)",
            'link' => "#", // placeholder URL – replace with real resources later
        ];
    }
    return $recs;
}

/* ---------------------------------------------------------------------
 * CV Studio: alias-aware skill normalisation and transparent gap analysis
 * ------------------------------------------------------------------- */

function skill_norm(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/\s+/u', ' ', str_replace('_', ' ', $s)) ?? $s;
    return trim($s, " \t.,;:-");
}

/**
 * Map of normalised name/alias => canonical skill name, from `skills` + `skill_aliases`.
 * Also returns canonical => list of all normalised spellings.
 */
function skill_taxonomy(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $map = [];
    $spellings = [];
    foreach ($pdo->query('SELECT skill_name FROM skills')->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $map[skill_norm($name)] = $name;
        $spellings[$name][] = skill_norm($name);
    }
    try {
        $rows = $pdo->query('SELECT a.alias, s.skill_name FROM skill_aliases a JOIN skills s ON s.skill_id = a.skill_id')->fetchAll();
        foreach ($rows as $r) {
            $map[skill_norm($r['alias'])] = $r['skill_name'];
            $spellings[$r['skill_name']][] = skill_norm($r['alias']);
        }
    } catch (PDOException $e) {
        // skill_aliases is optional (added by the CV Studio migration).
    }
    return $cache = ['map' => $map, 'spellings' => $spellings];
}

function skill_canonical(string $name, array $tax): string
{
    $n = skill_norm($name);
    return $tax['map'][$n] ?? trim($name);
}

/** Whole-word, case-insensitive search that respects symbols such as C++ or CI/CD. */
function skill_in_text(string $needle, string $haystackLower): bool
{
    if ($needle === '') {
        return false;
    }
    $pattern = '/(?<![a-z0-9+#])' . preg_quote($needle, '/') . '(?![a-z0-9+#])/u';
    return (bool)preg_match($pattern, $haystackLower);
}

/** Canonical taxonomy skills mentioned anywhere in free text. */
function detect_taxonomy_skills(PDO $pdo, string $text): array
{
    $tax = skill_taxonomy($pdo);
    $lower = mb_strtolower($text);
    $found = [];
    foreach ($tax['spellings'] as $canonical => $spellings) {
        foreach ($spellings as $sp) {
            if (mb_strlen($sp) >= 2 && skill_in_text($sp, $lower)) {
                $found[$canonical] = true;
                break;
            }
        }
    }
    return array_keys($found);
}

function split_skill_list(?string $csv): array
{
    $items = preg_split('/[,;\n]+/', (string)$csv) ?: [];
    return array_values(array_unique(array_filter(array_map('trim', $items), 'strlen')));
}

/**
 * Compare CV content against a vacancy. Required-skill match % =
 * matched required / total required × 100. Informational only.
 *
 * @param array $cv   Normalised CV content (see includes/cv-functions.php).
 * @param array $job  Row with required_skills and optional_skills.
 */
function cv_skill_gap(PDO $pdo, array $cv, array $job): array
{
    $tax = skill_taxonomy($pdo);

    $cvSkills = [];
    foreach (['technical', 'soft'] as $t) {
        foreach ($cv['skills'][$t] ?? [] as $s) {
            $cvSkills[skill_norm(skill_canonical($s['name'], $tax))] = true;
        }
    }
    // Text outside the skills section, for "mentioned in CV" matches.
    $other = $cv;
    $other['skills'] = ['technical' => [], 'soft' => []];
    $otherText = mb_strtolower(function_exists('cv_plain_text') ? cv_plain_text($other) : '');

    $check = function (string $required) use ($tax, $cvSkills, $otherText): array {
        $canonical = skill_canonical($required, $tax);
        $key = skill_norm($canonical);
        $spellings = array_unique(array_merge([$key, skill_norm($required)], $tax['spellings'][$canonical] ?? []));
        foreach ($spellings as $sp) {
            if (isset($cvSkills[$sp]) || isset($cvSkills[skill_norm(skill_canonical($sp, $tax))])) {
                return ['skill' => $required, 'matched' => true, 'source' => 'Skills section'];
            }
        }
        // Composite requirement such as "HTML/CSS": every part must be present.
        if (!isset($tax['map'][$key]) && str_contains($required, '/')) {
            $parts = array_filter(array_map('trim', explode('/', $required)), 'strlen');
            $ok = count($parts) > 1;
            foreach ($parts as $p) {
                $pk = skill_norm(skill_canonical($p, $tax));
                if (!isset($cvSkills[$pk]) && !skill_in_text($pk, $otherText)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return ['skill' => $required, 'matched' => true, 'source' => 'All parts found in CV'];
            }
        }
        foreach ($spellings as $sp) {
            if (mb_strlen($sp) >= 2 && skill_in_text($sp, $otherText)) {
                return ['skill' => $required, 'matched' => true, 'source' => 'Mentioned in experience/projects'];
            }
        }
        return ['skill' => $required, 'matched' => false, 'source' => ''];
    };

    $required = array_map($check, split_skill_list($job['required_skills'] ?? ''));
    $optional = array_map($check, split_skill_list($job['optional_skills'] ?? ''));
    $matchedRequired = count(array_filter($required, fn($r) => $r['matched']));

    return [
        'required' => $required,
        'optional' => $optional,
        'matched_required' => $matchedRequired,
        'total_required' => count($required),
        'percent' => count($required) > 0 ? (int)round($matchedRequired / count($required) * 100) : null,
    ];
}
