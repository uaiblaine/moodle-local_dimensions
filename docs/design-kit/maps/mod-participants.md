# Mapa de Campos — `MOD.PART` · Modal Participantes (as-is)

Modal hospedeiro (`core/modal`, **sem rodapé**) com um `<h5>` de nome do template e **quatro** abas:
**Coortes / Usuários / Atribuir papéis / Métodos de inscrição**. As abas são **artesanais** — não há
dependência do JS de abas do Bootstrap dentro do modal (`participants_manager.js:110`): o
`activateTab` alterna `.active`/`.show` e `aria-selected` na mão, com um *roving tabindex*
WAI-ARIA (Setas/Home/End) por cima.

Três dos quatro panes nascem **vazios** no Mustache e são montados por JS; só o de Usuários chega
renderizado do servidor. Essa assimetria é a origem do achado de loading registrado no fim deste
mapa — **não** é a mesma lacuna do `EST`/`FWK`/`PLN`.

- **Mustache:** [`templates/central/participants_manager.mustache`](../../../templates/central/participants_manager.mustache) (154 linhas, host), [`cohort_manager.mustache`](../../../templates/central/cohort_manager.mustache) (50), [`roles_manager.mustache`](../../../templates/central/roles_manager.mustache) (77), [`enrol_methods.mustache`](../../../templates/central/enrol_methods.mustache) (129)
- **PHP:** [`classes/output/dynamictabs/plans.php`](../../../classes/output/dynamictabs/plans.php) — o modal **não tem renderable próprio**; ele lê tudo do `data-*` da região do `PLN` (`:329-333`)
- **AMD:** [`participants_manager.js`](../../../amd/src/central/participants_manager.js) (241, host), [`cohort_manager.js`](../../../amd/src/central/cohort_manager.js), [`participants_users.js`](../../../amd/src/central/participants_users.js), [`roles_manager.js`](../../../amd/src/central/roles_manager.js), [`enrol_methods.js`](../../../amd/src/central/enrol_methods.js) (1033)
- **To-be no DS:** [`modal-shell.html`](../modal-shell.html) (cabeçalho D2 + links no rodapé), [`cohort-assign.html`](../cohort-assign.html) (estilo gestão de grupos + sync)

> **Resync 2026-07-14.** A versão anterior deste mapa congelou em `159a800` (2026-06-29) — a mesma
> safra do `EST`, do `FWK` e do `PLN`. Medido, não estimado:
>
> - **24 refs no mapa antigo; 12 quebradas — todas as 12 em `participants_manager.mustache`.** As 3
>   de `cohort_manager.mustache` e as 9 de `roles_manager.mustache` **resolvem**: esses dois arquivos
>   não mudaram desde então. O estrago é concentrado, não difuso.
> - **Duas quebras são do tipo pior — resolvem para um controle real, só que de outro ID.**
>   `PART-INDIVIDUAL` apontava `:84`, que hoje é o **select de atribuir a usuário**; `PART-ADD`
>   apontava `:95`, que hoje é o **ícone `fa-filter`** do botão de filtros. Um leitor confere, vê um
>   elemento plausível e segue. As outras dez caíam em `</ul>`, `</li>`, `{{#canassignroles}}`,
>   `<div>`s de layout e uma `{{#str}}` no meio de um `<label>`.
> - **`PART-TAB-ENROL` estava marcada `_to-be_`.** Ela **shipou** em `3d1d5cb` (2026-07-11 23:03) —
>   **~70 minutos** depois de o `0b3782c` (21:53) escrever a linha que a chamava de proposta.
> - **Zero refs de JS**, como no `BAR`. O mapa listava quatro módulos AMD num bullet e não apontava
>   uma linha sequer para dentro deles: nada de `HEADER_PAGES`, `injectHeaderLinks`, `activateTab`,
>   `ensureMounted`, roving tabindex, `modal-xl`, região de toast ou `setRemoveOnClose`.
> - **O que faltava inteiro:** o **dropdown de filtros** (`7c54c0b`, 2026-07-03) — o mapa antigo
>   listava coorte/busca/individual como se fossem controles soltos na barra; eles moram **dentro de
>   um dropdown** com atributos BS4+BS5 lado a lado. Mais: a 4ª aba, o `ROLES-FORM` (o formulário de
>   papéis inteiro nasce `hidden`), os `<caption>` de acessibilidade das três tabelas, e toda a
>   casca do modal (título, chip de fechar, `modal-xl`, links de cabeçalho).
>
> O template foi de **119 → 154** linhas e o host JS de **158 → 241**. Quatro commits passaram por
> cima: `7c54c0b` (filtros), `94734d0` (links de cabeçalho + restyle da tabela e do fechar),
> `3d1d5cb` (aba de inscrição), `f84d30a` (`modal-xl`).

