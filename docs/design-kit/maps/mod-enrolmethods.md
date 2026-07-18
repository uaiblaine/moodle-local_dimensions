# Mapa de Campos — `MOD.ENROL` · Métodos de inscrição (as-is)

**4ª aba** do modal Participantes (`MOD.PART`), depois de Coortes / Usuários / Atribuir papéis.
Configura **em massa** os métodos de inscrição dos cursos vinculados às competências do template,
sempre amarrado a um coorte do plano. O pane nasce **vazio** no host
(`participants_manager.mustache:150-151`) e é montado por `enrol_methods.js:1082-1112`.

- **Mustache:** [`enrol_methods.mustache`](../../../templates/central/enrol_methods.mustache) (121, esqueleto da aba), [`enrol_group.mustache`](../../../templates/central/enrol_group.mustache) (65, um grupo do accordion), [`enrol_detail.mustache`](../../../templates/central/enrol_detail.mustache) (82, corpo do modal de detalhe)
- **AMD:** [`enrol_methods.js`](../../../amd/src/central/enrol_methods.js) (1112) — reusa `action_button.js` (`iconButton`, `:38-49`) e `errors.js` (`notifyError`)
- **WS (5, todos em `db/services.php:346-386`):** `list_enrol_competencies` (raízes paginadas + *bootstrap* de mount), `list_enrol_courses` (linhas com o status dos **dois** métodos), `queue_enrol_action`, `get_enrol_queue_status`, `set_enrol_instance_status`
- **Task:** [`process_enrol_method`](../../../classes/task/process_enrol_method.php) — adhoc por `(courseid, método, cohortid)`
- **Helper:** [`classes/local/enrol_methods.php`](../../../classes/local/enrol_methods.php) — `eligible_roles()` (`:58-73`), `default_roleid()` (`:81-89`)
- **CSS:** [`styles.css:5955-6021`](../../../styles.css)

> **Resync 2026-07-15 — este mapa era uma _spec_, e o código passou por cima dela na mesma noite.**
> Medido, não estimado:
>
> - **Zero refs quebradas — porque havia zero refs.** A versão anterior (`0b3782c`) **não tinha
>   coluna `Origem`**: o cabeçalho das tabelas era `| ID | Rótulo | Tipo | Dados | Regra / notas |`.
>   Um `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+'` no arquivo antigo devolve **0**. Não é o
>   estrago dos outros mapas (Task 7: 23/23; Task 9: 21/21; Task 10: 12/24) — aqui a coluna de
>   proveniência **faltava inteira**. As 22 IDs existiam sem uma única origem.
> - **A janela real é de 69 minutos.** O mapa entrou em `0b3782c` (**2026-07-11 21:53:13**,
>   autor e committer batem) e o `3d1d5cb` shipou a aba em **23:03:05** — `(1783821785 − 1783817593)
>   / 60 = 69`. **Não existe commit às 21:37** (a janela 21:00–23:30 tem 8 commits; nenhum nesse
>   minuto), e **nenhum par "21:37 / 86 minutos" existe** no `mod-participants.md` — pelo contrário,
>   aquele mapa **corrobora** esta janela, reportando os mesmos ~70 minutos (23:03 − 21:53) em `:29-30`
>   e `:198`. E o **primeiro** código do recurso é anterior à aba: a task em `5df19b7` (22:41, +48 min)
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
>   **`cohort | self`** (`enrol_methods.mustache:59`, `:63`) e o estado nasce `method: 'cohort'`
>   (`enrol_methods.js:1091`). **`sync` não existe em lugar nenhum** — o rótulo *visível* é que é
>   "Sincronização de coortes" (`central_enrol_method_cohort`). Confundir os dois quebra
>   `data-method`, o `state.method`, o argumento `method` dos 3 WSes e a chave da task.
> - **`enrol_row.mustache` foi deletado** em `33f7697`, com motivo registrado: o lint de Mustache
>   renderiza o template isolado e o validador de HTML **rejeita um fragmento `tr` solto**. As
>   linhas viraram `createElement` em `makeRow` (`:366-463`) — mesmo padrão das abas Usuários/Papéis.

