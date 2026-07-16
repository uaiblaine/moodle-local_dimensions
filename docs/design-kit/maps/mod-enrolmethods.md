# Mapa de Campos — `MOD.ENROL` · Métodos de inscrição (as-is)

**4ª aba** do modal Participantes (`MOD.PART`), depois de Coortes / Usuários / Atribuir papéis.
Configura **em massa** os métodos de inscrição dos cursos vinculados às competências do template,
sempre amarrado a um coorte do plano. O pane nasce **vazio** no host
(`participants_manager.mustache:150-151`) e é montado por `enrol_methods.js:1026-1053`.

- **Mustache:** [`enrol_methods.mustache`](../../../templates/central/enrol_methods.mustache) (137, esqueleto da aba), [`enrol_group.mustache`](../../../templates/central/enrol_group.mustache) (65, um grupo do accordion), [`enrol_detail.mustache`](../../../templates/central/enrol_detail.mustache) (82, corpo do modal de detalhe)
- **AMD:** [`enrol_methods.js`](../../../amd/src/central/enrol_methods.js) (1053) — reusa `action_button.js` (`iconButton`, `:38-49`) e `errors.js` (`notifyError`)
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
>   **`cohort | self`** (`enrol_methods.mustache:74`, `:78`) e o estado nasce `method: 'cohort'`
>   (`enrol_methods.js:1035`). **`sync` não existe em lugar nenhum** — o rótulo *visível* é que é
>   "Sincronização de coortes" (`central_enrol_method_cohort`). Confundir os dois quebra
>   `data-method`, o `state.method`, o argumento `method` dos 3 WSes e a chave da task.
> - **`enrol_row.mustache` foi deletado** em `33f7697`, com motivo registrado: o lint de Mustache
>   renderiza o template isolado e o validador de HTML **rejeita um fragmento `tr` solto**. As
>   linhas viraram `createElement` em `makeRow` (`:316-406`) — mesmo padrão das abas Usuários/Papéis.

## Portões — quatro regiões, uma revelada por vez

