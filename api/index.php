<?php
/**
 * Portfolio — Mohamed Tarchouli (lecture seule)
 * ---------------------------------------------------------------
 * Pas de dashboard, pas d'écriture sur le disque (compatible Vercel).
 * Pour ajouter une matière ou un exercice : ajoutez le fichier
 * directement dans public/docs/<matiere>/ puis faites git push.
 *
 * Chaque matière peut contenir des sous-dossiers (ex: "uml", "figma").
 * Ils sont détectés automatiquement et affichés comme des sections
 * séparées, avec le nom du sous-dossier en titre.
 *
 * Structure attendue :
 *   api/index.php   (ce fichier)
 *   public/docs/M201/exercice1.pdf
 *   public/docs/M202/uml/diagram1.mdj
 *   public/docs/M202/figma/maquette1.png
 *   public/docs/meta.json   (optionnel — noms complets des matières)
 *   public/images/profile.png
 *
 * ⚠️ Si votre structure de dossiers est différente, changez juste
 * la ligne DOCS_DIR ci-dessous pour qu'elle pointe vers public/docs.
 */

define('DOCS_DIR', __DIR__ . '/../public/docs');

// Ordre préféré d'affichage des sous-dossiers (les autres suivent,
// classés alphabétiquement). Ajoutez-en d'autres ici si besoin.
define('SUBFOLDER_ORDER', ['uml', 'figma']);

// ----------------------------------------------------------------
// Contenu du portfolio (partie facile à modifier)
// ----------------------------------------------------------------

// PHRASES DU HERO : écrites puis effacées en boucle, comme un message.
// La première est aussi le texte affiché si JavaScript est désactivé.
$heroPhrases = [
    'Stagiaire en développement web full stack',
    'Bienvenue sur mon portfolio',
];
// La plus longue sert à réserver la hauteur (évite que la page saute).
$heroLongest = $heroPhrases[0];
foreach ($heroPhrases as $ph) {
    if (strlen($ph) > strlen($heroLongest)) $heroLongest = $ph;
}

// PROJETS : pour en ajouter un, copiez une ligne et changez les valeurs.
// 'url' peut être un lien externe (https://...) ou une page de votre site.
// 'url' vide = carte "bientôt" (non cliquable, bordure en pointillés).
$projects = [
    [
        'title' => 'Hours',
        'desc'  => "Landing page d'un e-commerce pour une marque de vêtements, réalisée en HTML, CSS et JavaScript pour un client freelance.",
        'url'   => 'https://levimohamed.github.io/front-end/',
    ],
    [
        'title' => 'Ce portfolio',
        'desc'  => 'Un site PHP en lecture seule, déployé sur Vercel, qui regroupe mes modules et leurs exercices.',
        'url'   => 'index.php#modules',
    ],
    [
        'title' => 'Facilo',
        'desc'  => "Une plateforme de quick annonces, dans l'esprit d'Avito.",
        'url'   => 'https://facilo-project-team-aim.vercel.app/',
    ],
];

// CERTIFICATS : pour en ajouter un, copiez une ligne et changez les valeurs.
$certificates = [
    [
        'title'  => 'Python Essentials 1',
        'issuer' => 'Délivré par Cisco',
        'url'    => 'https://www.credly.com/badges/aebb6a10-3013-4a90-9cd5-0aefd840399f/linked_in_profile',
    ],
];

// COMPÉTENCES : groupes affichés dans la section "Compétences".
$skills = [
    ['title' => 'Front-end',              'icon' => 'monitor',   'items' => ['HTML', 'CSS', 'JavaScript', 'Bootstrap', 'Figma']],
    ['title' => 'Back-end',               'icon' => 'server',    'items' => ['PHP', 'Python', 'SQL', 'MySQL']],
    ['title' => 'Analyse et conception',  'icon' => 'layers',    'items' => ['Merise', 'UML']],
    ['title' => 'Bureautique',            'icon' => 'file-text', 'items' => ['Excel', 'PowerPoint', 'Word', 'Canva']],
    ['title' => 'Outils',                 'icon' => 'tool',      'items' => ['Git et GitHub', 'GitLab', 'Vercel', 'Jira', 'GanttProject', 'StarUML']],
];

