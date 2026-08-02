<?php
/**
 * @var string $placement 'dashboard'|'module_pages'|'coparent'
 * Inclusion volontairement silencieuse (rien n'est affiché s'il n'y a rien à montrer).
 */
$_spotlight = \App\Models\AbhdHighlight::pickForPlacement($placement);
if ($_spotlight):
?>
<a class="abhd-spotlight" href="<?= BASE_URL ?>/highlights/<?= (int)$_spotlight['id'] ?>/go" target="_blank" rel="noopener noreferrer">
    <?php if ($_spotlight['image_path']): ?>
        <img class="abhd-spotlight-img" src="<?= htmlspecialchars(BASE_URL . $_spotlight['image_path']) ?>" alt="<?= htmlspecialchars($_spotlight['title']) ?>">
    <?php endif; ?>
    <span class="abhd-spotlight-footer">
        <span class="abhd-spotlight-text">
            <strong class="abhd-spotlight-title"><?= htmlspecialchars($_spotlight['title']) ?></strong>
            <?php if ($_spotlight['description']): ?>
                <span class="abhd-spotlight-desc"><?= htmlspecialchars($_spotlight['description']) ?></span>
            <?php endif; ?>
        </span>
        <span class="abhd-spotlight-tag"><?= htmlspecialchars(parse_url($_spotlight['url'], PHP_URL_HOST) ?: $_spotlight['url']) ?></span>
    </span>
</a>
<?php endif; unset($_spotlight); ?>
