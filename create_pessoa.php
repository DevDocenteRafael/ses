<?php
$db = new PDO('sqlite:database/database.sqlite');
$email = 'aluno@teste.com';
$telefone = '61999999999';
$senha = password_hash('123456', PASSWORD_BCRYPT);
$sql = 'INSERT INTO pessoa (nome, email, telefone, senha, data_cadastro, created_at, updated_at) VALUES (?, ?, ?, ?, datetime("now"), datetime("now"), datetime("now"))';
$stmt = $db->prepare($sql);
$result = $stmt->execute(['João Aluno Teste', $email, $telefone, $senha]);
if ($result) {
    echo 'Pessoa criada com sucesso!' . "\n";
    echo 'Email: ' . $email . "\n";
    echo 'Senha: 123456' . "\n";
    echo 'Telefone: ' . $telefone;
} else {
    echo 'Erro ao criar pessoa';
}
