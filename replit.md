# Gestão de Consultas — Hospital de Malanje

Plataforma web para coordenar pacientes, consultas, fila de atendimento e acompanhamento operacional do Hospital de Malanje, sem módulos de ERP.

## Run & Operate

- `pnpm --filter @workspace/api-server run dev` — run the API server (port 5000)
- `pnpm run typecheck` — full typecheck across all packages
- `pnpm run build` — typecheck + build all packages
- `pnpm --filter @workspace/api-spec run codegen` — regenerate API hooks and Zod schemas from the OpenAPI spec
- `pnpm --filter @workspace/db run push` — push DB schema changes (dev only)
- Required env: `DATABASE_URL` — Postgres connection string

## Stack

- pnpm workspaces, Node.js 24, TypeScript 5.9
- API: Express 5
- DB: PostgreSQL + Drizzle ORM
- Validation: Zod (`zod/v4`), `drizzle-zod`
- API codegen: Orval (from OpenAPI spec)
- Build: esbuild (CJS bundle)

## Where things live

- `artifacts/hospital-malanje/src/` — interface, shell e páginas operacionais
- `artifacts/api-server/src/routes/hospital.ts` — endpoints do domínio hospitalar
- `artifacts/api-server/src/lib/hospital-data.ts` — composição das respostas de consultas
- `artifacts/api-server/src/lib/seed.ts` — dados iniciais de demonstração
- `lib/api-spec/openapi.yaml` — contrato fonte das operações e tipos
- `lib/db/src/schema/` — tabelas persistentes do domínio

## Architecture decisions

- O produto é deliberadamente focado no circuito de consultas; compras, stock, faturação, salários e contabilidade ficam fora do escopo.
- A agenda usa estados operacionais (`scheduled`, `confirmed`, `waiting`, `in_progress`, `completed`, `cancelled`, `no_show`) para refletir a fila real de atendimento.
- Datas de agenda e nascimento são armazenadas como dias de calendário; timestamps são usados apenas para criação e atividade.
- O dashboard e a fila consultam a API e a base de dados; os dados iniciais servem para a primeira abertura da aplicação não ficar vazia.

## Product

O sistema permite acompanhar o resumo diário de consultas, pesquisar e registar pacientes, marcar consultas por departamento e profissional, avançar o estado da fila, consultar o histórico de cada paciente, acompanhar a atividade recente e ver indicadores de atendimento.

## User preferences

- O utilizador pediu uma plataforma de gestão de consultas hospitalares, não um ERP.

## Gotchas

- Depois de alterar `lib/api-spec/openapi.yaml`, executar `pnpm --filter @workspace/api-spec run codegen` antes de validar os pacotes.
- O gerador atual deve receber campos numéricos como `number` no OpenAPI; `integer` gera `zod.int()`, incompatível com o catálogo Zod 3.25 desta base.

## Pointers

- See the `pnpm-workspace` skill for workspace structure, TypeScript setup, and package details
