<?php
/* Standalone fullscreen page — does NOT use templates/layout.php */
$jours  = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$moisFr = ['','jan.','fév.','mar.','avr.','mai','juin','juil.','août','sep.','oct.','nov.','déc.'];
$todayStr = date('Y-m-d');
$monthNames = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($family['name'] ?? 'FamilyBoard') ?> — Écran mural</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --wb:  #0f172a;
        --ws:  rgba(255,255,255,.05);
        --wb2: rgba(255,255,255,.03);
        --wd:  rgba(255,255,255,.07);
        --wt:  #e2e8f0;
        --wm:  #64748b;
        --wdm: #334155;
        --wp:  #4A90D9;
        --wg:  #2ECC71;
        --wr:  #E74C3C;
        --ww:  #F39C12;
    }

    html, body { height: 100%; overflow: hidden; background: var(--wb); color: var(--wt);
        font-family: 'Inter', system-ui, sans-serif; font-size: 15px; line-height: 1.5; }

    /* ── App shell ── */
    .wall { display: flex; flex-direction: column; height: 100vh; }

    /* ── Header ── */
    .wh {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 2rem;
        padding: .6rem 1.75rem;
        background: var(--wb2);
        border-bottom: 1px solid var(--wd);
        flex-shrink: 0;
    }

    .wh-clock { display: flex; align-items: baseline; gap: .5rem; }
    .wh-clock-h { font-size: 2.8rem; font-weight: 700; letter-spacing: -.02em; color: #fff; font-variant-numeric: tabular-nums; }
    .wh-clock-s { font-size: 1.4rem; font-weight: 300; color: var(--wm); font-variant-numeric: tabular-nums; }

    .wh-date { font-size: .95rem; color: var(--wm); }

    .wh-right { display: flex; align-items: center; gap: 1.25rem; }

    .wh-weather { display: none; align-items: center; gap: .45rem; font-size: 1.05rem; font-weight: 500; }
    .wh-weather-sub { font-size: .72rem; color: var(--wm); }

    .wh-exit {
        font-size: .72rem; color: var(--wdm); text-decoration: none;
        padding: .3rem .65rem; border-radius: 6px; border: 1px solid rgba(255,255,255,.09);
        transition: color .2s, border-color .2s;
    }
    .wh-exit:hover { color: var(--wt); border-color: rgba(255,255,255,.3); }

    /* ── Grid ── */
    .wg {
        display: grid;
        grid-template-columns: 2fr 1.55fr 1.1fr;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }

    .wc {
        padding: 1.1rem 1.35rem;
        overflow-y: auto;
        border-right: 1px solid var(--wd);
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,.07) transparent;
    }
    .wc:last-child { border-right: none; }
    .wc::-webkit-scrollbar { width: 3px; }
    .wc::-webkit-scrollbar-track { background: transparent; }
    .wc::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 2px; }

    .wc-title {
        font-size: .62rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
        color: var(--wdm); margin-bottom: .8rem; padding-bottom: .35rem; border-bottom: 1px solid var(--wd);
    }

    /* ── Calendar ── */
    .wd-sep { height: 1px; background: var(--wd); margin: .75rem 0; }

    .wd-label {
        display: flex; align-items: center; gap: .55rem;
        font-size: .8rem; font-weight: 600; color: var(--wm); margin-bottom: .4rem;
    }
    .wd-label.today-row { color: #fff; font-size: .9rem; }

    .wd-today-chip {
        background: var(--wp); color: #fff; font-size: .58rem;
        padding: .08rem .42rem; border-radius: 20px; font-weight: 700; letter-spacing: .05em;
    }

    .wd-events { display: flex; flex-direction: column; gap: 2px; }

    .wd-ev {
        display: flex; align-items: flex-start; gap: .45rem;
        padding: .26rem .38rem; border-radius: 5px; background: var(--ws); font-size: .8rem;
    }
    .wd-ev-bar { width: 3px; align-self: stretch; border-radius: 2px; flex-shrink: 0; min-height: 14px; }
    .wd-ev-time { font-size: .7rem; color: var(--wm); min-width: 34px; flex-shrink: 0; padding-top: 1px; }
    .wd-ev-allday { font-size: .65rem; color: var(--wdm); font-style: italic; min-width: 34px; flex-shrink: 0; padding-top: 2px; }
    .wd-ev-title { color: var(--wt); flex: 1; }

    .wd-custody {
        display: flex; align-items: center; gap: .42rem;
        padding: .24rem .42rem; border-radius: 5px; font-size: .78rem; margin-top: 2px;
        background: var(--wb2);
    }
    .wd-custody-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .wd-custody-lbl { color: var(--wm); }
    .wd-custody-lbl strong { color: var(--wt); font-weight: 500; }

    .wd-empty { font-size: .75rem; color: var(--wdm); padding: .2rem .38rem; font-style: italic; }

    /* ── Tasks / Shopping ── */
    .wl-block { margin-bottom: 1.15rem; }

    .wl-head {
        display: flex; align-items: center; gap: .42rem;
        font-size: .82rem; font-weight: 600; color: var(--wt); margin-bottom: .42rem;
    }
    .wl-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .wl-count { margin-left: auto; font-size: .67rem; color: var(--wdm); font-weight: 400; }

    .wl-task {
        display: flex; align-items: center; gap: .52rem;
        padding: .3rem .35rem; border-radius: 5px; cursor: pointer;
        transition: background .12s; user-select: none;
    }
    .wl-task:hover { background: var(--ws); }

    .wl-cb {
        width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid var(--wdm);
        flex-shrink: 0; display: flex; align-items: center; justify-content: center;
        font-size: .58rem; transition: all .12s; color: #fff;
    }
    .wl-task.done .wl-cb { background: var(--wg); border-color: var(--wg); }
    .wl-task-lbl { font-size: .82rem; color: var(--wt); flex: 1; }
    .wl-task.done .wl-task-lbl { text-decoration: line-through; color: var(--wdm); }
    .wl-task-due { font-size: .67rem; color: var(--ww); flex-shrink: 0; }

    .wl-done { font-size: .75rem; color: var(--wg); padding: .25rem .35rem; }
    .wl-empty { font-size: .75rem; color: var(--wdm); padding: .25rem .35rem; font-style: italic; }

    .wl-sep { height: 1px; background: var(--wd); margin: .9rem 0 .75rem; }

    /* ── Budget ── */
    .wb-box {
        background: var(--ws); border-radius: 9px; padding: .85rem .95rem; margin-bottom: .85rem;
    }
    .wb-month {
        font-size: .6rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
        color: var(--wdm); margin-bottom: .65rem;
    }
    .wb-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: .28rem 0; font-size: .85rem;
    }
    .wb-row:not(:last-child) { border-bottom: 1px solid var(--wd); }
    .wb-lbl { color: var(--wm); }
    .wb-val { font-weight: 600; font-variant-numeric: tabular-nums; }
    .wb-val.pos { color: var(--wg); }
    .wb-val.neg { color: var(--wr); }
    .wb-val.neu { color: var(--wt); }

    .wb-balance {
        border-radius: 8px; padding: .7rem; text-align: center; margin-bottom: 1rem;
    }
    .wb-balance.pos { background: rgba(46,204,113,.07); border: 1px solid rgba(46,204,113,.18); }
    .wb-balance.neg { background: rgba(231,76,60,.07);  border: 1px solid rgba(231,76,60,.18); }
    .wb-bal-lbl  { font-size: .65rem; color: var(--wm); margin-bottom: .15rem; }
    .wb-bal-amt  { font-size: 1.55rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .wb-bal-amt.pos { color: var(--wg); }
    .wb-bal-amt.neg { color: var(--wr); }

    .wb-goal { margin-bottom: .7rem; }
    .wb-goal-hd { display: flex; justify-content: space-between; font-size: .78rem; margin-bottom: .28rem; }
    .wb-goal-name { color: var(--wt); }
    .wb-goal-pct  { color: var(--wm); font-size: .72rem; }
    .wb-goal-bar  { height: 5px; background: rgba(255,255,255,.07); border-radius: 3px; overflow: hidden; }
    .wb-goal-fill { height: 100%; border-radius: 3px; }

    /* ── Footer ── */
    .wf {
        display: flex; align-items: center; justify-content: flex-end; gap: .45rem;
        padding: .28rem .9rem; background: rgba(0,0,0,.25); flex-shrink: 0;
    }
    .wf-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--wg);
        animation: blink 2.5s ease-in-out infinite; }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: .25; } }
    .wf-txt { font-size: .62rem; color: var(--wdm); }

    /* ── Cursor hide ── */
    body.no-cursor, body.no-cursor * { cursor: none !important; }

    /* ── Responsive ── */
    @media (max-width: 900px) {
        .wg { grid-template-columns: 1.6fr 1.4fr; }
        .wc:nth-child(3) { display: none; }
    }
    @media (max-width: 600px) {
        .wg { grid-template-columns: 1fr; }
        .wc:nth-child(2), .wc:nth-child(3) { display: none; }
        .wh-clock-h { font-size: 2rem; }
    }
    </style>