> **Nota de rótulo (verificada, e incômoda).** O mapa antigo chamava a 3ª aba de "Papéis" e a coluna
> de coorte dela de "Coorte". Nenhum dos dois é o que a tela mostra. `central_roles_tab` =
> **"Atribuir papéis"** (`lang/pt_br:272`), e no pane de papéis o pt-BR traduz *cohort* como
> **"Público-alvo"** (`central_roles_col_cohort` `:255`, `central_roles_selectcohort` `:267`) —
> enquanto o resto do modal diz **"Coorte"** (`central_participants_col_cohort` `:196`,
> `central_participants_tab_cohorts` `:212`). O EN é uniforme (`Cohort` / `Cohorts tab`); a
> divergência é só do pt-BR, e ela **vaza para o usuário**: `central_roles_nocohorts` (`:261`) manda
> o usuário para a *"aba Públicos-alvo"* — **uma aba que não existe com esse nome**; a aba se chama
> "Coortes". Este mapa registra os rótulos **como são renderizados**. A correção do pt-BR é de
> `lang/`, fora do escopo do kit.

## Casca do modal (só JS — não há Mustache para isto)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-MODAL` | Gerenciar participantes | modal | `participants_manager.js:144` | `Modal.create({title, body})` | `core/modal` puro — **não** passa `footer`. `setRemoveOnClose(true)` (`:145`): o modal é descartado ao fechar, então **todo o estado montado morre junto** (ver `PART-LATCH`). Título via `central_participants_title` (`:137`) |
| `PART-DIALOG` | `[sem rótulo]` | classes no `.modal-dialog` | `participants_manager.js:151-154` | `modal-xl` + `local-dimensions-participants-modal` + `local-dimensions-headerlink-modal` | as **três** de uma vez. `modal-xl` é do **próprio Bootstrap** (800px em `lg`, 1140px em `xl`, idêntico em 4 e 5) — a API do core só expõe `setLarge()`, daí a classe na mão |
| `PART-FOOT` | `[sem rótulo]` | rodapé oculto | — | `.modal-footer.hidden` | o core chama `hideFooter()` no `show()` quando o rodapé não tem filhos (`public/lib/amd/src/modal.js:875-879`; `hasFooterContent` = `getFooter().children().length`, `:686-688`). O rodapé **existe** no DOM, só está `hidden` — **basta um filho para o core revelá-lo sozinho** (é o que o D2 explora) |
| `PART-TOAST` | `[sem rótulo]` | região de toast | `participants_manager.js:167` | `addToastRegion(modal.getBody()[0])` | padrão da casa: sem ela, o toast dos gerenciadores renderiza **atrás** do diálogo (`.toast-wrapper` é `z-index:1051`, o modal é `1055`). O **host** é dono da região; `cohort_manager` e `participants_users` **não** criam a sua. O core remove no fechamento |
| `PART-CLOSE` | Fechar | chip | `styles.css:3557-3586` | `.btn-close` do core, reestilizado | `1.75rem`, raio `8px`, fundo `#e7f0f9`, glifo FA `\f00d` em `#0f4d85` (**7,53:1** medido), hover `#d4e6fb` (**6,82:1**). Literais, sem variante dark |