> **Resync 2026-07-18 — re-medido contra `c07d5e5` depois da fatia de diferenciação de métodos.**
> A fatia (`c07d5e5`, ícone por método + ação nomeada) inseriu ~50 linhas no topo do JS e **deslocou
> todas** as refs `enrol_methods.js:NNN` — re-medidas uma a uma aqui. **Sem bump de `version.php`:** a
> fatia não a toca e a versão segue **congelada em `2026071306`** até a 2.0 (só strings/JS/templates,
> nenhum WS novo nem mudança estrutural). As refs de `enrol_methods.mustache`
> (~18 fora) e as de `styles.css` (~266 fora) já estavam **desatualizadas antes** desta fatia (drift de
> commits anteriores) e foram corrigidas nesta passada. As refs de PHP/task/helper (`external/*`,
> `process_enrol_method`, `classes/local/enrol_methods`, `db/services`, `dynamictabs/plans`) e de
> `enrol_group`/`enrol_detail` foram reconferidas e a maioria seguia certa; `participants_manager.js` e
> `tabs.js` haviam derivado e foram ajustadas. **Pendência conhecida, fora do escopo desta passada:** os
> **quatro botões `enrol-refresh` por-pane foram removidos** na fatia do refresh de cabeçalho (D2,
> `7d69197`) e substituídos por um refresh no cabeçalho do modal (`mount` devolve `{refresh: () => init(state)}`,
> `enrol_methods.js:1111`; consumido em `participants_manager.js:213-232`, `attachRefresh`); a narrativa de
> `ENROL-REFRESH` e do "pane em branco" ainda descreve os botões antigos e precisa de um re-sync próprio,
> como os mapas irmãos ganharam em `77243d1`.

## Portões — quatro regiões, uma revelada por vez

