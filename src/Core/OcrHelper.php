<?php
namespace App\Core;

class OcrHelper
{
    private static array $binaryPaths = [
        '/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/snap/bin',
    ];

    // ── Binary detection ─────────────────────────────────────────────────────

    public static function findBinary(string $name): string
    {
        foreach (self::$binaryPaths as $dir) {
            $path = $dir . '/' . $name;
            if (is_executable($path)) return $path;
        }
        $found = trim((string)shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null'));
        return $found ?: '';
    }

    // ── OCR runner ────────────────────────────────────────────────────────────

    public static function run(string $tmpPath, string $mime): string
    {
        // PDF → pdftotext
        if ($mime === 'application/pdf') {
            $bin = self::findBinary('pdftotext');
            if ($bin) {
                $out = []; $code = 0;
                exec($bin . ' ' . escapeshellarg($tmpPath) . ' - 2>/dev/null', $out, $code);
                $text = trim(implode("\n", $out));
                if ($text !== '' && $code === 0) return $text;
            }
        }

        // Image → tesseract (stdout mode)
        $bin = self::findBinary('tesseract');
        if (!$bin) return '';

        foreach (['fra', 'eng', ''] as $lang) {
            $flag = $lang ? " -l $lang" : '';
            $out  = []; $code = 0;
            exec($bin . ' ' . escapeshellarg($tmpPath) . ' stdout' . $flag . ' 2>/dev/null', $out, $code);
            $text = trim(implode("\n", $out));
            if ($text !== '') return $text;
            if ($code === 0) break;
        }
        return '';
    }

    public static function info(): array
    {
        $tBin = self::findBinary('tesseract') ?: null;
        $langs = [];
        if ($tBin) {
            $out = [];
            exec($tBin . ' --list-langs 2>&1', $out);
            $langs = array_values(array_filter(array_slice($out, 1), fn($l) => trim($l) !== ''));
        }
        return [
            'tesseract'       => $tBin,
            'tesseract_langs' => $langs,
            'pdftotext'       => self::findBinary('pdftotext') ?: null,
            'php_exec'        => function_exists('exec'),
            'shell_exec'      => function_exists('shell_exec'),
            'tmp_dir'         => sys_get_temp_dir(),
            'tmp_writable'    => is_writable(sys_get_temp_dir()),
        ];
    }

    // ── File storage (shared by Warranty and Document) ───────────────────────

    public static function saveUploadedFile(array $file, string $subDir, int $familyId, ?array $allowedMimes = null, int $maxSize = 20 * 1024 * 1024): array
    {
        $allowedMimes ??= ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
        // Blobs from MediaRecorder report a type like "audio/webm;codecs=opus" — compare on the base mime only.
        $mimeBase = strtolower(trim(explode(';', $file['type'])[0]));
        if (!in_array($mimeBase, $allowedMimes, true)) {
            throw new \RuntimeException('Type de fichier non autorisé.');
        }
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('Fichier trop volumineux (max ' . round($maxSize / 1024 / 1024) . ' Mo).');
        }
        // Le Content-Type déclaré par le client n'est qu'un en-tête de requête, entièrement
        // falsifiable — on vérifie le contenu réel du fichier avant de lui faire confiance.
        if (!self::realMimeMatches($file['tmp_name'], $mimeBase)) {
            throw new \RuntimeException('Le contenu du fichier ne correspond pas au type annoncé.');
        }

        // Le Content-Type et le nom envoyés par le client sont falsifiables : on ne
        // dérive jamais l'extension stockée du nom de fichier fourni par le client.
        $ext      = self::extensionForMime($mimeBase);
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir      = BASE_PATH . '/storage/' . $subDir . '/' . $familyId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Erreur lors de l\'enregistrement du fichier.');
        }

        return [
            '/storage/' . $subDir . '/' . $familyId . '/' . $filename,
            $file['name'],
            $mimeBase,
        ];
    }

    /**
     * Même principe que saveUploadedFile(), mais pour des octets déjà en mémoire (un
     * téléchargement depuis une API tierce comme Digiposte, pas un $_FILES d'upload HTTP
     * classique — move_uploaded_file() refuserait un fichier qui n'a pas transité par un POST
     * multipart). Le "type déclaré" ici vient de l'API distante, pas d'un client final, mais
     * reste vérifié contre le contenu réel par la même prudence : une API tierce peut aussi
     * mentir ou se tromper sur le Content-Type.
     */
    public static function saveRemoteFile(string $bytes, string $declaredMime, string $subDir, int $familyId, ?array $allowedMimes = null, int $maxSize = 20 * 1024 * 1024): array
    {
        $allowedMimes ??= ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
        $mimeBase = strtolower(trim(explode(';', $declaredMime)[0]));
        if (!in_array($mimeBase, $allowedMimes, true)) {
            throw new \RuntimeException('Type de fichier non autorisé.');
        }
        if (strlen($bytes) > $maxSize) {
            throw new \RuntimeException('Fichier trop volumineux (max ' . round($maxSize / 1024 / 1024) . ' Mo).');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'digiposte_');
        if ($tmpPath === false || file_put_contents($tmpPath, $bytes) === false) {
            throw new \RuntimeException('Erreur lors de l\'enregistrement temporaire du fichier.');
        }
        if (!self::realMimeMatches($tmpPath, $mimeBase)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Le contenu du fichier ne correspond pas au type annoncé.');
        }

        $ext = self::extensionForMime($mimeBase);
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir = BASE_PATH . '/storage/' . $subDir . '/' . $familyId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $dest = $dir . '/' . $filename;
        if (!rename($tmpPath, $dest)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Erreur lors de l\'enregistrement du fichier.');
        }

        return ['/storage/' . $subDir . '/' . $familyId . '/' . $filename, $mimeBase];
    }

    /**
     * Vérifie le contenu réel du fichier via mime_content_type() (libmagic) plutôt que de faire
     * confiance au Content-Type déclaré par le client. Tolérance pour les conteneurs ambigus
     * (webm/ogg/mp4 audio-only sont parfois rapportés sans le préfixe audio/ selon la version de
     * libmagic) et vérification supplémentaire par getimagesize() pour les images.
     */
    private static function realMimeMatches(string $tmpPath, string $declaredMime): bool
    {
        $real = @mime_content_type($tmpPath) ?: '';
        if (str_starts_with($declaredMime, 'image/')) {
            return $real === $declaredMime && @getimagesize($tmpPath) !== false;
        }
        if ($declaredMime === 'application/pdf') {
            return $real === 'application/pdf';
        }
        $mimeEquivalences = [
            'audio/webm'  => ['audio/webm', 'video/webm'],
            'audio/ogg'   => ['audio/ogg', 'application/ogg', 'video/ogg'],
            'audio/mp4'   => ['audio/mp4', 'video/mp4'],
            'audio/x-m4a' => ['audio/mp4', 'video/mp4', 'audio/x-m4a'],
            'audio/mpeg'  => ['audio/mpeg'],
            'audio/wav'   => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
            'audio/x-wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
            'audio/aac'   => ['audio/aac', 'audio/x-aac'],
            // Bureautique/e-mail : tolérance similaire — un .docx/.xlsx est un ZIP, parfois
            // reconnu comme "application/zip" par une base libmagic ancienne ; un .doc/.xls est
            // un conteneur OLE2 générique ; un .eml est du texte brut, parfois reconnu comme
            // "text/plain" plutôt que le type message/rfc822 exact.
            'application/msword' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' =>
                ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'application/vnd.ms-excel' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' =>
                ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'message/rfc822' => ['message/rfc822', 'text/plain'],
        ];
        return in_array($real, $mimeEquivalences[$declaredMime] ?? [$declaredMime], true);
    }

    private static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'audio/webm' => 'webm',
            'audio/ogg'  => 'ogg',
            'audio/mp4', 'audio/x-m4a' => 'm4a',
            'audio/mpeg' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/aac'  => 'aac',
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'message/rfc822' => 'eml',
            default      => 'bin',
        };
    }

    /** MIME whitelist for browser-recorded voice notes (MediaRecorder output varies by browser). */
    public const VOICE_MIMES = [
        'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/x-m4a',
        'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/aac',
    ];

    /** MIME whitelist for dossier attachments (pdf/word/excel/image/email — voir DisputeCase). */
    public const DISPUTE_DOC_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'message/rfc822',
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    ];

    // ── Document classifier ──────────────────────────────────────────────────

    /** Document type definitions: label, icon, color, keywords with weight */
    public static array $types = [
        'identity' => [
            'label' => "Pièce d'identité",
            'icon'  => '🪪',
            'color' => '#4A90D9',
            'keywords' => [
                'carte nationale d\'identité' => 10, 'cni' => 6, 'passeport' => 10,
                'passport' => 10, 'permis de conduire' => 10, 'driving licence' => 8,
                'date de naissance' => 5, 'né le' => 4, 'née le' => 4,
                'nationalité' => 5, 'republic' => 3, 'française' => 2,
                'numéro national' => 6, 'n° national' => 6,
            ],
        ],
        'address' => [
            'label' => 'Justificatif de domicile',
            'icon'  => '🏠',
            'color' => '#27AE60',
            'keywords' => [
                'justificatif de domicile' => 10, 'attestation de domicile' => 10,
                'quittance de loyer' => 10, 'enedis' => 8, 'edf' => 7, 'engie' => 7,
                'suez' => 6, 'veolia' => 6, 'saur' => 6, 'orange' => 5,
                'sfr' => 5, 'bouygues' => 5, 'free' => 4, 'numericable' => 5,
                'facture d\'électricité' => 9, 'facture de gaz' => 9,
                'facture d\'eau' => 9, 'facture internet' => 9,
                'consommation' => 4, 'point de livraison' => 6, 'pdl' => 5,
                'attestation d\'hébergement' => 10, 'hébergé' => 5,
            ],
        ],
        'tax' => [
            'label' => 'Impôts & taxes',
            'icon'  => '🏛️',
            'color' => '#8E44AD',
            'keywords' => [
                'direction générale des finances publiques' => 10, 'dgfip' => 10,
                'avis d\'imposition' => 10, 'revenu fiscal de référence' => 10,
                'impôt sur le revenu' => 9, 'taxe foncière' => 9,
                'taxe d\'habitation' => 9, 'déclaration de revenus' => 8,
                'contribution sociale' => 6, 'trésor public' => 7,
                'numéro fiscal' => 8, 'référence de l\'avis' => 7,
                'montant de l\'impôt' => 8, 'base imposable' => 7,
            ],
        ],
        'payslip' => [
            'label' => 'Bulletin de paie',
            'icon'  => '💼',
            'color' => '#E67E22',
            'keywords' => [
                'bulletin de paie' => 10, 'bulletin de salaire' => 10,
                'fiche de paie' => 10, 'salaire brut' => 9, 'salaire net' => 9,
                'net à payer' => 9, 'cotisations sociales' => 8, 'urssaf' => 8,
                'siret' => 5, 'employeur' => 5, 'salarié' => 5,
                'convention collective' => 6, 'congés payés' => 5,
                'prévoyance' => 4, 'mutuelle' => 4,
            ],
        ],
        'bank' => [
            'label' => 'Relevé bancaire / RIB',
            'icon'  => '🏦',
            'color' => '#2C3E50',
            'keywords' => [
                'relevé de compte' => 10, 'relevé bancaire' => 10,
                'iban' => 8, 'bic' => 6, 'rib' => 8,
                'solde créditeur' => 9, 'solde débiteur' => 9,
                'code banque' => 7, 'code guichet' => 7,
                'numéro de compte' => 7, 'titulaire du compte' => 7,
                'crédit mutuel' => 6, 'bnp' => 5, 'société générale' => 5,
                'caisse d\'épargne' => 5, 'la banque postale' => 5,
                'crédit agricole' => 5, 'lcl' => 5, 'boursorama' => 5,
                'bankin' => 4, 'n26' => 4, 'revolut' => 4,
            ],
        ],
        'insurance' => [
            'label' => 'Assurance',
            'icon'  => '🛡️',
            'color' => '#16A085',
            'keywords' => [
                'attestation d\'assurance' => 10, 'police d\'assurance' => 10,
                'contrat d\'assurance' => 9, 'numéro de police' => 8,
                'sinistre' => 6, 'prime' => 5, 'franchise' => 6,
                'assurance habitation' => 9, 'assurance auto' => 9,
                'assurance vie' => 9, 'assurance santé' => 9,
                'maif' => 7, 'macif' => 7, 'axa' => 7, 'allianz' => 7,
                'groupama' => 7, 'mma' => 7, 'matmut' => 7, 'pacifica' => 7,
                'covéa' => 6, 'april' => 5,
            ],
        ],
        'civil' => [
            'label' => 'État civil',
            'icon'  => '📋',
            'color' => '#7F8C8D',
            'keywords' => [
                'acte de naissance' => 10, 'acte de mariage' => 10,
                'acte de décès' => 10, 'livret de famille' => 10,
                'extrait d\'acte' => 9, 'mairie' => 6, 'état civil' => 8,
                'officier d\'état civil' => 8, 'commune de' => 4,
                'jugement de divorce' => 9, 'tribunal' => 5,
                'copie intégrale' => 7, 'extrait avec filiation' => 8,
            ],
        ],
        'medical' => [
            'label' => 'Médical',
            'icon'  => '🏥',
            'color' => '#E74C3C',
            'keywords' => [
                'ordonnance' => 10, 'prescription médicale' => 10,
                'médecin' => 7, 'docteur' => 6, 'patient' => 5,
                'diagnostic' => 6, 'traitement' => 5, 'pharmacie' => 7,
                'sécurité sociale' => 7, 'cpam' => 8, 'carte vitale' => 9,
                'mutuelle santé' => 7, 'remboursement' => 5,
                'hôpital' => 7, 'clinique' => 6, 'infirmier' => 6,
                'vaccination' => 7, 'carnet de santé' => 8,
            ],
        ],
        'contract' => [
            'label' => 'Contrat',
            'icon'  => '📝',
            'color' => '#D35400',
            'keywords' => [
                'contrat de travail' => 10, 'contrat de bail' => 10,
                'contrat de location' => 10, 'bail d\'habitation' => 10,
                'période d\'essai' => 8, 'loyer mensuel' => 8,
                'locataire' => 7, 'bailleur' => 7, 'preneur' => 6,
                'durée du contrat' => 7, 'résiliation' => 6,
                'cdi' => 8, 'cdd' => 8, 'temps plein' => 5,
                'temps partiel' => 5, 'embauche' => 6,
            ],
        ],
        'diploma' => [
            'label' => 'Diplôme & certificat',
            'icon'  => '🎓',
            'color' => '#F39C12',
            'keywords' => [
                'diplôme' => 10, 'certificat' => 7, 'attestation de réussite' => 9,
                'université' => 7, 'lycée' => 6, 'baccalauréat' => 9,
                'master' => 8, 'licence' => 7, 'doctorat' => 9,
                'mention' => 5, 'félicitations' => 5, 'jury' => 5,
                'formation professionnelle' => 8, 'bts' => 7, 'bep' => 7,
                'cap' => 6, 'dut' => 7, 'iut' => 6,
            ],
        ],
        'invoice' => [
            'label' => 'Facture',
            'icon'  => '🧾',
            'color' => '#95A5A6',
            'keywords' => [
                'facture' => 8, 'invoice' => 8, 'numéro de facture' => 9,
                'tva' => 7, 'h.t.' => 6, 'ttc' => 6,
                'total à payer' => 8, 'montant dû' => 7,
                'bon de commande' => 7, 'devis' => 6,
                'siret' => 4, 'tva intra' => 7,
            ],
        ],
    ];

    /**
     * Classify a document from its OCR text.
     * Returns ['type' => string, 'label' => string, 'icon' => string, 'confidence' => float]
     */
    public static function classify(string $text): array
    {
        if (trim($text) === '') {
            return self::unknown();
        }

        $lower  = mb_strtolower($text);
        $scores = [];

        foreach (self::$types as $typeKey => $typeDef) {
            $score = 0;
            foreach ($typeDef['keywords'] as $kw => $weight) {
                if (str_contains($lower, $kw)) {
                    $score += $weight;
                }
            }
            $scores[$typeKey] = $score;
        }

        arsort($scores);
        $best      = array_key_first($scores);
        $bestScore = $scores[$best];

        if ($bestScore < 6) {
            return self::unknown();
        }

        // Confidence: score relative to max possible (rough)
        $maxScore  = array_sum(array_values(self::$types[$best]['keywords']));
        $confidence = min(1.0, $bestScore / ($maxScore * 0.25));

        return [
            'type'       => $best,
            'label'      => self::$types[$best]['label'],
            'icon'       => self::$types[$best]['icon'],
            'color'      => self::$types[$best]['color'],
            'confidence' => round($confidence, 2),
            'score'      => $bestScore,
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::$types[$type]['label'] ?? 'Autre';
    }

    public static function typeIcon(string $type): string
    {
        return self::$types[$type]['icon'] ?? '📄';
    }

    public static function typeColor(string $type): string
    {
        return self::$types[$type]['color'] ?? '#95A5A6';
    }

    private static function unknown(): array
    {
        return ['type' => 'other', 'label' => 'Autre', 'icon' => '📄', 'color' => '#95A5A6', 'confidence' => 0.0, 'score' => 0];
    }

    // ── Date d'expiration ─────────────────────────────────────────────────────

    /** Mots-clés à proximité desquels une date trouvée est probablement une échéance (plutôt
     *  qu'une date de naissance, d'émission...). Cherchés dans le texte OCR brut (accents
     *  conservés), pas dans la version normalisée servant à classify(). */
    private const EXPIRY_KEYWORDS = [
        "date d'expiration", 'date d\'expiration', 'expire le', 'expire fin',
        "valable jusqu'au", "valable jusqu'en", 'date de validité', 'date limite de validité',
        'fin de validité', 'expiry date', 'valid until', "date d'échéance", 'échéance le',
        "à renouveler avant", 'renouvellement avant',
    ];

    /**
     * Cherche une date d'échéance dans un texte OCR, à proximité d'un mot-clé connu
     * (contrairement à la première date dd/mm/yyyy trouvée n'importe où, qui est bien plus
     * souvent une date de naissance ou d'émission). Renvoie 'Y-m-d' ou null si rien de fiable.
     */
    public static function extractExpiryDate(string $text): ?string
    {
        if (trim($text) === '') return null;
        $lower = mb_strtolower($text);
        $datePattern = '(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})';

        foreach (self::EXPIRY_KEYWORDS as $kw) {
            $pos = mb_strpos($lower, mb_strtolower($kw));
            if ($pos === false) continue;
            // La date suit presque toujours le mot-clé sur la même ligne, à quelques mots —
            // une fenêtre de 80 caractères après le mot-clé couvre ce cas sans déborder sur la
            // ligne suivante d'un document dense.
            $window = mb_substr($text, $pos, mb_strlen($kw) + 80);
            if (preg_match('/' . $datePattern . '/', $window, $m)) {
                $date = self::normalizeDateParts($m[1], $m[2], $m[3]);
                if ($date) return $date;
            }
        }
        return null;
    }

    private static function normalizeDateParts(string $d, string $m, string $y): ?string
    {
        if (strlen($y) === 2) $y = ((int)$y < 50 ? '20' : '19') . $y;
        $d = str_pad($d, 2, '0', STR_PAD_LEFT);
        $m = str_pad($m, 2, '0', STR_PAD_LEFT);
        if (!checkdate((int)$m, (int)$d, (int)$y)) return null;
        return "$y-$m-$d";
    }
}
