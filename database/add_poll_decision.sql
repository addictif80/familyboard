-- Décision actée : une fois un sondage clos, son option gagnante peut être transformée en un
-- clic en événement calendrier ou en tâche — on garde une trace pour ne pas le refaire par
-- erreur et pour l'afficher ("Décision actée : ...") à la place des boutons.
ALTER TABLE polls ADD COLUMN IF NOT EXISTS decided_as ENUM('event','task') NULL;
