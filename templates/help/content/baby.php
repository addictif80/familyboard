<?php
return [
    'summary' => 'Le quotidien de bébé (repas, changes, sommeil), le suivi de grossesse, la naissance et les consultations, partagés par tous les proches concernés.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'bebe', 'title' => 'Ajouter un bébé', 'html' => <<<'H'
<ol>
<li>Cliquez sur <span class="hg-ui">+ Nouveau bébé</span> (ou <span class="hg-ui">Ajouter un bébé</span> si la liste est vide).</li>
<li>Saisissez le <strong>Prénom</strong> (obligatoire), puis <span class="hg-ui">Enregistrer</span>.</li>
<li>Sélectionnez le bébé dans la liste déroulante en haut de page.</li>
</ol>
<p>Pour chaque bébé : <span class="hg-ui">Modifier</span> (nom, photo) et <span class="hg-ui">Supprimer</span> (<strong>toutes ses données sont alors perdues</strong>, une confirmation est demandée). Plusieurs bébés peuvent être suivis.</p>
H],
        ['id' => 'quotidien', 'title' => '📊 Suivi quotidien', 'html' => <<<'H'
<p>L'onglet <strong>Suivi quotidien</strong> affiche une frise de la journée. Naviguez avec <span class="hg-ui">‹ Jour préc.</span> et <span class="hg-ui">Jour suiv. ›</span>. Des pastilles résument la journée (nombre de repas, selles, pipis, couches, lavages, sommeil).</p>
<h3>Enregistrer un événement</h3>
<ol>
<li>Appuyez sur un bouton rapide : <span class="hg-ui">🍼 Repas</span>, <span class="hg-ui">💩 Selles</span>, <span class="hg-ui">💧 Pipi</span>, <span class="hg-ui">🧷 Couche</span> ou <span class="hg-ui">🛁 Lavage</span>.</li>
<li>Vérifiez le <strong>Type</strong> et la <strong>Date et heure</strong> (maintenant par défaut).</li>
<li>Pour un repas, indiquez la <strong>Quantité (ml)</strong> ; ajoutez des <strong>Notes</strong> si besoin ; <span class="hg-ui">Enregistrer</span>.</li>
</ol>
<h3>Le sommeil</h3>
<p><span class="hg-ui">😴 Sommeil</span> enregistre un <strong>Début</strong> et une <strong>Fin</strong> (facultative). Si la fin est vide, le sommeil est « en cours 🔴 » ; un bouton <span class="hg-ui">😴 Arrêter le sommeil</span> le termine plus tard.</p>
<p>Chaque ligne de la frise peut être modifiée ou supprimée. Plusieurs personnes peuvent contribuer au suivi du même enfant : tous les adultes voient la même journée.</p>
H],
        ['id' => 'grossesse', 'title' => '🤰 Grossesse', 'html' => <<<'H'
<ol>
<li>Dans l'onglet <strong>Grossesse</strong>, cliquez sur <span class="hg-ui">✏️ Modifier</span>.</li>
<li>Indiquez la <strong>Date des dernières règles (DDR)</strong> et, si vous la connaissez, la <strong>Date prévisionnelle d'accouchement (DPA)</strong>. Si la DPA est laissée vide, elle est calculée automatiquement (DDR + 280 jours).</li>
<li>Ajoutez des <strong>Notes</strong>, puis <span class="hg-ui">Enregistrer</span>.</li>
</ol>
<h3>Consultations prénatales</h3>
<p>Une fois la grossesse enregistrée, <span class="hg-ui">+ Ajouter</span> crée une consultation : <strong>Date et heure</strong>, <strong>Praticien</strong>, <strong>Détails</strong> et <strong>Images (échographies)</strong> avec <span class="hg-ui">+ Ajouter une image</span>. Supprimer une consultation supprime aussi ses images.</p>
H],
        ['id' => 'naissance', 'title' => '🎉 Naissance', 'html' => <<<'H'
<p>L'onglet <strong>Naissance</strong> garde les informations de naissance : <strong>Date de naissance</strong>, <strong>Heure</strong>, <strong>Poids (kg)</strong>, <strong>Taille (cm)</strong> et <strong>Détails</strong> (<span class="hg-ui">✏️ Modifier</span>, puis <span class="hg-ui">Enregistrer</span>). Un résumé s'affiche en haut de page.</p>
H],
        ['id' => 'consultations', 'title' => '🩺 Consultations', 'html' => <<<'H'
<p>L'onglet <strong>Consultations</strong> liste les consultations médicales de bébé : <span class="hg-ui">+ Ajouter</span> avec <strong>Date et heure</strong>, <strong>Praticien</strong> et <strong>Détails</strong>. Chaque consultation peut être modifiée ou supprimée.</p>
H],
        ['id' => 'liste', 'title' => '🎁 Liste de naissance', 'html' => <<<'H'
<ol>
<li>Dans l'onglet <strong>Liste de naissance</strong>, ajoutez des articles : <strong>Lien du produit</strong> (le titre est récupéré automatiquement si vous le laissez vide), <strong>Titre</strong>, <strong>Prix (€)</strong>, <strong>Notes</strong> (taille, couleur…) et une <strong>Photo manuelle</strong> si vous n'avez pas de lien. Il faut au moins un titre ou un lien. <span class="hg-ui">+ Ajouter à la liste</span>.</li>
<li>Cliquez sur <span class="hg-ui">🔗 Copier le lien à partager</span> et envoyez-le à vos proches.</li>
</ol>
<p>Vos proches réservent un cadeau <strong>sans créer de compte</strong> (« Je le prends »). Vous ne voyez jamais ce qui a été réservé : la surprise est garantie. Vous pouvez supprimer un article de la liste.</p>
H],
        ['id' => 'croissance', 'title' => 'Taille et poids', 'html' => <<<'H'
<div class="hg-note">Le suivi de croissance (taille, poids) ne se trouve pas dans ce module : il est dans le module <a href="{{app_url}}/aide/health">Santé</a>, par enfant.</div>
H],
        ['id' => 'baby-sitter', 'title' => 'Avec la baby-sitter', 'html' => <<<'H'
<p>Un <a href="{{app_url}}/aide/sitter">accès baby-sitter</a> montre le dernier repas, le dernier change, le dernier bain et le sommeil en cours enregistrés ici.</p>
H],
    ],
];
