<?php
return [
    'summary' => 'Le carnet d\'adresses partagé de la famille : médecin, école, artisans, famille élargie, avec numéros d\'urgence.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'ajouter', 'title' => 'Ajouter un contact', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Ajouter un contact</span> (ou <span class="hg-ui">Ajouter le premier contact</span>).</li>
<li>Renseignez au moins le <strong>Prénom</strong> (obligatoire). Les autres champs sont facultatifs : <strong>Nom</strong>, <strong>Téléphone</strong>, <strong>Téléphone 2</strong> (fixe, travail…), <strong>E-mail</strong>, <strong>Adresse postale</strong>, <strong>Date de naissance</strong>, <strong>Notes</strong>.</li>
<li>Choisissez une <strong>Couleur</strong> et, si vous le souhaitez, une photo avec <span class="hg-ui">📷 Choisir une photo</span> (ou en cliquant sur l'avatar).</li>
<li><span class="hg-ui">Enregistrer</span>.</li>
</ol>
<p>Un contact est visible par tous les membres de la famille. Cliquez sur un contact pour ouvrir ses <strong>Détails</strong>, le modifier ou le supprimer.</p>
H],
        ['id' => 'chercher', 'title' => 'Chercher et appeler', 'html' => <<<'H'
<p>Le champ <span class="hg-ui">🔍 Rechercher un contact…</span> filtre la liste au fil de la frappe. Sur mobile, un appui sur un numéro lance l'appel. Un encadré <strong>🚨 Numéros d'urgence</strong> en haut de page regroupe les numéros utiles (appel en un appui).</p>
H],
        ['id' => 'vcard', 'title' => 'Exporter vers votre smartphone', 'html' => <<<'H'
<p><span class="hg-ui">📲 Exporter .vcf</span> télécharge tous vos contacts dans un fichier vCard que vous pouvez importer dans le carnet d'adresses d'un téléphone ou d'un autre service. Le détail d'un contact permet aussi de télécharger sa vCard individuelle.</p>
H],
        ['id' => 'liens', 'title' => 'Liens avec les autres modules', 'html' => <<<'H'
<ul>
<li>Les dates de naissance des contacts alimentent les <strong>anniversaires</strong> du tableau de bord, avec rappel par e-mail 7 jours avant.</li>
<li>Un contact peut apparaître sur une <a href="{{app_url}}/aide/emergency">fiche d'urgence</a> (par exemple le médecin traitant d'un enfant).</li>
<li>Les accès temporaires <a href="{{app_url}}/aide/sitter">baby-sitter</a> et l'<a href="{{app_url}}/aide/kiosk">écran mural</a> affichent une sélection de contacts.</li>
<li>Le jour de la fête d'un prénom est signalé (🎉 Fête) quand elle correspond à un membre ou contact.</li>
</ul>
H],
    ],
];
