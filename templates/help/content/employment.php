<?php
return [
    'summary' => 'Le suivi d\'un salarié : horaires, congés et RTT, absences, arrêts de travail et estimation de paie.',
    'who' => ['Administrateur et membres adultes', 'Non accessible aux ados'],
    'sections' => [
        ['id' => 'avertissement', 'title' => 'À savoir', 'html' => <<<'H'
<div class="hg-warn">Ce module fournit une <strong>estimation personnelle</strong> (congés, heures, paie), pas un bulletin de paie officiel ni un document légal. Vérifiez toujours avec votre vrai bulletin de paie.</div>
H],
        ['id' => 'profil', 'title' => 'Créer un profil salarié', 'html' => <<<'H'
<p>Cliquez sur <span class="hg-ui">+</span> (ou <span class="hg-ui">+ Nouveau profil</span>) puis renseignez :</p>
<ul>
<li><strong>Membre de la famille</strong> (obligatoire) ;</li>
<li><strong>Employeur</strong> : saisissez le <strong>SIREN</strong> (9 chiffres) et <span class="hg-ui">🔍 Rechercher</span> pour retrouver le nom et l'adresse, ou renseignez-les à la main ;</li>
<li><strong>Contrat</strong> : intitulé du poste, type (<em>CDI</em>, <em>CDD</em>, <em>Temps partiel</em>, <em>Apprentissage</em>, <em>Autre</em>), date d'embauche, fin de période d'essai, couleur ;</li>
<li><strong>Rémunération</strong> : mode (<em>Taux horaire</em> ou <em>Salaire mensuel fixe</em>), taux horaire brut ou salaire mensuel brut, heures contractuelles par semaine, seuil des heures supplémentaires de tranche 2 et majorations de tranches 1 et 2 ;</li>
<li><strong>Congés & RTT</strong> : jour et mois de remise à zéro annuelle, congés acquis par mois, RTT par an ;</li>
<li><strong>Estimation de paie</strong> : taux de cotisations salariales et taux de prélèvement à la source.</li>
</ul>
<p>✏️ Modifier et 🗑 Supprimer sont disponibles sur la fiche.</p>
H],
        ['id' => 'planning', 'title' => '📅 Planning', 'html' => <<<'H'
<p>Saisissez l'<strong>horaire hebdomadaire récurrent</strong> (avec la pause en minutes) puis <span class="hg-ui">Enregistrer le planning</span>. Pour une date précise (heure supplémentaire, jour chômé…), ajoutez un <strong>correctif jour par jour</strong> : date, heures travaillées et note ; 0 h signifie jour non travaillé ce jour-là.</p>
H],
        ['id' => 'conges', 'title' => '🏖️ Congés & RTT', 'html' => <<<'H'
<p>Les compteurs <strong>Acquis</strong>, <strong>Pris</strong>, <strong>Ajustements</strong> et <strong>Solde</strong> sont calculés depuis la remise à zéro annuelle, pour les congés payés et pour les RTT. Les congés se posent depuis le <a href="{{app_url}}/aide/calendar">Calendrier</a> : cochez « Congé / absence salarié » puis « Congé payé » sur un événement ; ils apparaissent ici automatiquement, en ne déduisant que les jours normalement travaillés. Utilisez les <strong>ajustements manuels</strong> (date, jours +/-, note) pour un report ou une régularisation.</p>
H],
        ['id' => 'absences', 'title' => '📋 Absences et 🏥 arrêts de travail', 'html' => <<<'H'
<p>Les <strong>absences non payées</strong> se saisissent depuis le Calendrier (« Congé / absence salarié » puis « Absence non payée »). Pour un <strong>arrêt de travail</strong> : <strong>Début</strong>, <strong>Fin</strong>, <strong>Motif</strong>, <strong>IJSS reçues (€)</strong>, <strong>Complément employeur (€)</strong> et <strong>Notes</strong>, puis <span class="hg-ui">+ Ajouter l'arrêt de travail</span>. Pour joindre l'arrêt lui-même, envoyez-le dans Documents puis liez-le depuis l'onglet « Documents ».</p>
H],
        ['id' => 'paie', 'title' => '💶 Estimation de paie', 'html' => <<<'H'
<ol>
<li>Choisissez le <strong>Mois</strong> et l'<strong>Année</strong>, ajoutez éventuellement des <strong>primes</strong> (libellé + montant).</li>
<li>Cliquez sur <span class="hg-ui">⚙️ Calculer</span>.</li>
</ol>
<p>Le résultat détaille : heures travaillées, salaire de base, heures supplémentaires, primes, <strong>total brut</strong>, cotisations estimées, <strong>net social estimé</strong>, prélèvement à la source estimé et <strong>net estimé à verser</strong>. <span class="hg-ui">⬇️ Télécharger le PDF</span> exporte le calcul ; l'<strong>Historique</strong> conserve les mois précédents.</p>
H],
        ['id' => 'docs', 'title' => '📎 Documents', 'html' => <<<'H'
<p>Bulletins de paie, contrat, arrêts scannés : envoyez-les dans le module <a href="{{app_url}}/aide/documents">Documents</a> puis liez-les à la fiche depuis cet onglet.</p>
H],
    ],
];
