# Mapa de Campos — `MOD.PART` · Modal Participantes (as-is)

Modal hospedeiro (`core/modal`, **com rodapé** — o D2 moveu os links de admin para lá, ver `PART-FOOT`) com um `<h5>` de nome do template e **quatro** abas:
**Coortes / Usuários / Atribuir papéis / Métodos de inscrição**. As abas são **artesanais** — não há
dependência do JS de abas do Bootstrap dentro do modal (`participants_manager.js:97`): o
`activateTab` alterna `.active`/`.show` e `aria-selected` na mão, com um *roving tabindex*
WAI-ARIA (Setas/Home/End) por cima.

Três dos quatro panes nascem **vazios** no Mustache e são montados por JS; só o de Usuários chega
renderizado do servidor. Essa assimetria é a origem do achado de loading registrado no fim deste
mapa — **não** é a mesma lacuna do `EST`/`FWK`/`PLN`.

- **Mustache:** [`templates/central/participants_manager.mustache`](../../../templates/central/participants_manager.mustache) (154 linhas, host), [`cohort_manager.mustache`](../../../templates/central/cohort_manager.mustache) (50), [`roles_manager.mustache`](../../../templates/central/roles_manager.mustache) (77), [`enrol_methods.mustache`](../../../templates/central/enrol_methods.mustache) (137)
- **PHP:** [`classes/output/dynamictabs/plans.php`](../../../classes/output/dynamictabs/plans.php) — o modal **não tem renderable próprio**; ele lê tudo do `data-*` da região do `PLN` (`:329-333`)
- **AMD:** [`participants_manager.js`](../../../amd/src/central/participants_manager.js) (237, host), [`cohort_manager.js`](../../../amd/src/central/cohort_manager.js), [`participants_users.js`](../../../amd/src/central/participants_users.js), [`roles_manager.js`](../../../amd/src/central/roles_manager.js), [`enrol_methods.js`](../../../amd/src/central/enrol_methods.js) (1053)
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
| `PART-MODAL` | Gerenciar participantes | modal | `participants_manager.js:131` | `Modal.create({title, body})` | `core/modal` puro — **não** passa `footer` ao `create`; o rodapé vazio do core é revelado depois pelos links de admin (D2, ver `PART-FOOT`). `setRemoveOnClose(true)` (`:132`): o modal é descartado ao fechar, então **todo o estado montado morre junto** (ver `PART-LATCH`). Título via `central_participants_title` (`:124`) |
| `PART-DIALOG` | `[sem rótulo]` | classes no `.modal-dialog` | `participants_manager.js:138-142` | `modal-xl` + `local-dimensions-participants-modal` | as **duas** de uma vez — o D2 **largou** `local-dimensions-headerlink-modal`: o chip de fechar já chega pela arm `:has(.modal-body [class*='local-dimensions-'])`, porque o corpo carrega `.local-dimensions-participants` (comentário em `:140-142`). `modal-xl` é do **próprio Bootstrap** (800px em `lg`, 1140px em `xl`, idêntico em 4 e 5) — a API do core só expõe `setLarge()`, daí a classe na mão |
| `PART-EXPAND` | Expandir / Restaurar tamanho | par de botões (cabeçalho) | `participants_manager.js:147` → `modal_expander.js:68` | `data-action="modal-expand"` / `="modal-restore"` · `fa fa-expand`/`fa-compress` | shipado (`8ea9daf`, `mtube: expandir`). Dois botões sempre presentes, injetados antes do `.btn-close` (`modal_expander.js:82-83`); com os links movidos ao rodapé (D2) o agrupamento com o fechar vem agora da regra re-alojada `.modal-header:has(.local-dimensions-modal-sizetoggle) .modal-title` (`styles.css:3653`), não da ordem de inserção. O CSS mostra o que casa com `.local-dimensions-modal-expanded` no `.modal-dialog` (`styles.css:3634`+), sem troca de ícone em JS. Expandido = `max-width:96vw` sobre o `modal-xl` (`styles.css:3642`). O tamanho persiste na chave `modalexpanded` do `PREF_CENTRAL_DISPLAY` (**compartilhada com o `mod-links`**) — ver `mod-links.md` para a mecânica e as duas decisões de a11y (anel de foco próprio, foco devolvido ao contrário no toggle) |
| `PART-FOOT` | `[sem rótulo]` | rodapé revelado | `participants_manager.js:145` | `.modal-footer` + `.local-dimensions-modal-footer-links` | o core chama `hideFooter()` no `show()` quando o rodapé não tem filhos (`public/lib/amd/src/modal.js:875-879`; `hasFooterContent` = `getFooter().children().length`, `:686-688`). O D2 explora exatamente isso: `injectFooterLinks` (`:67-94`) é **aguardado antes** do `show()` (`:145` → `:236`), então o grupo de links já é filho do rodapé no `show()` e o core o revela sozinho (a mecânica `hasFooterContent` continua valendo) |
| `PART-TOAST` | `[sem rótulo]` | região de toast | `participants_manager.js:171` | `addToastRegion(modal.getBody()[0])` | padrão da casa: sem ela, o toast dos gerenciadores renderiza **atrás** do diálogo (`.toast-wrapper` é `z-index:1051`, o modal é `1055`). O **host** é dono da região; `cohort_manager` e `participants_users` **não** criam a sua. O core remove no fechamento |
| `PART-CLOSE` | Fechar | chip | `styles.css:3702-3731` | `.btn-close` do core, reestilizado | `1.75rem`, raio `8px`, fundo `#e7f0f9`, glifo FA `\f00d` em `#0f4d85` (**7,53:1** medido), hover `#d4e6fb` (**6,82:1**). Literais, sem variante dark |

