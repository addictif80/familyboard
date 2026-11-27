<?php
return [
    'summary' => 'L\'abonnement de la famille, les modules Premium et le stockage personnel ou familial.',
    'who' => ['Administrateurs (abonnement, stockage familial)', 'Tous les membres (stockage perso)'],
    'sections' => [
        ['id' => 'principe', 'title' => 'Comment ça marche', 'html' => <<<'H'
<p>L'onglet <strong>Réglages › Abonnement</strong> indique le <strong>statut actuel</strong>. Selon la configuration de l'instance, certains modules sont <strong>gratuits</strong> et d'autres <strong>Premium</strong> (marqués ⭐ dans ce guide). Si la facturation n'est pas activée, tous les modules sont accessibles librement.</p>
H],
        ['id' => 'abonner', 'title' => 'S\'abonner', 'html' => <<<'H'
<ol>
<li>Consultez les <strong>paliers disponibles</strong> (prix mensuel et annuel ; chaque palier fixe un nombre maximal de membres, ou aucun).</li>
<li>Choisissez le palier puis <span class="hg-ui">S'abonner</span> (mensuel ou annuel). Le paiement se fait via Stripe.</li>
<li>Une <strong>période d'essai gratuite</strong> peut s'appliquer au premier abonnement : une carte est enregistrée et débitée à la fin de l'essai, sauf résiliation avant. Le jour exact est affiché.</li>
</ol>
<p><span class="hg-ui">Gérer mon abonnement (Stripe)</span> permet de changer de moyen de paiement, de télécharger les factures ou de résilier. Seul un <strong>administrateur</strong> peut gérer l'abonnement.</p>
H],
        ['id' => 'echec', 'title' => 'Paiement en échec ou résiliation', 'html' => <<<'H'
<p>Les modules Premium sont alors bloqués pour toute la famille, mais leurs données sont <strong>conservées jusqu'à une date limite</strong> affichée : réabonnez-vous avant pour les retrouver telles quelles. Passé ce délai, elles sont <strong>supprimées définitivement</strong>.</p>
H],
        ['id' => 'perso', 'title' => 'Mon espace personnel', 'html' => <<<'H'
<p>Chaque membre dispose d'un <strong>espace de stockage personnel</strong> (quota gratuit, plus les add-ons achetés). <span class="hg-ui">☁️ Accéder à mon espace Nextcloud</span> ouvre cet espace avec les mêmes identifiants que l'application, en plus de l'espace partagé de la famille. L'onglet <strong>Stockage</strong> affiche le quota, vos add-ons (<span class="hg-ui">Gérer mes add-ons (Stripe)</span>) et vos demandes.</p>
<h3>Acheter un palier perso</h3>
<ul>
<li>Un <strong>adulte</strong> confirmé achète directement pour lui-même.</li>
<li>Un <strong>mineur</strong> (ou une personne dont la date de naissance n'est pas renseignée) ne peut que <span class="hg-ui">Demander à un adulte</span> : un adulte de la famille voit la demande sous « Demandes en attente » et clique sur <span class="hg-ui">Approuver et payer</span> (ou <span class="hg-ui">Refuser</span>). Rien n'est activé avant la confirmation du paiement.</li>
</ul>
H],
        ['id' => 'famille', 'title' => 'Stockage familial partagé', 'html' => <<<'H'
<p>L'espace partagé de la famille indique l'espace <strong>utilisé</strong> et le quota total. Les administrateurs peuvent acheter un <strong>palier familial</strong> et gérer les add-ons familiaux depuis Stripe.</p>
<div class="hg-note">Après un paiement, le stockage est disponible à la prochaine synchronisation (quelques minutes).</div>
H],
    ],
];
