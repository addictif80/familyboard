// ============================================
// FamilyBoard - Récap annuel (export PowerPoint via PptxGenJS, vendorisé localement)
// ============================================

const MONTH_NAMES_FR = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

async function generateAnnualRecap() {
    const year = document.getElementById('recap-year').value;
    const btn = document.getElementById('recap-generate-btn');
    const status = document.getElementById('recap-status');
    btn.disabled = true;
    status.textContent = 'Préparation des données…';

    try {
        const r = await apiFetch(`${BASE_URL}/api/annual-recap/data?year=${encodeURIComponent(year)}`);
        if (!r.success) {
            status.textContent = r.error || 'Erreur lors de la préparation du récap.';
            btn.disabled = false;
            return;
        }
        status.textContent = 'Génération du PowerPoint…';
        buildPptx(r.stats);
        status.textContent = 'Téléchargement lancé !';
    } catch (e) {
        status.textContent = 'Erreur : ' + (e.message || e);
    } finally {
        btn.disabled = false;
    }
}

function buildPptx(stats) {
    const pptx = new PptxGenJS();
    pptx.defineLayout({ name: 'FB', width: 10, height: 5.63 });
    pptx.layout = 'FB';

    const brand = '4A90D9';
    const dark = '1F2937';
    const muted = '6B7280';

    // Slide 1 — titre
    let slide = pptx.addSlide();
    slide.background = { color: brand };
    slide.addText(`Récap ${stats.year}`, {
        x: 0.5, y: 1.9, w: 9, h: 1, fontSize: 44, bold: true, color: 'FFFFFF', align: 'center',
    });
    slide.addText(stats.family_name, {
        x: 0.5, y: 2.9, w: 9, h: 0.6, fontSize: 20, color: 'FFFFFF', align: 'center',
    });

    // Slide 2 — calendrier
    slide = pptx.addSlide();
    addSlideTitle(slide, '📅 Le calendrier', dark);
    const busiest = stats.events.busiest_month ? MONTH_NAMES_FR[stats.events.busiest_month - 1] : null;
    addBulletList(slide, [
        `${stats.events.total} événement(s) au calendrier familial cette année.`,
        busiest ? `Le mois le plus chargé : ${busiest} (${stats.events.busiest_month_count} événement${stats.events.busiest_month_count > 1 ? 's' : ''}).` : null,
    ].filter(Boolean), muted);

    // Slide 3 — tâches & budget
    slide = pptx.addSlide();
    addSlideTitle(slide, '✅ Tâches & 💰 Budget', dark);
    addBulletList(slide, [
        `${stats.tasks.completed} tâche(s) menée(s) à bien.`,
        `Revenus : ${formatEuro(stats.budget.income)} — Dépenses : ${formatEuro(stats.budget.expense)}.`,
        `Solde sur l'année : ${formatEuro(stats.budget.balance)}.`,
    ], muted);

    // Slide 4 — vie de famille
    slide = pptx.addSlide();
    addSlideTitle(slide, '📸 Vie de famille', dark);
    const bullets = [`${stats.posts.total} publication(s) sur le mur familial, dont ${stats.posts.with_photo} avec photo.`];
    if (stats.birthdays.count > 0) {
        bullets.push(`${stats.birthdays.count} anniversaire(s) suivi(s)${stats.birthdays.names.length ? ' : ' + stats.birthdays.names.join(', ') : ''}.`);
    }
    addBulletList(slide, bullets, muted);

    // Slides photos marquantes
    (stats.photos || []).forEach((p) => {
        const s = pptx.addSlide();
        s.background = { color: '111827' };
        try {
            s.addImage({ data: p.data_url, x: 1, y: 0.4, w: 8, h: 4.3, sizing: { type: 'contain', w: 8, h: 4.3 } });
        } catch (e) {
            // Image corrompue/non supportée — on saute cette diapositive plutôt que de casser tout l'export.
            return;
        }
        if (p.caption) {
            s.addText(p.caption, { x: 0.5, y: 4.9, w: 9, h: 0.5, fontSize: 12, color: 'D1D5DB', align: 'center' });
        }
    });

    // Slide finale
    slide = pptx.addSlide();
    slide.background = { color: brand };
    slide.addText('Merci FamilyBoard 💙', {
        x: 0.5, y: 2.3, w: 9, h: 1, fontSize: 32, bold: true, color: 'FFFFFF', align: 'center',
    });

    pptx.writeFile({ fileName: `Recap-${stats.year}-FamilyBoard.pptx` });
}

function addSlideTitle(slide, text, color) {
    slide.addText(text, { x: 0.5, y: 0.4, w: 9, h: 0.7, fontSize: 28, bold: true, color });
}

function addBulletList(slide, items, color) {
    slide.addText(
        items.map((t) => ({ text: t, options: { bullet: true, breakLine: true } })),
        { x: 0.6, y: 1.5, w: 8.8, h: 3.3, fontSize: 18, color, valign: 'top' }
    );
}

function formatEuro(n) {
    return Number(n).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}