As quatro nascem `hidden` no Mustache; `init()` revela **uma**: em sucesso, um de `enrol-disabled` /
`enrol-empty` / `enrol-main` (`enrol_methods.js:954-967`); numa falha **precoce** — a carga inicial
rejeita antes de qualquer `hidden = false` — o `catch` revela `enrol-error` (`:939`) e esconde as
outras três (`:940-942`). Os alerts são blocos simples de propósito: o comentário de
`enrol_methods.mustache:33-35` avisa que um `.d-flex` no próprio alert **venceria** o atributo `hidden`
(`display` é `!important` nas utilities), então o flex mora num wrapper interno.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-ROOT` | `[sem rótulo]` | região/raiz | `enrol_methods.mustache:32` | `data-region="enrol"` · `.local-dimensions-enrol` | é o `state.root`; o listener delegado pousa nele (`enrol_methods.js:990`) |
| `ENROL-DISABLED` | aviso: os dois plugins desabilitados no site | alerta | `enrol_methods.mustache:36-38` | `data-region="enrol-disabled"` · `alert-warning` | `central_enrol_bothdisabled` (`:37`). Revelado por `enrol_methods.js:954-959` quando `!cohortenabled && !selfenabled` — a aba inteira fica inerte. Quem conserta é o `PART-LINK-ENROL`, que **só o admin do site vê** (ver o descasamento de fechaduras abaixo) |
| `ENROL-EMPTY` | aviso: nenhum coorte vinculado | alerta | `enrol_methods.mustache:39-41` | `data-region="enrol-empty"` · `alert-info` | `central_enrol_empty` (`:40`) manda o usuário para a **aba Coortes**. Revelado por `enrol_methods.js:961-965` quando `!cohortdata.cohorts.length` |
| `ENROL-ERROR` | aviso: falha ao carregar os métodos | alerta | `enrol_methods.mustache:42-44` | `data-region="enrol-error"` · `alert-warning` | **ENTREGUE em 2026-07-16 (`c2d9471`)** — era a 4ª porta que este mapa pedia como *to-be* (ver a seção da trava de montagem). `central_enrol_loadfailed` (`:43`). Revelado no `catch` da carga inicial de `init` (`enrol_methods.js:939`), que esconde as outras três (`:940-942`), e **relança** para o *swallow* do mount ainda emitir o toast. `alert-warning` (não `danger`): a falha é transitória/retentável. *(O atalho de recuperação por-pane era um `enrol-refresh` local; desde a fatia D2 `7d69197` a recuperação é o refresh do cabeçalho do modal — ver a nota de resync 2026-07-18.)* |
| `ENROL-MAIN` | `[sem rótulo]` | região principal | `enrol_methods.mustache:45` | `data-region="enrol-main"` | revelada em `enrol_methods.js:967`. **Tudo** o que segue mora aqui dentro |
| `ENROL-REFRESH` | Atualizar | ~~botão ×4~~ **removido (D2 `7d69197`)** | *[não existe mais no pane]* | ~~`data-action="enrol-refresh"`~~ | **Os quatro botões `enrol-refresh` por-pane foram removidos** na fatia do refresh de cabeçalho (D2, `7d69197`): o refresh passou ao cabeçalho do modal (`mount` devolve `{refresh: () => init(state)}`, `enrol_methods.js:1111`; ligado por `attachRefresh` em `participants_manager.js:213-232`). Linha mantida como marcador — a narrativa antiga (`ENROL-REFRESH`/IMP-05/"pane em branco") ainda a cita e aguarda re-sync próprio (ver nota 2026-07-18) |

## Barra de configuração

Uma linha `d-flex` com os três controles distribuídos (`enrol_methods.mustache:46`), a dica embaixo.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-COHORT` | **Coorte do plano** | select | `enrol_methods.mustache:51` | `data-region="enrol-cohort"` · `form-select` | rótulo em `:48-50`. Opções via `list_template_cohorts` (`enrol_methods.js:969-970`) — **os coortes já vinculados ao template**, não todos os do site. Trocar dispara `reload` (`:1048-1050`) |
| `ENROL-METHOD` | Método | grupo de botões | `enrol_methods.mustache:57-67` | `data-region="enrol-method"` · `role="group"` | **`cohort`** (`:59-62`, nasce `active`/`btn-primary`/`aria-pressed="true"`, ícone estático `fa-users`) e **`self`** (`:63-66`, ícone estático `fa-user-plus`). Rotulado por `aria-labelledby` → o `<span>` de `:54-56` (não é `<label>`: não há um controle único a apontar). Trocar **não refaz fetch** — `applyMethodChange` (`:756-780`) repinta das `data-*` da linha |
| `ENROL-METHOD-OFF` | `[sem rótulo]` | regra de disponibilidade | `enrol_methods.js:891-898` | `button.disabled = !enabled` | cada segmento é desabilitado se o plugin correspondente estiver off no site (`enrol_is_enabled`, `list_enrol_competencies.php:202-203`). Se **só** `cohort` estiver off, o pane troca sozinho para `self` (`:896-898`) |
| `ENROL-ROLE` | **Papel atribuído** | select | `enrol_methods.mustache:73` | `data-region="enrol-role"` · `form-select` | rótulo em `:70-72`. `eligible_roles()` = `$CFG->gradebookroles` **∩** `get_default_enrol_roles($context)` (`classes/local/enrol_methods.php:58-73`) — gradebook **e** atribuível por inscrição. Default = arquétipo *student* quando elegível, senão o primeiro (`:81-89`). Trocar **não** recarrega (`enrol_methods.js:1051-1052`): só vale na próxima ação |
| `ENROL-HINT` | `[sem rótulo]` | texto | `enrol_methods.mustache:76` | `data-region="enrol-hint"` | `central_enrol_hint_cohort` / `_hint_self`, trocado em `enrol_methods.js:766-767` e `:976-977` |

