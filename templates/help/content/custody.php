<?php
return [
    'summary' => 'Le planning de garde de vos enfants : alternance automatique, exceptions, vacances, propositions de répartition et accès restreint pour l\'autre parent.',
    'who' => ['Administrateur et membres adultes', 'Les co-parents ont leur propre espace (voir « Espace co-parent »)'],
    'coparent' => [
        'summary' => 'Le calendrier de garde de l\'enfant, en lecture, avec la possibilité de proposer des jours à l\'autre parent.',
        'sections' => [
            ['id' => 'voir', 'title' => 'Consulter et proposer', 'html' => <<<'H'
<p>Depuis votre espace, le bouton <span class="hg-ui">📅 Calendrier de garde</span> affiche le planning et les <strong>Prochains jours de garde</strong>. Le bouton <span class="hg-ui">Proposer des jours</span> permet de suggérer un échange ou un ajustement à l'autre parent. Vous ne modifiez pas directement le planning : c'est l'autre parent qui le gère.</p>
H],
        ],
    ],
    'sections' => [
        ['id' => 'creer', 'title' => 'Créer le planning d\'un enfant', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Enfant</span>.</li>
<li>Choisissez l'<strong>Enfant</strong> dans la liste (fiches déjà connues de la famille) ou <em>+ Nouvel enfant…</em> et saisissez son prénom, puis une <strong>Couleur</strong>.</li>
<li>Choisissez le <strong>Type de récurrence</strong> (voir ci-dessous) ou <em>Aucune (saisie manuelle)</em>.</li>
<li>Renseignez les <strong>deux parents</strong> : un nom et une couleur chacun. Le menu <em>Remplir depuis un membre…</em> reprend un membre de la famille. Les deux noms doivent être différents.</li>
<li>Ajoutez des <strong>Notes</strong> si besoin, puis <span class="hg-ui">Enregistrer</span>.</li>
</ol>
<p>Le bouton ✏️ modifie ensuite le planning ; la suppression d'un planning supprime aussi tous ses événements (confirmation demandée).</p>
H],
        ['id' => 'recurrence', 'title' => 'Les types de périodicité', 'html' => <<<'H'
<table class="hg-tbl">
<tr><th>Type</th><th>Effet</th></tr>
<tr><td>1 jour sur 2</td><td>Les parents alternent chaque jour.</td></tr>
<tr><td>1 semaine sur 2 / 2 semaines sur 2</td><td>Alternance par semaine entière.</td></tr>
<tr><td>1 mois sur 2</td><td>Alternance mensuelle.</td></tr>
<tr><td>1 weekend sur 2</td><td>Week-ends (samedi-dimanche) alternés.</td></tr>
<tr><td>1 weekend sur 2 + 1 jour fixe en semaine</td><td>Comme ci-dessus ; en plus, chaque semaine, le parent qui n'a pas le week-end récupère l'enfant un jour choisi (ex. le mercredi).</td></tr>
<tr><td>1 weekend par mois</td><td>Le 1er samedi du mois.</td></tr>
</table>
<p>Réglages associés : la <strong>Date de début</strong> (premier jour où le parent 1 prend la garde ; pour les week-ends, choisissez un samedi), le <strong>Jour de passation</strong> (jour de la semaine où la garde bascule) et l'<strong>Heure de passage</strong>. L'heure de passage s'applique à tous les passages : un week-end « 1 sur 2 » commence ainsi le vendredi à cette heure et se termine le lundi à cette heure, pas à minuit.</p>
<p>Les périodes récurrentes s'affichent automatiquement dans le calendrier.</p>
H],
        ['id' => 'exceptions', 'title' => 'Exceptions et périodes manuelles', 'html' => <<<'H'
<p><span class="hg-ui">+ Exception de garde</span> modifie une période précise (vacances, échange ponctuel…). Choisissez l'<strong>Enfant</strong>, le <strong>Parent gardien</strong>, la <strong>Date et l'heure d'arrivée</strong>, la <strong>Date et l'heure de départ</strong> et des <strong>Notes</strong>. Cliquez sur une période existante pour la modifier ou la supprimer. Les événements de garde sont aussi visibles dans le <a href="{{app_url}}/aide/calendar">calendrier</a>.</p>
H],
        ['id' => 'vacances', 'title' => '🏖️ Périodes de vacances', 'html' => <<<'H'
<p>Le bouton 🏖️ d'un planning permet de définir des périodes (<strong>Libellé</strong>, <strong>Du</strong>, <strong>Au</strong>) avec une <strong>Répartition</strong> : <em>1 semaine sur 2</em>, <em>2 semaines sur 4</em> ou <em>Semaines paires / impaires</em>, et le parent chez qui la période <strong>Commence</strong>. Une période peut être modifiée ou supprimée.</p>
H],
        ['id' => 'proposition', 'title' => '📅 Proposition de garde', 'html' => <<<'H'
<p>Cet outil aide à préparer une répartition (par exemple pour les vacances) avant de l'appliquer.</p>
<ol>
<li>Cliquez sur <span class="hg-ui">📅 Proposition de garde</span>, choisissez l'enfant et la période (<strong>Du</strong> / <strong>Au</strong>), puis <span class="hg-ui">Générer</span>.</li>
<li>Attribuez les jours à chaque parent un par un, ou avec les raccourcis <span class="hg-ui">Semaine</span>, <span class="hg-ui">Weekend</span>, <span class="hg-ui">Tout</span> (<span class="hg-ui">Effacer tout</span> remet à zéro). Un compteur indique le nombre de jours de chaque parent.</li>
<li><span class="hg-ui">📷 Exporter en image</span> produit une image à envoyer à l'autre parent, ou <span class="hg-ui">✅ Appliquer au calendrier</span> ajoute les blocs de garde. Les événements existants ne sont pas supprimés.</li>
</ol>
H],
        ['id' => 'checklist', 'title' => '📋 À ne pas oublier — transfert du jour', 'html' => <<<'H'
<p>Cette liste (cartable, doudou, médicaments…) n'apparaît <strong>que le jour d'un transfert de garde</strong>, pour ne pas devenir un rappel permanent. Ajoutez un élément avec <span class="hg-ui">+ Ajouter</span> (pour tous les enfants ou pour un seul), cochez-le le jour J ; la croix ✕ retire l'élément de la liste pour tous les prochains transferts.</p>
H],
        ['id' => 'coparent', 'title' => '🔒 Inviter l\'autre parent', 'html' => <<<'H'
<ol>
<li>Cliquez sur 🔒 sur le planning de l'enfant.</li>
<li>Saisissez l'<strong>Email</strong> de l'autre parent et cochez les <strong>Enfants concernés</strong> (au moins un).</li>
<li><span class="hg-ui">Envoyer l'invitation</span>.</li>
</ol>
<p>L'autre parent se connecte avec un <strong>accès limité</strong> au calendrier de garde, aux propositions de garde, au journal parental et aux documents et évènements des enfants sélectionnés, jamais au reste de vos données familiales. Voir <a href="{{app_url}}/aide/coparent-espace">Espace co-parent</a>.</p>
H],
        ['id' => 'journal', 'title' => '📜 Journal d\'activité et partage de dépenses', 'html' => <<<'H'
<p>Le bouton 📜 ouvre l'historique des actions liées à la garde de cet enfant. Si un co-parent est rattaché au planning, un bouton <em>Partager une dépense avec …</em> mène vers le module <a href="{{app_url}}/aide/additions">Additions</a>.</p>
H],
    ],
];