> **A segunda função de `local-dimensions-headerlink-modal` — largada no participants, mantida no
> framework (D2, shipado).** O chip de fechar sai de um **grupo de dois seletores** em
> `styles.css:3702-3703`: o primeiro (`.modal:not(...):has(.modal-body [class*='local-dimensions-'])`)
> pega os modais cujo **corpo** carrega classe do plugin; o segundo
> (`.local-dimensions-headerlink-modal .btn-close`) é um gancho **independente**, para os modais que
> **escapam** do `:has()`. O D2 **largou** a classe do modal de participantes — o corpo carrega
> `.local-dimensions-participants`, então a arm `:has()` já dá o chip (comentário em
> `participants_manager.js:140-142`). Ela **ficou** no `ModalForm` de framework, que renderiza markup
> de formulário do core e por isso escapa do `:has()`: `frameworks.js:139-143` a aplica **antes** do
> teste de capability de `:144`, com um comentário dizendo exatamente isso — pousa **mesmo quando
> nenhum link é renderizado** e é o **único** gancho de chip que sobrou para aquele form. O D2 também
> aposentou as regras de título/margem que a classe carregava no cabeçalho; o `flex-grow` do título
> foi **re-alojado** em `.modal-header:has(.local-dimensions-modal-sizetoggle) .modal-title`
> (`styles.css:3653`), agrupando expander + fechar sem depender daquela classe.

## Links de rodapé (injetados por JS, todos de uma vez)

