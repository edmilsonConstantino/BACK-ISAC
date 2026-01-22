<?php
require_once __DIR__ . '/../config/bootstrap.php';

echo "🌱 Iniciando seed da tabela students...\n";

try {
    // Verifica se já existem estudantes cadastrados
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['count'] > 0) {
        echo "⚠️  Tabela 'students' já possui dados ({$row['count']} registros). Seed ignorado.\n";
        exit(0);
    }

    // Função para gerar números de matrícula aleatórios
    function generateMatricula() {
        return 'M' . rand(1000, 9999);
    }

    // Array de estudantes de exemplo
    $students = [
        [
            'nome' => 'João Aluno',
            'email' => 'joao.aluno@example.com',
            'telefone' => '843123456',
            'data_nascimento' => '2002-05-12',
            'endereco' => 'Rua A, Bairro B, Cidade C',
            'numero_matricula' => generateMatricula(),
            'curso' => 'Engenharia Informática',
            'ano_ingresso' => '2021',
            'status' => 'ativo'
        ],
        [
            'nome' => 'Maria Estudante',
            'email' => 'maria.estudante@example.com',
            'telefone' => '843654321',
            'data_nascimento' => '2003-03-20',
            'endereco' => 'Rua X, Bairro Y, Cidade Z',
            'numero_matricula' => generateMatricula(),
            'curso' => 'Gestão Empresarial',
            'ano_ingresso' => '2022',
            'status' => 'ativo'
        ],
        [
            'nome' => 'Pedro Silva',
            'email' => 'pedro.silva@example.com',
            'telefone' => '843987654',
            'data_nascimento' => '2001-11-05',
            'endereco' => 'Avenida Central, Cidade D',
            'numero_matricula' => generateMatricula(),
            'curso' => 'Economia',
            'ano_ingresso' => '2020',
            'status' => 'ativo'
        ]
    ];

    // Inserir estudantes no banco
    $stmt = $pdo->prepare("
        INSERT INTO students (
            nome, email, telefone, data_nascimento, endereco, numero_matricula,
            curso, ano_ingresso, status
        ) VALUES (
            :nome, :email, :telefone, :data_nascimento, :endereco, :numero_matricula,
            :curso, :ano_ingresso, :status
        )
    ");

    foreach ($students as $student) {
        $stmt->execute($student);
        echo "✅ Estudante '{$student['nome']}' inserido com sucesso!\n";
    }

    echo "\n🎉 Seed da tabela 'students' concluído!\n";

} catch (PDOException $e) {
    echo "❌ Erro ao popular tabela 'students': " . $e->getMessage() . "\n";
    exit(1);
}