## Barra de filtros

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-SEARCH` | Pesquisar competências | input texto | `enrol_methods.mustache:82-84` | `data-region="enrol-search"` · `.local-dimensions-enrol-search` | **faltava inteiro** (`ec9d813`). Rótulo `visually-hidden` (`:79-81`) **e** `placeholder` com a mesma string. Debounce de **300 ms** → `reload` (`enrol_methods.js:1033-1044`); o comentário de `:1037-1038` diz por que é server-side: a lista é paginada, um filtro client-side perderia as páginas não carregadas. Largura fixa `14rem` (`styles.css:5999-6001`) |
| `ENROL-CAT` | Categoria de curso | select | `enrol_methods.mustache:90-91` | `data-region="enrol-category"` | rótulo `visually-hidden` (`:87-89`). Opções = `central_enrol_categoryall` + as categorias **dos cursos vinculados** (`enrol_methods.js:882-885`; `list_enrol_competencies.php:185-197`). Trocar dispara `reload` (`:1053-1055`) |
| `ENROL-HIDDEN` | Mostrar cursos ocultos | switch | `enrol_methods.mustache:94-95` | `data-region="enrol-hidden"` · `.form-check.form-switch` | rótulo real em `:96-98` (`for`/`id` — o seletor `"checkbox"` do Behat exige `<label>`, não `aria-label`). Ocultos escondidos por padrão (`enrol_methods.js:1095`); trocar dispara `reload` (`:1056-1058`) |
| `ENROL-VISCOUNT` | `[sem rótulo]` | contador | `enrol_methods.mustache:100` | `data-region="enrol-viscount"` | `central_enrol_viscount` ("N cursos exibidos") com `data.totalcourses` = **cursos configuráveis distintos após os filtros** (`enrol_methods.js:498-503`; `list_enrol_competencies.php:151`) |

## Accordion — grupos de competência

`ENROL-TREE` é uma caixa de rolagem própria: `max-height: 50vh; overflow-y: auto`
(`styles.css:5961-5964`) para a barra de config acima e o rodapé de ações abaixo ficarem sempre
visíveis. Grupos via `renderGroupHtml` → `appendNodeContents` (`enrol_methods.js:340-354`, `:487`).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-TREE` | `[sem rótulo]` | contêiner-JS | `enrol_methods.mustache:102` | `data-region="enrol-tree"` | vazio quando `!data.total` → parágrafo `nothingtodisplay` (`enrol_methods.js:488-493`) |
| `ENROL-GROUP` | `[sem rótulo]` | grupo | `enrol_group.mustache:36` | `data-group={id}` · `data-name={name}` | `data-name` é lido de volta em `loadCourses` (`enrol_methods.js:529`) para carimbar o nome da competência na linha |
| `ENROL-GROUP-CB` | Selecionar todos os cursos de {competência} | checkbox | `enrol_group.mustache:38-39` | `data-groupcheck={id}` | `aria-label` via `central_enrol_selectall`. Só alcança as linhas **já carregadas** do grupo e **pula as em processamento** (`enrol_methods.js:1063-1071`) |
| `ENROL-TOGGLE` | {nome da competência} | botão | `enrol_group.mustache:40-47` | `data-action="enrol-toggle"` · `aria-expanded` | chevron (`:44`) + nome (`:45`) + badge de contagem (`:46`). **O nome é o `shortname`** (`enrol_methods.js:350`), não o `fullname`. Rotação do chevron e o *fade/slide* são **CSS puro** keyed no `aria-expanded` (`styles.css:5970-5992`) |
| `ENROL-GROUP-COUNT` | N cursos | badge | `enrol_group.mustache:46` | `badge bg-secondary text-dark` | `central_enrol_courses` / `_coursesone` (singular próprio, `enrol_methods.js:342-344`). O par `bg-secondary` + `text-dark` é deliberado — ver a nota de contraste |
| `ENROL-CHILDREN` | `[sem rótulo]` | contêiner | `enrol_group.mustache:49` | `data-children={id}` · `data-offset="0"` · `hidden` | **carga preguiçosa na 1ª expansão**, com trava `data-loaded` que é **revertida no erro** (`enrol_methods.js:568-576`), então re-expandir sempre tenta de novo. A trava do host **também** passou a se recuperar (`c96a3e9`), mas só numa rejeição pré-fiação — ver a seção da trava de montagem |
| `ENROL-CAPTION` | {nome da competência} | caption | `enrol_group.mustache:51` | `visually-hidden` | — |
| `ENROL-HEAD` | Selecionar · Curso · Categoria · Papel · Status · Ações | cabeçalho | `enrol_group.mustache:53-60` | — | **6 colunas** (`545ba17` trocou o accordion solto por `table generaltable` listrada). A 1ª é `{{#str}}select{{/str}}` **`visually-hidden`** (`:54`); as outras cinco são strings do core (`course`, `category`, `role`, `status`, `actions`) |
| `ENROL-ROWS` | `[sem rótulo]` | contêiner-JS | `enrol_group.mustache:62` | `data-region="enrol-rows"` | `<tbody>`; linhas via `makeRow` |
| `ENROL-MORECOMPS` | Carregar mais | botão | `enrol_methods.js:496` | `data-action="enrol-morecomps"` · `data-offset` | página de **20** competências (`:39`); o botão se remove ao clicar (`:1008`) |
| `ENROL-MORECOURSES` | Carregar mais | botão | `enrol_methods.js:544` | `data-action="enrol-morecourses"` · `data-competencyid` · `data-offset` | página de **25** cursos (`:40`) |

