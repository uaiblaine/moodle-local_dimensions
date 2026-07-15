# Mapa de Campos — `MOD.ENROL` · Métodos de inscrição (as-is)

**4ª aba** do modal Participantes (`MOD.PART`), depois de Coortes / Usuários / Atribuir papéis.
Configura **em massa** os métodos de inscrição dos cursos vinculados às competências do template,
sempre amarrado a um coorte do plano. O pane nasce **vazio** no host
(`participants_manager.mustache:150-151`) e é montado por `enrol_methods.js:1010-1033`.

- **Mustache:** [`enrol_methods.mustache`](../../../templates/central/enrol_methods.mustache) (129, esqueleto da aba), [`enrol_group.mustache`](../../../templates/central/enrol_group.mustache) (65, um grupo do accordion), [`enrol_detail.mustache`](../../../templates/central/enrol_detail.mustache) (82, corpo do modal de detalhe)
- **AMD:** [`enrol_methods.js`](../../../amd/src/central/enrol_methods.js) (1033) — reusa `action_button.js` (`iconButton`, `:38-49`) e `errors.js` (`notifyError`)
- **WS (5, todos em `db/services.php:346-386`):** `list_enrol_competencies` (raízes paginadas + *bootstrap* de mount), `list_enrol_courses` (linhas com o status dos **dois** métodos), `queue_enrol_action`, `get_enrol_queue_status`, `set_enrol_instance_status`
- **Task:** [`process_enrol_method`](../../../classes/task/process_enrol_method.php) — adhoc por `(courseid, método, cohortid)`
- **Helper:** [`classes/local/enrol_methods.php`](../../../classes/local/enrol_methods.php) — `eligible_roles()` (`:58-73`), `default_roleid()` (`:81-89`)
- **CSS:** [`styles.css:5690-5755`](../../../styles.css)

> **Resync 2026-07-15 — este mapa era uma _spec_, e o código passou por cima dela na mesma noite.**
> Medido, não estimado:
>
> - **Zero refs quebradas — porque havia zero refs.** A versão anterior (`0b3782c`) **não tinha
>   coluna `Origem`**: o cabeçalho das tabelas era `| ID | Rótulo | Tipo | Dados | Regra / notas |`.
>   Um `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+'` no arquivo antigo devolve **0**. Não é o
>   estrago dos outros mapas (Task 7: 23/23; Task 9: 21/21; Task 10: 12/24) — aqui a coluna de
>   proveniência **faltava inteira**. As 22 IDs existiam sem uma única origem.
> - **A janela real é de 69 minutos, não 86.** O mapa entrou em `0b3782c` (**2026-07-11 21:53:13**,
>   autor e committer batem) e o `3d1d5cb` shipou a aba em **23:03:05** — `(1783821785 − 1783817593)
>   / 60 = 69`. **Não existe commit às 21:37** (a janela 21:00–23:30 tem 8 commits; nenhum nesse
>   minuto). O par "21:37 / 86 minutos" que circula no `mod-participants.md:180` está errado nos dois
>   números. E o **primeiro** código do recurso é anterior à aba: a task em `5df19b7` (22:41, +48 min)
>   e os WSes em `ee7a9e8` (22:51, +58 min) — os três bullets "planejado" do mapa antigo já eram
>   falsos antes da aba existir.
> - **Oito commits passaram por cima** de `enrol_methods.js`: `3d1d5cb` (aba), `432195c` (polish do
>   1º teste manual), `1d15e9f` (atalho de plugins + portão de ambos-desabilitados), `545ba17`
>   (accordion vira **tabela rotulada** + contraste), `33f7697` (linhas **DOM-built**, remove
>   `enrol_row.mustache`), `a5ef9a8` (toggle por linha), `8eea9ef` (toast + barra distribuída +
>   segmento primário), `ec9d813` (busca de competências).
> - **O que o mapa antigo não tinha como ter:** a busca (`ENROL-SEARCH`), o portão de
>   ambos-desabilitados (`ENROL-DISABLED`), os **três** botões de atualizar, o toggle
>   habilitar/desabilitar por linha (`ENROL-TOGGLE-STATUS`), os dois "Carregar mais", a coluna de
>   papel por linha, o `<caption>`/`<thead>` da tabela, e todo o `bootstrap` de mount.
> - **`ENROL-METHOD` não é `sync`.** O mapa antigo dizia `sync | self`; o código diz
>   **`cohort | self`** (`enrol_methods.mustache:66`, `:70`) e o estado nasce `method: 'cohort'`
>   (`enrol_methods.js:1019`). **`sync` não existe em lugar nenhum** — o rótulo *visível* é que é
>   "Sincronização de coortes" (`central_enrol_method_cohort`). Confundir os dois quebra
>   `data-method`, o `state.method`, o argumento `method` dos 3 WSes e a chave da task.
> - **`enrol_row.mustache` foi deletado** em `33f7697`, com motivo registrado: o lint de Mustache
>   renderiza o template isolado e o validador de HTML **rejeita um fragmento `tr` solto**. As
>   linhas viraram `createElement` em `makeRow` (`:315-405`) — mesmo padrão das abas Usuários/Papéis.