// MENU : [ancre, libellé, icône]
$nav = [
    ['accueil',     'Accueil',     'home'],
    ['apropos',     'À propos',    'user'],
    ['competences', 'Compétences', 'code'],
    ['projets',     'Projets',     'folder'],
    ['certificats', 'Certificats', 'award'],
    ['modules',     'Modules',     'book'],
    ['contact',     'Contact',     'mail'],
];

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

/** Affiche une icône SVG définie dans le "sprite" en haut de la page. */
function ic(string $id): string {
    return '<svg class="ic" aria-hidden="true" focusable="false"><use href="#i-' . $id . '"/></svg>';
}

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
    // Correctif : on compare avec le séparateur final pour éviter qu'un
    // dossier voisin ("docs-autre") passe le test du préfixe.
    if (strpos($real, $realDocs . DIRECTORY_SEPARATOR) !== 0) return null;
    if (!is_dir($real)) return null;
    return $real;
}

/** Résout un sous-dossier (ex: "uml") à l'intérieur d'une matière, en toute sécurité. */
function subfolderDir(string $matiereRealDir, string $sub): ?string {
    $sub = safeSegment($sub);
    $real = realpath($matiereRealDir . '/' . $sub);
    if ($real === false) return null;
    if (strpos($real, $matiereRealDir . DIRECTORY_SEPARATOR) !== 0) return null;
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
$pageTitle = $currentDir !== null
    ? htmlspecialchars($currentCode) . ' — Mohamed Tarchouli'
    : 'Mohamed Tarchouli — Stagiaire en développement web full stack';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<meta name="description" content="Portfolio de Mohamed Tarchouli, stagiaire en développement web full stack : projets, compétences, modules et exercices.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --black:#000;
    --white:#fff;
    --g1:#f3f3f3;
    --g2:#e2e2e2;
    --g3:#6a6a6a;
    --g4:#a6a6a6;
    --wrap:1180px;
    --display:"Bricolage Grotesque","Helvetica Neue",Arial,sans-serif;
    --body:"DM Sans",-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  }
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{
    margin:0;background:var(--white);color:var(--black);
    font-family:var(--body);font-size:1.05rem;line-height:1.6;
    -webkit-font-smoothing:antialiased;
  }
  a{color:inherit;text-decoration:none;}
  h1,h2,h3{font-family:var(--display);margin:0;line-height:1.05;letter-spacing:-0.03em;}
  :focus-visible{outline:2px solid currentColor;outline-offset:3px;}

  .wrap{max-width:var(--wrap);margin:0 auto;padding:0 28px;}
  .ic{width:1.15em;height:1.15em;flex:0 0 auto;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
  .sprite{position:absolute;width:0;height:0;overflow:hidden;}

  /* ---------- Menu ---------- */
  .topbar{
    position:sticky;top:0;z-index:20;
    background:rgba(0,0,0,.92);color:var(--white);
    -webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);
    border-bottom:1px solid rgba(255,255,255,.14);
  }
  .topbar .bar{
    max-width:var(--wrap);margin:0 auto;padding:14px 28px;
    display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  }
  .brand{font-family:var(--display);font-weight:700;font-size:1.2rem;letter-spacing:-0.02em;}
  .links{display:flex;gap:4px;}
  .links a{
    display:flex;align-items:center;gap:8px;
    padding:8px 14px;border-radius:999px;
    font-size:.95rem;color:var(--g4);
    transition:color .2s,background .2s;
  }
  .links a:hover{color:var(--white);background:rgba(255,255,255,.12);}

  /* Bouton hamburger (3 lignes) : visible seulement sur petits écrans */
  .menu-toggle{
    display:none;position:relative;width:44px;height:44px;padding:0;
    border:1px solid rgba(255,255,255,.3);border-radius:10px;
    background:transparent;color:var(--white);cursor:pointer;
    transition:background .2s,border-color .2s;
  }
  .menu-toggle:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.6);}
  .menu-toggle span{
    position:absolute;left:11px;right:11px;height:2px;border-radius:2px;background:currentColor;
    transition:top .3s ease,transform .3s ease,opacity .2s ease;
  }
  .menu-toggle span:nth-child(1){top:14px;}
  .menu-toggle span:nth-child(2){top:21px;}
  .menu-toggle span:nth-child(3){top:28px;}
  .topbar.open .menu-toggle span:nth-child(1){top:21px;transform:rotate(45deg);}
  .topbar.open .menu-toggle span:nth-child(2){opacity:0;}
  .topbar.open .menu-toggle span:nth-child(3){top:21px;transform:rotate(-45deg);}

  /* ---------- Hero (fond noir + grille animée) ---------- */
  .hero{position:relative;overflow:hidden;background:var(--black);color:var(--white);isolation:isolate;}
  .hero::before{
    content:"";position:absolute;inset:0;z-index:-1;
    background-image:
      linear-gradient(rgba(255,255,255,.09) 1px,transparent 1px),
      linear-gradient(90deg,rgba(255,255,255,.09) 1px,transparent 1px);
    background-size:56px 56px;
    -webkit-mask-image:radial-gradient(ellipse 75% 85% at 72% 45%,#000,transparent 78%);
    mask-image:radial-gradient(ellipse 75% 85% at 72% 45%,#000,transparent 78%);
    animation:drift 28s linear infinite;
  }
  .hero::after{
    content:"";position:absolute;z-index:-1;
    width:520px;height:520px;right:6%;top:8%;border-radius:50%;
    background:radial-gradient(circle,rgba(255,255,255,.16),transparent 65%);
    animation:float 14s ease-in-out infinite alternate;
  }
  @keyframes drift{to{background-position:56px 56px,56px 56px;}}
  @keyframes float{from{transform:translate(0,0);}to{transform:translate(-70px,60px);}}

  .hero-inner{
    max-width:var(--wrap);margin:0 auto;padding:80px 28px;
    min-height:calc(100vh - 62px);
    display:flex;align-items:center;justify-content:space-between;gap:48px;
  }
  .hero h1{font-size:clamp(3.4rem,10vw,8rem);font-weight:700;letter-spacing:-0.05em;line-height:.92;}
  .hero .role{font-size:clamp(1.15rem,2.2vw,1.6rem);font-weight:500;margin:28px 0 0;}
  .hero .lead{color:var(--g4);max-width:46ch;margin:14px 0 0;}
  .cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:36px;}
  .btn{
    display:inline-flex;align-items:center;gap:10px;
    padding:14px 24px;border-radius:999px;border:1.5px solid var(--white);
    font-weight:500;transition:background .2s,color .2s;
  }
  .btn.solid{background:var(--white);color:var(--black);}
  .btn.solid:hover{background:transparent;color:var(--white);}
  .btn.ghost{border-color:rgba(255,255,255,.4);color:var(--white);}
  .btn.ghost:hover{background:var(--white);color:var(--black);border-color:var(--white);}

  .avatar{
    position:relative;flex:0 0 auto;overflow:hidden;
    width:clamp(180px,24vw,300px);aspect-ratio:1;border-radius:50%;
    border:1px solid rgba(255,255,255,.35);background:#111;
    display:flex;align-items:center;justify-content:center;
    font-family:var(--display);font-size:3.4rem;font-weight:700;color:var(--g4);
  }
  .avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;filter:grayscale(1) contrast(1.05);transition:filter .5s ease;}
  .avatar:focus-visible{outline-offset:6px;}
  .avatar:focus-visible img,.avatar.colored img{filter:none;}
  @media (hover:hover){.avatar:hover img{filter:none;}}
  @media (hover:none){.avatar{cursor:pointer;-webkit-tap-highlight-color:transparent;}}

  /* Indice animé sous la photo : survol (PC) ou toucher (téléphone) */
  .avatar-wrap{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:20px;}
  .avatar-hint{
    display:flex;flex-direction:column;align-items:center;gap:6px;margin:0;
    color:var(--white);font-size:.98rem;white-space:nowrap;
    animation:hint-pulse 2.4s ease-in-out infinite;
  }
  .avatar-hint .ic{width:1.5em;height:1.5em;animation:nudge 1.3s ease-in-out infinite;}
  .hint-hover{display:none;}
  @media (hover:hover){.hint-hover{display:inline;}.hint-tap{display:none;}}
  @keyframes nudge{0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}
  @keyframes hint-pulse{0%,100%{opacity:.5;}50%{opacity:1;}}

  /* Ligne qui s'écrit et s'efface */
  .sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}
  .swap{display:grid;}
  .swap .sizer,.swap .typed{grid-area:1/1;}
  .swap .sizer{visibility:hidden;}
  .typed::after{
    content:"";display:inline-block;width:2px;height:1em;margin-left:3px;
    vertical-align:-.12em;background:currentColor;animation:caret 1s steps(1) infinite;
  }
  .typed.is-typing::after{animation:none;}
  @keyframes caret{50%{opacity:0;}}

  .hero.mini .hero-inner{min-height:0;padding:64px 28px 56px;}
  .hero.mini h1{font-size:clamp(2.6rem,7vw,5rem);}

  /* Une seule séquence d'entrée au chargement */
  .rise{opacity:0;transform:translateY(20px);animation:rise .9s cubic-bezier(.2,.7,.2,1) forwards;animation-delay:var(--d,0s);}
  @keyframes rise{to{opacity:1;transform:none;}}

  /* ---------- Sections ---------- */
  .section{padding:104px 0;scroll-margin-top:62px;}
  .section.alt{background:var(--g1);}
  .section.dark{background:var(--black);color:var(--white);}
  .section-head{margin-bottom:44px;max-width:60ch;}
  .section-head h2,.about h2{font-size:clamp(2.2rem,5vw,3.6rem);}
  .section-head p{color:var(--g3);margin:14px 0 0;}
  .dark .section-head p{color:var(--g4);}

  /* À propos */
  .about{display:grid;grid-template-columns:1fr 1.4fr;gap:48px;align-items:start;}
  .about .text p{font-size:1.25rem;line-height:1.6;margin:0 0 18px;}
  .about .text p:last-child{margin-bottom:0;}

  /* Compétences */
  .skills{display:flex;flex-wrap:wrap;justify-content:center;gap:20px;}
  .skill-group{flex:0 1 calc((100% - 40px) / 3);min-width:280px;border:1.5px solid var(--black);padding:28px;}
  .skill-group h3{display:flex;align-items:center;gap:12px;font-size:1.5rem;}
  .chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px;}
  .chip{border:1px solid currentColor;border-radius:999px;padding:4px 14px;font-size:.92rem;}

  /* Projets (cartes-liens) */
  .projects{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;}
  .project{
    display:flex;flex-direction:column;gap:12px;min-height:200px;padding:28px;
    background:var(--white);border:1.5px solid var(--black);
    transition:background .25s,color .25s;
  }
  .project h3{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;font-size:1.7rem;}
  .project p{margin:0;color:var(--g3);transition:color .25s;}
  .project .go{width:1.3em;height:1.3em;transition:transform .25s;}
  .project:hover{background:var(--black);color:var(--white);}
  .project:hover p{color:var(--g4);}
  .project:hover .go{transform:translate(3px,-3px);}
  .project.soon{border-style:dashed;border-color:var(--g4);color:var(--g3);}
  .project.soon:hover{background:var(--white);color:var(--g3);}

  /* Modules */
  .empty{color:var(--g3);padding:24px 0;}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;}
  .card{
    position:relative;display:flex;flex-direction:column;height:180px;
    background:var(--white);border:1.5px solid var(--black);padding:24px 22px 20px;
    transition:background .25s,color .25s;
  }
  .card::before{content:"";position:absolute;top:-1.5px;left:22px;width:38px;height:8px;background:var(--black);transition:background .25s;}
  .card .code{font-family:var(--display);font-size:2rem;font-weight:700;letter-spacing:-.03em;line-height:1.1;margin-top:10px;}
  .card .name{
    color:var(--g3);font-size:.95rem;margin-top:6px;
    display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
  }
  .card .count{margin-top:auto;padding-top:10px;font-size:.85rem;color:var(--g3);}
  .card:hover{background:var(--black);color:var(--white);}
  .card:hover::before{background:var(--white);}
  .card:hover .name,.card:hover .count{color:var(--g4);}

  /* Contact */
  .contact-list{list-style:none;margin:0;padding:0;border-top:1px solid rgba(255,255,255,.22);}
  .contact-list li{border-bottom:1px solid rgba(255,255,255,.22);}
  .contact-list a{display:flex;align-items:center;gap:18px;padding:26px 4px;transition:padding .25s;}
  .contact-list a:hover{padding-left:16px;}
  .contact-list .ic{width:1.5em;height:1.5em;}
  .contact-list .label{color:var(--g4);min-width:96px;}
  .contact-list .value{flex:1;min-width:0;font-family:var(--display);font-size:clamp(1.05rem,2.6vw,1.8rem);overflow-wrap:anywhere;}
  .contact-list .go{color:var(--g4);}

  footer{background:var(--black);color:var(--g4);border-top:1px solid rgba(255,255,255,.14);font-size:.88rem;}
  footer .wrap{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding-top:26px;padding-bottom:26px;}
  footer a{display:inline-flex;align-items:center;gap:8px;transition:color .2s;}
  footer a:hover{color:var(--white);}

  /* ---------- Page d'un module ---------- */
  main.page{max-width:var(--wrap);margin:0 auto;padding:56px 28px 110px;}
  .back{display:inline-flex;align-items:center;gap:8px;margin-bottom:32px;font-weight:500;border-bottom:1.5px solid var(--black);padding-bottom:2px;}
  .subject-title{display:flex;align-items:baseline;gap:16px;flex-wrap:wrap;margin-bottom:8px;}
  .subject-title h1{font-size:clamp(2.6rem,6vw,4.2rem);}
  .subject-title .full-name{color:var(--g3);font-size:1.15rem;}
  .subject-sub{color:var(--g3);margin-bottom:32px;}

  .subfolder-block{margin-top:44px;}
  .subfolder-block h3{font-size:1.5rem;letter-spacing:.01em;display:inline-block;padding-bottom:8px;border-bottom:2px solid var(--black);}
  .subfolder-count{color:var(--g3);font-size:.88rem;margin:8px 0 12px;}

  ul.exercices{list-style:none;margin:0;padding:0;border-top:1px solid var(--g2);}
  ul.exercices li{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 8px;border-bottom:1px solid var(--g2);transition:background .2s;}
  ul.exercices li:hover{background:var(--g1);}
  .file-info{display:flex;align-items:baseline;gap:12px;min-width:0;}
  .file-info .ext{flex:0 0 auto;background:var(--black);color:var(--white);font-size:.72rem;padding:3px 8px;border-radius:3px;}
  .file-info .fname{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;}
  .file-info .fsize{flex:0 0 auto;color:var(--g3);font-size:.85rem;}
  .dl-btn{
    flex:0 0 auto;display:inline-flex;align-items:center;gap:8px;
    border:1.5px solid var(--black);border-radius:999px;padding:9px 18px;font-size:.9rem;white-space:nowrap;
    transition:background .2s,color .2s;
  }
  .dl-btn:hover{background:var(--black);color:var(--white);}

  /* ---------- Mobile ---------- */
  @media (max-width:860px){
    .hero-inner{flex-direction:column-reverse;align-items:flex-start;gap:36px;min-height:0;padding:56px 24px 72px;}
    .avatar{width:150px;font-size:2.6rem;}
    .section{padding:72px 0;}
    .about{grid-template-columns:1fr;gap:24px;}
  }
  @media (max-width:960px){
    .menu-toggle{display:block;}
    .brand{font-size:1.1rem;}
    /* Panneau du menu : glisse et apparaît, puis les liens entrent un par un */
    .links{
      position:absolute;top:100%;left:0;right:0;
      flex-direction:column;gap:0;
      padding:8px 28px 24px;
      background:rgba(0,0,0,.97);border-bottom:1px solid rgba(255,255,255,.14);
      max-height:calc(100vh - 62px);overflow-y:auto;
      opacity:0;visibility:hidden;transform:translateY(-14px);
      transition:opacity .3s ease,transform .3s ease,visibility 0s linear .3s;
    }
    .topbar.open .links{
      opacity:1;visibility:visible;transform:none;
      transition:opacity .3s ease,transform .3s ease,visibility 0s;
    }
    .links a{
      padding:16px 4px;border-radius:0;font-size:1.1rem;color:var(--white);
      border-bottom:1px solid rgba(255,255,255,.12);
      opacity:0;transform:translateY(-8px);
      transition:opacity .35s ease,transform .35s ease,background .2s;
    }
    .links a:last-child{border-bottom:0;}
    .topbar.open .links a{opacity:1;transform:none;transition-delay:calc(var(--i) * 55ms + 80ms);}
  }
  @media (max-width:480px){
    .wrap,.topbar .bar,main.page{padding-left:18px;padding-right:18px;}
    .links{padding-left:18px;padding-right:18px;}
    .hero-inner,.hero.mini .hero-inner{padding-left:18px;padding-right:18px;}
    .contact-list a{gap:12px;}
    .contact-list .label{display:none;}
    ul.exercices li{flex-wrap:wrap;}
  }

  @media (prefers-reduced-motion:reduce){
    html{scroll-behavior:auto;}
    .hero::before,.hero::after{animation:none;}
    .rise{animation:none;opacity:1;transform:none;}
    .links,.links a,.menu-toggle span{transition:none !important;}
    .avatar-hint,.avatar-hint .ic,.typed::after{animation:none;}
    .avatar-hint{opacity:1;}
  }