> **A segunda função de `local-dimensions-headerlink-modal` — não apague a classe ao mover os
> links.** O `styles.css:3557-3558` é um **grupo de dois seletores**: o primeiro
> (`.modal:not(...):has(.modal-body [class*='local-dimensions-'])`) pega os modais cujo **corpo**
> carrega classe do plugin; o segundo (`.local-dimensions-headerlink-modal .btn-close`) é um gancho
> **independente**, para os modais que **escapam** do `:has()` — o caso do `ModalForm` de framework,
> que renderiza markup de formulário do core. A prova de que a classe **não** existe só pelo link:
> `frameworks.js:139-143` a aplica **antes** do teste de capability de `:144`, com um comentário
> dizendo exatamente isso. Ela pousa **mesmo quando nenhum link é renderizado**. Remover a classe
> apaga o chip de fechar lá — mover os links (D2) aposenta as regras de `:3532-3540`, **não** a
> classe.

## Links de cabeçalho (injetados por JS, `d-none` alternado)

`HEADER_PAGES` (`participants_manager.js:46-55`) declara **4** destinos — um por aba. Cada link é
`<a target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm
local-dimensions-headerlink d-none">` + `<i class="fa fa-arrow-up-right-from-square me-1">`
(`:83`, `:85`), inserido com `header.insertBefore(link, closebtn)` (`:90`) — ou seja, **à esquerda do
chip de fechar**. O rótulo visível **é** o nome acessível (`:88-89`: sem `title`/`aria-label` extra).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-LINK-COHORTS` | Abrir página de coortes | link | `participants_manager.js:47-48` | `/cohort/index.php` · flag `cancohortpage` | `moodle/cohort:view` **ou** `:manage` no sistema (`dynamictabs/plans.php:239-240`) |
| `PART-LINK-USERS` | Abrir página de usuários | link | `participants_manager.js:49-50` | `/admin/user.php` · flag `canuserpage` | `moodle/user:update` **ou** `:delete` (`:241-242`) |
| `PART-LINK-ROLES` | Abrir página de papéis | link | `participants_manager.js:51-52` | `/admin/roles/manage.php` · flag `canassignroles` | `moodle/role:manage` (`:238`) |
| `PART-LINK-ENROL` | Gerenciar plugins de inscrição | link | `participants_manager.js:53-54` | `/admin/settings.php?section=manageenrols` · flag `canenrolpage` | `moodle/site:config` (`:243`) |
| `PART-LINK-SHOW` | `[sem rótulo]` | regra de exibição | `participants_manager.js:103-107` | `classList.toggle('d-none', pane !== paneregion)` | **um visível por vez** — o da aba ativa. `injectHeaderLinks` devolve `{}` cedo se nenhum for permitido (`:72-74`), e aí `showHeaderLinkFor` vira no-op |

> **Aba e link são portas com fechaduras diferentes — e em duas abas elas divergem.** A aba Papéis e
> o link de papéis compartilham a **mesma** flag (`canassignroles`), então andam juntos. Já a aba
> **Métodos de inscrição** é gated em `canmanageenrol`, que o `plans.mustache:133` alimenta com
> **`{{canmanage}}`** = `moodle/competency:templatemanage` **no contexto** (`dynamictabs/plans.php:98`,
> `:329`) — enquanto o **link** dela quer `moodle/site:config` **no sistema**. Um gestor de template
> vê a aba e **não** vê o link. As abas Coortes e Usuários são incondicionais; seus links, não.

## Host + abas

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-ROOT` | `[sem rótulo]` | região/raiz | `participants_manager.mustache:36` | `data-region="participants"` · `data-contextid` | o contexto vem do `data-contextid` da região do `PLN` (`participants_manager.js:140`) |
| `PART-HEADER` | nome do template | heading | `participants_manager.mustache:38` | `{{templatename}}` | `<h5 class="mb-0">`, dentro de `.local-dimensions-participants-header` (`:37`). **Duplica** o nome que já está no título do modal — o modal se chama "Gerenciar participantes" e o `<h5>` diz de qual plano |
| `PART-TABLIST` | `[sem rótulo]` | tablist | `participants_manager.mustache:40` | `data-region="participant-tabs"` | `<ul class="nav nav-tabs" role="tablist">`; os `<li>` são `role="presentation"` e o `<button>` é quem tem `role="tab"` |
| `PART-TAB-COHORTS` | Coortes | aba | `participants_manager.mustache:42-46` | `data-target-pane="pane-cohorts"` · `data-region="tab-cohorts"` | **nasce ativa** (`aria-selected="true"`, `.active` em `:42`, `:44`); incondicional |
| `PART-TAB-USERS` | Usuários | aba | `participants_manager.mustache:49-53` | `data-target-pane="pane-users"` | incondicional |
| `PART-TAB-ROLES` | **Atribuir papéis** | aba | `participants_manager.mustache:57-61` | `data-target-pane="pane-roles"` | só `{{#canassignroles}}` (`:55`-`:63`) |
| `PART-TAB-ENROL` | Métodos de inscrição | aba | `participants_manager.mustache:66-70` | `data-target-pane="pane-enrol"` | só `{{#canmanageenrol}}` (`:64`-`:72`). **Shipou em `3d1d5cb`** — monta `MOD.ENROL` |
| `PART-PANE-COHORTS` | `[sem rótulo]` | pane **vazio** | `participants_manager.mustache:75-76` | `data-region="pane-cohorts"` | `<div></div>` — preenchido por `mountCohorts` |
| `PART-PANE-USERS` | `[sem rótulo]` | pane **renderizado** | `participants_manager.mustache:77-144` | `data-region="pane-users"` | **o único** que chega pronto do servidor |
| `PART-PANE-ROLES` | `[sem rótulo]` | pane **vazio** | `participants_manager.mustache:146-147` | `data-region="pane-roles"` | `{{#canassignroles}}` (`:145`-`:148`) |
| `PART-PANE-ENROL` | `[sem rótulo]` | pane **vazio** | `participants_manager.mustache:150-151` | `data-region="pane-enrol"` | `{{#canmanageenrol}}` (`:149`-`:152`) |

