<?php
return [
    'summary' => 'Le cloud associé à {{app}} : votre espace Nextcloud (fichiers, agenda, contacts…), vos photos, l\'adresse e-mail famille et le coffre de mots de passe, avec un seul identifiant.',
    'who' => ['Tous les membres'],
    'sections' => [
        ['id' => 'identifiant', 'title' => 'Un seul identifiant', 'html' => <<<'H'
<p>Votre espace Nextcloud s'ouvre avec <strong>le même identifiant et le même mot de passe</strong> que {{app}}. Vous y retrouvez vos fichiers personnels ainsi que l'<strong>espace partagé de votre famille</strong>. Le bouton <span class="hg-ui">☁️ Accéder à mon espace Nextcloud</span> se trouve dans <a href="{{app_url}}/aide/stockage-abonnement">Réglages › Abonnement / Stockage</a> (il n'apparaît que si le service est configuré sur votre instance).</p>
H],
        ['id' => 'nextcloud', 'title' => 'Ce que contient Nextcloud', 'html' => <<<'H'
<ul>
<li><strong>Fichiers</strong> : vos documents, accessibles depuis tous vos appareils ;</li>
<li><strong>Talk</strong> (messages et visio), <strong>Mail</strong>, <strong>Contacts</strong>, <strong>Agenda</strong>, <strong>Notes</strong>, <strong>Deck</strong> (projets en cartes), <strong>Office</strong> (textes, tableurs, présentations en ligne) et <strong>Memories</strong> (photos classées par date).</li>
</ul>
<p>Des applications mobiles existent pour Nextcloud, Talk et Notes. Sur iPhone, contacts et agenda sont intégrés à iOS ; sur Android, l'application DAVx⁵ les synchronise.</p>
<div class="hg-note">Les applications réellement proposées dépendent de la configuration de votre instance : en cas de doute, demandez à l'administrateur.</div>
H],
        ['id' => 'stockage', 'title' => 'Espace de stockage', 'html' => <<<'H'
<p>Chaque membre dispose d'un quota gratuit, extensible par des add-ons ; la famille a aussi un espace partagé. Voir <a href="{{app_url}}/aide/stockage-abonnement">Stockage et abonnement</a>.</p>
H],
        ['id' => 'photos', 'title' => 'Vos photos', 'html' => <<<'H'
<p>Les photos du téléphone peuvent être sauvegardées automatiquement avec l'application de photos proposée par votre instance (par exemple Immich) ; Memories, dans Nextcloud, classe vos photos par date. Pour les albums partagés de la famille, voir <a href="{{app_url}}/aide/albums">Albums</a>.</p>
H],
        ['id' => 'mail', 'title' => 'Adresse e-mail famille', 'html' => <<<'H'
<p>Si votre instance le propose, une <strong>adresse e-mail famille</strong> est créée : tout e-mail envoyé à cette adresse est transmis à chaque membre. Elle s'affiche dans <a href="{{app_url}}/aide/famille">Famille</a>, avec un bouton pour la copier.</p>
H],
        ['id' => 'mdp', 'title' => 'Mots de passe', 'html' => <<<'H'
<p>Le coffre de mots de passe est décrit dans <a href="{{app_url}}/aide/vault_passwords">Mots de passe</a>.</p>
H],
    ],
];
