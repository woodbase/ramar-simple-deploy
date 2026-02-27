<?php

$config = require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../src/github.php';

$settingsFile = __DIR__ . '/../settings.json';

$settings = file_exists($settingsFile)
    ? json_decode(file_get_contents($settingsFile), true)
    : [];

$defaultBranch = $settings['default_branch'] ?? null;

$branches = getBranches($config);
$selectedBranch = $defaultBranch ?? ($branches[0]['name'] ?? null);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Deploy Dashboard</title>
<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

.card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(20px);
    padding: 40px;
    border-radius: 16px;
    width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}

select, button {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: none;
    margin-bottom: 10px;
    font-size: 14px;
}

.primary {
    background: #22c55e;
    color: black;
    font-weight: 600;
}

.secondary {
    background: #334155;
    color: white;
}

.default-badge {
    font-size: 12px;
    color: #94a3b8;
    margin-bottom: 15px;
}

#commit-info {
    background:#1e293b;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    font-size:13px;
}

#log-output {
    background: #0f172a;
    padding: 15px;
    border-radius: 10px;
    height: 180px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 12px;
    color: #22c55e;
    border: 1px solid #1e293b;
}
</style>
</head>
<body>

<div class="card">
    <h1>🚀 Deploy Dashboard</h1>

    <?php if ($defaultBranch): ?>
        <div class="default-badge">
            Default branch: <strong><?= htmlspecialchars($defaultBranch) ?></strong>
        </div>
    <?php endif; ?>

    <div id="commit-info">Loading commit info...</div>

    <form method="post" action="/deploy_action.php">
        <input type="hidden" name="action" id="action-field">

        <select name="branch" id="branch-select">
            <?php foreach ($branches as $branch): ?>
                <option value="<?= htmlspecialchars($branch['name']) ?>"
                    <?= $branch['name'] === $defaultBranch ? 'selected' : '' ?>>
                    <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit"
            onclick="document.getElementById('action-field').value='deploy'"
            class="primary">
            Deploy Selected Branch
        </button>

        <button type="submit"
            onclick="document.getElementById('action-field').value='set_default'"
            class="secondary">
            Set as Default
        </button>
    </form>

    <h3>Deploy Log</h3>
    <div id="log-output"></div>
</div>

<script>
const branchSelect = document.getElementById('branch-select');
const commitBox = document.getElementById('commit-info');

function loadCommit(branch) {
    fetch('/commit.php?branch=' + branch)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                commitBox.innerHTML = "Error loading commit.";
                return;
            }

            commitBox.innerHTML = `
                <strong>Latest Commit</strong><br>
                Hash: ${data.sha}<br>
                Author: ${data.author}<br>
                Date: ${data.date}<br>
                Message: ${data.message}
            `;
        })
        .catch(() => {
            commitBox.innerHTML = "Commit fetch failed.";
        });
}

function loadLogs() {
    fetch('/logs.php')
        .then(res => res.text())
        .then(data => {
            const logBox = document.getElementById('log-output');
            logBox.innerHTML = data;
            logBox.scrollTop = logBox.scrollHeight;
        });
}

branchSelect.addEventListener('change', function() {
    loadCommit(this.value);
});

setInterval(loadLogs, 2000);
loadLogs();
loadCommit(branchSelect.value);
</script>

</body>
</html>