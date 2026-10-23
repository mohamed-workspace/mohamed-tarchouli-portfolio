<?php
/**
 * Portfolio des matières — affichage uniquement (lecture seule)
 * ---------------------------------------------------------------
 * Pas de dashboard, pas d'écriture sur le disque (compatible Vercel).
 * Pour ajouter une matière ou un exercice : ajoutez le fichier
 * directement dans public/docs/<matiere>/ puis faites git push.
 *
 * NOUVEAU : chaque matière peut contenir des sous-dossiers
 * (ex: "uml", "figma"). Ils sont détectés automatiquement et
 * affichés comme des sections séparées sur la page, avec le nom
 * du sous-dossier en titre au-dessus de ses fichiers.
 *
 * Structure attendue :
 *   api/index.php   (ce fichier)
 *   public/docs/M201/exercice1.pdf
 *   public/docs/M202/uml/diagram1.mdj
 *   public/docs/M202/figma/maquette1.png
 *   public/docs/meta.json   (optionnel — noms complets des matières)
 *
 * ⚠️ Si votre structure de dossiers est différente, changez juste
 * la ligne DOCS_DIR ci-dessous pour qu'elle pointe vers public/docs.
 */

define('DOCS_DIR', __DIR__ . '/../public/docs');

// Ordre préféré d'affichage des sous-dossiers (les autres suivent,
// classés alphabétiquement). Ajoutez-en d'autres ici si besoin.
define('SUBFOLDER_ORDER', ['uml', 'figma']);

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

/** Compte récursivement tous les fichiers d'un dossier (tous niveaux). */
function countFilesRecursive(string $dir): int {
    $count = 0;
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = $dir . '/' . $entry;
        if (is_dir($full)) {
            $count += countFilesRecursive($full);
        } elseif (is_file($full)) {
            $count++;
        }
    }
    return $count;
}

