# Inventário — Report Builder no `local_dimensions`

Base: `main` (5.3, `2026100505`) em 2026-10-17. O código de `classes/reportbuilder/` é o mesmo no
`MOODLE_502_STABLE` (só muda o `#[\Override]`). Core de referência: `~/dev/moodle-502`.

## 1. O que existe

### Datasources

| Datasource | Tabela principal (grão) | Entidades | Defaults |
|---|---|---|---|
| `dimensions_competencies` (estende o core `competencies`) | `competency_framework` → `competency` → `competency_usercomp` (1 linha por competência × usuário) | context, framework, competency, usercompetency, user, cohort (do core) + **`competency_extras`** (plugin) | herdados do core |
| `learning_plans` | `competency_plan` (1 linha por plano; ×N se usar coorte) | **`plan`**, **`template`** (com campos customizados), user (dono), cohort (coortes **do modelo**) | colunas: nome, status, usuário, modelo; filtros: status, prazo, modelo; condições: status, modelo visível |

### Entidades do plugin

| Entidade | Tabela | Colunas | Filtros/condições |
|---|---|---|---|
| `competency_extras` | `competency` (alias emprestado do core) | todos os campos customizados da área `competency` | idem |
| `plan` | `competency_plan` | name, description, status, duedate, timecreated, timemodified | name, status, duedate, timecreated, timemodified, **userid (número)** |
| `template` | `competency_template` | shortname, description, visible, duedate, timecreated, timemodified + **todos os campos customizados da área `lp`** | idem + campos customizados |

### Herdado do core (`core_competency`) pelo `dimensions_competencies`

| Entidade | Colunas |
|---|---|
| framework | name, description, idnumber, scale, visible, timecreated, timemodified |
| competency | name, description, idnumber, timecreated, timemodified |
| usercompetency | status, proficient, rating (**sem filtro de rating**) |

### Testes

Nenhum teste PHPUnit cobre `classes/reportbuilder/` (só a referência de strings em `lang_string_references_test`).

## 2. Cobertura das áreas pedidas

| Área | Situação | Tabelas core envolvidas |
|---|---|---|
| Competências com campos customizados | **Existe** (`dimensions_competencies` + `competency_extras`) | `competency`, `customfield_data` (área `competency`) |
| Competências vinculadas a cursos e atividades | **Falta** | `competency_coursecomp`, `competency_coursecompsetting`, `competency_modulecomp`, `competency_usercompcourse`, `course_modules` |
| Planos com campos customizados em entidade própria | **Feito** (`template_extras`, branch `feat/rb-template-extras`) | `competency_plan`, `competency_template`, `customfield_data` (área `lp`) |
| Evidências | **Falta** | `competency_evidence` (evidência de avaliação) e `competency_userevidence` + `competency_userevidencecomp` (prova de aprendizagem prévia) |

Observação: a área `lp` guarda dados por **modelo** (`competency_template`), não por **plano**
(`competency_plan`). Um plano individual (sem modelo) não tem campos customizados. Ver decisão D1.

## 3. O que adicionar

### 3.1 Entidades novas (reutilizáveis)

| Entidade | Tabela | Colunas propostas | Filtros |
|---|---|---|---|
| `template_extras` | `competency_template` (alias emprestado) | campos customizados da área `lp`, retirados de `template` | idem |
| `course_competency` **(feito)** | `competency_coursecomp` + `competency_coursecompsetting` | ruleoutcome (Nada / Evidência / Recomendar / Completar), sortorder, pushratingstouserplans (padrão do site quando o curso não tem linha de configuração), timecreated | ruleoutcome, pushratings |
| `module_competency` **(feito)** | `competency_modulecomp` (+ `course_modules` emprestado do datasource, `modules`) | **nome da atividade** (`get_fast_modinfo()`), **tipo de atividade**, ruleoutcome, overridegrade, sortorder, timecreated | tipo de atividade, ruleoutcome, overridegrade |
| `usercompetency_course` | `competency_usercompcourse` | proficient, rating (via JOIN de `scale`), timemodified | proficient, rating |
| `plan_competency` | subconsulta `plancomp ∪ templatecomp` | sortorder, rating no plano (`usercompplan` se o plano estiver concluído, senão `usercomp`), proficient | proficient, rating |
| `evidence` | `competency_evidence` | action (`ACTION_LOG` / `ACTION_COMPLETE` / `ACTION_OVERRIDE`), descrição (`get_string(descidentifier, desccomponent, desca)`), url, nota (rating via `scale`), note, timecreated | action, timecreated, rating |
| `user_evidence` | `competency_userevidence` + `competency_userevidencecomp` | name, description, url, nº de arquivos, timecreated | name, timecreated |

