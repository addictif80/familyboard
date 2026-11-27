<?php
/** Ouverture commune des pages du guide : styles, barre latérale (sommaire + recherche).
 *  Variables attendues : $groups, $currentSlug (ou null sur l'accueil), $user. */
use App\Core\HelpGuide;
?>
<style>
.hg-wrap{display:grid;grid-template-columns:290px minmax(0,1fr);gap:1.5rem;max-width:1180px;margin:1.5rem auto;padding:0 1rem;align-items:start}
.hg-side{position:sticky;top:1rem;max-height:calc(100vh - 2rem);overflow:auto;padding:1rem}
.hg-search{position:relative;margin-bottom:.9rem}
.hg-search input{width:100%;padding:.55rem .7rem;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text)}
.hg-results{position:absolute;left:0;right:0;top:100%;z-index:20;margin-top:.3rem;background:var(--card-bg);border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.18);max-height:60vh;overflow:auto;display:none}
.hg-results a{display:block;padding:.6rem .8rem;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text)}
.hg-results a:last-child{border-bottom:0}
.hg-results small{display:block;color:var(--text-muted);margin-top:.15rem;font-size:.78rem}
.hg-grp{margin:.7rem 0 .2rem;font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--text-muted)}
.hg-link{display:flex;gap:.45rem;align-items:center;padding:.3rem .5rem;border-radius:7px;text-decoration:none;color:var(--text);font-size:.88rem}
.hg-link:hover{background:var(--bg)}
.hg-link.active{background:var(--primary);color:#fff}
.hg-main{padding:1.75rem 2rem;min-width:0}
.hg-main h1{margin:0 0 .3rem;font-size:1.7rem}
.hg-sum{color:var(--text-muted);margin:0 0 1.25rem;font-size:1.02rem}
.hg-main h2{margin:2rem 0 .6rem;font-size:1.25rem;padding-top:.4rem;border-top:1px solid var(--border)}
.hg-main h3{margin:1.2rem 0 .4rem;font-size:1.02rem}
.hg-main p,.hg-main li{line-height:1.6}
.hg-main ul,.hg-main ol{padding-left:1.3rem}
.hg-main li{margin:.25rem 0}
.hg-main kbd,.hg-main .hg-ui{background:var(--bg);border:1px solid var(--border);border-radius:5px;padding:0 .35rem;font-size:.86em;font-family:inherit;white-space:nowrap}
.hg-note,.hg-tip,.hg-warn{border-radius:10px;padding:.7rem 1rem;margin:.9rem 0;font-size:.93rem;border-left:4px solid}
.hg-note{background:rgba(74,144,217,.10);border-color:#4A90D9}
.hg-tip{background:rgba(46,160,67,.10);border-color:#2ea043}
.hg-warn{background:rgba(217,119,6,.12);border-color:#d97706}
.hg-who{display:flex;flex-wrap:wrap;gap:.35rem;margin:.3rem 0 1rem}
.hg-badge{font-size:.74rem;padding:.15rem .55rem;border-radius:99px;background:var(--bg);border:1px solid var(--border);color:var(--text-muted)}
.hg-badge.prem{background:rgba(245,158,11,.15);border-color:#f59e0b;color:#b45309}
.hg-tbl{width:100%;border-collapse:collapse;margin:.8rem 0;font-size:.92rem}
.hg-tbl th,.hg-tbl td{border:1px solid var(--border);padding:.45rem .6rem;text-align:left;vertical-align:top}
.hg-tbl th{background:var(--bg)}
.hg-flow{display:flex;flex-wrap:wrap;align-items:stretch;gap:.4rem;margin:1rem 0}
.hg-flow .st{flex:1 1 150px;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:.6rem .7rem;font-size:.88rem}
.hg-flow .st b{display:block;margin-bottom:.15rem}
.hg-flow .ar{align-self:center;color:var(--text-muted);font-size:1.2rem}
.hg-nav2{display:flex;justify-content:space-between;gap:1rem;margin-top:2.2rem;padding-top:1rem;border-top:1px solid var(--border)}
.hg-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.8rem;margin:.8rem 0 1.4rem}
.hg-card{display:block;padding:.85rem 1rem;border:1px solid var(--border);border-radius:12px;text-decoration:none;color:var(--text);background:var(--card-bg)}
.hg-card:hover{border-color:var(--primary)}
.hg-card b{display:block}
.hg-card span{display:block;color:var(--text-muted);font-size:.82rem;margin-top:.2rem;line-height:1.4}
@media (max-width:860px){.hg-wrap{grid-template-columns:1fr}.hg-side{position:static;max-height:none}.hg-main{padding:1.2rem 1rem}}
</style>
<div class="hg-wrap">
<aside class="card hg-side">
    <div class="hg-search">
        <input type="search" id="hg-q" placeholder="🔎 Rechercher dans le guide…" autocomplete="off" aria-label="Rechercher dans le guide">
        <div class="hg-results" id="hg-results"></div>
    </div>
    <a class="hg-link <?= $currentSlug === null ? 'active' : '' ?>" href="<?= BASE_URL ?>/aide">🧭 Accueil du guide</a>
    <?php foreach ($groups as $g): ?>
        <div class="hg-grp"><?= $g['icon'] ?> <?= htmlspecialchars($g['title']) ?></div>
        <?php foreach ($g['items'] as $slug): $m = HelpGuide::meta($slug); ?>
            <a class="hg-link <?= $currentSlug === $slug ? 'active' : '' ?>" href="<?= BASE_URL ?>/aide/<?= htmlspecialchars($slug) ?>">
                <span><?= $m['icon'] ?></span><span><?= htmlspecialchars($m['title']) ?></span>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>
</aside>
<main class="card hg-main">