function listMatieres(): array {
    if (!is_dir(DOCS_DIR)) return [];
    $items = [];
    foreach (scandir(DOCS_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = DOCS_DIR . '/' . $entry;
        if (is_dir($full)) {
            $items[] = ['code' => $entry, 'count' => countFilesRecursive($full)];
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

/** Résout un sous-dossier (ex: "uml") à l'intérieur d'une matière, en toute sécurité. */
function subfolderDir(string $matiereRealDir, string $sub): ?string {
    $sub = safeSegment($sub);
    $real = realpath($matiereRealDir . '/' . $sub);
    if ($real === false) return null;
    if (strpos($real, $matiereRealDir) !== 0) return null;
    if (!is_dir($real)) return null;
    return $real;
}

function humanSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' o';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' Ko';
    return round($bytes / (1024 * 1024), 1) . ' Mo';
}

/** Liste les fichiers (pas les dossiers) directement dans $dir. */
function filesIn(string $dir): array {
    $files = [];
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $dir . '/' . $f;
        if (is_file($full)) {
            $files[] = [
                'name' => $f,
                'size' => humanSize(filesize($full)),
                'ext'  => strtolower(pathinfo($f, PATHINFO_EXTENSION)) ?: 'fichier',
            ];
        }
    }
    usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $files;
}

/** Liste les noms des sous-dossiers directement dans $dir, dans l'ordre souhaité. */
function subDirNames(string $dir): array {
    $names = [];
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (is_dir($dir . '/' . $entry)) $names[] = $entry;
    }
    $order = SUBFOLDER_ORDER;
    usort($names, function ($a, $b) use ($order) {
        $ia = array_search(strtolower($a), $order);
        $ib = array_search(strtolower($b), $order);
        if ($ia === false) $ia = 999;
        if ($ib === false) $ib = 999;
        if ($ia === $ib) return strcmp($a, $b);
        return $ia <=> $ib;
    });
    return $names;
}

// ----------------------------------------------------------------
// Action: téléchargement d'un fichier (lecture seule, pas d'écriture)
// ----------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $matiereDirReal = matiereDir($_GET['matiere'] ?? '');
    if ($matiereDirReal === null) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }

    $dir = $matiereDirReal;
    if (!empty($_GET['dossier'])) {
        $dir = subfolderDir($matiereDirReal, $_GET['dossier']);
        if ($dir === null) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }
    }

    $filename = safeSegment($_GET['file'] ?? '');
    if ($filename === '') {
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

// $rootExercices  = fichiers directement dans le dossier de la matière
// $subfolders     = [ ['name' => 'uml', 'files' => [...]], ['name' => 'figma', 'files' => [...]] ]
$rootExercices = [];
$subfolders = [];
$totalCount = 0;

if ($currentDir !== null) {
    $rootExercices = filesIn($currentDir);
    $totalCount += count($rootExercices);

    foreach (subDirNames($currentDir) as $subName) {
        $subDirReal = $currentDir . '/' . $subName;
        $subFiles = filesIn($subDirReal);
        $subfolders[] = ['name' => $subName, 'files' => $subFiles];
        $totalCount += count($subFiles);
    }
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
  .card{position:relative;display:flex;flex-direction:column;background:var(--paper-alt);border:1px solid var(--line);padding:22px 20px 20px;height:170px;}
.card::before{content:"";position:absolute;top:0;left:20px;width:38px;height:8px;background:var(--accent);}
.card .code{font-family:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,serif;font-size:1.6rem;margin-top:14px;}
.card .name{
  color:#4b4536;font-size:0.92rem;margin-top:6px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
  overflow:hidden;text-overflow:ellipsis;
}
.card .count{margin-top:auto;padding-top:10px;font-size:0.8rem;color:var(--accent-2);}
.card:hover{border-color:var(--accent);}

  .empty{color:#6b6455;padding:40px 0;}

  .back{display:inline-block;margin-bottom:24px;font-size:0.9rem;color:var(--accent-2);border-bottom:1px solid var(--accent-2);}
  .subject-title{display:flex;align-items:baseline;gap:14px;flex-wrap:wrap;margin-bottom:6px;}
  .subject-title h1{font-size:2.2rem;}
  .subject-title .full-name{color:#6b6455;font-size:1rem;}
  .subject-sub{color:#6b6455;margin-bottom:28px;font-size:0.92rem;}

  /* ---------- Sous-dossiers (UML, FIGMA, ...) ---------- */
  .subfolder-block{margin-top:36px;}
  .subfolder-block h3{
    font-family:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,serif;
    font-size:1.3rem;
    letter-spacing:0.02em;
    text-transform:uppercase;
    color:var(--ink);
    margin:0 0 4px;
    padding-bottom:10px;
    border-bottom:2px solid var(--accent);
    display:inline-block;
  }
  .subfolder-count{color:#8a8272;font-size:0.82rem;margin-bottom:4px;}

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
<style>
  :root{--ink:#101010;--paper:#fbfbf9;--paper-alt:#f1f1ee;--accent:#111;--accent-2:#555;--line:#deded9;--muted:#74746e;--max:1120px}
  html{scroll-behavior:smooth;scroll-padding-top:92px}
  body{background:var(--paper);color:var(--ink);font-family:Inter,"Segoe UI",Arial,sans-serif;line-height:1.65}
  a{color:inherit}
  nav.topbar{background:rgba(251,251,249,.94);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
  nav.topbar .bar{max-width:var(--max);min-height:76px;padding:14px 28px}
  nav.topbar .brand{font-family:inherit;font-size:.96rem;font-weight:750;letter-spacing:-.04em;display:flex;align-items:center;gap:11px}
  nav.topbar .brand:before{content:"MT";display:grid;place-items:center;width:34px;height:34px;background:#111;color:#fff;border-radius:50%;font-size:.68rem;letter-spacing:.02em}
  nav.topbar .links{align-items:center;gap:5px}
  nav.topbar .links a{font-size:.82rem;color:#555;padding:9px 12px;border-radius:99px;transition:background .2s,color .2s}
  nav.topbar .links a:hover{background:#111;color:#fff}
  .nav-icon{width:15px;height:15px;vertical-align:-3px;margin-right:5px;stroke:currentColor;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  header.site{max-width:var(--max);min-height:410px;padding:78px 28px 70px;position:relative;align-items:center;gap:58px;border-bottom:1px solid var(--line);overflow:hidden}
  header.site:after{content:"PORTFOLIO · 2025—2026";position:absolute;right:28px;bottom:20px;color:#999;font-size:.65rem;letter-spacing:.16em}
  header.site .avatar{width:168px;height:168px;border:0;border-radius:50%;filter:grayscale(1);box-shadow:0 0 0 1px var(--line),0 0 0 10px var(--paper),0 0 0 11px var(--line)}
  header.site .intro{flex:1}
  header.site .kicker{font-size:.72rem;color:#555;text-transform:uppercase;letter-spacing:.18em;font-weight:700;margin-bottom:15px}
  header.site h1{font-family:inherit;font-size:clamp(3.4rem,8vw,6.7rem);line-height:.94;letter-spacing:-.075em;font-weight:760;max-width:850px}
  header.site p{font-size:1.05rem;max-width:54ch;color:#5f5f59;margin:23px 0 0}
  main{max-width:var(--max);padding:70px 28px 100px}
  .section-block{padding-top:88px}
  .section-block:first-child{padding-top:0}
  .section-heading{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:28px}
  .section-heading h2,.section-block h2{font-family:inherit;font-size:clamp(1.8rem,4vw,2.55rem);letter-spacing:-.06em;line-height:1.1;margin:0 0 25px;font-weight:700}
  .section-heading h2{margin:0}
  .eyebrow{font-size:.7rem;text-transform:uppercase;letter-spacing:.16em;color:var(--muted)}
  .projects-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
  .project-card{min-height:170px;background:transparent;border:1px solid var(--line);padding:24px;transition:transform .2s,border-color .2s,background .2s}
  .project-card:hover{transform:translateY(-3px);border-color:#111;background:#fff}
  .project-card h3{font-size:1rem;letter-spacing:-.02em}
  .project-card p{color:var(--muted);max-width:48ch}
  .project-mark{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;color:#888;font-size:.7rem;letter-spacing:.12em;text-transform:uppercase}
  .project-mark span:last-child{font-size:1.2rem;color:#111}
  .grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
  .card{height:190px;background:transparent;border:1px solid var(--line);padding:22px 22px 18px;transition:background .2s,color .2s,transform .2s,border-color .2s}
  .card:before{display:none}
  .card:after{content:"↗";position:absolute;right:20px;top:19px;font-size:1.05rem;color:#888;transition:transform .2s,color .2s}
  .card:hover{background:#111;color:#fff;border-color:#111;transform:translateY(-3px)}
  .card:hover:after{transform:translate(2px,-2px);color:#fff}
  .card .code{font-family:inherit;font-size:1.65rem;font-weight:700;letter-spacing:-.06em;margin-top:3px}
  .card .name{color:var(--muted);font-size:.86rem;margin-top:7px}
  .card:hover .name,.card:hover .count{color:#c7c7c2}
  .card .count{color:#777;font-size:.76rem;letter-spacing:.02em}
  .about-layout{display:grid;grid-template-columns:1.2fr .8fr;gap:48px;align-items:start;border-top:1px solid var(--line);padding-top:28px}
  .about-copy{font-size:1.12rem;max-width:58ch;color:#454540;margin:0}
  .about-copy strong{color:#111}
  .focus-list{list-style:none;margin:0;padding:0;border-top:1px solid var(--line)}
  .focus-list li{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid var(--line);font-size:.9rem}
  .focus-list li span:last-child{color:var(--muted);text-align:right}
  .contact-layout{display:grid;grid-template-columns:.8fr 1.2fr;gap:40px;align-items:start}
  .contact-note{font-size:1.02rem;color:#555;margin:0;max-width:36ch}
  .contact-list{border-top:1px solid var(--line)}
  .contact-list li{padding:16px 2px;border-color:var(--line);align-items:center;gap:14px}
  .contact-icon{display:grid;place-items:center;width:34px;height:34px;border:1px solid var(--line);border-radius:50%;color:#111;flex:0 0 auto}
  .contact-icon svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .contact-list .label{min-width:72px;color:#777;font-size:.78rem;text-transform:uppercase;letter-spacing:.08em}
  .contact-list a{margin-left:auto;border:0;color:#111;font-size:.88rem;text-align:right}
  .contact-list a:hover{text-decoration:underline;text-underline-offset:4px}
  .back{margin-bottom:32px;color:#333;border-color:#777}
  .subject-title h1{font-family:inherit;letter-spacing:-.06em}
  .subject-title .full-name,.subject-sub{color:var(--muted)}
  .subfolder-block h3{font-family:inherit;border-bottom:1px solid #111;letter-spacing:.1em}
  ul.exercices li{padding:18px 4px}
  .file-info .ext{background:#111;border-radius:3px}
  .dl-btn{border-radius:99px;padding:8px 16px;transition:background .2s,color .2s}
  footer{max-width:var(--max);border-top:1px solid var(--line);padding:24px 28px;color:#777;letter-spacing:.04em}
  @media(max-width:680px){header.site{min-height:0;padding:58px 22px 65px;gap:32px}header.site .avatar{width:108px;height:108px}header.site h1{font-size:clamp(3rem,12vw,5rem)}main{padding:52px 22px 70px}.section-block{padding-top:66px}.about-layout,.contact-layout{grid-template-columns:1fr;gap:25px}.projects-grid{grid-template-columns:1fr}.contact-list a{max-width:58%;overflow-wrap:anywhere}nav.topbar .bar{padding:12px 18px}nav.topbar .links{gap:0}nav.topbar .links a{font-size:.76rem;padding:8px 7px}.nav-icon{display:none}}
  @media(max-width:420px){nav.topbar .brand{font-size:.84rem}nav.topbar .links a{font-size:.7rem;padding:7px 5px}header.site .avatar{width:82px;height:82px}header.site{gap:25px}header.site p{font-size:.94rem}.section-heading{align-items:start;flex-direction:column}.file-info{gap:8px}.file-info .fsize{display:none}.dl-btn{padding:7px 11px;font-size:.8rem}}
</style>
</head>
<body>

<nav class="topbar">
  <div class="bar">
    <div class="brand">Mohamed Tarchouli</div>
    <div class="links">
      <a href="index.php#accueil"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1z"/></svg>Accueil</a>
      <a href="index.php#projets"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6M7 16h3"/></svg>Projets</a>
      <a href="index.php#modules"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg>Modules</a>
      <a href="index.php#contact"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>Contact</a>
    </div>
  </div>
</nav>

<?php if ($currentDir === null): ?>
<header class="site" id="accueil">
  <!-- LIGNE À MODIFIER : remplacez ce src par le lien (ou chemin) de votre vraie photo -->
  <img class="avatar" src="/images/profile.png" alt="Photo de Mohamed Tarchouli">
  <div class="intro">
    <div class="kicker">Portfolio scolaire · Développement & conception</div>
    <h1>Mohamed<br>Tarchouli<span aria-hidden="true">.</span></h1>
    <p>Étudiant passionné par le développement web et la conception. Je transforme les idées en projets clairs, utiles et bien construits.</p>
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

  <a class="back" href="index.php">← Toutes les Modules</a>

  <div class="subject-title">
    <h1><?= htmlspecialchars($currentCode) ?></h1>
    <?php if (!empty($meta[$currentCode])): ?>
      <span class="full-name"><?= htmlspecialchars($meta[$currentCode]) ?></span>
    <?php endif; ?>
  </div>
  <div class="subject-sub"><?= $totalCount ?> fichier<?= $totalCount > 1 ? 's' : '' ?> disponible<?= $totalCount > 1 ? 's' : '' ?></div>

  <?php if ($totalCount === 0): ?>
    <p class="empty">Aucun exercice n'a encore été ajouté pour cette Module.</p>
  <?php endif; ?>

  <?php if (!empty($rootExercices)): ?>
    <ul class="exercices">
      <?php foreach ($rootExercices as $ex): ?>
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

  <?php foreach ($subfolders as $sf): ?>
    <div class="subfolder-block">
      <h3><?= htmlspecialchars(strtoupper($sf['name'])) ?></h3>
      <div class="subfolder-count"><?= count($sf['files']) ?> fichier<?= count($sf['files']) > 1 ? 's' : '' ?></div>
      <?php if (empty($sf['files'])): ?>
        <p class="empty">Aucun fichier dans ce dossier.</p>
      <?php else: ?>
        <ul class="exercices">
          <?php foreach ($sf['files'] as $ex): ?>
            <li>
              <div class="file-info">
                <span class="ext"><?= htmlspecialchars($ex['ext']) ?></span>
                <span class="fname"><?= htmlspecialchars($ex['name']) ?></span>
                <span class="fsize"><?= $ex['size'] ?></span>
              </div>
              <a class="dl-btn" href="index.php?action=download&matiere=<?= urlencode($currentCode) ?>&dossier=<?= urlencode($sf['name']) ?>&file=<?= urlencode($ex['name']) ?>">
                Télécharger
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

<?php else: ?>

  <section class="section-block" id="projets">
    <div class="section-heading"><h2>Projets sélectionnés</h2><span class="eyebrow">Apprendre · Concevoir · Construire</span></div>
    <div class="projects-grid">
      <div class="project-card">
        <div class="project-mark"><span>01 / Conception</span><span aria-hidden="true">↗</span></div>
        <h3>Modélisation UML</h3>
        <p>Analyse des besoins et représentation de systèmes avec des diagrammes clairs et structurés.</p>
      </div>
      <div class="project-card">
        <div class="project-mark"><span>02 / Design</span><span aria-hidden="true">↗</span></div>
        <h3>Ateliers Figma</h3>
        <p>Exploration du prototypage d’interfaces, des parcours utilisateurs et des principes visuels.</p>
      </div>
    </div>
  </section>

  <section class="section-block" id="apropos">
    <div class="section-heading"><h2>À propos</h2><span class="eyebrow">Un esprit curieux, une approche pratique</span></div>
    <div class="about-layout">
      <p class="about-copy">Je développe mes compétences en <strong>conception et développement</strong> à travers des cours, des ateliers et des projets pratiques. Ce portfolio rassemble mes travaux et ressources, organisés par module.</p>
      <ul class="focus-list">
        <li><span>Développement web</span><span>Front-end · Back-end</span></li>
        <li><span>Conception</span><span>UML · Modélisation</span></li>
        <li><span>Design d’interface</span><span>Figma · Prototypage</span></li>
        <li><span>Organisation</span><span>Agile · GANTT · Git</span></li>
      </ul>
    </div>
  </section>

  <section class="section-block" id="modules">
    <div class="section-heading"><h2>Modules</h2><span class="eyebrow">Cours & travaux pratiques</span></div>
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
    <div class="section-heading"><h2>Contact</h2><span class="eyebrow">Restons en contact</span></div>
    <div class="contact-layout"><p class="contact-note">Une question, une idée de projet ou simplement envie d’échanger ? Retrouvez-moi ici.</p>
    <ul class="contact-list">
      <li><span class="contact-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span><span class="label">Email</span><a href="mailto:mohamed.tarchouli.contact@gmail.com">mohamed.tarchouli.contact@gmail.com</a></li>
      <li><span class="contact-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 6v-3.9a3.4 3.4 0 0 0-.9-2.7c3-.3 6.2-1.5 6.2-6.8a5.3 5.3 0 0 0-1.4-3.7 4.9 4.9 0 0 0-.1-3.7S17.6 1 15 3a13.5 13.5 0 0 0-7 0C5.4 1 4.2 1.2 4.2 1.2A4.9 4.9 0 0 0 4.1 5 5.3 5.3 0 0 0 2.7 8.7c0 5.3 3.2 6.5 6.2 6.8A3.4 3.4 0 0 0 8 18.2V22"/></svg></span><span class="label">GitHub</span><a href="https://github.com/mohamed-workspace" target="_blank" rel="noopener">mohamed-workspace</a></li>
      <li><span class="contact-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></span><span class="label">LinkedIn</span><a href="https://linkedin.com/in/mohamed-tarchouli-691583403?utm_source=share_via&amp;utm_content=profile&amp;utm_medium=member_android" target="_blank" rel="noopener">Mohamed Tarchouli</a></li>
    </ul>
    </div>
  </section>

<?php endif; ?>
</main>

<footer>Portfolio scolaire</footer>
</body>
</html>