</style>
</head>
<body id="top">

<!-- Icônes (sprite SVG) -->
<svg class="sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
  <symbol id="i-code" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></symbol>
  <symbol id="i-folder" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></symbol>
  <symbol id="i-book" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></symbol>
  <symbol id="i-github" viewBox="0 0 24 24"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></symbol>
  <symbol id="i-linkedin" viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></symbol>
  <symbol id="i-arrow-up-right" viewBox="0 0 24 24"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></symbol>
  <symbol id="i-arrow-left" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></symbol>
  <symbol id="i-arrow-up" viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></symbol>
  <symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
  <symbol id="i-monitor" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></symbol>
  <symbol id="i-server" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></symbol>
  <symbol id="i-award" viewBox="0 0 24 24"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></symbol>
  <symbol id="i-layers" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></symbol>
  <symbol id="i-file-text" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></symbol>
  <symbol id="i-tool" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></symbol>
</svg>

<nav class="topbar" aria-label="Navigation principale">
  <div class="bar">
    <a class="brand" href="index.php#accueil">Mohamed Tarchouli</a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu" aria-label="Ouvrir le menu">
      <span></span><span></span><span></span>
    </button>
    <div class="links" id="menu">
      <?php foreach ($nav as $i => $n): ?>
        <a href="index.php#<?= $n[0] ?>" style="--i:<?= $i ?>"><?= ic($n[2]) ?><span class="lbl"><?= htmlspecialchars($n[1]) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>

