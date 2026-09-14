<?php
/**
 * Portfolio des matières — affichage uniquement (lecture seule)
 * ---------------------------------------------------------------
 * Pas de dashboard, pas d'écriture sur le disque (compatible Vercel).
 * Pour ajouter une matière ou un exercice : ajoutez le fichier
 * directement dans public/docs/<matiere>/ puis faites git push.
 *
 * Structure attendue :
 *   api/index.php   (ce fichier)
 *   public/docs/M201/exercice1.pdf
 *   public/docs/M203/...
 *   public/docs/meta.json   (optionnel — noms complets des matières)
 *
 * ⚠️ Si votre structure de dossiers est différente, changez juste
 * la ligne DOCS_DIR ci-dessous pour qu'elle pointe vers public/docs.
 */

define('DOCS_DIR', __DIR__ . '/../public/docs');

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------
function loadMeta(): array {
    $file = DOCS_DIR . '/meta.json';
    if (is_file($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return [];
}

function listMatieres(): array {
    if (!is_dir(DOCS_DIR)) return [];
    $items = [];
    foreach (scandir(DOCS_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = DOCS_DIR . '/' . $entry;
        if (is_dir($full)) {
            $count = 0;
            foreach (scandir($full) as $f) {
                if ($f !== '.' && $f !== '..' && is_file($full . '/' . $f)) $count++;
            }
            $items[] = ['code' => $entry, 'count' => $count];
        }
    }
    usort($items, fn($a, $b) => strcmp($a['code'], $b['code']));
    return $items;
}

/** Empêche toute tentative de sortir du dossier docs/ (path traversal) */
function safeSegment(string $s): string {
    return basename($s);
}

function matiereDir(string $code): ?string {
    $code = safeSegment($code);
    $realDocs = realpath(DOCS_DIR);
    if ($realDocs === false) return null;
    $real = realpath($realDocs . '/' . $code);
    if ($real === false) return null;
    if (strpos($real, $realDocs) !== 0) return null;
    if (!is_dir($real)) return null;
    return $real;
}

function humanSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' o';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' Ko';
    return round($bytes / (1024 * 1024), 1) . ' Mo';
}

// ----------------------------------------------------------------
// Action: téléchargement d'un fichier (lecture seule, pas d'écriture)
// ----------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $dir = matiereDir($_GET['matiere'] ?? '');
    $filename = safeSegment($_GET['file'] ?? '');
    if ($dir === null || $filename === '') {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    $path = $dir . '/' . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

// ----------------------------------------------------------------
// Détermine la vue actuelle
// ----------------------------------------------------------------
$meta = loadMeta();
$currentCode = isset($_GET['matiere']) ? safeSegment($_GET['matiere']) : null;
$currentDir = $currentCode ? matiereDir($currentCode) : null;

$exercices = [];
if ($currentDir !== null) {
    foreach (scandir($currentDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $currentDir . '/' . $f;
        if (is_file($full)) {
            $exercices[] = [
                'name' => $f,
                'size' => humanSize(filesize($full)),
                'ext'  => strtolower(pathinfo($f, PATHINFO_EXTENSION)) ?: 'fichier',
            ];
        }
    }
    usort($exercices, fn($a, $b) => strcmp($a['name'], $b['name']));
}

$matieres = listMatieres();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $currentDir !== null ? htmlspecialchars($currentCode) . ' — ' : '' ?>Portfolio des matières</title>
<style>
  :root{
    --ink:#1C2333;
    --paper:#F7F5F0;
    --paper-alt:#EFEAE0;
    --accent:#8B5E34;
    --accent-2:#4B6355;
    --line:#D8D3C7;
  }
  *{box-sizing:border-box;}
  body{margin:0;background:var(--paper);color:var(--ink);font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.5;}
  h1{font-family:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,serif;font-weight:600;margin:0;}
  a{color:inherit;text-decoration:none;}

  /* ---------- Barre de navigation ---------- */
  nav.topbar{
    position:sticky; top:0; z-index:10;
    background:var(--paper);
    border-bottom:1px solid var(--line);
  }
  nav.topbar .bar{
    max-width:960px;margin:0 auto;padding:18px 24px;
    display:flex;align-items:center;justify-content:space-between;
    gap:16px;flex-wrap:wrap;
  }
  nav.topbar .brand{
    font-family:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,serif;
    font-size:1.15rem;
  }
  nav.topbar .links{display:flex;gap:26px;}
  nav.topbar .links a{font-size:0.92rem;color:#4b4536;}
  nav.topbar .links a:hover{color:var(--accent);}

  header.site{padding:48px 24px 40px;max-width:960px;margin:0 auto;border-bottom:1px solid var(--line);
    display:flex;align-items:center;gap:32px;flex-wrap:wrap;}
  header.site .avatar{
    flex:0 0 auto;
    width:112px;height:112px;
    border-radius:50%;
    object-fit:cover;
    border:1px solid var(--line);
  }
  header.site .intro{flex:1 1 260px;min-width:0;}
  header.site .kicker{font-size:0.85rem;color:var(--accent-2);margin-bottom:10px;}
  header.site h1{font-size:2.6rem;letter-spacing:-0.01em;}
  header.site p{max-width:52ch;color:#4b4536;margin-top:12px;}

  main{max-width:960px;margin:0 auto;padding:40px 24px 80px;}

  .section-block{padding-top:52px;}
  .section-block:first-child{padding-top:0;}
  .section-block h2{
    font-family:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,serif;
    font-size:1.7rem;margin-bottom:20px;
  }

  /* ---------- Projets ---------- */
  .projects-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;}
  .project-card{background:var(--paper-alt);border:1px solid var(--line);padding:22px 20px;}
  .project-card h3{font-size:1.05rem;margin:0 0 8px;font-weight:600;}
  .project-card p{margin:0;color:#4b4536;font-size:0.9rem;}

  /* ---------- Contact ---------- */
  .contact-list{list-style:none;margin:0;padding:0;}
  .contact-list li{padding:10px 0;border-top:1px solid var(--line);display:flex;gap:10px;font-size:0.95rem;}
  .contact-list li:last-child{border-bottom:1px solid var(--line);}
  .contact-list .label{color:#8a8272;min-width:90px;}
  .contact-list a{border-bottom:1px solid var(--accent-2);color:var(--accent-2);}

  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px;}
  .card{position:relative;display:block;background:var(--paper-alt);border:1px solid var(--line);padding:22px 20px 20px;min-height:130px;}
  .card::before{content:"";position:absolute;top:0;left:20px;width:38px;height:8px;background:var(--accent);}
  .card .code{font-family:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,serif;font-size:1.6rem;margin-top:14px;}
  .card .name{color:#4b4536;font-size:0.92rem;margin-top:6px;}
  .card .count{position:absolute;bottom:16px;left:20px;font-size:0.8rem;color:var(--accent-2);}
  .card:hover{border-color:var(--accent);}

  .empty{color:#6b6455;padding:40px 0;}

  .back{display:inline-block;margin-bottom:24px;font-size:0.9rem;color:var(--accent-2);border-bottom:1px solid var(--accent-2);}
  .subject-title{display:flex;align-items:baseline;gap:14px;flex-wrap:wrap;margin-bottom:6px;}
  .subject-title h1{font-size:2.2rem;}
  .subject-title .full-name{color:#6b6455;font-size:1rem;}
  .subject-sub{color:#6b6455;margin-bottom:28px;font-size:0.92rem;}

  ul.exercices{list-style:none;margin:0;padding:0;border-top:1px solid var(--line);}
  ul.exercices li{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 4px;border-bottom:1px solid var(--line);}
  .file-info{display:flex;align-items:baseline;gap:12px;min-width:0;}
  .file-info .ext{flex:0 0 auto;background:var(--ink);color:var(--paper);font-size:0.72rem;padding:3px 7px;}
  .file-info .fname{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .file-info .fsize{flex:0 0 auto;color:#8a8272;font-size:0.85rem;}
  .dl-btn{flex:0 0 auto;border:1px solid var(--ink);padding:8px 16px;font-size:0.88rem;white-space:nowrap;}
  .dl-btn:hover{background:var(--ink);color:var(--paper);}

  footer{max-width:960px;margin:0 auto;padding:24px;color:#9a927e;font-size:0.8rem;text-align:center;}

  @media (max-width:480px){
    nav.topbar .bar{padding:14px 18px;}
    nav.topbar .links{gap:16px;}
    header.site{padding:32px 18px 24px;gap:20px;}
    header.site .avatar{width:84px;height:84px;}
    header.site h1{font-size:2rem;}
    main{padding:28px 18px 60px;}
  }
</style>
</head>
<body>

<nav class="topbar">
  <div class="bar">
    <div class="brand">Mohamed Tarchouli</div>
    <div class="links">
      <a href="index.php#accueil">Accueil</a>
      <a href="index.php#projets">Projets</a>
      <a href="index.php#modules">Modules</a>
      <a href="index.php#contact">Contact</a>
    </div>
  </div>
</nav>

<?php if ($currentDir === null): ?>
<header class="site" id="accueil">
  <!-- LIGNE À MODIFIER : remplacez ce src par le lien (ou chemin) de votre vraie photo -->
  <img class="avatar" src="public\images\1782739568114.png" alt="Photo de Mohamed Tarchouli">
  <div class="intro">
    <div class="kicker">Portfolio scolaire</div>
    <h1>Mes matières</h1>
    <p>Retrouvez ici toutes les matières et leurs exercices, classés par module. Cliquez sur une matière pour voir les fichiers disponibles.</p>
  </div>
</header>
<?php else: ?>
<header class="site">
  <div class="intro">
    <div class="kicker">Portfolio scolaire</div>
    <h1>Exercices</h1>
  </div>
</header>
<?php endif; ?>

<main>
<?php if ($currentDir !== null): ?>

  <a class="back" href="index.php">← Toutes les matières</a>

  <div class="subject-title">
    <h1><?= htmlspecialchars($currentCode) ?></h1>
    <?php if (!empty($meta[$currentCode])): ?>
      <span class="full-name"><?= htmlspecialchars($meta[$currentCode]) ?></span>
    <?php endif; ?>
  </div>
  <div class="subject-sub"><?= count($exercices) ?> fichier<?= count($exercices) > 1 ? 's' : '' ?> disponible<?= count($exercices) > 1 ? 's' : '' ?></div>

  <?php if (empty($exercices)): ?>
    <p class="empty">Aucun exercice n'a encore été ajouté pour cette matière.</p>
  <?php else: ?>
    <ul class="exercices">
      <?php foreach ($exercices as $ex): ?>
        <li>
          <div class="file-info">
            <span class="ext"><?= htmlspecialchars($ex['ext']) ?></span>
            <span class="fname"><?= htmlspecialchars($ex['name']) ?></span>
            <span class="fsize"><?= $ex['size'] ?></span>
          </div>
          <a class="dl-btn" href="index.php?action=download&matiere=<?= urlencode($currentCode) ?>&file=<?= urlencode($ex['name']) ?>">
            Télécharger
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

<?php else: ?>

  <section class="section-block" id="projets">
    <h2>Projets</h2>
    <div class="projects-grid">
      <div class="project-card">
        <h3>Nom du projet</h3>
        <p>Courte description du projet — technologies utilisées et rôle joué.</p>
      </div>
      <div class="project-card">
        <h3>Nom du projet</h3>
        <p>Courte description du projet — technologies utilisées et rôle joué.</p>
      </div>
    </div>
  </section>

  <section class="section-block" id="modules">
    <h2>Modules</h2>
    <?php if (empty($matieres)): ?>
      <p class="empty">Aucune matière n'a encore été ajoutée. Ajoutez un dossier dans public/docs/ puis faites un push.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($matieres as $m): ?>
          <a class="card" href="index.php?matiere=<?= urlencode($m['code']) ?>">
            <div class="code"><?= htmlspecialchars($m['code']) ?></div>
            <div class="name"><?= htmlspecialchars($meta[$m['code']] ?? '') ?></div>
            <div class="count"><?= $m['count'] ?> fichier<?= $m['count'] > 1 ? 's' : '' ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="section-block" id="contact">
    <h2>Contact</h2>
    <ul class="contact-list">
      <li><span class="label">Email</span><a href="mailto:votre.email@exemple.com">votre.email@exemple.com</a></li>
      <li><span class="label">GitHub</span><a href="https://github.com/votre-profil" target="_blank" rel="noopener">github.com/votre-profil</a></li>
      <li><span class="label">LinkedIn</span><a href="https://linkedin.com/in/votre-profil" target="_blank" rel="noopener">linkedin.com/in/votre-profil</a></li>
    </ul>
  </section>

<?php endif; ?>
</main>

<footer>Portfolio scolaire</footer>
</body>
</html>
