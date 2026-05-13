<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Acesso negado. Apenas administradores.';
    exit;
}

// Buscar utilizadores
$stmt = $conn->prepare('SELECT id, email, name, role FROM users ORDER BY id ASC');
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
?>
<?php if (!empty($_SESSION['flash'])): ?>
    <div style="max-width:900px;margin:10px 0;padding:10px;border-radius:6px;background:#eef2ff;color:#0b6e4f;"><?= htmlspecialchars($_SESSION['flash']) ?></div>
<?php unset($_SESSION['flash']); endif; ?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gerir Utilizadores</title>
    <?php require_once __DIR__ . '/../inc/site_head.php'; ?>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#111}
        table{border-collapse:collapse;width:100%;max-width:900px}
        th,td{padding:10px;border:1px solid #ddd;text-align:left}
        th{background:#f3f4f6}
        .btn{display:inline-block;padding:8px 12px;background:#0b6e4f;color:#fff;border-radius:6px;text-decoration:none}
        .danger{background:#b91c1c}
        select{padding:6px}
        .note{color:#6b7280;font-size:14px;margin-bottom:12px}
    </style>
</head>
<body>
    <h1>Gerir Utilizadores</h1>
    <p class="note">Como administrador, pode alterar o role dos utilizadores (não pode alterar o seu próprio role aqui).</p>
    <p><a href="home.php" class="btn">Voltar</a></p>

    <table>
        <thead>
            <tr><th>ID</th><th>Nome</th><th>Email</th><th>Role</th><th>Ação</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['id']) ?></td>
                    <td><?= htmlspecialchars($u['name'] ?: '') ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td>
                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                            <em>Seu utilizador</em>
                        <?php else: ?>
                            <form method="post" action="process_set_role.php" style="display:inline">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <select name="role">
                                    <option value="user" <?= $u['role']==='user' ? 'selected' : '' ?>>user</option>
                                    <option value="editor" <?= $u['role']==='editor' ? 'selected' : '' ?>>editor</option>
                                    <option value="admin" <?= $u['role']==='admin' ? 'selected' : '' ?>>admin</option>
                                </select>
                                <button type="submit" class="btn">Guardar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