`ADMIN_PAGES` (`participants_manager.js:47-56`) declara **4** destinos — um por aba; cada objeto
carrega só `path`/`flag`/`strkey` (a chave `pane:` do desenho antigo de cabeçalho **caiu** com o D2).
`injectFooterLinks` (`:67-94`) filtra os permitidos (`region.dataset[flag] === '1'`, `:72`) e, se algum
sobra, monta um `<div class="local-dimensions-modal-footer-links">` (`:78`) com um `<a target="_blank"
rel="noopener noreferrer" class="btn btn-link p-0">` (`:84`) + `<i class="fa fa-external-link me-1">`
(`:86`) por destino e faz `footer.appendChild(group)` (`:93`) — o rodapé antes oculto ganha filho e o
core o revela (ver `PART-FOOT`). Ficam **à esquerda** (`margin-right:auto`, `styles.css:3666`), com a
ação primária — quando há — à direita. **Não** há mais alternância `d-none` por aba: **todos os
permitidos aparecem de uma vez**. O rótulo visível **é** o nome acessível (`:89-90`: sem
`title`/`aria-label` extra).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-LINK-COHORTS` | Abrir página de coortes | link | `participants_manager.js:48-49` | `/cohort/index.php` · flag `cancohortpage` | `moodle/cohort:view` **ou** `:manage` no sistema (`dynamictabs/plans.php:239-240`) |
| `PART-LINK-USERS` | Abrir página de usuários | link | `participants_manager.js:50-51` | `/admin/user.php` · flag `canuserpage` | `moodle/user:update` **ou** `:delete` (`:241-242`) |
| `PART-LINK-ROLES` | Abrir página de papéis | link | `participants_manager.js:52-53` | `/admin/roles/manage.php` · flag `canassignroles` | `moodle/role:manage` (`:238`) |
| `PART-LINK-ENROL` | Gerenciar plugins de inscrição | link | `participants_manager.js:54-55` | `/admin/settings.php?section=manageenrols` · flag `canenrolpage` | `moodle/site:config` (`:243`) |
| `PART-LINK-ALL` | `[sem rótulo]` | regra de exibição | `participants_manager.js:72-93` | `ADMIN_PAGES.filter(dataset[flag] === '1')` | **todos os permitidos de uma vez** — não há mais um-por-aba nem toggle. `injectFooterLinks` devolve cedo se o rodapé não existir (`:69-71`) ou se nenhum link for permitido (`:73-75`); `showHeaderLinkFor` foi **removido** |

> **Aba e link são portas com fechaduras diferentes — e em duas abas elas divergem.** A aba Papéis e
> o link de papéis compartilham a **mesma** flag (`canassignroles`), então andam juntos. Já a aba
> **Métodos de inscrição** é gated em `canmanageenrol`, que o `plans.mustache:133` alimenta com
> **`{{canmanage}}`** = `moodle/competency:templatemanage` **no contexto** (`dynamictabs/plans.php:98`,
> `:329`) — enquanto o **link** dela quer `moodle/site:config` **no sistema**. Um gestor de template
> vê a aba e **não** vê o link. As abas Coortes e Usuários são incondicionais; seus links, não.

## Host + abas

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-ROOT` | `[sem rótulo]` | região/raiz | `participants_manager.mustache:36` | `data-region="participants"` · `data-contextid` | o contexto vem do `data-contextid` da região do `PLN` (`participants_manager.js:127`) |
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
mount só liga os eventos e busca as linhas. Desde `c96a3e9`, essa busca inicial (`applyFilters`) é
**engolida num toast** (`:310`): os fios já estão no lugar, então uma falha de primeira carga não
trava o pane — os controles de filtro visíveis re-rodam `applyFilters` sobre o mesmo estado (é o que
mantém a re-montagem via trava liberada segura para este pane; ver `PART-LATCH` e o achado IMP-03).

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
| `PART-ROWBTN` | `[sem rótulo]` | regra de CSS | `styles.css:3689-3691` | `#local-dimensions-pane-users button.btn...me-1` | `margin-bottom: 5px` **escopado só neste pane** — nas outras abas a margem extra desalinhava botões solitários |

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

O pane é vazio (`participants_manager.mustache:150-151`) e montado por `enrol_methods.js:1026-1053`.
Desde `c96a3e9`, o `mount` **engole a carga inicial (`init`) num toast** (`:1052`) porque os
listeners são delegados no **próprio contêiner** (`state.root`) e sobrevivem ao `replaceNodeContents`
— logo o "ele limpa, logo re-monta seguro" **não** vale para o enrol. Isso deixava um resíduo aberto
(chip filado): se o `init` rejeitasse **antes** de revelar qualquer região, o pane ficava em branco e o
`ENROL-REFRESH` (que morava **dentro** das regiões ocultas) ficava inalcançável. **ENTREGUE em
2026-07-16 (`c2d9471`):** o `init` embrulha essa primeira carga num `try/catch`
(`enrol_methods.js:858-885`) e, na falha, revela uma região de erro dedicada `enrol-error`
(`enrol_methods.mustache:52-59`; `:880` revela, `:881-883` oculta empty/disabled/main) cujo próprio
`ENROL-REFRESH` (`enrol_methods.mustache:55-57`) mora **fora** das três ocultas e re-roda o `init`
(`:931-932`); o re-lançamento (`:884`) mantém o toast do `mount` (`:1052`). O chip está feito — ver o
achado IMP-03, item 4. O conteúdo tem mapa próprio — ver [`mod-enrolmethods.md`](mod-enrolmethods.md). **Ressalva medida:**
aquele mapa se declara *"to-be — proposta, ainda sem código"* e foi escrito **~70 minutos antes** de o
`3d1d5cb` shipar o código; ele está tão desatualizado quanto este estava. Resync próprio pendente.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `ENROL-REFRESH` | Atualizar | botão | `enrol_methods.mustache:39-41`, `:47-49`, `:55-57`, `:116-118` | `data-action="enrol-refresh"` | **quatro** ocorrências (três `alert`s + a barra de filtros; a do `enrol-error` — `:55-57` — chegou em `c2d9471`, e é a que reabilita a recuperação: seu `alert` é o único revelado quando o `init` falha antes das demais regiões). `<i class="fa fa-rotate me-1">` + `{{#str}}refresh, moodle{{/str}}`. **É o único "atualizar" do modal inteiro** — as outras três abas não têm nenhum. Precedente direto do IMP-05: a string e o ícone já existem, sem string nova |
| `ENROL-SELBAR` | `[sem rótulo]` | barra de seleção | `enrol_methods.mustache:121-135` | `.border-top.pt-2` | contador + "em processamento" (`fa-spinner fa-spin`, `:124`) + Remover método / Aplicar método, ambos `disabled` por padrão (`:128`, `:131`) |

