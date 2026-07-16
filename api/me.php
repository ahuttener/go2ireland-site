<?php
/** Quem está logado. Único GET da API — não muda estado, então não exige X-KM. */
declare(strict_types=1);
require __DIR__ . '/_boot.php';
km_session();
$id = km_user_id();
if ($id === null) km_json(['ok' => true, 'user' => null]);
$st = km_db()->prepare('SELECT name, email FROM km_users WHERE id = ?');
$st->execute([$id]);
$u = $st->fetch();
km_json(['ok' => true, 'user' => $u ?: null]);
