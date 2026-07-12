# Mapa de Campos — `MOD.ENROL` · Métodos de inscrição (to-be — proposta, ainda sem código)

Nova **4ª aba do modal Participantes** (`MOD.PART`), depois de Coortes / Usuários / Papéis.
Configura **em massa** os métodos de inscrição dos cursos vinculados às competências do template,
sempre amarrado a coorte. Diferente dos demais mapas, este é **to-be** (spec): as origens são os
arquivos **planejados**, não código shipado. Wireframe interativo aprovado em 2026-07-11 — ver
[[dimensions-enrolment-methods-tab]] na memória do projeto.

- **Mustache (planejado):** `templates/central/enrol_methods.mustache` (host da aba), montado por `participants_manager.mustache` como 4ª aba.
- **AMD (planejado):** `amd/src/central/enrol_methods.js` (mount + accordion lazy + seleção em massa), reusa `action_button.js`.
- **WS (planejado):** listar competências+cursos por template (paginado), status por `(curso, método, coorte)`, enfileirar aplicar/remover (task adhoc), consultar fila.
- **Task (planejado):** adhoc por `(courseid, método, cohortid)` — cria/remove instância `enrol_cohort`/`enrol_self`.

## Configuração da ação

| ID | Rótulo | Tipo | Dados | Regra / notas |
| --- | --- | --- | --- | --- |
| `PART-TAB-ENROL` | Métodos de inscrição | aba | `data-target-pane="pane-enrol"` | 4ª aba; monta `MOD.ENROL` |
| `ENROL-COHORT` | Coorte vinculado ao plano | select | coortes já vinculados ao template | obrigatório; troca recalcula status |
| `ENROL-METHOD` | Método | segmented | `sync` \| `self` | autoinscrição é sempre restrita ao coorte (`customint5`) |
| `ENROL-ROLE` | Papel atribuído | select | papéis equivalentes a estudante (`gradebookroles` + atribuíveis no contexto) | único por operação; default Estudante |
| `ENROL-HINT` | descrição do método | texto | — | atualiza com o método selecionado |

## Filtros

| ID | Rótulo | Tipo | Dados | Regra / notas |
| --- | --- | --- | --- | --- |
| `ENROL-CAT` | Categoria de curso | select | categorias dos cursos vinculados | filtra as linhas |
| `ENROL-HIDDEN` | Mostrar cursos ocultos | switch | — | ocultos escondidos por padrão |
| `ENROL-VISCOUNT` | contador de visíveis | texto | — | — |

## Lista competências × cursos

| ID | Rótulo | Tipo | Dados | Regra / notas |
| --- | --- | --- | --- | --- |
| `ENROL-TREE` | `[sem rótulo]` | contêiner-JS | competências do template + cursos via `course_competency` | accordion; carga sob demanda ao expandir; lista pagina |
| `ENROL-GROUP` | competência | linha-cabeçalho | checkbox "selecionar todos" + nome + contagem + chevron | cursos sem permissão **não entram** na contagem |
| `ENROL-ROW` | curso | linha | — | uma por curso editável |
| `ENROL-ROW-CB` | selecionar | checkbox | — | vira ⟳ (travado) quando a combinação está processando |
| `ENROL-ROW-NAME` | código + nome truncado | texto | `shortname` + trecho do `fullname` | nome completo só no detalhe |
| `ENROL-STATUS` | status | pill | Configurado / Não configurado / Processando | reflete **só** o método+coorte selecionado |
| `ENROL-INFO` | detalhes do curso | botão | abre `ENROL-DETAIL` | — |

## Ações e estados

| ID | Rótulo | Tipo | Dados | Regra / notas |
| --- | --- | --- | --- | --- |
| `ENROL-SELCOUNT` | nº selecionados | contador | — | habilita/desabilita os botões |
| `ENROL-PROC` | nº em processamento | indicador | tarefas na fila do método+coorte atual | — |
| `ENROL-APPLY` | Aplicar método | botão | — | cria só onde falta (idempotente); enfileira task |
| `ENROL-REMOVE` | Remover método | botão | — | abre `ENROL-CONFIRM`; remove só onde existe |
| `ENROL-CONFIRM` | confirmação de remoção | modal | — | avisa que desmatricula conforme `unenrolaction` do método |
| `ENROL-DETAIL` | detalhes do curso | modal | nome completo, categoria, visível, competência, status dos 2 métodos + última execução | link `/course/view.php?id=` em nova aba |
| `ENROL-EMPTY` | sem coorte vinculado | empty state | — | pede para vincular coorte na aba Coortes |

## Concorrência (regra de negócio)

Cada `(curso, método, coorte)` é uma **tarefa independente**; combinações diferentes rodam em
paralelo. Enquanto uma está em processamento fica **indisponível para reenfileirar** (dedup por
chave `courseid + método + cohortid`, Lock API, idempotência). O mesmo curso segue livre para outra
combinação. Ver [[dimensions-concurrency-audit-2026-07]] para o padrão de locks da Central.
