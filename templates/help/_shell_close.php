</main>
</div>
<script>
(function () {
    var q = document.getElementById('hg-q'), box = document.getElementById('hg-results'), t = null;
    if (!q) return;
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    q.addEventListener('input', function () {
        clearTimeout(t);
        var v = q.value.trim();
        if (v.length < 2) { box.style.display = 'none'; return; }
        t = setTimeout(function () {
            fetch('<?= BASE_URL ?>/api/help/search?q=' + encodeURIComponent(v), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var rs = (d && d.results) || [];
                    if (!rs.length) { box.innerHTML = '<div style="padding:.7rem .8rem;color:var(--text-muted);font-size:.88rem">Aucun résultat. Essayez un autre mot.</div>'; box.style.display = 'block'; return; }
                    box.innerHTML = rs.map(function (r) {
                        return '<a href="<?= BASE_URL ?>/aide/' + encodeURIComponent(r.slug) + (r.anchor ? '#' + encodeURIComponent(r.anchor) : '') + '">'
                            + '<strong>' + esc(r.icon) + ' ' + esc(r.chapter) + (r.section ? ' › ' + esc(r.section) : '') + '</strong>'
                            + '<small>' + esc(r.snippet) + '</small></a>';
                    }).join('');
                    box.style.display = 'block';
                }).catch(function () { box.style.display = 'none'; });
        }, 250);
    });
    document.addEventListener('click', function (e) { if (!box.contains(e.target) && e.target !== q) box.style.display = 'none'; });
})();
</script>
