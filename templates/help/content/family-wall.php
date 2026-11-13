<?php
return [
    'summary' => 'Un affichage plein écran pour une tablette ou une télé : agenda, tâches, courses et budget, mis à jour tout seul.',
    'who' => ['Membres connectés'],
    'sections' => [
        ['id' => 'ouvrir', 'title' => 'L\'ouvrir', 'html' => <<<'H'
<p>Ouvrez <span class="hg-ui">📺 Écran mural</span> dans la barre de gauche, depuis l'appareil à dédier à cet usage. L'écran s'affiche en plein écran et ne demande plus d'interaction. Pour un écran qui doit rester affiché <strong>sans connexion</strong> à un compte, utilisez plutôt le <a href="{{app_url}}/aide/kiosk">mode kiosque</a>.</p>
H],
        ['id' => 'contenu', 'title' => 'Ce qu\'il affiche', 'html' => <<<'H'
<ul>
<li><strong>Agenda — 7 jours</strong> : les événements d'aujourd'hui et des jours suivants, avec les jours de garde.</li>
<li><strong>Tâches & Courses</strong> : le nombre de tâches restantes et de courses à acheter (« Tout est fait ! », « Liste vide » quand c'est fini).</li>
<li><strong>Budget</strong> : revenus, dépenses et solde du mois, objectifs, dernières dépenses.</li>
<li><strong>Fête du jour</strong> et heure.</li>
<li>Des <strong>minuteurs</strong> prédéfinis, avec une alarme à échéance (touchez l'écran pour l'arrêter).</li>
</ul>
<p>Un compte à rebours (« Actualisation dans 5:00 ») indique la prochaine mise à jour automatique : l'écran se rafraîchit toutes les 5 minutes.</p>
H],
        ['id' => 'minuteurs', 'title' => 'Minuteurs', 'html' => <<<'H'
<p>Les minuteurs se créent dans <em>Réglages › Accès partagés › Minuteurs de l'écran mural</em> : un nom (« Machine à laver ») et une durée en minutes, avec une case pour les afficher sur l'écran mural. Chaque minuteur se démarre d'un bouton ▶ et s'arrête avec ⏹.</p>
<div class="hg-note">Si un minuteur se termine et que personne n'est à la maison (selon la position partagée par les membres et le « Domicile » configuré), une notification push est envoyée au lieu de faire sonner l'alarme dans le vide ; l'alarme se déclenche dès le retour de quelqu'un. Voir <a href="{{app_url}}/aide/kiosk">Écran mural (kiosque)</a>.</div>
H],
        ['id' => 'nuit', 'title' => 'Mode sombre la nuit', 'html' => <<<'H'
<p>Un administrateur peut faire basculer automatiquement l'écran mural en sombre la nuit (horaires fixes, ou du coucher au lever du soleil) dans <em>Réglages › Famille › Mode sombre</em>. Ce réglage n'affecte pas l'application normale : chacun garde son propre thème clair ou sombre.</p>
H],
    ],
];
