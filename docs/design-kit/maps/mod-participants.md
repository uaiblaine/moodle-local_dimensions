# Mapa de Campos — `MOD.PART` · Modal Participantes (as-is)

Modal hospedeiro com cabeçalho + abas **Coortes / Usuários / Papéis**. A aba Coortes monta
`MOD.COHORT`; a grade Usuários é inline; a aba Papéis monta `MOD.ROLES` (só se `canassignroles`).

- **Mustache:** [`templates/central/participants_manager.mustache`](../../../templates/central/participants_manager.mustache) (host), [`cohort_manager.mustache`](../../../templates/central/cohort_manager.mustache), [`roles_manager.mustache`](../../../templates/central/roles_manager.mustache)
- **AMD:** [`participants_manager.js`](../../../amd/src/central/participants_manager.js), [`cohort_manager.js`](../../../amd/src/central/cohort_manager.js), [`participants_users.js`](../../../amd/src/central/participants_users.js), [`roles_manager.js`](../../../amd/src/central/roles_manager.js)
- **To-be no DS:** `cohort-assign.html` (estilo gestão de grupos + sync).

## Host + abas

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-HEADER` | nome do template | heading | `participants_manager.mustache:36` | `templatename` | — |
| `PART-TAB-COHORTS` | Coortes | aba | `participants_manager.mustache:40` | `data-target-pane="pane-cohorts"` | ativa por padrão |
| `PART-TAB-USERS` | Usuários | aba | `participants_manager.mustache:47` | `data-target-pane="pane-users"` | — |
| `PART-TAB-ROLES` | Papéis | aba | `participants_manager.mustache:55` | `data-target-pane="pane-roles"` | só se `canassignroles` |
| `PART-TAB-ENROL` | Métodos de inscrição | aba | _to-be_ | `data-target-pane="pane-enrol"` | nova 4ª aba → monta `MOD.ENROL` (ver [`mod-enrolmethods.md`](mod-enrolmethods.md)) |

## Aba Coortes (`MOD.COHORT`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `COHORT-ADD` | Adicionar coorte | select/autocomplete | `cohort_manager.mustache:35` | `data-region="cohort-add"` | — |
| `COHORT-HEAD` | Coorte · Membros · Planos · (ações) | cabeçalho | `cohort_manager.mustache:41-46` | — | 4ª coluna sem rótulo |
| `COHORT-ROWS` | `[sem rótulo]` | contêiner-JS | `cohort_manager.mustache:48` | `data-region="cohort-rows"` | linhas via `cohort_manager.js` |

## Aba Usuários (inline)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-COHORTFILTER` | Filtrar por coorte | select | `participants_manager.mustache:73` | `data-region="participant-cohort"` | — |
| `PART-SEARCH` | Buscar | input texto | `participants_manager.mustache:80` | `data-region="participant-search"` | — |
| `PART-INDIVIDUAL` | Mostrar individuais | switch | `participants_manager.mustache:84` | `data-region="participant-individual"` | — |
| `PART-ADD` | Adicionar participante | select/autocomplete | `participants_manager.mustache:95` | `data-region="participant-add"` | — |
| `PART-HEAD` | Usuário · Status · Modelo · Coorte · Individual · (ações) | cabeçalho | `participants_manager.mustache:101-107` | — | 6ª coluna sem rótulo |
| `PART-ROWS` | `[sem rótulo]` | contêiner-JS | `participants_manager.mustache:110` | `data-region="participant-rows"` | linhas via `participants_users.js` |
| `PART-SENTINEL` | `[sem rótulo]` | sentinela | `participants_manager.mustache:112` | `data-region="participant-sentinel"` | scroll infinito |

## Aba Papéis (`MOD.ROLES`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ROLES-NOROLES` | aviso sem papéis | alerta | `roles_manager.mustache:31` | `data-region="role-noroles"` | `hidden` até JS |
| `ROLES-NOCOHORTS` | aviso sem coortes | alerta | `roles_manager.mustache:34` | `data-region="role-nocohorts"` | `hidden` até JS |
| `ROLES-USER` | Selecionar usuário | select | `roles_manager.mustache:43` | `data-region="role-user"` | — |
| `ROLES-ROLE` | Selecionar papel | select | `roles_manager.mustache:49` | `data-region="role-role"` | — |
| `ROLES-COHORT` | Selecionar coorte | select | `roles_manager.mustache:55` | `data-region="role-cohort"` | — |
| `ROLES-ADD` | Adicionar | botão | `roles_manager.mustache:57` | `data-action="role-add"` | — |
| `ROLES-HEAD` | Usuário · Papel · Coorte · Status · (ações) | cabeçalho | `roles_manager.mustache:64-69` | — | 5ª coluna sem rótulo |
| `ROLES-ROWS` | `[sem rótulo]` | contêiner-JS | `roles_manager.mustache:72` | `data-region="role-rows"` | linhas via `roles_manager.js` |
| `ROLES-NOTES` | notas (background/global) | texto | `roles_manager.mustache:74-75` | — | atribuição assíncrona/global |