## Aba Coortes (`MOD.COHORT`)

Montada por `cohort_manager.js:208-233`: strings → `renderForPromise` → `replaceNodeContents` →
`setup`.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `COHORT-ADD` | Adicionar coorte | select/autocomplete | `cohort_manager.mustache:35-36` | `data-region="cohort-add"` · `data-contextid` | rótulo em `:34`; realçado no `ModalEvents.shown` (o `enhance` do core resolve por `document.querySelector`) |
| `COHORT-CAPTION` | Coortes | caption | `cohort_manager.mustache:39` | `visually-hidden` | acessibilidade da tabela — **não estava no mapa** |
| `COHORT-HEAD` | Coorte · Membros · Planos · Ações | cabeçalho | `cohort_manager.mustache:42-45` | — | 4ª coluna é `{{#str}}actions{{/str}}` (**tem** rótulo — "Ações"; o mapa antigo dizia "sem rótulo") |
| `COHORT-ROWS` | `[sem rótulo]` | contêiner-JS | `cohort_manager.mustache:48` | `data-region="cohort-rows"` | linhas via `local_dimensions_list_template_cohorts` |

## Aba Usuários (renderizada no servidor)

Montada por `participants_users.js:262+`: **não** faz `replaceNodeContents` — o markup já existe, o
mount só liga os eventos e busca as linhas.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-ADD` | **Atribuir a usuário** | select/autocomplete | `participants_manager.mustache:84-85` | `data-region="participant-add"` | rótulo em `:81-83`. `participants_users.js:272-274` carimba `contextid`/`templateid` no `dataset` do próprio select (*dataset-as-truth*). O mapa antigo dizia "Adicionar participante" — não é o rótulo |
| `PART-FILTERS` | Filtros | dropdown | `participants_manager.mustache:90-96` | `data-region="participant-filters"` | **faltava inteiro.** `<i class="fa fa-filter me-1">` + `{{#str}}filters, moodle{{/str}}`. Carrega **`data-toggle` E `data-bs-toggle`** lado a lado (`:93`) — o 4.5 é BS4 e escuta `data-toggle`; o 5.x é BS5 e escuta `data-bs-toggle`, e os atributos de JS **não** são pontes um do outro. `data-bs-auto-close="outside"` mantém o menu aberto no BS5; no BS4 quem faz isso é o `<form>` (`:100`) — comentado em `:87-89` |
| `PART-FILTERS-MENU` | `[sem rótulo]` | menu | `participants_manager.mustache:97-99` | `.dropdown-menu` | **as duas** classes de alinhamento: `dropdown-menu-right` (BS4) + `dropdown-menu-end` (BS5) |
| `PART-COHORTFILTER` | Filtrar por coorte | select | `participants_manager.mustache:106-107` | `data-region="participant-cohort"` | rótulo em `:102-105`; **dentro** do dropdown |
| `PART-SEARCH` | **Buscar por nome** | input texto | `participants_manager.mustache:114-115` | `data-region="participant-search"` | rótulo em `:110-113`; dentro do dropdown. Debounce em `participants_users.js` (`state.debounce`) |
| `PART-INDIVIDUAL` | **Mostrar planos individuais** | switch | `participants_manager.mustache:118-120` | `data-region="participant-individual"` | `.form-check.form-switch` (`:117`), rótulo em `:121-123`; dentro do dropdown |
| `PART-CAPTION` | Usuários | caption | `participants_manager.mustache:130` | `visually-hidden` | — |
| `PART-HEAD` | Usuário · Status · Modelo · Coorte · Individual · Ações | cabeçalho | `participants_manager.mustache:133-138` | — | 6 colunas, a última é "Ações" (`{{#str}}actions{{/str}}`, `:138`) |
| `PART-ROWS` | `[sem rótulo]` | contêiner-JS | `participants_manager.mustache:141` | `data-region="participant-rows"` | `<tbody>` |
| `PART-SENTINEL` | `[sem rótulo]` | sentinela | `participants_manager.mustache:143` | `data-region="participant-sentinel"` | scroll infinito via `IntersectionObserver` (`participants_users.js`, `state.observer`) |
| `PART-ROWBTN` | `[sem rótulo]` | regra de CSS | `styles.css:3546-3548` | `#local-dimensions-pane-users button.btn...me-1` | `margin-bottom: 5px` **escopado só neste pane** — nas outras abas a margem extra desalinhava botões solitários |

## Aba Atribuir papéis (`MOD.ROLES`)

Montada por `roles_manager.js:218-250`. O `refresh` (`:247`) é quem decide qual dos três blocos
aparece.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ROLES-NOROLES` | aviso: nenhum papel atribuível no contexto de usuário | alerta | `roles_manager.mustache:31-33` | `data-region="role-noroles"` | `hidden` até o JS decidir |
| `ROLES-NOCOHORTS` | aviso: vincule um público-alvo antes | alerta | `roles_manager.mustache:34-36` | `data-region="role-nocohorts"` | `hidden` até o JS. **O texto manda o usuário para a "aba Públicos-alvo", que não existe** — a aba é "Coortes" (ver nota de rótulo) |
| `ROLES-FORM` | `[sem rótulo]` | contêiner | `roles_manager.mustache:37` | `data-region="role-form"` | **faltava.** Embrulha **tudo** o que vem abaixo e nasce `hidden` — o pane pode ficar inteiro invisível se um dos dois avisos vencer |
| `ROLES-USER` | Usuário | select/autocomplete | `roles_manager.mustache:43` | `data-region="role-user"` | rótulo em `:40-42` |
| `ROLES-ROLE` | Papel | select | `roles_manager.mustache:49` | `data-region="role-role"` | rótulo em `:46-48` |
| `ROLES-COHORT` | **Público-alvo** | select | `roles_manager.mustache:55` | `data-region="role-cohort"` | rótulo em `:52-54` |
| `ROLES-ADD` | **Atribuir papel** | botão | `roles_manager.mustache:57-59` | `data-action="role-add"` | `btn-primary` |
| `ROLES-CAPTION` | Atribuir papéis | caption | `roles_manager.mustache:62` | `visually-hidden` | — |
| `ROLES-HEAD` | Usuário · Papel · **Público-alvo** · Status · Ações | cabeçalho | `roles_manager.mustache:65-69` | — | 5 colunas, a última "Ações" (`:69`) |
| `ROLES-ROWS` | `[sem rótulo]` | contêiner-JS | `roles_manager.mustache:72` | `data-region="role-rows"` | `local_dimensions_list_template_cohort_roles` |
| `ROLES-NOTES` | notas (segundo plano / global) | texto | `roles_manager.mustache:74-75` | — | a atribuição é assíncrona e vale **globalmente**, não só neste plano |

## Aba Métodos de inscrição (`MOD.ENROL`)

O pane é vazio (`participants_manager.mustache:150-151`) e montado por `enrol_methods.js:1010-1033`.
O conteúdo tem mapa próprio — ver [`mod-enrolmethods.md`](mod-enrolmethods.md). **Ressalva medida:**
aquele mapa se declara *"to-be — proposta, ainda sem código"* e foi escrito **~70 minutos antes** de o
`3d1d5cb` shipar o código; ele está tão desatualizado quanto este estava. Resync próprio pendente.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-REFRESH` | Atualizar | botão | `enrol_methods.mustache:39-41`, `:47-49`, `:108-110` | `data-action="enrol-refresh"` | **três** ocorrências (dois `alert`s + a barra de filtros). `<i class="fa fa-rotate me-1">` + `{{#str}}refresh, moodle{{/str}}`. **É o único "atualizar" do modal inteiro** — as outras três abas não têm nenhum. Precedente direto do IMP-05: a string e o ícone já existem, sem string nova |
| `ENROL-SELBAR` | `[sem rótulo]` | barra de seleção | `enrol_methods.mustache:113-127` | `.border-top.pt-2` | contador + "em processamento" (`fa-spinner fa-spin`, `:116`) + Remover método / Aplicar método, ambos `disabled` por padrão (`:120`, `:123`) |

## Comportamento do host (`participants_manager.js`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-ACTIVATE` | `[sem rótulo]` | troca de aba | `participants_manager.js:116-127` | `activateTab` | **abas artesanais** (`:110`: "no Bootstrap tab JS dependency in the modal"): alterna `.active` + `aria-selected` nos botões (`:118-121`) e `.show`/`.active` nos panes (`:122-126`). **Síncrono** |
| `PART-ROVING` | `[sem rótulo]` | teclado | `participants_manager.js:194`, `:202`, `:217-238` | `tabindex` 0/-1 | roving tabindex WAI-ARIA: a aba ativa é o **único** ponto de tabulação (`:202`). `ArrowRight` (`:225`), `ArrowLeft` (`:227`), `Home` (`:229`), `End` (`:231`) — circulares, com `preventDefault` e foco movido (`:236`) |
| `PART-MOUNT` | `[sem rótulo]` | montagem preguiçosa | `participants_manager.js:174-187` | `ensureMounted` | um `if` por aba, casando `button.dataset.region` |
| `PART-LATCH` | `[sem rótulo]` | trava de montagem | `participants_manager.js:161-163`, `:175-176`, `:179-180`, `:183-184` | `usersmounted` / `rolesmounted` / `enrolmounted` | **a trava é levantada _antes_ do await** e o mount é disparado sem ser aguardado (`.catch(notifyError)`). Ver o achado abaixo |
| `PART-COHORTMOUNT` | `[sem rótulo]` | montagem inicial | `participants_manager.js:168` | `mountCohorts(...)` | roda no `ModalEvents.shown` — **sem trava nenhuma** (só é chamado uma vez) |

## Achado IMP-03 — a lacuna de loading **deste** modal (derivada do `ensureMounted`)

O plano supunha "loading na troca de aba". **A premissa estava errada nos dois sentidos**, e o
código diz o seguinte:

**1. O loading do core não alcança este modal.** É verdade que existe loading de troca de aba
pronto — mas ele é do `core/dynamic_tabs` (`public/lib/amd/src/dynamic_tabs.js:92-97` → `loadTab` →
`addIconToContainer` em `:153`; `:85-89` esvazia o pane anterior), e ele governa as **abas da
página** (`EST`/`FWK`/`PLN`). As abas **deste modal** são artesanais (`activateTab`,
`participants_manager.js:116-127`) e **não passam nem perto** do `dynamic_tabs`. Aqui não há nada a
herdar: a lacuna é real e é nossa.

**2. A lacuna maior não é a troca de aba — é o _primeiro paint_.** `Modal.create` recebe o corpo
renderizado e `modal.show()` é chamado em `:240`; só **depois** o `ModalEvents.shown` (`:164`)
dispara `mountCohorts` (`:168`), que ainda precisa de strings + `renderForPromise` +
`replaceNodeContents` + um WS (`cohort_manager.js:209-232`). Como o pane de Coortes **nasce vazio**
(`participants_manager.mustache:75-76`) e é a aba que **nasce ativa** (`:42`), o modal abre com as
quatro abas desenhadas e **o corpo em branco embaixo delas**. Não é uma troca de aba — é a abertura.

**3. Na troca de aba, a lacuna é assimétrica — 3 panes de 4.** `selectTab` chama `activateTab`
(`:192`) **antes** de `ensureMounted` (`:195`), então o pane fica **visível e vazio** enquanto o
mount corre. Vale para Coortes, Papéis (`:146-147`) e Métodos de inscrição (`:150-151`), os três
`<div></div>`. **Usuários é a exceção**: o pane vem renderizado do servidor (`:77-144`), então
filtros e cabeçalho da tabela aparecem na hora e só as **linhas** faltam. Uma correção que trate as
4 abas igual está tratando 3 problemas e 1 não-problema.

**4. O defeito que ninguém tinha registrado: a trava é definitiva.** Em `ensureMounted`
(`:174-187`) cada `<flag>mounted = true` é executado **antes** do `await` — o mount é disparado e
**não** é aguardado, só `.catch(notifyError)`. Se ele rejeitar (WS fora, rede caindo — exatamente o
que o `errors.js` existe para rotear), o toast aparece, o pane fica em branco e **a trava continua
`true`**. Clicar em outra aba e voltar **não** tenta de novo: o `if` já falha no `!<flag>mounted`.
E como **não há nenhum controle de atualizar** nas abas Coortes/Usuários/Papéis (o único do modal é
o `ENROL-REFRESH` da 4ª aba), e como `setRemoveOnClose(true)` (`:145`) descarta o modal ao fechar,
**a única recuperação é fechar e reabrir o modal**. É este o achado que amarra o IMP-03 ao IMP-11
aqui: um spinner mostra que falhou; **só o "atualizar" deixa tentar de novo**.

**Conclusão para o to-be:** o alvo não é "loading na troca de aba" genérico. É (a) o pane de Coortes
no primeiro paint, (b) os 3 panes vazios na troca — **não** o de Usuários, que precisa no máximo de
um esqueleto de linhas — e (c) uma trava que precisa ser **liberada no erro**, não só no sucesso,
com um "atualizar" que dê a segunda chance.
