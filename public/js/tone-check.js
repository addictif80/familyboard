// ============================================================
// Détecteur de ton conflictuel — analyse locale (aucun envoi externe, aucune IA), pensée pour
// le Journal parental où les messages entre coparents sont immuables une fois envoyés. Ne
// bloque jamais l'envoi : signale simplement, avant qu'il ne soit trop tard pour reformuler.
// ============================================================

const TONE_INSULTS = [
    'connard', 'connasse', 'con', 'abruti', 'débile', 'idiot', 'imbécile',
    'pathétique', 'minable', 'pitoyable', 'stupide', 'nul', 'merde', 'putain',
    'salope', 'salaud', 'crétin', 'lamentable', 'ridicule', 'incapable',
];

const TONE_ACCUSATORY_PHRASES = [
    'tu ne fais jamais', 'tu ne fais rien', 'tu fais toujours', "comme d'habitude",
    'comme toujours', 'encore une fois', "c'est toujours pareil", "tu n'écoutes jamais",
    'tu ne comprends jamais', "t'es incapable", 'de toute façon tu', 'comme d\'hab',
];

const TONE_THREATS = [
    'je vais porter plainte', 'mon avocat', 'je te préviens', 'tu vas le regretter',
    'la prochaine fois je', 'je te signale',
];

function _toneWordBoundaryMatch(lower, word) {
    // Évite qu'un mot comme "con" ne matche à l'intérieur de "conducteur" ou "contact".
    const re = new RegExp('(^|[^a-zà-ÿ])' + word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '($|[^a-zà-ÿ])', 'i');
    return re.test(lower);
}

function checkConflictualTone(text) {
    if (!text || text.trim().length < 3) return { flagged: false, reasons: [] };

    const lower = text.toLowerCase();
    const reasons = [];
    let score = 0;

    for (const w of TONE_INSULTS) {
        if (_toneWordBoundaryMatch(lower, w)) { score += 3; reasons.push('un mot potentiellement blessant'); break; }
    }
    for (const p of TONE_ACCUSATORY_PHRASES) {
        if (lower.includes(p)) { score += 2; reasons.push('une généralisation ("toujours"/"jamais") qui envenime souvent l\'échange'); break; }
    }
    for (const t of TONE_THREATS) {
        if (lower.includes(t)) { score += 3; reasons.push('une formulation qui ressemble à une menace ou un ultimatum'); break; }
    }

    // Cri (mots de 4 lettres et + en majuscules) — ignore les messages courts entièrement en
    // majuscules par habitude clavier plutôt que par intention.
    const shoutWords = (text.match(/\b[A-ZÀ-Ý]{4,}\b/g) || []).length;
    if (shoutWords >= 2) { score += 2; reasons.push('des mots en majuscules ("qui crient")'); }

    if (/[!?]{3,}/.test(text)) { score += 1; reasons.push('une ponctuation très appuyée'); }

    return { flagged: score >= 2, reasons: [...new Set(reasons)], score };
}