## Comportamento do host (`participants_manager.js`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PART-ACTIVATE` | `[sem rótulo]` | troca de aba | `participants_manager.js:103-114` | `activateTab` | **abas artesanais** (`:97`: "no Bootstrap tab JS dependency in the modal"): alterna `.active` + `aria-selected` nos botões (`:105-108`) e `.show`/`.active` nos panes (`:109-113`). **Síncrono** |
| `PART-ROVING` | `[sem rótulo]` | teclado | `participants_manager.js:203`, `:213-234` | `tabindex` 0/-1 | roving tabindex WAI-ARIA: a aba ativa é o **único** ponto de tabulação (`:203`). `ArrowRight` (`:221`), `ArrowLeft` (`:223`), `Home` (`:225`), `End` (`:227`) — circulares, com `preventDefault` e foco movido (`:232`) |
| `PART-MOUNT` | `[sem rótulo]` | montagem preguiçosa | `participants_manager.js:178-189` | `ensureMounted` | lê `button.dataset.region` (`:179`) e roteia por aba (`switch` if/else-if) para `startMount` — `tab-cohorts`/`tab-users`/`tab-roles`/`tab-enrol` (`:180-187`). Não monta mais direto: quem crava/libera a trava é o `startMount` (ver `PART-LATCH`), então **um re-clique numa aba com a trava liberada re-monta** |
| `PART-LATCH` | `[sem rótulo]` | trava de montagem | `participants_manager.js:153`, `:158-167` | `mounted = {cohorts, users, roles, enrol}` + `startMount` | **CORRIGIDO em 2026-07-16 (`c96a3e9`).** *Era:* três booleanos (`usersmounted`/`rolesmounted`/`enrolmounted`) levantados **antes** do await, mount fire-and-forget (`.catch(notifyError)`) — uma rejeição deixava a trava presa em `true` para sempre (ver o achado abaixo). *Agora:* uma tabela `mounted` (`:153`) e um `startMount(key, mountfn, selector)` (`:158-167`) que **crava** a trava de forma síncrona (`:162`, bloqueia o duplo-clique) e a **libera no `.catch`** (`:164`) — a próxima ativação da aba re-monta |
| `PART-COHORTMOUNT` | `[sem rótulo]` | montagem inicial | `participants_manager.js:172` | `startMount('cohorts', mountCohorts, …)` | roda no `ModalEvents.shown`. **CORRIGIDO em 2026-07-16 (`c96a3e9`):** era `mountCohorts(...)` fire-and-forget **sem trava e sem gatilho de retry** (o pane default falhava sem recuperação alguma); agora entra na tabela `mounted` via `startMount`, e reclicar a aba Coortes (`ensureMounted`, `:180-181`) o re-monta |

## Achado IMP-03 — a lacuna de loading **deste** modal (derivada do `ensureMounted`)

O plano supunha "loading na troca de aba". **A premissa estava errada nos dois sentidos**, e o
código diz o seguinte:

**1. O loading do core não alcança este modal.** É verdade que existe loading de troca de aba
pronto — mas ele é do `core/dynamic_tabs` (`public/lib/amd/src/dynamic_tabs.js:92-97` → `loadTab` →
`addIconToContainer` em `:153`; `:85-89` esvazia o pane anterior), e ele governa as **abas da
página** (`EST`/`FWK`/`PLN`). As abas **deste modal** são artesanais (`activateTab`,
`participants_manager.js:103-114`) e **não passam nem perto** do `dynamic_tabs`. Aqui não há nada a
herdar: a lacuna é real e é nossa.

**2. A lacuna maior não é a troca de aba — é o _primeiro paint_.** `Modal.create` recebe o corpo
renderizado e `modal.show()` é chamado em `:236`; só **depois** o `ModalEvents.shown` (`:168`)
dispara `startMount('cohorts', …)` (`:172`) → `mountCohorts`, que ainda precisa de strings +
`renderForPromise` + `replaceNodeContents` + um WS (`cohort_manager.js:209-232`). Como o pane de Coortes **nasce vazio**
(`participants_manager.mustache:75-76`) e é a aba que **nasce ativa** (`:42`), o modal abre com as
quatro abas desenhadas e **o corpo em branco embaixo delas**. Não é uma troca de aba — é a abertura.