<?php if ($currentDir === null): ?>

<!-- ======================= ACCUEIL ======================= -->
<header class="hero" id="accueil">
  <div class="hero-inner">
    <div class="intro">
      <h1 class="rise" style="--d:.05s">Mohamed<br>Tarchouli</h1>
      <p class="role rise" style="--d:.25s">
        <span class="sr-only"><?= htmlspecialchars($heroPhrases[0]) ?></span>
        <span class="swap" aria-hidden="true">
          <span class="sizer"><?= htmlspecialchars($heroLongest) ?></span>
          <span class="typed" id="typed" data-phrases="<?= htmlspecialchars(json_encode($heroPhrases, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"><?= htmlspecialchars($heroPhrases[0]) ?></span>
        </span>
      </p>
      <p class="lead rise" style="--d:.4s">Je conçois des interfaces claires et je construis la partie serveur qui va avec. Voici mes projets, mes compétences et les exercices de mes modules.</p>
      <div class="cta rise" style="--d:.55s">
        <a class="btn solid" href="#projets"><?= ic('folder') ?>Voir mes projets</a>
        <a class="btn ghost" href="#contact"><?= ic('mail') ?>Me contacter</a>
      </div>
    </div>
    <div class="avatar-wrap rise" style="--d:.3s">
      <div class="avatar" role="button" tabindex="0" aria-pressed="false" aria-label="Afficher la photo en couleurs">
        <span aria-hidden="true">MT</span>
        <img src="/images/profile.png" alt="Photo de Mohamed Tarchouli" onerror="this.remove()">
      </div>
      <p class="avatar-hint" aria-hidden="true">
        <?= ic('arrow-up') ?>
        <span class="hint-hover">Survolez-moi</span>
        <span class="hint-tap">Touchez-moi</span>
      </p>
    </div>
  </div>