## Linha de curso — **DOM-built**, não Mustache

`makeRow` (`enrol_methods.js:366-463`). Cada linha carrega o status dos **dois** métodos nas suas
`data-*` (`:370-384`), então trocar o segmento e abrir o detalhe **não refazem fetch**.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-ROW` | `[sem rótulo]` | linha | `enrol_methods.js:368-369` | `.local-dimensions-enrol-row` · `data-courseid` + 14 `data-*` | o **mesmo curso pode aparecer sob mais de uma competência**: toda escrita varre os *gêmeos* por `data-courseid` (`:261`, `:589`, `:619`, `:705`) |
| `ENROL-ROW-CB` | Selecionar {shortname} | checkbox | `enrol_methods.js:387-391` | `data-rowcheck="1"` | `aria-label` via `central_enrol_selectcourse`. **Escondido** (não desabilitado) quando processando (`:239`) |
| `ENROL-ROW-SPIN` | `[sem rótulo]` | spinner | `enrol_methods.js:392-396` | `data-region="row-spinner"` · `fa-spinner fa-spin` | ocupa o lugar do checkbox no estado processando (`:240`) — é a troca 1-para-1 que a spec previa |
| `ENROL-ROW-NAME` | {shortname} · {fullname} | cabeçalho de linha | `enrol_methods.js:400-418` | `<th scope="row">` | shortname em negrito + `·` + fullname **inteiro** (`:407`) — a spec previa truncar; o código **não trunca**. Curso oculto ganha `fa-eye-slash` + texto `visually-hidden` `hiddenfromstudents` (`:408-418`) |
| `ENROL-ROW-CAT` | {categoria} | célula | `enrol_methods.js:420-421` | — | texto puro desde `545ba17` (era badge) |
| `ENROL-ROW-ROLE` | {papel} | célula | `enrol_methods.js:423-424` | `data-region="row-role"` | preenchida **só** quando `configured` (`:226-228`); é o papel **efetivo da instância**, não o do `ENROL-ROLE` |
| `ENROL-STATUS` | Configurado / Processando / Não configurado | badge | `enrol_methods.js:426-437` | `data-region="row-status"` · `-icon` + `-text` | o pill é um `span.badge` com `<i data-region="row-status-icon">` + `<span data-region="row-status-text">` (`makeRow`, `:426-437`); `paintRow` (`:223-225`) põe a classe de cor (`STATUS_BADGES`, `:89-93`), a classe do ícone **por método** (`'fa ' + methodIcon + ' me-1'`) **e** grava a palavra de status no `-text`. O **texto** visível não mudou (Configurado/Processando/Não configurado) — deliberado: a asserção Behat "Not configured" continua valendo; só entrou um ícone por método (`fa-users`/`fa-user-plus`). Reflete **só** o método+coorte selecionados (`rowStatus`, `:192-194`) |
| `ENROL-TOGGLE-STATUS` | Inscrição habilitada / desabilitada | botão | `enrol_methods.js:441-453` | `data-action="enrol-toggle-status"` | **faltava inteiro** (`a5ef9a8`). Só aparece se `configured` (`:231`); ícone `fa-eye`/`fa-eye-slash` (`:233`). Chama `set_enrol_instance_status`, repinta os gêmeos, **pisca** (`:626`) e emite toast (`:629`) |
| `ENROL-INFO` | Detalhes | botão | `enrol_methods.js:454` | `data-action="enrol-info"` | via `iconButton('enrol-info', 'fa-circle-info', …)` — texto visível é o nome acessível |

## Rodapé de ações

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-SELCOUNT` | N selecionado(s) | contador | `enrol_methods.mustache:104` | `data-region="enrol-selcount"` | `central_enrol_selcount`; `state.selected.size` (`enrol_methods.js:274-279`) |
| `ENROL-PROC` | N em processamento | indicador | `enrol_methods.mustache:105-108` | `data-region="enrol-proccount"` · `hidden` | `fa-spinner fa-spin` (`:106`) + texto em `:107`. Escondido quando `pending.size === 0` (`enrol_methods.js:280-289`) |
| `ENROL-REMOVE` | Remover · {método} | botão | `enrol_methods.mustache:110-113` | `data-action="enrol-remove"` · `disabled` | `btn-outline-danger`; nasce desabilitado, habilitado só com seleção (`enrol_methods.js:290-292`). Não é mais `{{#str}}` estático: carrega `<i data-region="enrol-remove-icon">` + `<span data-region="enrol-remove-text">`; `setActionLabels` (`:302-311`) põe o ícone do método e o texto `central_enrol_remove_method` = "Remover · <método>". O mustache mantém `central_enrol_remove` genérico como rótulo de fallback pré-JS (`:112`) |
| `ENROL-APPLY` | Aplicar · {método} | botão | `enrol_methods.mustache:114-117` | `data-action="enrol-apply"` · `disabled` | `btn-primary`; mesma regra (`enrol_methods.js:290-292`). `<i data-region="enrol-apply-icon">` + `<span data-region="enrol-apply-text">`; `setActionLabels` (`:302-311`, **novo**, síncrono) põe o texto `central_enrol_apply_method` = "Aplicar · <método>" (fallback `central_enrol_apply`, `:116`). Chamado de `init` (`:978`) e de `applyMethodChange` (`:768`) a cada troca de método; os 4 rótulos resolvidos (apply/remove × cohort/self) são **pré-carregados** no 2º `getStrings` de `loadLabels` (`:128-137`), então o repaint é síncrono (`método` = `central_enrol_method_cohort`/`_self`) |