**3. Na troca de aba, a lacuna é assimétrica — 3 panes de 4.** `selectTab` chama `activateTab`
(`:194`) **antes** de `ensureMounted` (`:196`), então o pane fica **visível e vazio** enquanto o
mount corre. Vale para Coortes, Papéis (`:146-147`) e Métodos de inscrição (`:150-151`), os três
`<div></div>`. **Usuários é a exceção**: o pane vem renderizado do servidor (`:77-144`), então
filtros e cabeçalho da tabela aparecem na hora e só as **linhas** faltam. Uma correção que trate as
4 abas igual está tratando 3 problemas e 1 não-problema.

**4. CORRIGIDO em 2026-07-16 (`c96a3e9`) — a trava era definitiva; deixou de ser (com uma ressalva
no enrol, fechada depois em `c2d9471`).** *Era assim:* em `ensureMounted` cada `<flag>mounted = true` corria **antes** do `await`
e o mount ia fire-and-forget (`.catch(notifyError)`); se ele rejeitasse (WS fora, rede caindo — o
que o `errors.js` roteia), o toast aparecia, o pane ficava em branco e **a trava continuava `true`**.
Reclicar a aba **não** tentava de novo (o `if` já falhava no `!<flag>mounted`), e como não há
"atualizar" nas abas Coortes/Usuários/Papéis (o único é o `ENROL-REFRESH` da 4ª aba) e
`setRemoveOnClose(true)` (`:132`) descarta o modal ao fechar, **só reabrir o modal recuperava**. O
Coortes era pior: montava fire-and-forget no `shown`, **sem trava**, e sua própria aba não passava
pelo `ensureMounted` — pane default sem recuperação alguma. *Agora:* os quatro panes passam por um
`startMount` (`:158-167`) que **crava** a trava de forma síncrona (`:162`, bloqueia o duplo-clique) e
a **libera no `.catch`** (`:164`), então a próxima ativação da aba re-monta; o Coortes entrou na
tabela, então reclicar a aba default (`:180-181`) o recupera também.

*Ressalva medida — o enrol tinha um buraco, fechado em `c2d9471`:* liberar-no-erro só é seguro se o mount rejeitado
deixou o pane **sem fios**. Coortes e Papéis fazem `replaceNodeContents` e religam nós-filhos frescos
(re-montagem limpa), mas Usuários e enrol **não** — o de Usuários é renderizado no servidor e religado
no lugar, e o enrol **delega os listeners no próprio contêiner** (`state.root`), que o
`replaceNodeContents` **não** descarta (o "ele limpa, logo re-monta seguro" é **falso** para o enrol).
Por isso o único `await` pós-fios de cada um é engolido num toast (`participants_users.js:310`,
`enrol_methods.js:1052`): uma falha de **primeira carga resolve** o mount (a trava fica cravada, sem
retry, um só estado com fios). Usuários segue usável — os controles de filtro visíveis re-rodam
`applyFilters` sobre esse estado. **O enrol era o buraco:** se o `init` rejeitasse na primeira montagem
**antes** de revelar qualquer região, o pane ficava em branco e o `ENROL-REFRESH` (que morava
**dentro** das regiões ocultas) era inalcançável — a recuperação era reabrir o modal. **ENTREGUE em
2026-07-16 (`c2d9471`):** o `init` embrulha essa primeira carga num `try/catch`
(`enrol_methods.js:858-885`) e, na falha, revela a região de erro dedicada `enrol-error`
(`enrol_methods.mustache:52-59`; `:880` revela, `:881-883` oculta empty/disabled/main) e re-lança
(`:884`) para o toast do `mount` (`:1052`) ainda disparar; o `ENROL-REFRESH` dessa região
(`enrol_methods.mustache:55-57`) mora **fora** das três ocultas e re-roda o `init` (`:931-932`). Ou
seja: a trava-presa foi fechada para as quatro abas em `c96a3e9`; **o pane-em-branco de primeira-carga
do enrol foi fechado em `c2d9471`** — o chip está feito.

**Conclusão para o to-be:** o alvo não é "loading na troca de aba" genérico. É (a) o pane de Coortes
no primeiro paint, (b) os 3 panes vazios na troca — **não** o de Usuários, que precisa no máximo de
um esqueleto de linhas — e (c) a trava liberada no erro, **entregue em `c96a3e9`** (libera no `.catch`
e a aba re-monta), e o pane-em-branco de primeira-carga do enrol, **entregue em `c2d9471`**
(2026-07-16): uma região de erro dedicada `enrol-error`, revelada no `catch` do `init`, cujo
`ENROL-REFRESH` mora **fora** das regiões ocultas. Ambos os resíduos estão fechados.