</header>

<main>

  <!-- ======================= À PROPOS ======================= -->
  <section class="section" id="apropos">
    <div class="wrap about">
      <h2>À propos</h2>
      <div class="text">
        <p>Je suis stagiaire en développement web full stack. Je dessine des interfaces simples et lisibles en front-end, et je construis la partie serveur avec PHP et SQL.</p>
      </div>
    </div>
  </section>

  <!-- ======================= COMPÉTENCES ======================= -->
  <section class="section alt" id="competences">
    <div class="wrap">
      <div class="section-head">
        <h2>Compétences</h2>
        <p>Les langages et les outils que j'utilise au quotidien.</p>
      </div>
      <div class="skills">
        <?php foreach ($skills as $g): ?>
          <div class="skill-group">
            <h3><?= ic($g['icon']) ?><?= htmlspecialchars($g['title']) ?></h3>
            <div class="chips">
              <?php foreach ($g['items'] as $item): ?>
                <span class="chip"><?= htmlspecialchars($item) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ======================= PROJETS ======================= -->
  <section class="section" id="projets">
    <div class="wrap">
      <div class="section-head">
        <h2>Projets</h2>
      </div>
      <div class="projects">
        <?php foreach ($projects as $p):
          $isLink   = $p['url'] !== '';
          $external = $isLink && preg_match('#^https?://#', $p['url']);
          $tag      = $isLink ? 'a' : 'div';
          $attrs    = $isLink
              ? ' href="' . htmlspecialchars($p['url']) . '"' . ($external ? ' target="_blank" rel="noopener"' : '')
              : '';
        ?>
          <<?= $tag ?> class="project<?= $isLink ? '' : ' soon' ?>"<?= $attrs ?>>
            <h3>
              <span><?= htmlspecialchars($p['title']) ?></span>
              <?php if ($isLink): ?><?= str_replace('class="ic"', 'class="ic go"', ic('arrow-up-right')) ?><?php endif; ?>
            </h3>
            <p><?= htmlspecialchars($p['desc']) ?></p>
          </<?= $tag ?>>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ======================= CERTIFICATS ======================= -->
  <section class="section alt" id="certificats">
    <div class="wrap">
      <div class="section-head">
        <h2>Certificats</h2>
      </div>
      <div class="projects">
        <?php foreach ($certificates as $c): ?>
          <a class="project" href="<?= htmlspecialchars($c['url']) ?>" target="_blank" rel="noopener">
            <h3>
              <span><?= htmlspecialchars($c['title']) ?></span>
              <?= str_replace('class="ic"', 'class="ic go"', ic('arrow-up-right')) ?>
            </h3>
            <p><?= htmlspecialchars($c['issuer']) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ======================= MODULES ======================= -->
  <section class="section" id="modules">
    <div class="wrap">
      <div class="section-head">
        <h2>Modules</h2>
        <p>Retrouvez ici tous les modules et leurs exercices. Cliquez sur un module pour voir les fichiers disponibles.</p>
      </div>
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
    </div>
  </section>

  <!-- ======================= CONTACT ======================= -->
  <section class="section dark" id="contact">
    <div class="wrap">
      <div class="section-head">
        <h2>Contact</h2>
        <p>Un stage, un emploi, une mission freelance ou une question ? Écrivez-moi.</p>
      </div>
      <ul class="contact-list">
        <li><a href="mailto:mohamed.tarchouli.contact@gmail.com"><?= ic('mail') ?><span class="label">Email</span><span class="value">mohamed.tarchouli.contact@gmail.com</span><?= str_replace('class="ic"', 'class="ic go"', ic('arrow-up-right')) ?></a></li>
        <li><a href="tel:0636211910"><?= ic('phone') ?><span class="label">Téléphone</span><span class="value">06 36 21 19 10</span><?= str_replace('class="ic"', 'class="ic go"', ic('arrow-up-right')) ?></a></li>
        <li><a href="https://github.com/mohamed-workspace" target="_blank" rel="noopener"><?= ic('github') ?><span class="label">GitHub</span><span class="value">mohamed-workspace</span><?= str_replace('class="ic"', 'class="ic go"', ic('arrow-up-right')) ?></a></li>
        <li><a href="https://linkedin.com/in/mohamed-tarchouli-691583403?utm_source=share_via&amp;utm_content=profile&amp;utm_medium=member_android" target="_blank" rel="noopener"><?= ic('linkedin') ?><span class="label">LinkedIn</span><span class="value">Mohamed Tarchouli</span><?= str_replace('class="ic"', 'class="ic go"', ic('arrow-up-right')) ?></a></li>
      </ul>
    </div>
  </section>