O rating via `scale`: `LEFT JOIN {scale} ON id = COALESCE(competency.scaleid, framework.scaleid)` e
callback que faz `explode(',', scale)` com memo estático por `scaleid`. Serve para todas as
colunas de rating (ver O4).

### 3.2 Datasources novos

| Datasource | Grão (tabela principal) | Entidades | Pergunta que responde |
|---|---|---|---|
| `course_competencies` | `competency_coursecomp` | course_competency, course (core), course_category (core), module_competency, course_module (core, `LEFT JOIN` por `cm.course = cc.courseid AND mc.competencyid = cc.competencyid`), competency (core), **competency_extras**, framework (core) | "Quais competências cada curso e cada atividade desenvolve, com qual regra e com quais tags/tipo do Dimensions?" |
| `course_competency_ratings` | `competency_usercompcourse` | usercompetency_course, user, course, competency, **competency_extras**, framework, cohort (do usuário) | "Como os alunos foram avaliados em cada competência dentro de cada curso?" |
| `learning_plan_competencies` | `competency_plan` × `plan_competency` | plan, template, **template_extras**, user, plan_competency, competency, **competency_extras**, framework | "Quais competências cada plano tem e em qual estágio está cada aluno?" |
| `competency_evidence` | `competency_evidence` | evidence, usercompetency (core, por `usercompetencyid`), user (aluno), user `actionuser` (quem gerou), context (core, de onde veio), competency, **competency_extras**, framework | "Quem gerou qual evidência, para qual competência, de qual curso/atividade, com qual nota?" |
| `competency_user_evidence` | `competency_userevidence` × `userevidencecomp` | user_evidence, user, competency, **competency_extras**, framework | "Quais provas de aprendizagem prévia cada aluno enviou e a quais competências estão ligadas?" |

A atividade só pode ter competência que já esteja no curso (regra do `core_competency\api`), por
isso `competency_coursecomp` funciona como tabela principal de curso + atividade.

### 3.3 Colunas que faltam nas entidades existentes

| Entidade | Adicionar |
|---|---|
| `plan` | revisor (entidade user com alias `reviewer`), modelo original (`origtemplateid`), "baseado em modelo" (sim/não), link para o plano, nº de competências, nº proficientes, % de progresso (subconsulta correlacionada; agregável) |
| `template` | categoria/contexto (`contextid`), link para o modelo, nº de planos, nº de coortes |
| `competency_extras` | competência-pai, caminho na hierarquia, escala efetiva, nº de cursos vinculados, nº de competências relacionadas |

## 4. Otimizações e correções

| # | Sev. | Onde | Problema | Proposta |
|---|---|---|---|---|
| O1 | **Feito** (branch `feat/rb-template-extras`) | `template` | Os campos customizados ficam misturados na entidade `template`; competências usam entidade própria. Impede reutilizar os campos do modelo em outros datasources | Extrair para `template_extras` (mesmo padrão de `competency_extras`), título "Modelo de plano: campos Dimensions" |
| O2 | **Feito** (D2) | `competency_handler::can_view()` / `lp_handler::can_view()` retornam `true` | O helper `custom_fields` usa `can_view($field, 0)` para decidir a disponibilidade. Todos os campos técnicos (`customscss`, cores, imagem de fundo, `customcard`, `displaymode`, `lockedcardmode`…) aparecem para qualquer autor de relatório | Expor só os campos de negócio (`tag1`, `tag2`, `type`, `template_idnumber` e os campos criados pelo admin). Filtrar por shortname antes do `add_column()`. Ver D2 |
| O3 | Alta | Ausente nos dois datasources | Sem `is_available()`: os datasources aparecem mesmo com competências desativadas no site | `public static function is_available(): bool { return \core_competency\api::is_enabled(); }` |
| O4 | Alta | `usercompetency:rating` (core, herdado) | Callback faz `new competency($id)` + `get_scale()` **por linha** (N+1, 2 queries por linha). Com milhares de linhas, o relatório fica lento | Coluna própria de rating com `JOIN` de `scale` + memo estático, e filtro de rating. Reportar ao tracker do Moodle (MDL) |
| O5 | Média | `plan` filtro `userid` | Filtro numérico por ID de usuário: UX ruim e redundante com a entidade `user` | Remover. Relatórios salvos que o usam perdem o filtro (o reportbuilder ignora o filtro ausente); registrar no CHANGELOG |
| O6 | Média | `learning_plans` entidade `cohort` | São as coortes **do modelo**, mas o título é "Coorte", confundível com a coorte do aluno | `set_entity_title('Coortes do modelo')` e adicionar uma entidade separada de coorte do usuário (`cohort_members`) |
| O7 | Média | `learning_plans` condição padrão `template:visible` | Com `LEFT JOIN`, planos individuais (sem modelo) somem quando a condição é "Sim" | Documentar na descrição da condição ou trocar o campo por `COALESCE(visible, 1)` |
| O8 | Média | Testes | Nenhum teste dos datasources. As subconsultas novas (`plan_competency`, rating) precisam de cobertura | Um teste por datasource com `core_reportbuilder_testcase::datasource_stress_test_columns`, `…_columns_aggregation` e `…_conditions`, mais um teste de conteúdo com gerador |
| O9 | Baixa | `dimensions_competencies` | Defaults herdados do core não mostram nenhum campo Dimensions | Sobrescrever `get_default_columns()` incluindo `competency_extras:customfield_local_dimensions_type` e `tag1` |
| O10 | Baixa | Entidades do plugin | `add_joins($this->get_joins())` em cada coluna é redundante no 5.3 (o core removeu nas próprias entidades) | Remover só no `main`; manter nos ramos 4.5/5.1/5.2 |