## Portões — três regiões mutuamente exclusivas

As três nascem `hidden` no Mustache; `init()` revela **uma**. Os alerts são blocos simples de
propósito: o comentário de `enrol_methods.mustache:33-35` avisa que um `.d-flex` no próprio alert
**venceria** o atributo `hidden` (`display` é `!important` nas utilities), então o flex mora num
wrapper interno.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-ROOT` | `[sem rótulo]` | região/raiz | `enrol_methods.mustache:32` | `data-region="enrol"` · `.local-dimensions-enrol` | é o `state.root`; o listener delegado pousa nele (`enrol_methods.js:914`) |
| `ENROL-DISABLED` | aviso: os dois plugins desabilitados no site | alerta | `enrol_methods.mustache:36-43` | `data-region="enrol-disabled"` · `alert-warning` | `central_enrol_bothdisabled` (`:38`). Revelado por `enrol_methods.js:879-884` quando `!cohortenabled && !selfenabled` — a aba inteira fica inerte. Quem conserta é o `PART-LINK-ENROL`, que **só o admin do site vê** (ver o descasamento de fechaduras abaixo) |
| `ENROL-EMPTY` | aviso: nenhum coorte vinculado | alerta | `enrol_methods.mustache:44-51` | `data-region="enrol-empty"` · `alert-info` | `central_enrol_empty` (`:46`) manda o usuário para a **aba Coortes**. Revelado por `enrol_methods.js:886-890` quando `!cohortdata.cohorts.length` |
| `ENROL-MAIN` | `[sem rótulo]` | região principal | `enrol_methods.mustache:52` | `data-region="enrol-main"` | revelada em `enrol_methods.js:892`. **Tudo** o que segue mora aqui dentro — inclusive o 3º atualizar |
| `ENROL-REFRESH` | Atualizar | botão ×3 | `enrol_methods.mustache:39-41`, `:47-49`, `:108-110` | `data-action="enrol-refresh"` | um por região. `btn btn-outline-secondary btn-sm` + `<i class="fa fa-rotate me-1">` + `{{#str}}refresh, moodle{{/str}}`. Handler **próprio** (`enrol_methods.js:915-918`) → `init(state)`, **não** `reloadPane`. Ver a seção do IMP-05 |

## Barra de configuração

Uma linha `d-flex` com os três controles distribuídos (`enrol_methods.mustache:53`), a dica embaixo.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-COHORT` | **Coorte do plano** | select | `enrol_methods.mustache:58` | `data-region="enrol-cohort"` · `form-select` | rótulo em `:55-57`. Opções via `list_template_cohorts` (`enrol_methods.js:856-857`) — **os coortes já vinculados ao template**, não todos os do site. Trocar dispara `reload` (`:976-978`) |
| `ENROL-METHOD` | Método | grupo de botões | `enrol_methods.mustache:64-74` | `data-region="enrol-method"` · `role="group"` | **`cohort`** (`:66-69`, nasce `active`/`btn-primary`/`aria-pressed="true"`) e **`self`** (`:70-73`). Rotulado por `aria-labelledby` → o `<span>` de `:61-63` (não é `<label>`: não há um controle único a apontar). Trocar **não refaz fetch** — `applyMethodChange` (`:697-720`) repinta das `data-*` da linha |
| `ENROL-METHOD-OFF` | `[sem rótulo]` | regra de disponibilidade | `enrol_methods.js:831-838` | `button.disabled = !enabled` | cada segmento é desabilitado se o plugin correspondente estiver off no site (`enrol_is_enabled`, `list_enrol_competencies.php:202-203`). Se **só** `cohort` estiver off, o pane troca sozinho para `self` (`:836-838`) |
| `ENROL-ROLE` | **Papel atribuído** | select | `enrol_methods.mustache:80` | `data-region="enrol-role"` · `form-select` | rótulo em `:77-79`. `eligible_roles()` = `$CFG->gradebookroles` **∩** `get_default_enrol_roles($context)` (`classes/local/enrol_methods.php:58-73`) — gradebook **e** atribuível por inscrição. Default = arquétipo *student* quando elegível, senão o primeiro (`:81-89`). Trocar **não** recarrega (`enrol_methods.js:979-980`): só vale na próxima ação |
| `ENROL-HINT` | `[sem rótulo]` | texto | `enrol_methods.mustache:83` | `data-region="enrol-hint"` | `central_enrol_hint_cohort` / `_hint_self`, trocado em `enrol_methods.js:707-708` e `:901-902` |

## Barra de filtros

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-SEARCH` | Pesquisar competências | input texto | `enrol_methods.mustache:89-91` | `data-region="enrol-search"` · `.local-dimensions-enrol-search` | **faltava inteiro** (`ec9d813`). Rótulo `visually-hidden` (`:86-88`) **e** `placeholder` com a mesma string. Debounce de **300 ms** → `reload` (`enrol_methods.js:961-972`); o comentário de `:965-966` diz por que é server-side: a lista é paginada, um filtro client-side perderia as páginas não carregadas. Largura fixa `14rem` (`styles.css:5733-5735`) |
| `ENROL-CAT` | Categoria de curso | select | `enrol_methods.mustache:97-98` | `data-region="enrol-category"` | rótulo `visually-hidden` (`:94-96`). Opções = `central_enrol_categoryall` + as categorias **dos cursos vinculados** (`enrol_methods.js:822-825`; `list_enrol_competencies.php:185-197`). Trocar dispara `reload` (`:981-983`) |
| `ENROL-HIDDEN` | Mostrar cursos ocultos | switch | `enrol_methods.mustache:101-102` | `data-region="enrol-hidden"` · `.form-check.form-switch` | rótulo real em `:103-105` (`for`/`id` — o seletor `"checkbox"` do Behat exige `<label>`, não `aria-label`). Ocultos escondidos por padrão (`enrol_methods.js:1023`); trocar dispara `reload` (`:984-986`) |
| `ENROL-VISCOUNT` | `[sem rótulo]` | contador | `enrol_methods.mustache:107` | `data-region="enrol-viscount"` | `central_enrol_viscount` ("N cursos exibidos") com `data.totalcourses` = **cursos configuráveis distintos após os filtros** (`enrol_methods.js:440-445`; `list_enrol_competencies.php:151`) |

## Accordion — grupos de competência

`ENROL-TREE` é uma caixa de rolagem própria: `max-height: 50vh; overflow-y: auto`
(`styles.css:5695-5698`) para a barra de config acima e o rodapé de ações abaixo ficarem sempre
visíveis. Grupos via `renderGroupHtml` → `appendNodeContents` (`enrol_methods.js:289-303`, `:429`).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-TREE` | `[sem rótulo]` | contêiner-JS | `enrol_methods.mustache:112` | `data-region="enrol-tree"` | vazio quando `!data.total` → parágrafo `nothingtodisplay` (`enrol_methods.js:430-435`) |
| `ENROL-GROUP` | `[sem rótulo]` | grupo | `enrol_group.mustache:36` | `data-group={id}` · `data-name={name}` | `data-name` é lido de volta em `loadCourses` (`enrol_methods.js:471`) para carimbar o nome da competência na linha |
| `ENROL-GROUP-CB` | Selecionar todos os cursos de {competência} | checkbox | `enrol_group.mustache:38-39` | `data-groupcheck={id}` | `aria-label` via `central_enrol_selectall`. Só alcança as linhas **já carregadas** do grupo e **pula as em processamento** (`enrol_methods.js:991-998`) |
| `ENROL-TOGGLE` | {nome da competência} | botão | `enrol_group.mustache:40-47` | `data-action="enrol-toggle"` · `aria-expanded` | chevron (`:44`) + nome (`:45`) + badge de contagem (`:46`). **O nome é o `shortname`** (`enrol_methods.js:298`), não o `fullname`. Rotação do chevron e o *fade/slide* são **CSS puro** keyed no `aria-expanded` (`styles.css:5704-5714`) |
| `ENROL-GROUP-COUNT` | N cursos | badge | `enrol_group.mustache:46` | `badge bg-secondary text-dark` | `central_enrol_courses` / `_coursesone` (singular próprio, `enrol_methods.js:291-293`). O par `bg-secondary` + `text-dark` é deliberado — ver a nota de contraste |
| `ENROL-CHILDREN` | `[sem rótulo]` | contêiner | `enrol_group.mustache:49` | `data-children={id}` · `data-offset="0"` · `hidden` | **carga preguiçosa na 1ª expansão**, com trava `data-loaded` que é **revertida no erro** (`enrol_methods.js:510-518`) — ao contrário da trava do host, esta se recupera |
| `ENROL-CAPTION` | {nome da competência} | caption | `enrol_group.mustache:51` | `visually-hidden` | — |
| `ENROL-HEAD` | Selecionar · Curso · Categoria · Papel · Status · Ações | cabeçalho | `enrol_group.mustache:53-60` | — | **6 colunas** (`545ba17` trocou o accordion solto por `table generaltable` listrada). A 1ª é `{{#str}}select{{/str}}` **`visually-hidden`** (`:54`); as outras cinco são strings do core (`course`, `category`, `role`, `status`, `actions`) |
| `ENROL-ROWS` | `[sem rótulo]` | contêiner-JS | `enrol_group.mustache:62` | `data-region="enrol-rows"` | `<tbody>`; linhas via `makeRow` |
| `ENROL-MORECOMPS` | Carregar mais | botão | `enrol_methods.js:438` | `data-action="enrol-morecomps"` · `data-offset` | página de **20** competências (`:38`); o botão se remove ao clicar (`:936`) |
| `ENROL-MORECOURSES` | Carregar mais | botão | `enrol_methods.js:486` | `data-action="enrol-morecourses"` · `data-competencyid` · `data-offset` | página de **25** cursos (`:39`) |

## Linha de curso — **DOM-built**, não Mustache

`makeRow` (`enrol_methods.js:315-405`). Cada linha carrega o status dos **dois** métodos nas suas
`data-*` (`:319-333`), então trocar o segmento e abrir o detalhe **não refazem fetch**.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-ROW` | `[sem rótulo]` | linha | `enrol_methods.js:317-318` | `.local-dimensions-enrol-row` · `data-courseid` + 14 `data-*` | o **mesmo curso pode aparecer sob mais de uma competência**: toda escrita varre os *gêmeos* por `data-courseid` (`:228`, `:531`, `:561`, `:646`) |
| `ENROL-ROW-CB` | Selecionar {shortname} | checkbox | `enrol_methods.js:336-340` | `data-rowcheck="1"` | `aria-label` via `central_enrol_selectcourse`. **Escondido** (não desabilitado) quando processando (`:206`) |
| `ENROL-ROW-SPIN` | `[sem rótulo]` | spinner | `enrol_methods.js:341-345` | `data-region="row-spinner"` · `fa-spinner fa-spin` | ocupa o lugar do checkbox no estado processando (`:207`) — é a troca 1-para-1 que a spec previa |
| `ENROL-ROW-NAME` | {shortname} · {fullname} | cabeçalho de linha | `enrol_methods.js:349-367` | `<th scope="row">` | shortname em negrito + `·` + fullname **inteiro** (`:356`) — a spec previa truncar; o código **não trunca**. Curso oculto ganha `fa-eye-slash` + texto `visually-hidden` `hiddenfromstudents` (`:357-367`) |
| `ENROL-ROW-CAT` | {categoria} | célula | `enrol_methods.js:369-370` | — | texto puro desde `545ba17` (era badge) |
| `ENROL-ROW-ROLE` | {papel} | célula | `enrol_methods.js:372-373` | `data-region="row-role"` | preenchida **só** quando `configured` (`:193-195`); é o papel **efetivo da instância**, não o do `ENROL-ROLE` |
| `ENROL-STATUS` | Configurado / Processando / Não configurado | badge | `enrol_methods.js:375-379` | `data-region="row-status"` | classe via `STATUS_BADGES` (`:70-74`); reflete **só** o método+coorte selecionados (`rowStatus`, `:160-162`) |
| `ENROL-TOGGLE-STATUS` | Inscrição habilitada / desabilitada | botão | `enrol_methods.js:383-395` | `data-action="enrol-toggle-status"` | **faltava inteiro** (`a5ef9a8`). Só aparece se `configured` (`:198`); ícone `fa-eye`/`fa-eye-slash` (`:200`). Chama `set_enrol_instance_status`, repinta os gêmeos, **pisca** (`:568`) e emite toast (`:571`) |
| `ENROL-INFO` | Detalhes | botão | `enrol_methods.js:396` | `data-action="enrol-info"` | via `iconButton('enrol-info', 'fa-circle-info', …)` — texto visível é o nome acessível |

## Rodapé de ações

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-SELCOUNT` | N selecionado(s) | contador | `enrol_methods.mustache:114` | `data-region="enrol-selcount"` | `central_enrol_selcount`; `state.selected.size` (`enrol_methods.js:241-245`) |
| `ENROL-PROC` | N em processamento | indicador | `enrol_methods.mustache:115-118` | `data-region="enrol-proccount"` · `hidden` | `fa-spinner fa-spin` (`:116`) + texto em `:117`. Escondido quando `pending.size === 0` (`enrol_methods.js:247-256`) |
| `ENROL-REMOVE` | Remover método | botão | `enrol_methods.mustache:120-122` | `data-action="enrol-remove"` · `disabled` | `btn-outline-danger`; nasce desabilitado, habilitado só com seleção (`enrol_methods.js:257-259`) |
| `ENROL-APPLY` | Aplicar método | botão | `enrol_methods.mustache:123-125` | `data-action="enrol-apply"` · `disabled` | `btn-primary`; mesma regra |

## Modais

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-CONFIRM` | Remover método de inscrição | modal | `enrol_methods.js:589-600` | `Notification.saveCancelPromise` | **título** = `central_enrol_confirm_remove_title` (é por ele que o Behat acha o diálogo, não pela palavra "Confirmação"); corpo `central_enrol_confirm_remove` avisa da desmatrícula conforme a config do método; botão = `{{#str}}remove{{/str}}`. Cancelar **retorna** sem enfileirar (`:597-599`) |
| `ENROL-DETAIL` | {fullname} | modal | `enrol_methods.js:779-802` + `enrol_detail.mustache` | `Modal.create({large: true})` · `setRemoveOnClose(true)` | tabela rótulo/valor: categoria (`:55-58`), visível (`:59-62`), competência (`:63-66`) e **as duas linhas de método** (`:67-74`) — montadas por `statusLine` (`enrol_methods.js:747-770`), que compõe status + data + `Inativo` + papel. Tudo é **pré-localizado** no JS: o template não tem `{{#str}}` |
| `ENROL-DETAIL-LINK` | Abrir curso | link | `enrol_detail.mustache:77-81` | `target="_blank" rel="noopener noreferrer"` | `/course/view.php?id=` — nova aba |

## Regras de negócio

### As duas fechaduras da aba são diferentes — e uma delas descasa

A **aba** é gated em `canmanageenrol`, que `plans.mustache:133` alimenta com **`{{canmanage}}`** =
`moodle/competency:templatemanage` **no contexto** (`dynamictabs/plans.php:98`, `:329`). O **link**
do cabeçalho (`PART-LINK-ENROL`) quer `canenrolpage` = `moodle/site:config` **no sistema**
(`:243`). Logo: **um gestor de template vê a aba e não vê o link** — e o link é exatamente o
conserto que o `ENROL-DISABLED` pede. Os 5 WSes reexigem `templatemanage` no contexto do template
(p.ex. `list_enrol_competencies.php:104`, `queue_enrol_action.php:108-109`), então a fechadura da
aba é a real; a do link só decide se o atalho aparece. Não está documentado em nenhum outro lugar.

### A trava de montagem é definitiva — e aqui ela cega os três atualizar

Em `participants_manager.js:183-185`, `enrolmounted = true` é escrito **antes** do await e
`mountEnrol(...)` **não é aguardado** (`.catch(notifyError)`). Se o mount rejeitar, o toast aparece
e a trava fica `true`: voltar na aba **não** tenta de novo, e com `setRemoveOnClose(true)` (`:145`)
a única recuperação é **fechar e reabrir o modal**.

**A agravante é desta aba, e é verificável no código.** `mount` (`enrol_methods.js:1010-1033`)
renderiza o esqueleto (`:1012-1013`), liga os eventos (`:1031`) e **só então** faz `await
init(state)` (`:1032`). As três regiões nascem `hidden` e quem as revela é o `init`
(`:880-892`). Se o `Promise.all` de `:854-870` rejeitar — WS fora, rede caindo — o `init` sai por
exceção **antes** de qualquer `hidden = false`:

- as três regiões continuam `hidden`;
- os **três** botões `enrol-refresh` estão **dentro** delas (`:39-41` e `:47-49` nos alerts,
  `:108-110` dentro do `enrol-main`) — logo **nenhum dos três é alcançável**;
- a trava do host já é `true`.

Resultado: **o pane fica uma caixa em branco e o "atualizar" que existiria para salvá-lo está
escondido atrás do mesmo `init` que falhou.** A ressalva importa para o IMP-03: a conclusão do
`mod-participants.md` ("só o atualizar deixa tentar de novo") **não vale para a falha que mais
importa aqui** — ela vale para os estados `ENROL-EMPTY`/`ENROL-DISABLED`, em que o `init`
**teve sucesso** e revelou um alerta. Uma falha **tardia** (`loadCompetencies`, `:904`) é
recuperável, porque `main.hidden = false` já rodou em `:892`. O portão que falta é o **quarto**:
uma região de erro, revelada no `catch`, com um atualizar **fora** das outras três.

### Concorrência — dedup por `(curso, método, coorte)`

Cada combinação é uma **task adhoc independente**; combinações diferentes rodam em paralelo. A
chave é `process_enrol_method::key($courseid, $method, $cohortid)` (`:102`), o `pending_map()`
(`:114`) é consultado sob o lock da fila antes de enfileirar (`queue_enrol_action.php:144-148` →
`status = 'skipped'`) e a execução serializa na Lock API (`process_enrol_method.php:206-209`,
timeout 60 s → `central_enrol_busy`). O JS só marca `processing` no que **não** voltou `skipped`
(`enrol_methods.js:612-616`). O mesmo curso segue livre para a **outra** combinação — é por isso
que `pending` é reconstruído do zero a cada troca de método (`:709-717`).

### O poll

`POLL_MS = 5000` (`:40`), `setInterval` só enquanto há `pending` (`ensurePolling`, `:664-674`).
Cada volta consulta `get_enrol_queue_status` e vira as linhas prontas para `configured`/
`notconfigured`, com **flash** amarelo (`:649`). O timer para sozinho quando `pending` esvazia
(`:653-655`) **ou** quando a raiz sai do DOM (`!state.root.isConnected`, `:629`) — é o que impede
o poll de sobreviver ao `setRemoveOnClose` do modal. Regra da casa respeitada: **linha trocada →
flash** (`:568`, `:649`), nunca spinner de pane inteiro.

### Contraste: por que `bg-secondary` vem com `text-dark`

O comentário de `enrol_methods.js:68-69` registra a decisão, e ela se mede: o `secondary` do Boost
é um cinza claro (`#ced4da`) e o texto padrão do badge é branco — **1,49:1**, reprova. O par que o
código shipa, `bg-secondary` + `text-dark` (`#1d2125`), dá **10,84:1**. Vale para o
`ENROL-STATUS` "Não configurado" (`:73`) e para o `ENROL-GROUP-COUNT` (`enrol_group.mustache:46`).

## IMP-05 (`mtube: atualizar`) — **esta aba é o precedente visual**

A decisão e as verificações moram em [`bar-contextbar.md`](bar-contextbar.md). O que **este** mapa
fixa, de forma independente:

- Os **três** `data-action="enrol-refresh"` (`enrol_methods.mustache:39-41`, `:47-49`, `:108-110`)
  são a **única afordância de atualizar de todo o hub** — as outras três abas do modal não têm
  nenhuma, e nem a contextbar, nem `structure.mustache`, `frameworks.mustache` ou `plans.mustache`
  têm. O IMP-05 herda daqui **o ícone (`fa fa-rotate me-1`) e a string (`{{#str}}refresh,
  moodle{{/str}}`)**: **nenhuma string nova é necessária**.
- **Precisão medida (o mapa confirma de forma independente):** `reloadPane` existe
  (`tabs.js:51`) e tem **23 chamadas em 5 módulos** — `structure` 9, `frameworks` 6, `plans` 6,
  `competency_browser` 1, `context` 1. **Não** é verdade que "nada expõe `reloadPane`"; o que é
  verdade é que **nenhum controle de UI** o dispara. (Um `grep -rn reloadPane amd/src/` devolve
  **31** linhas: as 23 chamadas + 1 definição + 5 imports + 2 comentários — `frameworks.js:18` e
  `plans.js:795`. Contar as 31 como chamadas é o erro fácil.)
- **Este pane não usa `reloadPane`** e não é exceção a nada: seu atualizar é `init(state)`
  (`enrol_methods.js:915-918`) porque ele é **pane de modal**, não pane de aba do
  `core/dynamic_tabs` — `reloadPane` não o alcançaria.
- **A disciplina a copiar é a do mtube** (`course_report.js:286-299`, via `sourcesContent` do
  sourcemap): `disabled = true` + `fa-spin` no ícone, revertidos num `finally`. **Os três botões
  desta aba não têm essa disciplina** — clicar duas vezes dispara dois `init` concorrentes. O
  IMP-05 deve nascer com ela, e a aba de inscrição deveria ganhá-la junto.
- **Rastreabilidade das refs do mtube:** o `format_mtube` **não** tem `amd/src` neste checkout, só
  `amd/build`. Uma ref de JS do mtube por `file:line` **não resolve para ninguém** — por isso este
  mapa cita o mtube por **nome de símbolo**. Um `grep` no disco por esse `.js` não achar nada é
  **esperado**, não é ausência.
