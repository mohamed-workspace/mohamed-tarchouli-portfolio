<?php
/**
 * Tableau de bord (page séparée, protégée par mot de passe)
 * -----------------------------------------------------------
 * - Se connecter avec un mot de passe
 * - Ajouter une matière (crée un dossier dans /docs)
 * - Uploader des fichiers d'exercices dans une matière
 * - Supprimer un fichier ou une matière vide
 *
 * ⚠️ IMPORTANT — changez le mot de passe avant de mettre en ligne :
 * générez un nouveau hash avec la commande PHP :
 *   php -r "echo password_hash('votre_nouveau_mdp', PASSWORD_DEFAULT);"
 * puis collez le résultat dans ADMIN_PASSWORD_HASH ci-dessous.
 */

session_start();

define('DOCS_DIR', __DIR__ . '/docs');
define('ADMIN_PASSWORD_HASH', '$2y$10$3qqPLG2NT6/gAg1LrsvuLOtUiKO2.6L.gAMAyw78HIK38rNsbFrey'); // = "changeme123"

if (!is_dir(DOCS_DIR)) mkdir(DOCS_DIR, 0755, true);

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
function saveMeta(array $meta): void {
    file_put_contents(DOCS_DIR . '/meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function safeSegment(string $s): string {
    $s = trim($s);
    $s = preg_replace('/[^A-Za-z0-9_\-]/', '', $s); // code de matière : lettres/chiffres/-/_
    return $s;
}
function listMatieres(): array {
    $items = [];
    foreach (scandir(DOCS_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = DOCS_DIR . '/' . $entry;
        if (is_dir($full)) {
            $files = [];
            foreach (scandir($full) as $f) {
                if ($f !== '.' && $f !== '..' && is_file($full . '/' . $f)) $files[] = $f;
            }
            sort($files);
            $items[$entry] = $files;
        }
    }
    ksort($items);
    return $items;
}

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------
$error = '';

if (isset($_POST['login'])) {
    if (password_verify($_POST['password'] ?? '', ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = 'Mot de passe incorrect.';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: dashboard.php');
    exit;
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);

// ----------------------------------------------------------------
// Actions (uniquement si connecté)
// ----------------------------------------------------------------
$message = '';

if ($isLoggedIn) {

    // Ajouter une matière
    if (isset($_POST['add_matiere'])) {
        $code = safeSegment($_POST['code'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        if ($code === '') {
            $error = 'Code de matière invalide (lettres, chiffres, - et _ seulement).';
        } else {
            $dir = DOCS_DIR . '/' . $code;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if ($fullName !== '') {
                $meta = loadMeta();
                $meta[$code] = $fullName;
                saveMeta($meta);
            }
            $message = 'Matière "' . $code . '" ajoutée.';
        }
    }

    // Uploader un ou plusieurs fichiers
    if (isset($_POST['upload_files'])) {
        $code = safeSegment($_POST['matiere'] ?? '');
        $dir = DOCS_DIR . '/' . $code;
        if ($code === '' || !is_dir($dir)) {
            $error = 'Choisissez une matière valide.';
        } elseif (empty($_FILES['files']['name'][0])) {
            $error = 'Choisissez au moins un fichier.';
        } else {
            $count = 0;
            foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $name = basename($_FILES['files']['name'][$i]);
                move_uploaded_file($tmp, $dir . '/' . $name);
                $count++;
            }
            $message = $count . ' fichier(s) ajouté(s) à "' . $code . '".';
        }
    }

    // Supprimer un fichier
    if (isset($_POST['delete_file'])) {
        $code = safeSegment($_POST['matiere'] ?? '');
        $file = basename($_POST['file'] ?? '');
        $path = DOCS_DIR . '/' . $code . '/' . $file;
        if (is_file($path)) {
            unlink($path);
            $message = 'Fichier "' . $file . '" supprimé.';
        }
    }

    // Supprimer une matière (seulement si vide)
    if (isset($_POST['delete_matiere'])) {
        $code = safeSegment($_POST['matiere'] ?? '');
        $dir = DOCS_DIR . '/' . $code;
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            if (empty($files)) {
                rmdir($dir);
                $meta = loadMeta();
                unset($meta[$code]);
                saveMeta($meta);
                $message = 'Matière "' . $code . '" supprimée.';
            } else {
                $error = 'Impossible de supprimer : la matière contient encore des fichiers.';
            }
        }
    }
}

$matieres = $isLoggedIn ? listMatieres() : [];
$meta = $isLoggedIn ? loadMeta() : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord — Portfolio</title>
<style>
  :root{
    --ink:#1C2333;
    --paper:#EDEAE2;
    --panel:#242C40;
    --panel-2:#2E3750;
    --accent:#B98A54;
    --accent-2:#7FA98A;
    --line:#3B4460;
    --danger:#C0563E;
  }
  *{box-sizing:border-box;}
  body{
    margin:0;
    background:var(--ink);
    color:var(--paper);
    font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
    line-height:1.5;
  }
  h1,h2{
    font-family:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,serif;
    font-weight:600;
    margin:0 0 4px;
  }
  a{color:var(--accent-2);}
  .wrap{max-width:760px;margin:0 auto;padding:48px 22px 80px;}
  .top{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:34px;}
  .top p{color:#9aa1b8;margin:4px 0 0;font-size:0.9rem;}
  .logout{font-size:0.85rem;}

  .login-box{
    max-width:340px;
    margin:80px auto;
    background:var(--panel);
    padding:32px;
    border:1px solid var(--line);
  }
  .login-box h1{font-size:1.6rem;margin-bottom:18px;}

  .msg{padding:12px 16px;margin-bottom:24px;font-size:0.9rem;}
  .msg.ok{background:#243d2f;color:#bfe6cc;border:1px solid var(--accent-2);}
  .msg.err{background:#3d2823;color:#f0c3b6;border:1px solid var(--danger);}

  .panel{
    background:var(--panel);
    border:1px solid var(--line);
    padding:24px;
    margin-bottom:24px;
  }
  .panel h2{font-size:1.2rem;margin-bottom:16px;}

  label{display:block;font-size:0.85rem;color:#9aa1b8;margin-bottom:6px;}
  input[type=text], input[type=password], select{
    width:100%;
    padding:10px 12px;
    background:var(--panel-2);
    border:1px solid var(--line);
    color:var(--paper);
    font-size:0.95rem;
    margin-bottom:16px;
  }
  input[type=file]{
    width:100%;
    margin-bottom:16px;
    font-size:0.9rem;
  }
  .row{display:flex;gap:12px;}
  .row > div{flex:1;}

  button{
    background:var(--accent);
    color:#221708;
    border:none;
    padding:10px 18px;
    font-size:0.92rem;
    cursor:pointer;
  }
  button:hover{opacity:0.9;}
  button.danger{background:var(--danger);color:#fff;}
  button.ghost{background:transparent;border:1px solid var(--line);color:var(--paper);}

  .matiere-block{border-top:1px solid var(--line);padding-top:16px;margin-top:16px;}
  .matiere-block:first-child{border-top:none;margin-top:0;padding-top:0;}
  .matiere-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
  .matiere-head .code{font-family:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,serif;font-size:1.15rem;}
  .matiere-head .full{color:#9aa1b8;font-size:0.85rem;margin-left:8px;}
  ul.files{list-style:none;margin:0;padding:0;}
  ul.files li{
    display:flex;justify-content:space-between;align-items:center;
    padding:8px 0;border-top:1px solid var(--line);font-size:0.9rem;
  }
  ul.files li form{margin:0;}
  .empty-note{color:#6f7690;font-size:0.85rem;}
</style>
</head>
<body>
<div class="wrap">

<?php if (!$isLoggedIn): ?>

  <div class="login-box">
    <h1>Connexion</h1>
    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" required>
      <button type="submit" name="login" value="1">Se connecter</button>
    </form>
  </div>

<?php else: ?>

  <div class="top">
    <div>
      <h1>Tableau de bord</h1>
      <p>Gérez les matières et les exercices du portfolio.</p>
    </div>
    <a class="logout" href="dashboard.php?logout=1">Se déconnecter</a>
  </div>

  <?php if ($message): ?><div class="msg ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="panel">
    <h2>Ajouter une matière</h2>
    <form method="post">
      <div class="row">
        <div>
          <label for="code">Code (ex : M201)</label>
          <input type="text" id="code" name="code" required>
        </div>
        <div>
          <label for="full_name">Nom complet (optionnel)</label>
          <input type="text" id="full_name" name="full_name" placeholder="ex : Développement Web">
        </div>
      </div>
      <button type="submit" name="add_matiere" value="1">Ajouter la matière</button>
    </form>
  </div>

  <div class="panel">
    <h2>Ajouter des exercices</h2>
    <?php if (empty($matieres)): ?>
      <p class="empty-note">Ajoutez d'abord une matière ci-dessus.</p>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data">
        <label for="matiere">Matière</label>
        <select id="matiere" name="matiere" required>
          <?php foreach ($matieres as $code => $files): ?>
            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?><?= !empty($meta[$code]) ? ' — ' . htmlspecialchars($meta[$code]) : '' ?></option>
          <?php endforeach; ?>
        </select>
        <label for="files">Fichiers</label>
        <input type="file" id="files" name="files[]" multiple required>
        <button type="submit" name="upload_files" value="1">Téléverser</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Matières existantes</h2>
    <?php if (empty($matieres)): ?>
      <p class="empty-note">Aucune matière pour le moment.</p>
    <?php else: ?>
      <?php foreach ($matieres as $code => $files): ?>
        <div class="matiere-block">
          <div class="matiere-head">
            <div><span class="code"><?= htmlspecialchars($code) ?></span><?php if (!empty($meta[$code])): ?><span class="full"><?= htmlspecialchars($meta[$code]) ?></span><?php endif; ?></div>
            <?php if (empty($files)): ?>
              <form method="post" onsubmit="return confirm('Supprimer cette matière vide ?');">
                <input type="hidden" name="matiere" value="<?= htmlspecialchars($code) ?>">
                <button type="submit" name="delete_matiere" value="1" class="ghost">Supprimer</button>
              </form>
            <?php endif; ?>
          </div>
          <?php if (empty($files)): ?>
            <p class="empty-note">Aucun fichier.</p>
          <?php else: ?>
            <ul class="files">
              <?php foreach ($files as $f): ?>
                <li>
                  <span><?= htmlspecialchars($f) ?></span>
                  <form method="post" onsubmit="return confirm('Supprimer ce fichier ?');">
                    <input type="hidden" name="matiere" value="<?= htmlspecialchars($code) ?>">
                    <input type="hidden" name="file" value="<?= htmlspecialchars($f) ?>">
                    <button type="submit" name="delete_file" value="1" class="danger">Supprimer</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

<?php endif; ?>

</div>
</body>
</html>