## 5. Decisões

### Tomadas

| # | Decisão | Escolha |
|---|---|---|
| D1 | "Planos com campos customizados em nova categoria" | **Entidade de reportbuilder separada** (`template_extras`) para os campos que já existem no **modelo**, sem criar dado novo por plano. Descartado: nova área de customfield em `competency_plan` (dado por aluno, exigiria handler, formulário, privacy provider e backup/restore). |
| D2 | Quais campos do plugin aparecem nos relatórios | **Só os de negócio**: `tag1`, `tag2`, `type`, `template_idnumber` e os campos criados pelo admin. Os de apresentação ficam em `constants::REPORT_HIDDEN_CFIELDS`. |
| D3 | Curso e atividade | **Um datasource** (`course_competencies`), tabela principal `competency_coursecomp`. |

### Pendentes

| # | Decisão | Opções |
|---|---|---|
| D4 | Ramos | só `main` (5.3) · cherry-pick para 5.2, 5.1 e 4.5 (ver seção 6) |

## 6. Portabilidade para o Moodle 4.5 (e 5.1)

Os ramos `MOODLE_405_STABLE`, `MOODLE_501_STABLE` e `MOODLE_502_STABLE` já têm o mesmo
`classes/reportbuilder/`. Comparação do core 4.5 com o 5.2:

| API usada | 4.5 | 5.1 | 5.2 | Observação |
|---|---|---|---|---|
| Datasource `core_competency\…\competencies` e entidades framework/competency/usercompetency | sim | sim | sim | mesmo código (inclusive o N+1 do rating) |
| `core_reportbuilder\local\helpers\custom_fields` (disponibilidade por `can_view`) | sim | sim | sim | O2 vale para todos |
| `core_reportbuilder\local\entities\{course, user}`, `core_course\…\course_category`, `core_cohort\…\cohort`, `core\reportbuilder\local\entities\context` | sim | sim | sim | |
| `set_table_alias`, `set_table_join_alias`, `set_entity_title`, `is_available()` | sim | sim | sim | |
| `core_reportbuilder_testcase::datasource_stress_test_*` | sim | sim | sim | |
| `core_course\reportbuilder\local\entities\course_module` | **não** | **não** | sim | MDL-86699 |

| Item | Porta para 4.5? |
|---|---|
| `template_extras` (D1), O1–O9 | Sim, sem mudança de API. Só sai o `#[\Override]` e os atributos de teste vão para docblock |
| `course_competency`, `module_competency`, `usercompetency_course`, `plan_competency`, `evidence`, `user_evidence` | Sim (tabelas core existem desde o 3.1) |
| `course_competencies` com dados da atividade | Sim, com uma entidade própria mínima de atividade (`course_modules` + `modules`, nome via `get_fast_modinfo()` com memo por curso) nos ramos 4.5 e 5.1. No 5.2+ usar a entidade core |
| O10 | Não se aplica (só `main`) |
