<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/Database.php';

class User
{
    public function findByUsername(string $username): ?array
    {
        $pdo = Database::getConnection();

        $sql = 'SELECT u.id, u.username, u.password, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id = u.rol_id
                WHERE u.username = :username
                LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user !== false ? $user : null;
    }
}
