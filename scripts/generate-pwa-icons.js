#!/usr/bin/env node
/**
 * Régénère les icônes PWA et les écrans de démarrage iOS (apple-touch-startup-image) à partir
 * de public/icons/icon.svg — à relancer à chaque fois que le logo ou les couleurs de marque
 * changent (voir artefact Phase A "Splash", figé). Aucune dépendance de rastérisation SVG
 * externe : utilise le Chromium déjà fourni par Playwright pour un rendu fidèle.
 *
 * Usage : node scripts/generate-pwa-icons.js
 *
 * Produit :
 *  - public/icons/icon-192.png, icon-512.png (icône PWA/Android)
 *  - public/icons/splash/<appareil>-<orientation>.png        (clair)
 *  - public/icons/splash/<appareil>-<orientation>-dark.png   (sombre)
 *
 * Les couleurs de fond et le logo doivent rester synchronisés avec :
 *  - public/manifest.json (background_color/theme_color)
 *  - templates/layout.php (balises <link rel="apple-touch-startup-image">)
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const ICONS_DIR = path.join(ROOT, 'public/icons');
const SPLASH_DIR = path.join(ICONS_DIR, 'splash');
const SVG = fs.readFileSync(path.join(ICONS_DIR, 'icon.svg'), 'utf8');

// Identiques à templates/layout.php ($__iosSplashDevices) — garder synchronisé.
const DEVICES = [
    ['iphone-se',       375, 667,  2],
    ['iphone-standard', 390, 844,  3],
    ['iphone-pro',      393, 852,  3],
    ['iphone-plus',     414, 736,  3],
    ['iphone-xr-11',    414, 896,  2],
    ['iphone-max',      428, 926,  3],
    ['iphone-pro-max',  430, 932,  3],
    ['ipad',            768, 1024, 2],
    ['ipad-air',        820, 1180, 2],
    ['ipad-pro-11',     834, 1194, 2],
    ['ipad-pro-129',    1024, 1366, 2],
];

const THEMES = {
    light: { bg: '#F4F2FB', ink: '#1A1A2E' },
    dark:  { bg: '#15132A', ink: '#EDEBFB' },
};

function splashHtml(theme) {
    const t = THEMES[theme];
    return `<!doctype html><html><head><meta charset="utf-8"><style>
        *{margin:0;padding:0}
        html,body{width:100%;height:100%;background:${t.bg};display:flex;align-items:center;justify-content:center}
        .mark{width:22vw;max-width:220px;aspect-ratio:1}
    </style></head><body><div class="mark">${SVG}</div></body></html>`;
}

function iconHtml(size) {
    return `<!doctype html><html><head><meta charset="utf-8"><style>
        *{margin:0;padding:0}
        html,body{width:${size}px;height:${size}px}
        svg{display:block;width:${size}px;height:${size}px}
    </style></head><body>${SVG}</body></html>`;
}

async function shoot(page, html, width, height, outFile) {
    await page.setViewportSize({ width, height });
    await page.setContent(html);
    await page.screenshot({ path: outFile });
}

(async () => {
    fs.mkdirSync(SPLASH_DIR, { recursive: true });
    const browser = await chromium.launch();
    const page = await browser.newPage({ deviceScaleFactor: 1 });

    // Icônes PWA/Android
    await shoot(page, iconHtml(192), 192, 192, path.join(ICONS_DIR, 'icon-192.png'));
    await shoot(page, iconHtml(512), 512, 512, path.join(ICONS_DIR, 'icon-512.png'));
    console.log('✓ icon-192.png, icon-512.png');

    // Écrans de démarrage iOS — une image par appareil × orientation × thème
    for (const [name, w, h, ratio] of DEVICES) {
        for (const orientation of ['portrait', 'landscape']) {
            const [mw, mh] = orientation === 'portrait' ? [w, h] : [h, w];
            for (const theme of ['light', 'dark']) {
                const suffix = theme === 'dark' ? '-dark' : '';
                const outFile = path.join(SPLASH_DIR, `${name}-${orientation}${suffix}.png`);
                await shoot(page, splashHtml(theme), mw * ratio, mh * ratio, outFile);
            }
        }
        console.log(`✓ ${name} (portrait + landscape, clair + sombre)`);
    }

    await browser.close();
    console.log('Terminé.');
})();
