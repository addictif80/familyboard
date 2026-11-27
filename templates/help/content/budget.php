<?php
return [
    'summary' => 'Les revenus, dépenses, charges fixes et objectifs d\'épargne de la famille, mois par mois.',
    'who' => ['Administrateur et membres adultes', 'Non accessible aux ados'],
    'sections' => [
        ['id' => 'vue', 'title' => 'La vue du mois', 'html' => <<<'H'
<p>Naviguez de mois en mois avec ‹ et ›. Quatre totaux résument le mois : <strong>Revenus réels</strong>, <strong>Dépenses réelles</strong>, <strong>Solde réel</strong> et <strong>Budget fixe / mois</strong> (vos charges récurrentes). Le <strong>graphique par catégorie</strong> bascule entre <span class="hg-ui">Dépenses</span> et <span class="hg-ui">Revenus</span> et distingue les montants <em>fixes (récurrents)</em> et <em>variables (transactions)</em>. La liste <strong>Par catégorie</strong> détaille chaque poste.</p>
H],
        ['id' => 'transaction', 'title' => 'Enregistrer une transaction', 'html' => <<<'H'
<ol>
<li>Dans <strong>Transactions du mois</strong>, cliquez sur <span class="hg-ui">+ Ajouter</span>.</li>
<li>Choisissez <strong>💸 Dépense</strong> ou <strong>💵 Revenu</strong>.</li>
<li>Renseignez le <strong>Titre</strong>, le <strong>Montant (€)</strong> et la <strong>Date</strong> (obligatoires), puis une <strong>Catégorie</strong> et des <strong>Notes</strong>.</li>
</ol>
<p>La corbeille 🗑 supprime une transaction (confirmation demandée).</p>
H],
        ['id' => 'recurrent', 'title' => '🔁 Dépenses fixes et revenus récurrents', 'html' => <<<'H'
<p>Loyer, salaire, abonnements : saisissez-les une fois. <span class="hg-ui">+ Ajouter</span> ouvre le formulaire : <strong>💸 Prélèvement</strong> ou <strong>💵 Virement</strong>, <strong>Titre</strong>, <strong>Fournisseur / service</strong> (facultatif), <strong>Montant</strong>, <strong>Fréquence</strong> (<em>Mensuelle</em> ou <em>Annuelle</em>), <strong>Jour du mois</strong> (entre 1 et 28), <strong>Mois de renouvellement</strong> pour l'annuel, <strong>Catégorie</strong> et <strong>Membre concerné</strong>.</p>
<p>L'<strong>Alerte e-mail</strong> peut être réglée sur <em>Aucune alerte</em> ou 1, 2, 3, 5 ou 7 jours avant l'échéance. Titre, montant et jour sont obligatoires. ✏️ modifie, 🗑 supprime.</p>
H],
        ['id' => 'objectifs', 'title' => 'Objectifs d\'épargne', 'html' => <<<'H'
<p>Le bouton <span class="hg-ui">+</span> de la section <strong>Objectifs d'épargne</strong> crée un objectif : <strong>Nom</strong> (vacances, voiture…), <strong>Objectif (€)</strong>, <strong>Épargné (€)</strong>, <strong>Échéance</strong> et <strong>Couleur</strong>. Pour faire avancer un objectif, modifiez le montant épargné avec ✏️.</p>
H],
        ['id' => 'categories', 'title' => 'Personnaliser les catégories', 'html' => <<<'H'
<p>Le bouton ⚙️ de <strong>Par catégorie</strong> ouvre la gestion des catégories : ajoutez-en une (nom + emoji) ou supprimez-en avec 🗑. Les opérations sans catégorie apparaissent sous « Sans catégorie ».</p>
H],
        ['id' => 'acces', 'title' => 'Qui voit le budget ?', 'html' => <<<'H'
<p>Les adultes de la famille. Le module n'est <strong>pas accessible aux ados</strong> (voir <a href="{{app_url}}/aide/profils">Profils</a>).</p>
H],
    ],
];