</head>
<body>
<div class="wall">

    <!-- ══ HEADER ══════════════════════════════════════════════ -->
    <header class="wh">
        <div class="wh-clock">
            <span class="wh-clock-h" id="wH">--:--</span>
            <span class="wh-clock-s" id="wS">--</span>
        </div>
        <div class="wh-date" id="wDate">Chargement…</div>
        <div class="wh-right">
            <div class="wh-weather" id="wWeather">
                <span id="wWIcon"></span>
                <span id="wWTemp"></span>
                <span class="wh-weather-sub" id="wWDesc"></span>
            </div>
            <a href="<?= BASE_URL ?>/" class="wh-exit">← Retour</a>
        </div>
    </header>

    <!-- ══ GRID ════════════════════════════════════════════════ -->
    <div class="wg">

        <!-- ── COL 1 : CALENDRIER ── -->
        <div class="wc">
            <div class="wc-title">📅 Agenda — 7 jours</div>
            <?php $first = true; ?>
            <?php foreach ($byDate as $dateStr => $day): ?>
            <?php
                $dt       = new DateTime($dateStr);
                $isToday  = ($dateStr === $todayStr);
                $dayLabel = $jours[(int)$dt->format('w')] . ' ' . (int)$dt->format('j') . ' ' . $moisFr[(int)$dt->format('n')];
                $hasItems = !empty($day['events']) || !empty($day['custody']);
            ?>
            <?php if (!$first): ?><div class="wd-sep"></div><?php endif; ?>
            <?php $first = false; ?>
            <div>
                <div class="wd-label <?= $isToday ? 'today-row' : '' ?>">
                    <?= htmlspecialchars($dayLabel) ?>
                    <?php if ($isToday): ?><span class="wd-today-chip">Aujourd'hui</span><?php endif; ?>
                </div>
                <div class="wd-events">
                    <?php foreach ($day['events'] as $ev): ?>
                    <div class="wd-ev">
                        <div class="wd-ev-bar" style="background:<?= htmlspecialchars($ev['color'] ?? '#4A90D9') ?>"></div>
                        <?php if ($ev['is_all_day']): ?>
                        <span class="wd-ev-allday">Journée</span>
                        <?php else: ?>
                        <span class="wd-ev-time"><?= substr($ev['start_datetime'], 11, 5) ?></span>
                        <?php endif; ?>
                        <span class="wd-ev-title"><?= htmlspecialchars($ev['title']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($day['custody'] as $cu): ?>
                    <div class="wd-custody">
                        <div class="wd-custody-dot" style="background:<?= htmlspecialchars($cu['parent_color'] ?? '#888') ?>"></div>
                        <span class="wd-custody-lbl">
                            <?php if (!empty($cu['child_name'])): ?>
                                <?= htmlspecialchars($cu['child_name']) ?> : <strong><?= htmlspecialchars($cu['parent_name'] ?? '—') ?></strong>
                            <?php else: ?>
                                Garde <strong><?= htmlspecialchars($cu['parent_name'] ?? '—') ?></strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$hasItems): ?>
                    <div class="wd-empty">Rien de prévu</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── COL 2 : TÂCHES + COURSES ── -->
        <div class="wc">
            <?php if (!empty($taskLists)): ?>
            <div class="wc-title">✅ Tâches</div>
            <?php foreach ($taskLists as $list): ?>
            <div class="wl-block">
                <div class="wl-head">
                    <div class="wl-dot" style="background:<?= htmlspecialchars($list['color']) ?>"></div>
                    <?= htmlspecialchars($list['name']) ?>
                    <?php if (!empty($list['pending'])): ?>
                    <span class="wl-count"><?= count($list['pending']) ?> restante<?= count($list['pending']) > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <?php if (empty($list['pending'])): ?>
                <div class="wl-done">✓ Tout est fait !</div>
                <?php else: ?>
                <?php foreach ($list['pending'] as $task): ?>
                <div class="wl-task" id="wt-<?= $task['id'] ?>" onclick="wallToggle(<?= $task['id'] ?>, this)">
                    <div class="wl-cb"></div>
                    <span class="wl-task-lbl"><?= htmlspecialchars($task['title']) ?></span>
                    <?php if ($task['due_date'] && $task['due_date'] < $todayStr): ?>
                    <span class="wl-task-due" title="En retard">⏰</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($shoppingLists)): ?>
            <?php if (!empty($taskLists)): ?><div class="wl-sep"></div><?php endif; ?>
            <div class="wc-title">🛒 Courses</div>
            <?php foreach ($shoppingLists as $list): ?>
            <div class="wl-block">
                <div class="wl-head">
                    <div class="wl-dot" style="background:<?= htmlspecialchars($list['color']) ?>"></div>
                    <?= htmlspecialchars($list['name']) ?>
                    <?php if (!empty($list['pending'])): ?>
                    <span class="wl-count"><?= count($list['pending']) ?> article<?= count($list['pending']) > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <?php if (empty($list['pending'])): ?>
                <div class="wl-done">✓ Liste vide</div>
                <?php else: ?>
                <?php foreach ($list['pending'] as $task): ?>
                <div class="wl-task" id="wt-<?= $task['id'] ?>" onclick="wallToggle(<?= $task['id'] ?>, this)">
                    <div class="wl-cb"></div>
                    <span class="wl-task-lbl"><?= htmlspecialchars($task['title']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if (empty($taskLists) && empty($shoppingLists)): ?>
            <div class="wc-title">✅ Tâches & Courses</div>
            <div class="wl-empty">Aucune liste active.</div>
            <?php endif; ?>
        </div>

        <!-- ── COL 3 : BUDGET ── -->
        <div class="wc">
            <div class="wc-title">💰 Budget</div>
            <?php
                $inc  = $budget['income'];
                $exp  = $budget['expenses'];
                $bal  = $budget['balance'];
                $balCls = $bal >= 0 ? 'pos' : 'neg';
                $mlbl = $monthNames[(int)date('n')] . ' ' . date('Y');
            ?>
            <div class="wb-box">
                <div class="wb-month"><?= $mlbl ?></div>
                <div class="wb-row">
                    <span class="wb-lbl">Revenus</span>
                    <span class="wb-val pos">+<?= number_format($inc, 0, ',', ' ') ?> €</span>
                </div>
                <div class="wb-row">
                    <span class="wb-lbl">Dépenses</span>
                    <span class="wb-val neg">−<?= number_format($exp, 0, ',', ' ') ?> €</span>
                </div>
            </div>
            <div class="wb-balance <?= $balCls ?>">
                <div class="wb-bal-lbl">Solde du mois</div>
                <div class="wb-bal-amt <?= $balCls ?>">
                    <?= ($bal >= 0 ? '+' : '−') . number_format(abs($bal), 0, ',', ' ') ?> €
                </div>
            </div>

            <?php if (!empty($goals)): ?>
            <div class="wc-title" style="margin-top:1rem">🎯 Objectifs</div>
            <?php foreach ($goals as $g): ?>
            <?php $pct = $g['target_amount'] > 0 ? min(100, round($g['current_amount'] / $g['target_amount'] * 100)) : 0; ?>
            <div class="wb-goal">
                <div class="wb-goal-hd">
                    <span class="wb-goal-name"><?= htmlspecialchars($g['name']) ?></span>
                    <span class="wb-goal-pct"><?= $pct ?>% · <?= number_format($g['current_amount'], 0, ',', ' ') ?> / <?= number_format($g['target_amount'], 0, ',', ' ') ?> €</span>
                </div>
                <div class="wb-goal-bar">
                    <div class="wb-goal-fill" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($g['color'] ?? '#4A90D9') ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- .wg -->

    <!-- ══ FOOTER ══════════════════════════════════════════════ -->
    <footer class="wf">
        <div class="wf-dot"></div>
        <span class="wf-txt" id="wRefresh">Actualisation dans 5:00</span>
    </footer>

</div><!-- .wall -->

<script>
const BASE_URL    = <?= json_encode(BASE_URL) ?>;
const APP_TIMEZONE = <?= json_encode(APP_TIMEZONE) ?>;
</script>
<script src="<?= ASSETS_URL ?>/js/app.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= ASSETS_URL ?>/js/family-wall.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
