<?php
return [
    'summary' => 'Rédiger et imprimer des courriers (résiliation, réclamation…) avec destinataire, variables et modèles réutilisables.',
    'who' => ['Administrateur et membres adultes', 'Non accessible aux ados'],
    'sections' => [
        ['id' => 'creer', 'title' => 'Rédiger un courrier', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouveau courrier</span>.</li>
<li>Indiquez le destinataire : <strong>Civilité</strong> (Madame, Monsieur, Société), <strong>Nom</strong>, <strong>Prénom</strong> (non demandé pour une société), <strong>Complément</strong>, <strong>Adresse</strong> et <strong>Code postal et ville</strong>.</li>
<li>Renseignez le <strong>Lieu de rédaction</strong>, la <strong>Date</strong>, l'<strong>Objet</strong> et le <strong>Corps du courrier</strong> (obligatoires : civilité, nom, adresse, code postal et ville, objet, corps).</li>
<li><span class="hg-ui">Enregistrer le courrier</span>.</li>
</ol>
<p>Sur chaque courrier : 👁️ aperçu, ✏️ modifier, 🖨️ imprimer, 🗑️ supprimer. Renseignez l'adresse postale du foyer dans les paramètres de la famille pour qu'elle apparaisse comme expéditeur.</p>
H],
        ['id' => 'variables', 'title' => 'Variables dynamiques', 'html' => <<<'H'
<p>Cliquez sur une variable pour l'insérer dans le corps : les variables fixes (<code>{{civilite}}</code>, <code>{{nom_dest}}</code>, <code>{{prenom_dest}}</code>) sont remplies automatiquement depuis les champs du destinataire. <span class="hg-ui">+ Ajouter une variable</span> crée la vôtre.</p>
H],
        ['id' => 'modeles', 'title' => '📋 Modèles', 'html' => <<<'H'
<p>Pour réutiliser un courrier : saisissez un nom dans <em>Sauvegarder comme modèle</em> puis <span class="hg-ui">Sauver</span>. Pour le reprendre, choisissez-le dans <em>Charger un modèle</em> puis <span class="hg-ui">Charger</span>. Le bouton <span class="hg-ui">📋 Modèles</span> liste les modèles et permet de les supprimer.</p>
H],
    ],
];
