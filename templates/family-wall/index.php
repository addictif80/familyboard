<?php
/* Standalone fullscreen page — does NOT use templates/layout.php */
$jours  = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$moisFr = ['','jan.','fév.','mar.','avr.','mai','juin','juil.','août','sep.','oct.','nov.','déc.'];
$moisLong = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$todayStr = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?= htmlspecialchars($family['name'] ?? 'FamilyBoard') ?> — Écran mural</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/app.css?v=<?= APP_VERSION ?>">
    <style>
    /* ── Wall-mode overrides ──────────────────────────────────── */
    html, body { height: 100%; overflow: hidden; font-size: 15px; }

    .fw-wrap {
        display: flex;
        flex-direction: column;
        height: 100vh;
        background: var(--bg);
    }

    /* ── Blue header banner ── */
    .fw-banner {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: #fff;
        padding: .9rem 1.75rem;
        display: flex;
        align-items: center;
        gap: 2rem;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(74,144,217,.3);
    }

    .fw-banner-family {
        display: flex;
        align-items: center;
        gap: .45rem;
        font-size: 1rem;
        font-weight: 600;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .fw-banner-center {
        flex: 1;
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 1.1rem;
    }

    .fw-clock {
        font-size: 3rem;
        font-weight: 700;
        letter-spacing: -.025em;
        font-variant-numeric: tabular-nums;
        line-height: 1;
    }

    .fw-clock-sep { opacity: .6; animation: blink-colon .8s step-end infinite; }
    @keyframes blink-colon { 0%, 100% { opacity: .6; } 50% { opacity: .15; } }

    .fw-date {
        font-size: 1rem;
        opacity: .85;
        font-weight: 400;
    }

    .fw-banner-right {
        display: flex;
        align-items: center;
        gap: 1.1rem;
        flex-shrink: 0;
    }

    .fw-weather {
        display: none;
        align-items: center;
        gap: .4rem;
        font-size: 1rem;
        font-weight: 500;
        background: rgba(255,255,255,.15);
        padding: .3rem .75rem;
        border-radius: 20px;
    }

    .fw-weather-city { font-size: .72rem; opacity: .75; }

    .fw-nameday {
        font-size: .9rem;
        font-weight: 500;
        background: rgba(255,255,255,.15);
        padding: .3rem .75rem;
        border-radius: 20px;
        white-space: nowrap;
    }

    .fw-exit {
        color: rgba(255,255,255,.7);
        text-decoration: none;
        font-size: .8rem;
        padding: .3rem .7rem;
        border: 1px solid rgba(255,255,255,.3);
        border-radius: 7px;
        transition: all .15s;
        white-space: nowrap;
    }
    .fw-exit:hover { color: #fff; border-color: rgba(255,255,255,.7); background: rgba(255,255,255,.1); }

    /* ── Minuteurs ── */
    .fw-timers {
        display: flex; flex-wrap: wrap; gap: .6rem;
        padding: .75rem 1.25rem 0;
        flex-shrink: 0;
    }
    .fw-timer {
        display: flex; align-items: center; gap: .5rem;
        background: var(--card-bg); border: 1px solid var(--border); border-radius: 10px;
        padding: .5rem .8rem;
        box-shadow: var(--shadow);
    }
    .fw-timer.running { border-color: var(--primary); }
    .fw-timer.alarming {
        border-color: #E74C3C; background: rgba(231,76,60,.12);
        animation: fw-timer-pulse 1s ease-in-out infinite;
    }
    @keyframes fw-timer-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(231,76,60,.5); } 50% { box-shadow: 0 0 0 8px rgba(231,76,60,0); } }
    .fw-timer-label { font-weight: 600; font-size: .88rem; }
    .fw-timer-value { font-variant-numeric: tabular-nums; color: var(--text-muted); font-size: .85rem; min-width: 3.2em; }
    .fw-timer.alarming .fw-timer-value { color: #E74C3C; font-weight: 700; }
    .fw-timer-btn {
        border: none; border-radius: 50%; width: 30px; height: 30px;
        background: var(--primary); color: #fff; font-size: .85rem; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .fw-timer.alarming .fw-timer-btn.fw-timer-stop { background: #E74C3C; }

    /* ── Alarme plein écran ── */
    .fw-alarm-overlay {
        position: fixed; inset: 0; z-index: 999;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 1rem; cursor: pointer;
        background: #C0392B;
        animation: fw-alarm-flash .6s step-start infinite;
    }
    @keyframes fw-alarm-flash { 50% { background: #1a1a1a; } }
    .fw-alarm-icon { font-size: 6rem; }
    .fw-alarm-label { font-size: 3rem; font-weight: 800; color: #fff; text-align: center; padding: 0 2rem; }
    .fw-alarm-hint { font-size: 1.2rem; color: rgba(255,255,255,.85); }

    /* ── Card grid ── */
    .fw-grid {
        display: grid;
        grid-template-columns: 2fr 1.6fr 1.15fr;
        gap: 1rem;
        padding: 1rem 1.25rem;
        flex: 1;
        min-height: 0;
        overflow: hidden;
        align-items: stretch;
    }

    /* Cards that fill available height */
    .fw-card {
        background: var(--card-bg);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-height: 0;
    }

    .fw-card-head {
        padding: .85rem 1.1rem .6rem;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .fw-card-title {
        font-size: .8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--text-muted);
    }

    .fw-card-body {
        padding: .75rem 1.1rem;
        flex: 1;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--border) transparent;
    }
    .fw-card-body::-webkit-scrollbar { width: 4px; }
    .fw-card-body::-webkit-scrollbar-track { background: transparent; }
    .fw-card-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

    /* ── Agenda ── */
    .fw-day { margin-bottom: .9rem; }

    .fw-day-sep { height: 1px; background: var(--border); margin: .65rem 0; }

    .fw-day-label {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .82rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: .35rem;
    }
    .fw-day-label.fw-today { color: var(--text); font-size: .9rem; }

    .fw-today-chip {
        background: var(--primary);
        color: #fff;
        font-size: .6rem;
        padding: .08rem .45rem;
        border-radius: 20px;
        font-weight: 700;
    }

    .fw-ev {
        display: flex;
        align-items: flex-start;
        gap: .45rem;
        padding: .28rem .35rem;
        border-radius: 7px;
        font-size: .82rem;
        background: var(--bg);
        margin-bottom: 2px;
    }

    .fw-ev-bar { width: 3px; border-radius: 2px; align-self: stretch; min-height: 14px; flex-shrink: 0; }
    .fw-ev-time { font-size: .72rem; color: var(--text-muted); min-width: 36px; flex-shrink: 0; padding-top: 1px; }
    .fw-ev-title { color: var(--text); flex: 1; }

    .fw-custody {
        display: flex;
        align-items: center;
        gap: .4rem;
        padding: .22rem .35rem;
        border-radius: 7px;
        font-size: .78rem;
        color: var(--text-muted);
        margin-bottom: 2px;
    }
    .fw-custody-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .fw-custody-lbl strong { color: var(--text); font-weight: 500; }

    .fw-day-empty { font-size: .75rem; color: var(--text-muted); padding: .15rem .35rem; font-style: italic; }

    /* ── Tasks / Shopping ── */
    .fw-list { margin-bottom: 1rem; }

    .fw-list-head {
        display: flex;
        align-items: center;
        gap: .42rem;
        font-size: .84rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: .4rem;
    }

    .fw-list-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .fw-list-count { margin-left: auto; font-size: .68rem; color: var(--text-muted); font-weight: 400; }

    .fw-task {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .3rem .35rem;
        border-radius: 7px;
        cursor: pointer;
        transition: background .12s;
        user-select: none;
        font-size: .84rem;
    }
    .fw-task:hover { background: var(--bg); }

    .fw-task-cb {
        width: 17px;
        height: 17px;
        border-radius: 4px;
        border: 1.5px solid var(--border);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .58rem;
        color: #fff;
        transition: all .12s;
    }
    .fw-task.done .fw-task-cb { background: var(--success); border-color: var(--success); }

    .fw-task-lbl { color: var(--text); flex: 1; }
    .fw-task.done .fw-task-lbl { text-decoration: line-through; color: var(--text-muted); }
    .fw-task-due { font-size: .67rem; color: var(--warning); flex-shrink: 0; }

    .fw-all-done { font-size: .8rem; color: var(--success); padding: .2rem .35rem; }
    .fw-empty-list { font-size: .8rem; color: var(--text-muted); padding: .2rem .35rem; font-style: italic; }

    .fw-subsep {
        height: 1px;
        background: var(--border);
        margin: .85rem 0 .7rem;
    }
    .fw-sub-title {
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: .55rem;
    }

    /* ── Budget ── */
    .fw-budget-month {
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: .6rem;
    }

    .fw-brow {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .35rem 0;
        font-size: .88rem;
        border-bottom: 1px solid var(--border);
    }
    .fw-brow:last-of-type { border-bottom: none; }
    .fw-brow-lbl { color: var(--text-muted); }
    .fw-brow-val { font-weight: 600; font-variant-numeric: tabular-nums; }
    .fw-brow-val.pos { color: var(--success); }
    .fw-brow-val.neg { color: var(--danger); }

    .fw-balance {
        margin: .75rem 0;
        padding: .7rem .9rem;
        border-radius: 9px;
        text-align: center;
    }
    .fw-balance.pos { background: rgba(46,204,113,.08); border: 1px solid rgba(46,204,113,.2); }
    .fw-balance.neg { background: rgba(231,76,60,.08);  border: 1px solid rgba(231,76,60,.2); }
    .fw-balance-lbl { font-size: .65rem; color: var(--text-muted); margin-bottom: .15rem; }
    .fw-balance-amt { font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .fw-balance-amt.pos { color: var(--success); }
    .fw-balance-amt.neg { color: var(--danger); }

    .fw-expenses-title {
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin: .75rem 0 .45rem;
    }

    .fw-expense {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .3rem 0;
        border-bottom: 1px solid var(--border);
        font-size: .83rem;
    }
    .fw-expense:last-child { border-bottom: none; }
    .fw-expense-cat { font-size: .75rem; flex-shrink: 0; }
    .fw-expense-title { flex: 1; color: var(--text); }
    .fw-expense-amount { font-weight: 600; color: var(--danger); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fw-expense-date { font-size: .68rem; color: var(--text-muted); }

    .fw-goal { margin-bottom: .65rem; }
    .fw-goal-hd { display: flex; justify-content: space-between; font-size: .8rem; margin-bottom: .25rem; }
    .fw-goal-name { color: var(--text); font-weight: 500; }
    .fw-goal-pct  { color: var(--text-muted); font-size: .73rem; }
    .fw-goal-bar  { height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; }
    .fw-goal-fill { height: 100%; border-radius: 3px; }

    /* ── Footer ── */
    .fw-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .4rem;
        padding: .25rem 1.25rem;
        border-top: 1px solid var(--border);
        background: var(--card-bg);
        flex-shrink: 0;
    }
    .fw-footer-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--success); animation: blink-dot 2.5s ease-in-out infinite; }
    @keyframes blink-dot { 0%, 100% { opacity: 1; } 50% { opacity: .2; } }
    .fw-footer-txt { font-size: .65rem; color: var(--text-muted); }

    /* ── Cursor hide ── */
    body.no-cursor, body.no-cursor * { cursor: none !important; }

    /* ── Responsive ── */
    @media (max-width: 960px) {
        .fw-grid { grid-template-columns: 1.5fr 1.5fr; overflow-y: auto; }
        .fw-card:nth-child(3) { grid-column: 1 / -1; min-height: 200px; }
    }
    @media (max-width: 600px) {
        .fw-grid { grid-template-columns: 1fr; overflow-y: auto; }
        .fw-clock { font-size: 2rem; }
        .fw-date { display: none; }
        .fw-nameday { display: none; }
    }
    </style>
</head>
<body>
<div class="fw-wrap">

    <!-- ══ HEADER BANNER ═══════════════════════════════════════ -->
    <header class="fw-banner">
        <div class="fw-banner-family">
            🏠 <?= htmlspecialchars($family['name'] ?? 'Famille') ?>
        </div>

        <div class="fw-banner-center">
            <div class="fw-clock">
                <span id="fwH">--</span><span class="fw-clock-sep">:</span><span id="fwM">--</span>
            </div>
            <div class="fw-date" id="fwDate">Chargement…</div>
        </div>

        <div class="fw-banner-right">
            <?php if ($todayNameDay): ?>
            <div class="fw-nameday">🎉 Fête : <?= htmlspecialchars($todayNameDay) ?></div>
            <?php endif; ?>
            <div class="fw-weather" id="fwWeather">
                <span id="fwWIcon"></span>
                <span id="fwWTemp"></span>
                <span class="fw-weather-city" id="fwWCity"></span>
            </div>
            <a href="<?= BASE_URL ?>/" class="fw-exit">← Retour</a>
        </div>
    </header>

    <!-- ══ MINUTEURS ═══════════════════════════════════════════ -->
    <?php if (!empty($timers)): ?>
    <div class="fw-timers" id="fwTimers">
        <?php foreach ($timers as $t): ?>
        <div class="fw-timer<?= $t['run_id'] ? ' running' : '' ?>"
             id="fw-timer-<?= (int)$t['id'] ?>"
             data-timer-id="<?= (int)$t['id'] ?>"
             data-duration-min="<?= (int)$t['duration_minutes'] ?>"
             data-label="<?= htmlspecialchars($t['label']) ?>"
             <?php if ($t['run_id']): ?>
             data-run-id="<?= (int)$t['run_id'] ?>"
             data-ends-at="<?= htmlspecialchars($t['ends_at']) ?>"
             data-held="<?= $t['held_for_return'] ? '1' : '0' ?>"
             <?php endif; ?>>
            <span class="fw-timer-label"><?= htmlspecialchars($t['label']) ?></span>
            <span class="fw-timer-value"><?= $t['run_id'] ? '--:--' : (int)$t['duration_minutes'] . ' min' ?></span>
            <button type="button" class="fw-timer-btn fw-timer-start" onclick="wallStartTimer(<?= (int)$t['id'] ?>)" <?= $t['run_id'] ? 'style="display:none"' : '' ?>>▶</button>
            <button type="button" class="fw-timer-btn fw-timer-stop" onclick="wallStopTimer(<?= (int)$t['id'] ?>)" <?= $t['run_id'] ? '' : 'style="display:none"' ?>>⏹</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══ ALARME PLEIN ÉCRAN ══════════════════════════════════ -->
    <div id="fwAlarmOverlay" class="fw-alarm-overlay" style="display:none">
        <div class="fw-alarm-icon">⏰</div>
        <div class="fw-alarm-label" id="fwAlarmLabel"></div>
        <div class="fw-alarm-hint">Touchez l'écran pour arrêter</div>
    </div>

    <!-- ══ GRID ════════════════════════════════════════════════ -->
    <div class="fw-grid">

        <!-- ── CARTE 1 : AGENDA ── -->
        <div class="fw-card">
            <div class="fw-card-head">
                <span>📅</span>
                <span class="fw-card-title">Agenda — 7 jours</span>
            </div>
            <div class="fw-card-body">
                <?php $first = true; ?>
                <?php foreach ($byDate as $dateStr => $day): ?>
                <?php
                    $dt      = new DateTime($dateStr);
                    $isToday = ($dateStr === $todayStr);
                    $label   = $jours[(int)$dt->format('w')] . ' ' . (int)$dt->format('j') . ' ' . $moisFr[(int)$dt->format('n')];
                    $hasContent = !empty($day['events']) || !empty($day['custody']);
                ?>
                <?php if (!$first): ?><div class="fw-day-sep"></div><?php endif; ?>
                <?php $first = false; ?>
                <div class="fw-day">
                    <div class="fw-day-label <?= $isToday ? 'fw-today' : '' ?>">
                        <?= htmlspecialchars($label) ?>
                        <?php if ($isToday): ?><span class="fw-today-chip">Aujourd'hui</span><?php endif; ?>
                    </div>
                    <?php foreach ($day['events'] as $ev): ?>
                    <div class="fw-ev">
                        <div class="fw-ev-bar" style="background:<?= htmlspecialchars($ev['color'] ?? '#4A90D9') ?>"></div>
                        <?php if ($ev['is_all_day']): ?>
                        <span class="fw-ev-time" style="font-style:italic;font-size:.68rem">Journée</span>
                        <?php else: ?>
                        <span class="fw-ev-time"><?= substr($ev['start_datetime'], 11, 5) ?></span>
                        <?php endif; ?>
                        <span class="fw-ev-title"><?= htmlspecialchars($ev['title']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($day['custody'] as $cu): ?>
                    <div class="fw-custody">
                        <div class="fw-custody-dot" style="background:<?= htmlspecialchars($cu['parent_color'] ?? '#888') ?>"></div>
                        <span class="fw-custody-lbl">
                            <?php if (!empty($cu['child_name'])): ?>
                                <?= htmlspecialchars($cu['child_name']) ?> : <strong><?= htmlspecialchars($cu['parent_name'] ?? '—') ?></strong>
                            <?php else: ?>
                                Garde <strong><?= htmlspecialchars($cu['parent_name'] ?? '—') ?></strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$hasContent): ?>
                    <div class="fw-day-empty">Rien de prévu</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── CARTE 2 : TÂCHES & COURSES ── -->
        <div class="fw-card">
            <div class="fw-card-head">
                <span>✅</span>
                <span class="fw-card-title">Tâches & Courses</span>
            </div>
            <div class="fw-card-body">
                <?php if (!empty($taskLists)): ?>
                <?php if (!empty($shoppingLists)): ?><div class="fw-sub-title">Tâches</div><?php endif; ?>
                <?php foreach ($taskLists as $list): ?>
                <div class="fw-list">
                    <div class="fw-list-head">
                        <div class="fw-list-dot" style="background:<?= htmlspecialchars($list['color']) ?>"></div>
                        <?= htmlspecialchars($list['name']) ?>
                        <?php if (!empty($list['pending'])): ?>
                        <span class="fw-list-count"><?= count($list['pending']) ?> restante<?= count($list['pending']) > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($list['pending'])): ?>
                    <div class="fw-all-done">✓ Tout est fait !</div>
                    <?php else: ?>
                    <?php foreach ($list['pending'] as $task): ?>
                    <div class="fw-task" id="fwt-<?= $task['id'] ?>" onclick="wallToggle(<?= $task['id'] ?>, this)">
                        <div class="fw-task-cb"></div>
                        <span class="fw-task-lbl"><?= htmlspecialchars($task['title']) ?></span>
                        <?php if ($task['due_date'] && $task['due_date'] < $todayStr): ?>
                        <span class="fw-task-due" title="En retard">⏰</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($shoppingLists)): ?>
                <?php if (!empty($taskLists)): ?><div class="fw-subsep"></div><?php endif; ?>
                <?php if (!empty($taskLists)): ?><div class="fw-sub-title">Courses</div><?php endif; ?>
                <?php if (empty($taskLists)): ?><?php /* no extra header needed */ ?><?php endif; ?>
                <?php foreach ($shoppingLists as $list): ?>
                <div class="fw-list">
                    <div class="fw-list-head">
                        <div class="fw-list-dot" style="background:<?= htmlspecialchars($list['color']) ?>"></div>
                        <?= htmlspecialchars($list['name']) ?>
                        <?php if (!empty($list['pending'])): ?>
                        <span class="fw-list-count"><?= count($list['pending']) ?> article<?= count($list['pending']) > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($list['pending'])): ?>
                    <div class="fw-all-done">✓ Liste vide</div>
                    <?php else: ?>
                    <?php foreach ($list['pending'] as $task): ?>
                    <div class="fw-task" id="fwt-<?= $task['id'] ?>" onclick="wallToggle(<?= $task['id'] ?>, this)">
                        <div class="fw-task-cb"></div>
                        <span class="fw-task-lbl"><?= htmlspecialchars($task['title']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty($taskLists) && empty($shoppingLists)): ?>
                <div class="fw-empty-list">Aucune liste active.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── CARTE 3 : BUDGET ── -->
        <div class="fw-card">
            <div class="fw-card-head">
                <span>💰</span>
                <span class="fw-card-title">Budget</span>
            </div>
            <div class="fw-card-body">
                <?php
                    $inc    = $budget['income'];
                    $exp    = $budget['expenses'];
                    $bal    = $budget['balance'];
                    $bCls   = $bal >= 0 ? 'pos' : 'neg';
                    $mlbl   = $moisLong[(int)date('n')] . ' ' . date('Y');
                ?>
                <div class="fw-budget-month"><?= $mlbl ?></div>
                <div class="fw-brow">
                    <span class="fw-brow-lbl">Revenus</span>
                    <span class="fw-brow-val pos">+<?= number_format($inc, 0, ',', '\u{202F}') ?> €</span>
                </div>
                <div class="fw-brow">
                    <span class="fw-brow-lbl">Dépenses</span>
                    <span class="fw-brow-val neg">−<?= number_format($exp, 0, ',', '\u{202F}') ?> €</span>
                </div>

                <div class="fw-balance <?= $bCls ?>">
                    <div class="fw-balance-lbl">Solde du mois</div>
                    <div class="fw-balance-amt <?= $bCls ?>">
                        <?= ($bal >= 0 ? '+' : '−') . number_format(abs($bal), 0, ',', '\u{202F}') ?> €
                    </div>
                </div>

                <?php if (!empty($goals)): ?>
                <div class="fw-expenses-title">Objectifs</div>
                <?php foreach ($goals as $g): ?>
                <?php $pct = $g['target_amount'] > 0 ? min(100, round($g['current_amount'] / $g['target_amount'] * 100)) : 0; ?>
                <div class="fw-goal">
                    <div class="fw-goal-hd">
                        <span class="fw-goal-name"><?= htmlspecialchars($g['name']) ?></span>
                        <span class="fw-goal-pct"><?= $pct ?>%</span>
                    </div>
                    <div class="fw-goal-bar">
                        <div class="fw-goal-fill" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($g['color'] ?? '#4A90D9') ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($recentExpenses)): ?>
                <div class="fw-expenses-title">Dernières dépenses</div>
                <?php foreach ($recentExpenses as $tx): ?>
                <div class="fw-expense">
                    <span class="fw-expense-cat"><?= htmlspecialchars($tx['category_icon'] ?? '💸') ?></span>
                    <span class="fw-expense-title"><?= htmlspecialchars($tx['title']) ?></span>
                    <span class="fw-expense-date"><?= date('d/m', strtotime($tx['date'])) ?></span>
                    <span class="fw-expense-amount">−<?= number_format((float)$tx['amount'], 0, ',', '\u{202F}') ?> €</span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty($recentExpenses) && $inc == 0 && $exp == 0): ?>
                <div class="fw-empty-list" style="margin-top:.5rem">Aucune transaction ce mois.</div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- .fw-grid -->

    <!-- ══ FOOTER ══════════════════════════════════════════════ -->
    <footer class="fw-footer">
        <div class="fw-footer-dot"></div>
        <span class="fw-footer-txt" id="fwRefresh">Actualisation dans 5:00</span>
    </footer>

</div><!-- .fw-wrap -->

<script>
const BASE_URL     = <?= json_encode(BASE_URL) ?>;
const APP_TIMEZONE = <?= json_encode(APP_TIMEZONE) ?>;
const WALL_WEATHER_CITY = <?= json_encode($weatherCity ?? '') ?>;
</script>
<script src="<?= ASSETS_URL ?>/js/app.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= ASSETS_URL ?>/js/family-wall.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
