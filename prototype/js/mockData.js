const mockData = {
    students: [
        {
            id: 1,
            name: "Lucas Silva",
            age: 19,
            course: "Técnico em Informática",
            unit: "Senac Taguatinga",
            city: "Ceilândia",
            status: "Concluído",
            skills: ["JavaScript", "HTML/CSS", "Pacote Office"],
            availability: "Tarde/Noite",
            type: "Estágio",
            score: 95,
            lastUpdate: "2026-03-10",
            cpf: "123.456.789-00",
            email: "lucas.silva@aluno.df.senac.br",
            phone: "(61) 99876-5432",
            accessStatus: "Liberado",
            senacCourses: [
                { name: "Lógica de Programação", unit: "Senac Taguatinga", year: 2024, hours: 40 },
                { name: "Desenvolvimento Web Básico", unit: "Senac Taguatinga", year: 2024, hours: 60 },
                { name: "Banco de Dados SQL", unit: "Senac Taguatinga", year: 2024, hours: 40 }
            ]
        },
        {
            id: 2,
            name: "Mariana Oliveira",
            age: 34,
            course: "Qualificação em Gastronomia",
            unit: "Senac Brasília",
            city: "Plano Piloto",
            status: "Em andamento (50%)",
            skills: ["Cozinha Internacional", "Gestão de Estoque", "Liderança"],
            availability: "Integral",
            type: "CLT",
            score: 88,
            lastUpdate: "2026-03-12",
            cpf: "987.654.321-11",
            email: "mariana.oliveira@aluno.df.senac.br",
            phone: "(61) 98765-4321",
            accessStatus: "Liberado",
            senacCourses: [
                { name: "Boas Práticas na Manipulação de Alimentos", unit: "Senac Brasília", year: 2025, hours: 20 },
                { name: "Confeitaria Básica", unit: "Senac Brasília", year: 2025, hours: 40 }
            ]
        },
        {
            id: 3,
            name: "Ana Costa",
            age: 17,
            course: "Jovem Aprendiz em Serviços Administrativos",
            unit: "Senac Gama",
            city: "Gama",
            status: "Concluído",
            skills: ["Atendimento", "Organização", "Comunicação"],
            availability: "Manhã",
            type: "Jovem Aprendiz",
            score: 92,
            lastUpdate: "2026-03-15",
            cpf: "456.789.123-22",
            email: "ana.costa@aluno.df.senac.br",
            phone: "(61) 97654-3210",
            accessStatus: "Liberado",
            senacCourses: [
                { name: "Informática Básica", unit: "Senac Gama", year: 2024, hours: 60 },
                { name: "Técnicas de Arquivamento", unit: "Senac Gama", year: 2024, hours: 30 }
            ]
        }
    ],
    companies: [
        {
            id: 1,
            name: "Tech Solutions DF",
            activity: "Tecnologia da Informação",
            status: "Liberado",
            cnpj: "98.765.432/0001-11",
            responsible: "Roberto Santos"
        },
        {
            id: 2,
            name: "Restaurante Sabor & Arte",
            activity: "Gastronomia",
            status: "Bloqueado",
            cnpj: "12.345.678/0001-90",
            responsible: "Clara Mendes"
        },
        {
            id: 3,
            name: "Farmácias Saúde Total",
            activity: "Saúde",
            status: "Liberado",
            cnpj: "11.222.333/0001-44",
            responsible: "João Souza"
        }
    ],
    invitations: [
        {
            id: 1,
            studentId: 1,
            company: "Tech Solutions DF",
            date: "2026-03-16",
            status: "Pendente",
            position: "Estagiário de Desenvolvimento"
        }
    ],
    stats: {
        activeStudents: 1245,
        registeredCompanies: 87,
        successfulHires: 45
    }
};
// Functions to interact with mock data
const MockAPI = {
    getStudents: () => {
        // Load from localStorage if present to keep simulated state
        const stored = localStorage.getItem('mockStudents');
        if (stored) {
            return JSON.parse(stored);
        }
        localStorage.setItem('mockStudents', JSON.stringify(mockData.students));
        return mockData.students;
    },
    getStudentById: (id) => {
        const students = MockAPI.getStudents();
        return students.find(s => s.id == id);
    },
    getCompanies: () => {
        const stored = localStorage.getItem('mockCompanies');
        if (stored) {
            return JSON.parse(stored);
        }
        localStorage.setItem('mockCompanies', JSON.stringify(mockData.companies));
        return mockData.companies;
    },
    getPendingCompanies: () => {
        return MockAPI.getCompanies().filter(c => c.status === "Bloqueado" || c.status === "Aguardando Aprovação");
    },
    getInvitationsByStudent: (studentId) => mockData.invitations.filter(i => i.studentId == studentId),
    getStats: () => mockData.stats,

    // Simulate updating
    updateStudent: (id, data) => {
        const students = MockAPI.getStudents();
        const index = students.findIndex(s => s.id == id);
        if (index !== -1) {
            students[index] = { ...students[index], ...data };
            localStorage.setItem('mockStudents', JSON.stringify(students));
            return true;
        }
        return false;
    },
    updateCompany: (id, data) => {
        const companies = MockAPI.getCompanies();
        const index = companies.findIndex(c => c.id == id);
        if (index !== -1) {
            companies[index] = { ...companies[index], ...data };
            localStorage.setItem('mockCompanies', JSON.stringify(companies));
            return true;
        }
        return false;
    }
};
if (typeof window !== 'undefined') {
    window.MockAPI = MockAPI;
}