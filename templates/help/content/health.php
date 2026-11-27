<?php
return [
    'summary' => 'Le carnet de santé de chaque membre et enfant : vaccins, allergies, traitements, suivi de croissance (taille et poids) et carnet de médecins.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'prerequis', 'title' => 'Avant de commencer', 'html' => <<<'H'
<p>Le module suit des <strong>membres</strong> et des <strong>enfants</strong> de la famille. Si la liste est vide, ajoutez d'abord un enfant ou un membre (Famille / Réglages).</p>
H],
        ['id' => 'carnet', 'title' => '📋 Carnet de santé', 'html' => <<<'H'
<ol>
<li>Choisissez la personne (<strong>Concerne</strong>), le <strong>Type</strong> (<em>Vaccination</em>, <em>Allergie</em>, <em>Traitement</em>, <em>Antécédent</em> ou <em>Autre</em>) et la <strong>Date</strong>.</li>
<li>Saisissez le <strong>Titre</strong> (« DTP rappel », « allergie arachide »…) et des <strong>Détails</strong>.</li>
<li>Réglez un <strong>Rappel</strong> facultatif (date) : un e-mail est envoyé aux membres 30 jours puis 7 jours avant cette date. Puis <span class="hg-ui">+ Ajouter</span>.</li>
</ol>
<p>Le tableau liste Date, Concerne, Type, Titre et Rappel ; 🗑 supprime une entrée.</p>
<h3>💉 Calendrier vaccinal (France)</h3>
<p>Pour un enfant, un menu propose le <strong>calendrier vaccinal</strong> : choisissez un âge et le titre, la date (calculée depuis la date de naissance) et le rappel sont pré-remplis. Vérifiez toujours avec votre médecin : ce calendrier peut évoluer.</p>
H],
        ['id' => 'partage', 'title' => '🔗 Partager avec un médecin', 'html' => <<<'H'
<p>Génère un <strong>lien public et temporaire</strong> vers le carnet de santé d'<strong>une seule personne</strong>, à transmettre par SMS ou e-mail. Choisissez la personne et une <strong>Durée de validité</strong> (7, 14 ou 30 jours), puis <span class="hg-ui">Générer le lien</span> et <span class="hg-ui">Copier</span>. Le lien expire automatiquement ; la date d'expiration est affichée.</p>
H],
        ['id' => 'croissance', 'title' => '📈 Croissance', 'html' => <<<'H'
<p>Choisissez l'<strong>Enfant</strong>, la <strong>Date</strong>, la <strong>Taille (cm)</strong> et le <strong>Poids (kg)</strong>, puis <span class="hg-ui">+ Ajouter une mesure</span>. Les mesures sont listées dans un tableau (Date, Taille, Poids) ; 🗑 supprime une mesure.</p>
H],
        ['id' => 'medecins', 'title' => '🩺 Médecins', 'html' => <<<'H'
<p><span class="hg-ui">+ Nouveau médecin</span> : <strong>Concerne</strong>, <strong>Spécialité</strong> (généraliste, dentiste…), <strong>Nom</strong>, <strong>Téléphone</strong>, <strong>Adresse</strong>, <strong>Prochain rendez-vous</strong>, <strong>Renouvellement d'ordonnance</strong> et <strong>Notes</strong>. Les dates s'affichent sur la fiche (📅 Prochain RDV, 💊 Renouvellement ordonnance).</p>
H],
    ],
];