</main>

<?php else: ?>

<!-- ======================= PAGE D'UN MODULE ======================= -->
<header class="hero mini">
  <div class="hero-inner">
    <h1 class="rise">Exercices</h1>
  </div>
</header>

<main class="page">

  <a class="back" href="index.php#modules"><?= ic('arrow-left') ?>Tous les modules</a>

  <div class="subject-title">
    <h1><?= htmlspecialchars($currentCode) ?></h1>
    <?php if (!empty($meta[$currentCode])): ?>
      <span class="full-name"><?= htmlspecialchars($meta[$currentCode]) ?></span>
    <?php endif; ?>
  </div>
  <div class="subject-sub"><?= $totalCount ?> fichier<?= $totalCount > 1 ? 's' : '' ?> disponible<?= $totalCount > 1 ? 's' : '' ?></div>

  <?php if ($totalCount === 0): ?>
    <p class="empty">Aucun exercice n'a encore été ajouté pour ce module.</p>
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
          <a class="dl-btn" href="index.php?action=download&amp;matiere=<?= urlencode($currentCode) ?>&amp;file=<?= urlencode($ex['name']) ?>">
            <?= ic('download') ?>Télécharger
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
              <a class="dl-btn" href="index.php?action=download&amp;matiere=<?= urlencode($currentCode) ?>&amp;dossier=<?= urlencode($sf['name']) ?>&amp;file=<?= urlencode($ex['name']) ?>">
                <?= ic('download') ?>Télécharger
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

