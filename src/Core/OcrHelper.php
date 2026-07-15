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

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: self::extensionForMime($mimeBase);
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

    private static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'audio/webm' => 'webm',
            'audio/ogg'  => 'ogg',
            'audio/mp4', 'audio/x-m4a' => 'm4a',
            'audio/mpeg' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/aac'  => 'aac',
            default      => 'bin',
        };
    }

    /** MIME whitelist for browser-recorded voice notes (MediaRecorder output varies by browser). */
    public const VOICE_MIMES = [
        'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/x-m4a',
        'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/aac',
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
}