As quatro nascem `hidden` no Mustache; `init()` revela **uma**: em sucesso, um de `enrol-disabled` /
`enrol-empty` / `enrol-main` (`enrol_methods.js:895-908`); numa falha **precoce** — a carga inicial
rejeita antes de qualquer `hidden = false` — o `catch` revela `enrol-error` (`:880`) e esconde as
outras três (`:881-883`). Os alerts são blocos simples de propósito: o comentário de
`enrol_methods.mustache:33-35` avisa que um `.d-flex` no próprio alert **venceria** o atributo `hidden`
(`display` é `!important` nas utilities), então o flex mora num wrapper interno.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-ROOT` | `[sem rótulo]` | região/raiz | `enrol_methods.mustache:32` | `data-region="enrol"` · `.local-dimensions-enrol` | é o `state.root`; o listener delegado pousa nele (`enrol_methods.js:930`) |
| `ENROL-DISABLED` | aviso: os dois plugins desabilitados no site | alerta | `enrol_methods.mustache:36-43` | `data-region="enrol-disabled"` · `alert-warning` | `central_enrol_bothdisabled` (`:38`). Revelado por `enrol_methods.js:895-899` quando `!cohortenabled && !selfenabled` — a aba inteira fica inerte. Quem conserta é o `PART-LINK-ENROL`, que **só o admin do site vê** (ver o descasamento de fechaduras abaixo) |
| `ENROL-EMPTY` | aviso: nenhum coorte vinculado | alerta | `enrol_methods.mustache:44-51` | `data-region="enrol-empty"` · `alert-info` | `central_enrol_empty` (`:46`) manda o usuário para a **aba Coortes**. Revelado por `enrol_methods.js:902-906` quando `!cohortdata.cohorts.length` |
| `ENROL-ERROR` | aviso: falha ao carregar os métodos | alerta | `enrol_methods.mustache:52-59` | `data-region="enrol-error"` · `alert-warning` | **ENTREGUE em 2026-07-16 (`c2d9471`)** — era a 4ª porta que este mapa pedia como *to-be* (ver a seção da trava de montagem). `central_enrol_loadfailed` (`:54`). Revelado no `catch` da carga inicial de `init` (`enrol_methods.js:880`), que esconde as outras três (`:881-883`), e **relança** para o *swallow* do mount ainda emitir o toast. `alert-warning` (não `danger`): a falha é transitória/retentável. Seu `enrol-refresh` (`:55-57`) mora **fora** das outras três regiões — é o único alcançável quando elas seguem `hidden` |
| `ENROL-MAIN` | `[sem rótulo]` | região principal | `enrol_methods.mustache:60` | `data-region="enrol-main"` | revelada em `enrol_methods.js:908`. **Tudo** o que segue mora aqui dentro — inclusive o 4º atualizar |
| `ENROL-REFRESH` | Atualizar | botão ×4 | `enrol_methods.mustache:39-41`, `:47-49`, `:55-57`, `:116-118` | `data-action="enrol-refresh"` | um por região (as três de sucesso + a `ENROL-ERROR`). `btn btn-outline-secondary btn-sm` + `<i class="fa fa-rotate me-1">` + `{{#str}}refresh, moodle{{/str}}`. Handler **próprio** (`enrol_methods.js:931-934`) → `init(state)`, **não** `reloadPane`. Ver a seção do IMP-05 |

## Barra de configuração

Uma linha `d-flex` com os três controles distribuídos (`enrol_methods.mustache:61`), a dica embaixo.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-COHORT` | **Coorte do plano** | select | `enrol_methods.mustache:66` | `data-region="enrol-cohort"` · `form-select` | rótulo em `:63-65`. Opções via `list_template_cohorts` (`enrol_methods.js:861-862`) — **os coortes já vinculados ao template**, não todos os do site. Trocar dispara `reload` (`:992-994`) |
| `ENROL-METHOD` | Método | grupo de botões | `enrol_methods.mustache:72-82` | `data-region="enrol-method"` · `role="group"` | **`cohort`** (`:74-77`, nasce `active`/`btn-primary`/`aria-pressed="true"`) e **`self`** (`:78-81`). Rotulado por `aria-labelledby` → o `<span>` de `:69-71` (não é `<label>`: não há um controle único a apontar). Trocar **não refaz fetch** — `applyMethodChange` (`:698-721`) repinta das `data-*` da linha |
| `ENROL-METHOD-OFF` | `[sem rótulo]` | regra de disponibilidade | `enrol_methods.js:832-839` | `button.disabled = !enabled` | cada segmento é desabilitado se o plugin correspondente estiver off no site (`enrol_is_enabled`, `list_enrol_competencies.php:202-203`). Se **só** `cohort` estiver off, o pane troca sozinho para `self` (`:837-839`) |
| `ENROL-ROLE` | **Papel atribuído** | select | `enrol_methods.mustache:88` | `data-region="enrol-role"` · `form-select` | rótulo em `:85-87`. `eligible_roles()` = `$CFG->gradebookroles` **∩** `get_default_enrol_roles($context)` (`classes/local/enrol_methods.php:58-73`) — gradebook **e** atribuível por inscrição. Default = arquétipo *student* quando elegível, senão o primeiro (`:81-89`). Trocar **não** recarrega (`enrol_methods.js:995-996`): só vale na próxima ação |
| `ENROL-HINT` | `[sem rótulo]` | texto | `enrol_methods.mustache:91` | `data-region="enrol-hint"` | `central_enrol_hint_cohort` / `_hint_self`, trocado em `enrol_methods.js:708-709` e `:917-918` |

## Barra de filtros

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-SEARCH` | Pesquisar competências | input texto | `enrol_methods.mustache:97-99` | `data-region="enrol-search"` · `.local-dimensions-enrol-search` | **faltava inteiro** (`ec9d813`). Rótulo `visually-hidden` (`:94-96`) **e** `placeholder` com a mesma string. Debounce de **300 ms** → `reload` (`enrol_methods.js:977-988`); o comentário de `:981-982` diz por que é server-side: a lista é paginada, um filtro client-side perderia as páginas não carregadas. Largura fixa `14rem` (`styles.css:5733-5735`) |
| `ENROL-CAT` | Categoria de curso | select | `enrol_methods.mustache:105-106` | `data-region="enrol-category"` | rótulo `visually-hidden` (`:102-104`). Opções = `central_enrol_categoryall` + as categorias **dos cursos vinculados** (`enrol_methods.js:823-826`; `list_enrol_competencies.php:185-197`). Trocar dispara `reload` (`:997-999`) |
| `ENROL-HIDDEN` | Mostrar cursos ocultos | switch | `enrol_methods.mustache:109-110` | `data-region="enrol-hidden"` · `.form-check.form-switch` | rótulo real em `:111-113` (`for`/`id` — o seletor `"checkbox"` do Behat exige `<label>`, não `aria-label`). Ocultos escondidos por padrão (`enrol_methods.js:1039`); trocar dispara `reload` (`:1000-1002`) |
| `ENROL-VISCOUNT` | `[sem rótulo]` | contador | `enrol_methods.mustache:115` | `data-region="enrol-viscount"` | `central_enrol_viscount` ("N cursos exibidos") com `data.totalcourses` = **cursos configuráveis distintos após os filtros** (`enrol_methods.js:441-446`; `list_enrol_competencies.php:151`) |

## Accordion — grupos de competência

`ENROL-TREE` é uma caixa de rolagem própria: `max-height: 50vh; overflow-y: auto`
(`styles.css:5695-5698`) para a barra de config acima e o rodapé de ações abaixo ficarem sempre
visíveis. Grupos via `renderGroupHtml` → `appendNodeContents` (`enrol_methods.js:290-304`, `:430`).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-TREE` | `[sem rótulo]` | contêiner-JS | `enrol_methods.mustache:120` | `data-region="enrol-tree"` | vazio quando `!data.total` → parágrafo `nothingtodisplay` (`enrol_methods.js:431-436`) |
| `ENROL-GROUP` | `[sem rótulo]` | grupo | `enrol_group.mustache:36` | `data-group={id}` · `data-name={name}` | `data-name` é lido de volta em `loadCourses` (`enrol_methods.js:472`) para carimbar o nome da competência na linha |
| `ENROL-GROUP-CB` | Selecionar todos os cursos de {competência} | checkbox | `enrol_group.mustache:38-39` | `data-groupcheck={id}` | `aria-label` via `central_enrol_selectall`. Só alcança as linhas **já carregadas** do grupo e **pula as em processamento** (`enrol_methods.js:1007-1015`) |
| `ENROL-TOGGLE` | {nome da competência} | botão | `enrol_group.mustache:40-47` | `data-action="enrol-toggle"` · `aria-expanded` | chevron (`:44`) + nome (`:45`) + badge de contagem (`:46`). **O nome é o `shortname`** (`enrol_methods.js:299`), não o `fullname`. Rotação do chevron e o *fade/slide* são **CSS puro** keyed no `aria-expanded` (`styles.css:5704-5714`) |
| `ENROL-GROUP-COUNT` | N cursos | badge | `enrol_group.mustache:46` | `badge bg-secondary text-dark` | `central_enrol_courses` / `_coursesone` (singular próprio, `enrol_methods.js:292-294`). O par `bg-secondary` + `text-dark` é deliberado — ver a nota de contraste |
| `ENROL-CHILDREN` | `[sem rótulo]` | contêiner | `enrol_group.mustache:49` | `data-children={id}` · `data-offset="0"` · `hidden` | **carga preguiçosa na 1ª expansão**, com trava `data-loaded` que é **revertida no erro** (`enrol_methods.js:511-519`), então re-expandir sempre tenta de novo. A trava do host **também** passou a se recuperar (`c96a3e9`), mas só numa rejeição pré-fiação — ver a seção da trava de montagem |
| `ENROL-CAPTION` | {nome da competência} | caption | `enrol_group.mustache:51` | `visually-hidden` | — |
| `ENROL-HEAD` | Selecionar · Curso · Categoria · Papel · Status · Ações | cabeçalho | `enrol_group.mustache:53-60` | — | **6 colunas** (`545ba17` trocou o accordion solto por `table generaltable` listrada). A 1ª é `{{#str}}select{{/str}}` **`visually-hidden`** (`:54`); as outras cinco são strings do core (`course`, `category`, `role`, `status`, `actions`) |
| `ENROL-ROWS` | `[sem rótulo]` | contêiner-JS | `enrol_group.mustache:62` | `data-region="enrol-rows"` | `<tbody>`; linhas via `makeRow` |
| `ENROL-MORECOMPS` | Carregar mais | botão | `enrol_methods.js:439` | `data-action="enrol-morecomps"` · `data-offset` | página de **20** competências (`:38`); o botão se remove ao clicar (`:952`) |
| `ENROL-MORECOURSES` | Carregar mais | botão | `enrol_methods.js:487` | `data-action="enrol-morecourses"` · `data-competencyid` · `data-offset` | página de **25** cursos (`:39`) |

## Linha de curso — **DOM-built**, não Mustache

`makeRow` (`enrol_methods.js:316-406`). Cada linha carrega o status dos **dois** métodos nas suas
`data-*` (`:320-334`), então trocar o segmento e abrir o detalhe **não refazem fetch**.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-ROW` | `[sem rótulo]` | linha | `enrol_methods.js:318-319` | `.local-dimensions-enrol-row` · `data-courseid` + 14 `data-*` | o **mesmo curso pode aparecer sob mais de uma competência**: toda escrita varre os *gêmeos* por `data-courseid` (`:229`, `:532`, `:562`, `:647`) |
| `ENROL-ROW-CB` | Selecionar {shortname} | checkbox | `enrol_methods.js:337-341` | `data-rowcheck="1"` | `aria-label` via `central_enrol_selectcourse`. **Escondido** (não desabilitado) quando processando (`:207`) |
| `ENROL-ROW-SPIN` | `[sem rótulo]` | spinner | `enrol_methods.js:342-346` | `data-region="row-spinner"` · `fa-spinner fa-spin` | ocupa o lugar do checkbox no estado processando (`:208`) — é a troca 1-para-1 que a spec previa |
| `ENROL-ROW-NAME` | {shortname} · {fullname} | cabeçalho de linha | `enrol_methods.js:350-368` | `<th scope="row">` | shortname em negrito + `·` + fullname **inteiro** (`:357`) — a spec previa truncar; o código **não trunca**. Curso oculto ganha `fa-eye-slash` + texto `visually-hidden` `hiddenfromstudents` (`:358-368`) |
| `ENROL-ROW-CAT` | {categoria} | célula | `enrol_methods.js:370-371` | — | texto puro desde `545ba17` (era badge) |
| `ENROL-ROW-ROLE` | {papel} | célula | `enrol_methods.js:373-374` | `data-region="row-role"` | preenchida **só** quando `configured` (`:194-196`); é o papel **efetivo da instância**, não o do `ENROL-ROLE` |
| `ENROL-STATUS` | Configurado / Processando / Não configurado | badge | `enrol_methods.js:376-380` | `data-region="row-status"` | classe via `STATUS_BADGES` (`:71-75`); reflete **só** o método+coorte selecionados (`rowStatus`, `:161-163`) |
| `ENROL-TOGGLE-STATUS` | Inscrição habilitada / desabilitada | botão | `enrol_methods.js:384-396` | `data-action="enrol-toggle-status"` | **faltava inteiro** (`a5ef9a8`). Só aparece se `configured` (`:199`); ícone `fa-eye`/`fa-eye-slash` (`:201`). Chama `set_enrol_instance_status`, repinta os gêmeos, **pisca** (`:569`) e emite toast (`:572`) |
| `ENROL-INFO` | Detalhes | botão | `enrol_methods.js:397` | `data-action="enrol-info"` | via `iconButton('enrol-info', 'fa-circle-info', …)` — texto visível é o nome acessível |

## Rodapé de ações

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-SELCOUNT` | N selecionado(s) | contador | `enrol_methods.mustache:122` | `data-region="enrol-selcount"` | `central_enrol_selcount`; `state.selected.size` (`enrol_methods.js:242-246`) |
| `ENROL-PROC` | N em processamento | indicador | `enrol_methods.mustache:123-126` | `data-region="enrol-proccount"` · `hidden` | `fa-spinner fa-spin` (`:124`) + texto em `:125`. Escondido quando `pending.size === 0` (`enrol_methods.js:248-257`) |
| `ENROL-REMOVE` | Remover método | botão | `enrol_methods.mustache:128-130` | `data-action="enrol-remove"` · `disabled` | `btn-outline-danger`; nasce desabilitado, habilitado só com seleção (`enrol_methods.js:258-260`) |
| `ENROL-APPLY` | Aplicar método | botão | `enrol_methods.mustache:131-133` | `data-action="enrol-apply"` · `disabled` | `btn-primary`; mesma regra |

## Modais

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-CONFIRM` | Remover método de inscrição | modal | `enrol_methods.js:590-601` | `Notification.saveCancelPromise` | **título** = `central_enrol_confirm_remove_title` (é por ele que o Behat acha o diálogo, não pela palavra "Confirmação"); corpo `central_enrol_confirm_remove` avisa da desmatrícula conforme a config do método; botão = `{{#str}}remove{{/str}}`. Cancelar **retorna** sem enfileirar (`:598-600`) |
| `ENROL-DETAIL` | {fullname} | modal | `enrol_methods.js:780-803` + `enrol_detail.mustache` | `Modal.create({large: true})` · `setRemoveOnClose(true)` | tabela rótulo/valor: categoria (`:55-58`), visível (`:59-62`), competência (`:63-66`) e **as duas linhas de método** (`:67-74`) — montadas por `statusLine` (`enrol_methods.js:748-771`), que compõe status + data + `Inativo` + papel. Tudo é **pré-localizado** no JS: o template não tem `{{#str}}` |
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

### A trava de montagem e o pane em branco na 1ª montagem — os dois já fechados

**Como era.** Em `participants_manager.js`, `enrolmounted = true` (como `usersmounted`/`rolesmounted`)
era escrito **antes** do await e `mountEnrol(...)` **não era aguardado** (`.catch(notifyError)`). Se o
mount rejeitasse, o toast aparecia e a trava ficava `true`: voltar na aba **não** tentava de novo, e com
`setRemoveOnClose(true)` (`:145`) a única recuperação era **fechar e reabrir o modal**. O pane de Coortes
era pior — montado uma vez no `shown`, **sem trava nenhuma**, e como a própria aba de Coortes não roda o
`ensureMounted`, um pane-padrão que falhasse não tinha recuperação alguma dentro do modal.

**A trava — CORRIGIDO em 2026-07-16 (`c96a3e9`).** As quatro montagens passam por um só
`startMount(key, mountfn, selector)` (`participants_manager.js:166-175`) sobre uma tabela única
`mounted = {cohorts, users, roles, enrol}` (`:161`). Ele **reivindica a trava de forma síncrona**
(`mounted[key] = true`, `:170`) — um duplo-clique ainda dispara um único mount — e a **libera no `.catch`**
(`mounted[key] = false`, `:172`), então a **próxima ativação da aba tenta de novo**. Coortes entrou na
tabela (`:180`, e via `ensureMounted` em `:189`), logo re-clicar a aba-padrão também a recupera.
Liberar-no-catch só é seguro porque uma trava liberada sempre significa um pane **não-fiado**: Coortes e
Papéis dão `replaceNodeContents`-clear e fiam **nós-filhos frescos**, então um remount descarta os
listeners antigos e começa limpo.

**Correção que este mapa faz contra si mesmo: o enrol NÃO é idempotente sob `replaceNodeContents`.** `mount`
(`enrol_methods.js:1026-1053`) limpa o container com `replaceNodeContents` (`:1029`), mas isso esvazia só
os **filhos** — o `wireEvents` (`:1047`) **delega** o listener de `click` no **próprio elemento container**
(`state.root`, `:930`), que **sobrevive** ao clear. Um remount ingênuo empilharia um segundo jogo de
listeners. Por isso o único await pós-fiação foi **engolido para um toast**: `await init(state)` virou
`await init(state).catch(notifyError)` (`:1052`). Uma falha **pós-fiação** agora **resolve** o mount — a
trava fica `true`, nenhum re-clique refaz, e existe **exatamente um** estado fiado. É por isto que o enrol
não pode simplesmente liberar-e-remontar como Coortes/Papéis, e por que o *swallow* de `:1052` é
**obrigatório**, não opcional.

**O pane em branco na 1ª montagem — ENTREGUE em 2026-07-16 (`c2d9471`).** Este mapa desenhava, como
*to-be*, uma **4ª porta**: uma região de erro, revelada no `catch` do `init`, com um atualizar **fora** das
outras três. Foi **exatamente** o que shipou. As três regiões de sucesso nascem `hidden` e quem revela
**uma** é o `init` (`:895-908`). Se o `Promise.all` da carga inicial (`:859-875`) rejeitar — WS fora, rede
caindo —, o `init` **antes** saía por exceção antes de qualquer `hidden = false`, o erro era engolido pelo
`.catch` de `:1052`, e as três regiões — com os **três** `enrol-refresh` presos dentro delas
(`enrol_methods.mustache:39-41`, `:47-49` nos alerts, `:116-118` no `enrol-main`) — ficavam todas ocultas:
pane em branco, nenhum atualizar alcançável, recuperação só reabrindo o modal.

**Como ficou.** A carga inicial agora roda dentro de um `try` (`enrol_methods.js:858-875`); no `catch`, o
`init` **revela a `ENROL-ERROR`** (`error.hidden = false`, `:880`), **esconde** as outras três (`:881-883`)
e **relança** — o `.catch` de `:1052` ainda emite o toast. O `enrol-refresh` da `ENROL-ERROR`
(`enrol_methods.mustache:55-57`) mora **fora** das três regiões escondidas; seu handler delegado
(`enrol_methods.js:931-934`) re-roda `init(state)`, então a falha **precoce** virou **recuperável no lugar**,
sem reabrir o modal. `alert-warning` (não `danger`): a falha é transitória/retentável. A trava do host segue
`true` — o *swallow* de `:1052` continua deliberado (a alternativa, liberar e remontar, duplicaria os
listeners) —, mas isso agora **não** deixa mais o pane inútil: há uma afordância de retry dentro dele.

A falha **tardia** (`loadCompetencies`, `:920`) sempre foi recuperável e continua **deliberadamente fora**
do `try`: `main.hidden = false` já rodou em `:908`, então o `enrol-refresh` do `enrol-main`
(`enrol_methods.mustache:116-118`) fica visível — seu handler (`enrol_methods.js:931-934`) chama `init(state)`
de novo. Com a `ENROL-ERROR` fechando a falha **precoce** e o `enrol-main` cobrindo a **tardia**, a conclusão
do `mod-participants.md` ("só o atualizar deixa tentar de novo") agora vale para **todos** os estados: os
alertas `ENROL-EMPTY`/`ENROL-DISABLED` (em que o `init` **teve sucesso** e revelou um alerta), a falha
tardia, e — desde `c2d9471` — a falha precoce.

### Concorrência — dedup por `(curso, método, coorte)`

Cada combinação é uma **task adhoc independente**; combinações diferentes rodam em paralelo. A
chave é `process_enrol_method::key($courseid, $method, $cohortid)` (`:102`), o `pending_map()`
(`:114`) é consultado sob o lock da fila antes de enfileirar (`queue_enrol_action.php:144-148` →
`status = 'skipped'`) e a execução serializa na Lock API (`process_enrol_method.php:206-209`,
timeout 60 s → `central_enrol_busy`). O JS só marca `processing` no que **não** voltou `skipped`
(`enrol_methods.js:613-617`). O mesmo curso segue livre para a **outra** combinação — é por isso
que `pending` é reconstruído do zero a cada troca de método (`:710-718`).

### O poll

`POLL_MS = 5000` (`:40`), `setInterval` só enquanto há `pending` (`ensurePolling`, `:665-675`).
Cada volta consulta `get_enrol_queue_status` e vira as linhas prontas para `configured`/
`notconfigured`, com **flash** amarelo (`:650`). O timer para sozinho quando `pending` esvazia
(`:654-656`) **ou** quando a raiz sai do DOM (`!state.root.isConnected`, `:630`) — é o que impede
o poll de sobreviver ao `setRemoveOnClose` do modal. Regra da casa respeitada: **linha trocada →
flash** (`:569`, `:650`), nunca spinner de pane inteiro.

### Contraste: por que `bg-secondary` vem com `text-dark`

O comentário de `enrol_methods.js:69-70` registra a decisão, e ela se mede: o `secondary` do Boost
é um cinza claro (`#ced4da`) e o texto padrão do badge é branco — **1,49:1**, reprova. O par que o
código shipa, `bg-secondary` + `text-dark` (`#1d2125`), dá **10,84:1**. Vale para o
`ENROL-STATUS` "Não configurado" (`:74`) e para o `ENROL-GROUP-COUNT` (`enrol_group.mustache:46`).

## IMP-05 (`mtube: atualizar`) — **esta aba é o precedente visual**

A decisão e as verificações moram em [`bar-contextbar.md`](bar-contextbar.md). O que **este** mapa
fixa, de forma independente:

- Os **quatro** `data-action="enrol-refresh"` (`enrol_methods.mustache:39-41`, `:47-49`, `:55-57`,
  `:116-118` — o 4º nasceu com a `ENROL-ERROR` em `c2d9471`) são a **única afordância de atualizar
  de todo o hub** — as outras três abas do modal não têm nenhuma, e nem a contextbar, nem
  `structure.mustache`, `frameworks.mustache` ou `plans.mustache` têm. O IMP-05 herda daqui **o ícone
  (`fa fa-rotate me-1`) e a string (`{{#str}}refresh, moodle{{/str}}`)**: **nenhuma string nova é
  necessária**.
- **Precisão medida (o mapa confirma de forma independente):** `reloadPane` existe
  (`tabs.js:51`) e tem **23 chamadas em 5 módulos** — `structure` 9, `frameworks` 6, `plans` 6,
  `competency_browser` 1, `context` 1. **Não** é verdade que "nada expõe `reloadPane`"; o que é
  verdade é que **nenhum controle de UI** o dispara. (Um `grep -rn reloadPane amd/src/` devolve
  **31** linhas: as 23 chamadas + 1 definição + 5 imports + 2 comentários — `frameworks.js:18` e
  `plans.js:795`. Contar as 31 como chamadas é o erro fácil.)
- **Este pane não usa `reloadPane`** e não é exceção a nada: seu atualizar é `init(state)`
  (`enrol_methods.js:931-934`) porque ele é **pane de modal**, não pane de aba do
  `core/dynamic_tabs` — `reloadPane` não o alcançaria.
- **A disciplina a copiar é a do mtube** (`course_report.js:286-299`, via `sourcesContent` do
  sourcemap): `disabled = true` + `fa-spin` no ícone, revertidos num `finally`. **Os quatro botões
  desta aba não têm essa disciplina** — clicar duas vezes dispara dois `init` concorrentes. O
  IMP-05 deve nascer com ela, e a aba de inscrição deveria ganhá-la junto.
- **Rastreabilidade das refs do mtube:** o `format_mtube` **não** tem `amd/src` neste checkout, só
  `amd/build`. Uma ref de JS do mtube por `file:line` **não resolve para ninguém** — por isso este
  mapa cita o mtube por **nome de símbolo**. Um `grep` no disco por esse `.js` não achar nada é
  **esperado**, não é ausência.