</main>

<?php endif; ?>

<footer>
  <div class="wrap">
    <span>© <?= date('Y') ?> Mohamed Tarchouli · Stagiaire en développement web full stack</span>
    <a href="#top"><?= ic('arrow-up') ?>Haut de page</a>
  </div>
</footer>

<script>
// Photo : sur téléphone, un toucher affiche / masque les couleurs
(function () {
  var av = document.querySelector('.avatar');
  if (!av) return;
  var noHover = window.matchMedia('(hover: none)');
  function toggle() {
    if (!noHover.matches) return;
    var on = av.classList.toggle('colored');
    av.setAttribute('aria-pressed', on ? 'true' : 'false');
  }
  av.addEventListener('click', toggle);
  av.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
  });
})();

// Phrase du hero : s'écrit, reste un moment, s'efface, puis la suivante (en boucle)
(function () {
  var el = document.getElementById('typed');
  if (!el) return;
  var phrases;
  try { phrases = JSON.parse(el.getAttribute('data-phrases')); } catch (e) { return; }
  if (!phrases || phrases.length < 2) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var idx = 0, pos = phrases[0].length, deleting = false;

  function tick() {
    var full = phrases[idx], delay;
    if (!deleting) {
      if (pos < full.length) {
        pos++;
        el.textContent = full.slice(0, pos);
        el.classList.add('is-typing');
        delay = 55 + Math.random() * 60;
      } else {
        el.classList.remove('is-typing');
        deleting = true;
        delay = 2300;
      }
    } else {
      if (pos > 0) {
        pos--;
        el.textContent = full.slice(0, pos);
        el.classList.add('is-typing');
        delay = 28 + Math.random() * 25;
      } else {
        el.classList.remove('is-typing');
        deleting = false;
        idx = (idx + 1) % phrases.length;
        delay = 450;
      }
    }
    setTimeout(tick, delay);
  }
  setTimeout(tick, 2600);
})();

// Menu mobile : ouvre / ferme le panneau
(function () {
  var bar = document.querySelector('.topbar');
  var btn = document.querySelector('.menu-toggle');
  if (!bar || !btn) return;

  function setOpen(open) {
    bar.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
  }

  btn.addEventListener('click', function () {
    setOpen(!bar.classList.contains('open'));
  });
  bar.querySelectorAll('.links a').forEach(function (a) {
    a.addEventListener('click', function () { setOpen(false); });
  });
  document.addEventListener('click', function (e) {
    if (!bar.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });
  window.matchMedia('(min-width: 961px)').addEventListener('change', function (e) {
    if (e.matches) setOpen(false);
  });
})();
</script>

</body>
</html>