## Modais

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-CONFIRM` | Remover método de inscrição | modal | `enrol_methods.js:647-659` | `Notification.saveCancelPromise` | **título** = `central_enrol_confirm_remove_title` (é por ele que o Behat acha o diálogo, não pela palavra "Confirmação") — **inalterado**; corpo `central_enrol_confirm_remove` virou placeholder-**objeto** (`{$a->method}` + `{$a->count}`, era escalar `{$a}`=contagem), e a chamada JS passa `{method: <nome>, count: courseids.length}` (`:651`); botão = `{{#str}}remove{{/str}}`. Cancelar **retorna** sem enfileirar (`:656-658`) |
| `ENROL-DETAIL` | {fullname} | modal | `enrol_methods.js:839-862` + `enrol_detail.mustache` | `Modal.create({large: true})` · `setRemoveOnClose(true)` | tabela rótulo/valor: categoria (`:55-58`), visível (`:59-62`), competência (`:63-66`) e **as duas linhas de método** (`:67-74`) — cujos `<th>` (cohortlabel/selflabel) agora levam um ícone de método (`fa-users` `:68` / `fa-user-plus` `:72`) — montadas por `statusLine` (`enrol_methods.js:807-830`), que compõe status + data + `Inativo` + papel. Tudo é **pré-localizado** no JS: o template não tem `{{#str}}` |
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
`setRemoveOnClose(true)` (`:172`) a única recuperação era **fechar e reabrir o modal**. O pane de Coortes
era pior — montado uma vez no `shown`, **sem trava nenhuma**, e como a própria aba de Coortes não roda o
`ensureMounted`, um pane-padrão que falhasse não tinha recuperação alguma dentro do modal.

**A trava — CORRIGIDO em 2026-07-16 (`c96a3e9`).** As quatro montagens passam por um só
`startMount(key, mountfn, selector)` (`participants_manager.js:198-210`) sobre uma tabela única
`mounted = {cohorts, users, roles, enrol}` (`:191`). Ele **reivindica a trava de forma síncrona**
(`mounted[key] = true`, `:202`) — um duplo-clique ainda dispara um único mount — e a **libera no `.catch`**
(`mounted[key] = false`, `:207`), então a **próxima ativação da aba tenta de novo**. Coortes entrou na
tabela (`:237`, e via `ensureMounted` em `:243`), logo re-clicar a aba-padrão também a recupera.
Liberar-no-catch só é seguro porque uma trava liberada sempre significa um pane **não-fiado**: Coortes e
Papéis dão `replaceNodeContents`-clear e fiam **nós-filhos frescos**, então um remount descarta os
listeners antigos e começa limpo.

**Correção que este mapa faz contra si mesmo: o enrol NÃO é idempotente sob `replaceNodeContents`.** `mount`
(`enrol_methods.js:1082-1112`) limpa o container com `replaceNodeContents` (`:1085`), mas isso esvazia só
os **filhos** — o `wireEvents` (`:1103`) **delega** o listener de `click` no **próprio elemento container**
(`state.root`, `:990`), que **sobrevive** ao clear. Um remount ingênuo empilharia um segundo jogo de
listeners. Por isso o único await pós-fiação foi **engolido para um toast**: `await init(state)` virou
`await init(state).catch(notifyError)` (`:1108`). Uma falha **pós-fiação** agora **resolve** o mount — a
trava fica `true`, nenhum re-clique refaz, e existe **exatamente um** estado fiado. É por isto que o enrol
não pode simplesmente liberar-e-remontar como Coortes/Papéis, e por que o *swallow* de `:1108` é
**obrigatório**, não opcional.

**O pane em branco na 1ª montagem — ENTREGUE em 2026-07-16 (`c2d9471`).** Este mapa desenhava, como
*to-be*, uma **4ª porta**: uma região de erro, revelada no `catch` do `init`, com um atualizar **fora** das
outras três. Foi **exatamente** o que shipou. As três regiões de sucesso nascem `hidden` e quem revela
**uma** é o `init` (`:954-967`). Se o `Promise.all` da carga inicial (`:918-934`) rejeitar — WS fora, rede
caindo —, o `init` **antes** saía por exceção antes de qualquer `hidden = false`, o erro era engolido pelo
`.catch` de `:1108`, e as três regiões — com os **três** `enrol-refresh` presos dentro delas — ficavam
todas ocultas: pane em branco, nenhum atualizar alcançável, recuperação só reabrindo o modal. *(Os botões
`enrol-refresh` por-pane foram depois removidos na fatia D2 `7d69197`; esta narrativa aguarda re-sync
próprio — ver nota 2026-07-18.)*

**Como ficou.** A carga inicial agora roda dentro de um `try` (`enrol_methods.js:917-934`); no `catch`, o
`init` **revela a `ENROL-ERROR`** (`error.hidden = false`, `:939`), **esconde** as outras três (`:940-942`)
e **relança** — o `.catch` de `:1108` ainda emite o toast. Assim a falha **precoce** virou **recuperável**,
sem reabrir o modal. `alert-warning` (não `danger`): a falha é transitória/retentável. A trava do host segue
`true` — o *swallow* de `:1108` continua deliberado (a alternativa, liberar e remontar, duplicaria os
listeners). *(A recuperação **no lugar** era, quando este mapa foi escrito, um `enrol-refresh` dentro da
`ENROL-ERROR`; desde a fatia D2 `7d69197` o retry é o refresh do cabeçalho do modal — `mount` devolve
`{refresh: () => init(state)}` (`:1111`), consumido por `attachRefresh` (`participants_manager.js:213-232`)
— ver nota 2026-07-18.)*

A falha **tardia** (`loadCompetencies`, `:980`) sempre foi recuperável e continua **deliberadamente fora**
do `try`: `main.hidden = false` já rodou em `:967`, então o `enrol-main` fica visível e o refresh do
cabeçalho re-roda `init(state)`. *(No texto original o retry da falha tardia era um `enrol-refresh` dentro
do `enrol-main`, removido na D2 `7d69197` — ver nota 2026-07-18.)* Com a `ENROL-ERROR` fechando a falha
**precoce** e o `enrol-main` cobrindo a **tardia**, a conclusão
do `mod-participants.md` ("só o atualizar deixa tentar de novo") agora vale para **todos** os estados: os
alertas `ENROL-EMPTY`/`ENROL-DISABLED` (em que o `init` **teve sucesso** e revelou um alerta), a falha
tardia, e — desde `c2d9471` — a falha precoce.

### Concorrência — dedup por `(curso, método, coorte)`

Cada combinação é uma **task adhoc independente**; combinações diferentes rodam em paralelo. A
chave é `process_enrol_method::key($courseid, $method, $cohortid)` (`:102`), o `pending_map()`
(`:114`) é consultado sob o lock da fila antes de enfileirar (`queue_enrol_action.php:144-148` →
`status = 'skipped'`) e a execução serializa na Lock API (`process_enrol_method.php:206-209`,
timeout 60 s → `central_enrol_busy`). O JS só marca `processing` no que **não** voltou `skipped`
(`enrol_methods.js:671-675`). O mesmo curso segue livre para a **outra** combinação — é por isso
que `pending` é reconstruído do zero a cada troca de método (`:769-777`).

### O poll

`POLL_MS = 5000` (`:41`), `setInterval` só enquanto há `pending` (`ensurePolling`, `:723-733`).
Cada volta consulta `get_enrol_queue_status` e vira as linhas prontas para `configured`/
`notconfigured`, com **flash** amarelo (`:708`). O timer para sozinho quando `pending` esvazia
(`:712-714`) **ou** quando a raiz sai do DOM (`!state.root.isConnected`, `:688`) — é o que impede
o poll de sobreviver ao `setRemoveOnClose` do modal. Regra da casa respeitada: **linha trocada →
flash** (`:626`, `:708`), nunca spinner de pane inteiro.

### Contraste: por que `bg-secondary` vem com `text-dark`

O comentário de `enrol_methods.js:87-88` registra a decisão, e ela se mede: o `secondary` do Boost
é um cinza claro (`#ced4da`) e o texto padrão do badge é branco — **1,49:1**, reprova. O par que o
código shipa, `bg-secondary` + `text-dark` (`#1d2125`), dá **10,84:1**. Vale para o
`ENROL-STATUS` "Não configurado" (`:92`) e para o `ENROL-GROUP-COUNT` (`enrol_group.mustache:46`).

## IMP-05 (`mtube: atualizar`) — **esta aba é o precedente visual**

A decisão e as verificações moram em [`bar-contextbar.md`](bar-contextbar.md). O que **este** mapa
fixa, de forma independente:

- **[Histórico — ver nota 2026-07-18]** Os **quatro** `data-action="enrol-refresh"` por-pane eram,
  quando este mapa foi escrito, a **única afordância de atualizar de todo o hub**. Foram **removidos
  na fatia D2 (`7d69197`)** e substituídos por um **refresh no cabeçalho do modal** (`mount` devolve
  `{refresh: () => init(state)}`, `enrol_methods.js:1111`; ligado por `attachRefresh` em
  `participants_manager.js:213-232`), que passou a ser a afordância de atualizar. O ponto de
  precedência do IMP-05 fica **histórico** e a narrativa aguarda re-sync próprio.
- **Precisão medida (o mapa confirma de forma independente):** `reloadPane` existe
  (`tabs.js:69`) e tem **24 chamadas em 5 módulos** — `structure` 9, `frameworks` 6, `plans` 6,
  `context` 2, `competency_browser` 1. **Não** é verdade que "nada expõe `reloadPane`"; o que é
  verdade é que **nenhum controle de UI** o dispara. (Um `grep -rn reloadPane amd/src/` devolve
  **36** linhas: as 24 chamadas + 1 definição + 5 imports + 6 comentários — p.ex. `frameworks.js:18`
  e `plans.js:784`. Contar as 36 como chamadas é o erro fácil.)
- **Este pane não usa `reloadPane`** e não é exceção a nada: seu atualizar é `init(state)` — hoje via
  o handle `{refresh: () => init(state)}` que `mount` devolve (`enrol_methods.js:1111`) e o cabeçalho
  consome — porque ele é **pane de modal**, não pane de aba do `core/dynamic_tabs`; `reloadPane` não
  o alcançaria.
- **A disciplina a copiar é a do mtube** (`course_report.js:286-299`, via `sourcesContent` do
  sourcemap): `disabled = true` + `fa-spin` no ícone, revertidos num `finally`. Os (extintos) quatro
  botões desta aba **não tinham** essa disciplina — clicar duas vezes disparava dois `init`
  concorrentes; o refresh de cabeçalho que os substituiu (D2 `7d69197`) deveria nascer com ela. O
  IMP-05 herda a mesma recomendação.
- **Rastreabilidade das refs do mtube:** o `format_mtube` **não** tem `amd/src` neste checkout, só
  `amd/build`. Uma ref de JS do mtube por `file:line` **não resolve para ninguém** — por isso este
  mapa cita o mtube por **nome de símbolo**. Um `grep` no disco por esse `.js` não achar nada é
  **esperado**, não é ausência.
