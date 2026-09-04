# Gestão de Consultas — Hospital de Malanje

Plataforma web para coordenar pacientes, consultas, fila de atendimento e acompanhamento operacional do Hospital de Malanje, sem módulos de ERP.

## Run & Operate

- `pnpm --filter @workspace/hospital-malanje run dev` — iniciar a aplicação PHP/Tailwind
- `pnpm --filter @workspace/hospital-malanje run build` — compilar o CSS e preparar a aplicação
- Required env: `DATABASE_URL` — ligação PostgreSQL

## Stack

- PHP 8.4 com sessões PHP e PDO PostgreSQL
- Tailwind CSS 4 compilado localmente
- Organização MVC dentro de `artifacts/hospital-malanje`
- PostgreSQL para contas, pacientes, consultas e mensagens

## Where things live

- `artifacts/hospital-malanje/public/index.php` — front controller da aplicação
- `artifacts/hospital-malanje/php/app/Controllers/` — controladores MVC
- `artifacts/hospital-malanje/php/app/Models/` — acesso aos dados
- `artifacts/hospital-malanje/php/app/Views/` — páginas e layout
- `artifacts/hospital-malanje/php/app/Core/` — base de dados, sessões, CSRF e renderização
- `artifacts/hospital-malanje/public/app.css` — CSS Tailwind compilado

## Architecture decisions

- O produto é deliberadamente focado no circuito de consultas; compras, stock, faturação, salários e contabilidade ficam fora do escopo.
- A agenda usa estados operacionais (`scheduled`, `confirmed`, `waiting`, `in_progress`, `completed`, `cancelled`, `no_show`) para refletir a fila real de atendimento.
- Datas de agenda e nascimento são armazenadas como dias de calendário; timestamps são usados apenas para criação e atividade.
- O dashboard e a fila consultam a API e a base de dados; os dados iniciais servem para a primeira abertura da aplicação não ficar vazia.

## Product

O sistema permite acompanhar o resumo diário de consultas, pesquisar e registar pacientes, marcar consultas por departamento e profissional, avançar o estado da fila, consultar o histórico de cada paciente, acompanhar a atividade recente e ver indicadores de atendimento.

## User preferences

- O utilizador pediu uma plataforma de gestão de consultas hospitalares, não um ERP.

## Pointers

- See the `pnpm-workspace` skill for workspace structure, TypeScript setup, and package details
