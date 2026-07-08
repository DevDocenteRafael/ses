<?php
$db = new PDO('sqlite:database/database.sqlite');
$email = 'aluno@teste.com';
$password = password_hash('123456', PASSWORD_BCRYPT);
$sql = 'INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, datetime("now"), datetime("now"))';
$stmt = $db->prepare($sql);
$stmt->execute(['João Aluno', $email, $password]);
echo 'Usuário criado com sucesso!
Email: ' . $email . '
Senha: 123456';
