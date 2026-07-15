# Mapa de Campos — `PLN` · Aba Planos de aprendizagem (as-is)

Master-detail: um card branco à esquerda (engrenagem de opções, busca client-side, "Novo modelo",
filtro multi-competência e as linhas de modelo) e, à direita, o painel do modelo selecionado —
**cabeçalho gradiente que veste as cores do próprio modelo**, badge de status, três pílulas de
contagem, segunda engrenagem, chips de metadado e a lista de competências (grip, nome, taxonomia,
caminho, badge de estrutura, **kebab**). Um divisor de 22px redimensiona o mestre.

**O CRUD do modelo não mora no pane** — mora no sticky-footer da página, publicado por `init`
(`plans.js:796-804`) e roteado de volta por `dispatchPlansAction` (`plans.js:766-776`). **As ações
de competência dentro da lista, ao contrário, moram num kebab por linha** (`plans.mustache:396-436`)
— e isso está correto; ver a fronteira registrada abaixo.

- **Mustache:** [`templates/central/plans.mustache`](../../../templates/central/plans.mustache) (494 linhas), [`showhidden_toggle.mustache`](../../../templates/central/showhidden_toggle.mustache), [`collapsible_description.mustache`](../../../templates/collapsible_description.mustache), [`move_competency_modal.mustache`](../../../templates/central/move_competency_modal.mustache), [`delete_template_modal.mustache`](../../../templates/delete_template_modal.mustache)
- **PHP:** [`classes/output/dynamictabs/plans.php`](../../../classes/output/dynamictabs/plans.php) (336 linhas)
- **AMD:** [`amd/src/central/plans.js`](../../../amd/src/central/plans.js) (871 linhas), [`central/tabs.js`](../../../amd/src/central/tabs.js), [`central/action_footer.js`](../../../amd/src/central/action_footer.js), [`central/pane_resizer.js`](../../../amd/src/central/pane_resizer.js), [`central/preferences.js`](../../../amd/src/central/preferences.js)
- **To-be no DS:** sem componente dedicado — o master-detail **convergiu** (o `4c1f521` shipou o
  to-be desta própria tela). O que falta é o loading do `reloadPane`.

> **Nota de nome (verificada).** O rótulo desta aba é a string `learningplans` = **"Planos de
> aprendizagem"** (`central.php:101`; pt-BR `lang/pt_br:399`) — ela **não** segue a
> convenção `central_<x>_tab` que o `FWK` usa. É a **terceira** aba e **nunca nasce ativa**
> (`central.php:105` só marca `frameworks`), então o pane do `PLN` **nunca é renderizado no servidor
> no load da página**: ele sempre chega pelo `loadTab` do core. Isso importa para o IMP-03 — ver
> abaixo.

> **Resync 2026-07-14:** a versão anterior deste mapa congelou em `159a800` (2026-06-29), junto com a
> do `EST` e a do `FWK`. Desde então `plans.mustache` foi de **207 para 494** linhas e `plans.js` de
> **251 para 871** — e **as 21 de 21** refs do mapa antigo resolviam para linhas **não relacionadas**.
> **Nove** delas (`:68`, `:77`, `:85`, `:92`, `:93`, `:105`, `:111`, `:121`, `:122`) caíam dentro do
> *Example context* do docblock (que hoje vai de `:17` a `:126`), e `PLN-PARTICIPANTS` apontava `:187`,
> que é uma **linha em branco**. Todas foram re-derivadas, não corrigidas pontualmente.
>
> O defeito maior, porém, era **ausência**: não estavam mapeados o cabeçalho gradiente, o badge de
> status, duas das três contagens, **as duas** engrenagens de opções de exibição (e seus cinco
> switches), a busca client-side de planos, o filtro **multi**-competência, o toggle de ocultos, o
> resizer, os chips de metadado, a descrição colapsável, o grip de arrasto, o kebab inteiro — e
> **todo** o comportamento de `plans.js` (o mapa tinha **zero** refs de JS).
>
> **A ironia registrada:** o painel **to-be** da tela antiga dizia *"mover/remover num menu ⋮"* e
> *"contagem de alunos visível"*. As duas coisas **shiparam** em `4c1f521` (2026-07-01) — o kebab e a
> pílula "Planos: N". O to-be virou o as-is e o artefato nunca foi resincronizado. Duas reformas
> passaram por cima dele: `9e1a2cc` (2026-07-08, ações → sticky-footer) e `64337c8` (2026-07-09,
> redesenho pixel-perfect: classe do kebab, cabeçalho gradiente, engrenagens, chips).

## A fronteira da regra do sticky-footer (decidida 2026-07-14)

> A regra "nunca kebab por linha" governa o **CRUD da entidade da aba** (framework / nó da estrutura /
> template) — que vai pro sticky-footer porque é o que lança os modais. As ações de **competências
> dentro da lista de um plano** são **lista aninhada** e legitimamente usam kebab
> (`plans.mustache:396-436`): o sticky-footer desta aba já está ocupado pelas ações do template
> selecionado. **Este kebab está correto — não "conserte".**

O que sustenta a fronteira, verificado no código:

- **As duas listas são de entidades diferentes.** O sticky-footer age sobre o **template**
  selecionado (`plans.mustache:462-488`, cinco botões); o kebab age sobre uma **competência dentro**
  daquele template (`plans.mustache:396-436`, cinco itens). Não há um segundo rodapé disponível — o
  da página já está ocupado.
- **A prova mais forte é o `openForm` compartilhado.** O `edit-competency` do kebab
  (`plans.mustache:405-408`) e o `edit-template` do rodapé (`:465-468`) chamam **a mesma função**,
  `openForm` (`plans.js:211-219`, cujo `new ModalForm` está em `:212`) — que o `new-template` do
  cabeçalho (`:182-184`) também chama. Os **três** convivem porque o `openForm` é parametrizado pela
  `formclass`: `edit-competency` passa `COMPETENCY_FORM_CLASS` (`plans.js:739-745`), enquanto
  `edit-template` e `new-template` passam `FORM_CLASS` (`:732-738`, `:725-731`). **Entidades
  diferentes, mesmo mecanismo** — é exatamente por isso que um kebab e um rodapé podem coexistir sem
  ambiguidade.
- **O sticky-footer continua sendo o padrão dominante do hub, e os números são estes** (medidos, não
  estimados). O hub tem **17** sítios de construção de modal — **4** `new ModalForm` + **13**
  `Modal*.create`, todos sob `amd/src/central/` (o `Modal.create` de `amd/src/accordion.js:1191` é da
  visão do aluno, **fora** do hub). Destes 17, o rodapé **alcança 10** diretamente; é a **única
  porta** de **7**; e **8 dependem** dele (os 7 + `enrol_methods.js:799`, que só é montado de dentro
  do modal de participantes — `participants_manager.js:33`, importador único).
  - **Os 7 sem outra porta:** `rule_config.js:144` (`rules`), `competency_links.js:763` (`links`),
    `related_competencies.js:248` (`related`), `structure.js:987` (`moveto`) — os quatro só existem
    em `structure_footer_actions.mustache` — mais `participants_manager.js:144`
    (`manage-participants`), `competency_browser.js:106` (`browse-frameworks`) e `plans.js:251`
    (`delete-template` com planos), os três só em `plans.mustache:462-488`.
  - **Os outros 3 têm porta paralela no cabeçalho:** o form de framework (`frameworks.js:174` ←
    `frameworks.mustache:82`), o form de competência (`structure.js:797` ← `structure.mustache:127`)
    e o form de template (`plans.js:212` ← `plans.mustache:182`). **Mas o botão do rodapé é o único
    jeito de agir sobre a linha selecionada** — o do cabeçalho só cria.
  - **Não inflar:** são **7** sem outra porta, não 10. Os 7 que o rodapé **não** alcança direto são
    `frameworks.js:262` (import), `frameworks.js:345` (export), `plans.js:568` (mover para posição),
    `competency_detail.js:277` (detalhe), `structure.js:1224` (uso), `framework_scaleconfig.js:139`
    (delegado de dentro do form) e `enrol_methods.js:799`.

## Raiz e dados de página

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-ROOT` | `[sem rótulo]` | região/raiz | `plans.mustache:127-133` | `data-region="plans"` | carrega **11** atributos: `contexttype`, `categoryid`, `contextid`, `templateid`, `templatename`, `competencyids`, `excludeids`, `canassignroles`, `cancohortpage`, `canuserpage`, `canmanageenrol`, `canenrolpage`. `init` a resolve por seletor (`plans.js:782`) e guarda em `activeRegion`/`activePane` (`:787-788`) |
| `PLN-MIRROR-TPL` | `[sem rótulo]` | espelho de dataset | `plans.js:813-815` | `pane.dataset.templateid` | o servidor **auto-seleciona** um modelo (`dynamictabs/plans.php:143-156`); `init` espelha o id no dataset do **pane** senão os WSes recebem 0 → "Invalid context id" (o padrão *dataset-as-truth* do `CLAUDE.md`) |
| `PLN-MIRROR-FILTER` | `[sem rótulo]` | espelho de dataset | `plans.js:819-821` | `pane.dataset.competencyids` | espelha o filtro **já validado pelo servidor**, para que competências ilegíveis/apagadas que o servidor descartou não fiquem penduradas no dataset |

## Cabeçalho e toggle

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-EMPTY-CAT` | "Escolha primeiro a categoria de curso…" | empty-state | `plans.mustache:134-136` | `needscategoryselection` | bloqueia a aba inteira (o `{{^needscategoryselection}}` de `:138` embrulha todo o resto) |
| `PLN-SHOWDISABLED` | Mostrar modelos ocultos | switch | `showhidden_toggle.mustache:44-45`, chamado em `plans.mustache:139-141` | `data-action="{{action}}"` → `toggle-disabled` | **partial compartilhado** com `EST`/`FWK`: o `data-action` é **variável** no template e o valor literal vem de `dynamictabs/plans.php:289` (contexto em `:286-291`; **nulo quando não há nenhum oculto** → não renderiza). Estado na preferência `plansshowdisabled` (`plans.js:192-197`), **não** no servidor — as linhas ocultas ficam no DOM e o toggle só liga a classe `show-disabled` (`:194`, `:197`) |

## Painel mestre — cabeçalho e opções de exibição (engrenagem 1 de 2)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-LIST-TITLE` | "Modelos" | heading | `plans.mustache:146` | str `central_plans_templatestitle` | `<h2>` |
| `PLN-LIST-GEAR` | Opções de exibição | botão ícone | `plans.mustache:147-152` | `data-action="list-display-options"` | `fa-cog`; alterna `PLN-LIST-OPTS` e **persiste** o estado aberto/fechado na preferência `panels.planslist` (`plans.js:714-722`). **Não estava no mapa** |
| `PLN-LIST-OPTS` | `[sem rótulo]` | painel colapsável | `plans.mustache:155-170` | `data-region="list-display-options-panel"` | `role="group"`; estado restaurado por `applyPanelState` (`plans.js:428`) |
| `PLN-LIST-OPT-ID` | Mostrar identificadores | switch | `plans.mustache:158-163` | `data-list-toggle="id"` | liga `show-id` no container de linhas (`LISTDISPLAY_CLASSES`, `plans.js:57`) |
| `PLN-LIST-OPT-DUE` | Mostrar data de entrega | switch | `plans.mustache:164-169` | `data-list-toggle="duedate"` | liga `show-duedate`; preferência `planslist` (`plans.js:381-389`) |

## Painel mestre — busca e criação

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-TOOLBAR` | `[sem rótulo]` | contêiner | `plans.mustache:172` | `data-region="plan-toolbar"` | busca + botão novo |
| `PLN-PLANSEARCH` | Buscar por nome ou identificador | input search | `plans.mustache:178-179` | `data-region="plan-search-input"` | **busca client-side**, não recarrega nada: `applyPlanSearch` (`plans.js:160-178`) casa contra o haystack `data-search` (nome + idnumber em minúsculas, montado no servidor em `dynamictabs/plans.php:172`) e alterna a classe `local-dimensions-central-plan-filtered`. Rótulo em `visually-hidden` (`:175-177`). **É um elemento novo — não confundir com `PLN-SEARCH`** |
| `PLN-NEW` | Novo modelo | botão | `plans.mustache:182-184` | `data-action="new-template"` | `fa-plus`; só `{{#canmanage}}` (`:181`); `openForm` com `FORM_CLASS` e `id: 0` (`plans.js:725-731`) |

## Painel mestre — filtro multi-competência

Substituiu o badge único "Filtrado por: X". O filtro é uma **interseção cross-framework**: só sobram
modelos que contêm **todas** as competências escolhidas (`dynamictabs/plans.php:119-140`).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-FILTER` | `[sem rótulo]` | contêiner | `plans.mustache:188` | `data-region="competency-filter"` | — |
| `PLN-FILTER-LABEL` | "Filtrar por competências" | label | `plans.mustache:190` | str `central_plans_filterbycompetencies` | — |
| `PLN-FILTER-CLEAR` | Limpar filtro de competência | botão | `plans.mustache:192-194` | `data-action="clear-competency"` | `fa-times`; só com `{{#filteredbycompetency}}` (`:191`); zera o dataset e `reloadPane` (`plans.js:677-680`) |
| `PLN-FILTER-CHIP` | nome da competência | chip (loop) | `plans.mustache:199-206` | `competencyfilters` | `badge local-dimensions-central-chip-accent`; label = `shortname` (`dynamictabs/plans.php:136-139`) |
| `PLN-FILTER-CHIP-REMOVE` | "Remover {$a} do filtro" | botão ícone | `plans.mustache:201-205` | `data-action="remove-filter-competency"` | `aria-label` embute o nome; tira o id do CSV e `reloadPane` (`plans.js:681-685`) |
| `PLN-FILTER-ADD` | Adicionar ao filtro | botão | `plans.mustache:208-211` | `data-action="add-filter-competency"` | `fa-plus`; alterna o `hidden` do picker e move o foco pro input (`plans.js:686-702`). **Divulgação progressiva** — o `CLAUDE.md` avisa: no Behat, abrir antes de interagir |
| `PLN-FILTER-PICKER` | `[sem rótulo]` | painel | `plans.mustache:213-220` | `data-region="competency-filter-picker"` | nasce `hidden` (`:214`) |
| `PLN-SEARCH` | Filtrar planos por competência | select/autocomplete | `plans.mustache:218` | `data-region="competency-search"` | `form-select` (nunca `custom-select`); nasce **vazio** e é enriquecido por `enhance()` com o datasource `local_dimensions/central/competency_datasource` (`plans.js:838-840`), guardado por `dataset.enhanced` (`:824-825`) pra não enriquecer duas vezes a cada `reloadPane`. O `change` adiciona o id ao CSV e `reloadPane` (`:826-837`) |

## Painel mestre — lista de modelos

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-TPL-LIST` | `[sem rótulo]` | contêiner | `plans.mustache:224` | `data-region="template-rows"` | recebe as classes `show-id` / `show-duedate` / `show-disabled` |
| `PLN-TPL-ROW` | nome do modelo | botão | `plans.mustache:226-240` | `data-action="select-template"`, `data-region="template-row"` | o **botão inteiro** é a linha; `active` + `aria-current="true"` no selecionado (`:227`, `:229`); grava o id no dataset, **persiste** em `Preferences.saveNav({templateid})` e recarrega **preservando o scroll da lista** (`plans.js:671-676`) |
| `PLN-TPL-ICON` | `[sem rótulo]` | ícone | `plans.mustache:230` | `fa-clipboard-list` | decorativo |
| `PLN-TPL-NAME` | nome | texto | `plans.mustache:233` | `name` | — |
| `PLN-TPL-ID` | idnumber | chip | `plans.mustache:234` | `idnumber` | só se `idnumber`; visível só com `PLN-LIST-OPT-ID` |
| `PLN-TPL-DUE` | data de entrega | chip | `plans.mustache:236` | `duedate` | `fa-calendar`; só se `duedate`; visível só com `PLN-LIST-OPT-DUE`. Formatado no servidor com `userdate(..., strftimedate)` (`dynamictabs/plans.php:176-178`) |
| `PLN-TPL-COUNT` | N | contador | `plans.mustache:238` | `competencycount` | `api::count_competencies_in_template($id)` (`dynamictabs/plans.php:173`) — **uma query por linha**; ganha `is-selected` no modelo ativo |
| `PLN-TPL-HIDDEN` | "Oculto" | badge | `plans.mustache:239` | `^visible` | `badge bg-secondary`; str `hidden, tool_lp` |
| `PLN-SEARCH-EMPTY` | "Nenhum modelo corresponde à busca atual." | empty-state | `plans.mustache:242-244` | `data-region="plan-search-empty"` | nasce `hidden`; `applyPlanSearch` o revela quando a contagem de visíveis chega a zero (`plans.js:174-177`) — e **linha oculta só conta como visível se o toggle a revelou** (`:170`) |

## Divisor

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-RESIZER` | Redimensionar painéis | separator | `plans.mustache:258-262` | `data-region="plans-resizer"` | `role="separator"`, `aria-orientation="vertical"`, `tabindex="0"`; só com `{{#hastemplates}}` (`:257`). `initMasterResizer` (`plans.js:854-863`) com `cssvar` `--local-dimensions-plans-master-width`, mínimo **300**, máximo **1200**, reserva **382**; a largura persiste em **`localStorage`** sob `local_dimensions_plans_master_width` (`pane_resizer.js:63`, `:69`) — não é preferência de usuário |

## Detalhe — cabeçalho gradiente (veste as cores do modelo)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-DETAIL-HEADER` | `[sem rótulo]` | cabeçalho | `plans.mustache:266-269` | `data-region="plan-detail-header"` | três stops via custom properties inline (`--ld-plans-hdr-0/-48/-100`) + `color` do texto; a regra é `linear-gradient(140deg, …0%, …48%, …100%)` (`styles.css:4316`). O servidor calcula: base = campo `bgcolor` do modelo **ou `#0f6cbf`**, e os stops 48/100 são `helper::darken_hex(base, 0.16)` e `(base, 0.34)` (`dynamictabs/plans.php:271-272`, `:311-313`). Para o padrão `#0f6cbf` isso dá **`#0d5ba0`** e **`#0a477e`** (medido, reproduzindo `helper.php:2382-2397`). **Pegadinha:** os *fallbacks* do CSS (`#0d5a9f`, `#0a4680`, `styles.css:4316`) **não batem** com o que o PHP calcula — mas são inertes, porque `:267` grava as três custom properties **incondicionalmente**, então o fallback nunca pinta |
| `PLN-DETAIL-GLOW` | `[sem rótulo]` | brilho | `plans.mustache:268-269` | `aria-hidden="true"` | `radial-gradient` branco a 22% no canto superior esquerdo, inline |
| `PLN-DETAIL-TITLE` | nome do modelo | heading | `plans.mustache:274` | `selectedtemplatename` | `<h2>` |
| `PLN-STATUS` | "Habilitado" / "Oculto" | badge | `plans.mustache:275-280` | `selectedtemplatevisible` | `is-enabled` (str `central_plans_enabled`) ou `is-disabled` (str `hidden, tool_lp`) |
| `PLN-DETAIL-COUNT` | "Competências: N" | pílula | `plans.mustache:283-286` | `competencycount` | `count($competencies)` (`dynamictabs/plans.php:327`) |
| `PLN-COUNT-PLANS` | "Planos: N" | pílula | `plans.mustache:288-291` | `selectedtemplateplancount` | `helper::count_plans_by_template` (`dynamictabs/plans.php:319-321`); alimenta também o `data-plancount` do `PLN-DELETE`. **Não estava no mapa** |
| `PLN-COUNT-COHORTS` | "Coortes: N" | pílula | `plans.mustache:293-296` | `selectedtemplatecohortcount` | `helper::count_cohorts_by_template` (`dynamictabs/plans.php:322-324`). **Não estava no mapa** |
| `PLN-DETAIL-GEAR` | Opções de exibição | botão ícone | `plans.mustache:299-304` | `data-action="display-options"` | `fa-cog`; **a segunda engrenagem da aba**; preferência `panels.plansdetail` (`plans.js:705-713`). **Não estava no mapa** |
| `PLN-DISP-OPTS` | `[sem rótulo]` | painel colapsável | `plans.mustache:307-328` | `data-region="display-options-panel"` | `role="group"`; switches em variante escura (`-switch-dark`) porque ficam **sobre o gradiente** |
| `PLN-DISP-TAX` | Exibir taxonomia | switch | `plans.mustache:310-315` | `data-display-toggle="tax"` | liga `show-tax` na lista (`DISPLAY_CLASSES`, `plans.js:54`) |
| `PLN-DISP-PATH` | Mostrar caminhos | switch | `plans.mustache:316-321` | `data-display-toggle="path"` | liga `show-path` |
| `PLN-DISP-ID` | Mostrar identificadores | switch | `plans.mustache:322-327` | `data-display-toggle="id"` | liga `show-id`; preferência `plansdetail` (`plans.js:306-314`) |
| `PLN-CHIP-DISPLAY` | "Formato de exibição: {$a}" | chip | `plans.mustache:331-335` | `selectedtemplatehasdisplaymode` | `fa-eye`; variante accent. Vem de `constants::display_mode_options()` (`dynamictabs/plans.php:256-258`) |
| `PLN-CHIP-TYPE` | "Rótulo das competências: {$a}" | chip | `plans.mustache:336-340` | `selectedtemplatehastype` | `fa-tag`; variante glass; custom field `type` |
| `PLN-CHIP-DUE` | "Prazo: …" | chip | `plans.mustache:341-345` | `selectedtemplatehasduedate` | `fa-calendar`; **os dois-pontos e o espaço são literais no template** (`:343`), não fazem parte da string |
| `PLN-CHIP-TAG1` | tag 1 | chip | `plans.mustache:346-348` | `selectedtemplatehastag1` | glass; custom field |
| `PLN-CHIP-TAG2` | tag 2 | chip | `plans.mustache:349-351` | `selectedtemplatehastag2` | glass; custom field |

## Detalhe — corpo e lista de competências

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-DESC` | `[sem rótulo]` | descrição colapsável | `plans.mustache:357-363` | `selectedtemplatehasdescription` | partial `collapsible_description`; o gate usa a versão **sem tags** (`strip_tags` + `trim`, `dynamictabs/plans.php:265`, `:301`), então uma descrição com só `<p></p>` não abre o bloco. Reativada a cada refresh por `CollapsibleDescription.refresh(pane)` (`plans.js:808`) |
| `PLN-LIST-HEADER` | Competências do plano · Estrutura · Ações | cabeçalho de colunas | `plans.mustache:365-372` | — | o título é **dinâmico**: com custom field `type` usa str `central_plans_competencylistlabelled` ("{$a} do plano"), senão `central_plans_competencylist` (`:368`) |
| `PLN-COMP-ROW` | `[sem rótulo]` | linha (loop) | `plans.mustache:378` | `data-competency="{id}"` | `<li>`; **o seletor de linha do JS é `[data-competency]`** (`plans.js:129`, `:451`, `:465`) |
| `PLN-COMP-NAME` | shortname | botão | `plans.mustache:381-382` | `data-action="open-competency-detail"`, `data-region="comp-name"` | abre `MOD.DETAIL` (`plans.js:754-755`); **não** é o rodapé nem o kebab — é o próprio nome. Também é lido como rótulo pelas opções do `MOD.MOVETO` (`plans.js:560-563`) |
| `PLN-COMP-TAX` | "– taxonomia" | texto | `plans.mustache:383` | `taxonomy` | `helper::get_taxonomy_at_level` pelo nível da competência (`dynamictabs/plans.php:212-215`); visível só com `PLN-DISP-TAX` |
| `PLN-COMP-ID` | idnumber | chip | `plans.mustache:384` | `idnumber` | visível só com `PLN-DISP-ID` |
| `PLN-COMP-PATH` | caminho | trilha | `plans.mustache:386-390` | `path` | `fa-folder-o`; `helper::competency_breadcrumbs` (`dynamictabs/plans.php:205`); visível só com `PLN-DISP-PATH` |
| `PLN-COMP-STRUCT` | tag da estrutura | badge | `plans.mustache:392-394` | `frameworktag` | **cross-framework**: `idnumber` do framework, ou o `shortname` se não houver (`dynamictabs/plans.php:199-201`) |

## Kebab da competência — **lista aninhada, legítimo** (`plans.mustache:396-436`)

Todo o bloco é gated por `{{#canmanage}}` (`:395`, fecha em `:446`). `:396` abre a `div.dropdown` e
`:436` a fecha. **Os dois `data-*` lado a lado** (`data-toggle` **e** `data-bs-toggle`, `:399`) e as
**duas** classes de alinhamento (`dropdown-menu-right dropdown-menu-end`, `:403`) são o requisito
BS4/BS5 do `CLAUDE.md` — o comentário em `:397` registra o porquê.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-COMP-MENU` | "Ações: {shortname}" | botão kebab | `plans.mustache:398-402` | `data-toggle`/`data-bs-toggle="dropdown"` | `fa-ellipsis-v`; ícone-só, então o `aria-label` embute o nome da linha (`:400`) — o padrão que o `CLAUDE.md` exige para o seletor `"button"` do Behat |
| `PLN-COMP-EDIT` | Editar competência | dropdown-item | `plans.mustache:405-408` | `data-action="edit-competency"` | `fa-pencil`; str `editcompetency, tool_lp`; carrega `data-frameworkid`; `openForm` com `COMPETENCY_FORM_CLASS` (`plans.js:739-745`) — **mesma função** do `PLN-EDIT`, entidade diferente. **Não estava no mapa** |
| `PLN-COMP-UP` | Mover para cima | dropdown-item | `plans.mustache:411-414` | `data-action="move-competency-up"` | `fa-arrow-up`; `disabled` se `{{#first}}` (`:412`); **caminho in-place** — sem reload (`plans.js:636-661`) |
| `PLN-COMP-DOWN` | Mover para baixo | dropdown-item | `plans.mustache:417-420` | `data-action="move-competency-down"` | `fa-arrow-down`; `disabled` se `{{#last}}` (`:418`); idem in-place |
| `PLN-COMP-MOVETO` | Mover para posição… | dropdown-item | `plans.mustache:423-426` | `data-action="move-competency-to"` | `fa-arrows-v`; abre `MOD.MOVETO` (`plans.js:548-606`). **Não estava no mapa** |
| `PLN-COMP-REMOVE` | Remover competência | dropdown-item | `plans.mustache:430-433` | `data-action="remove-competency"` | `fa-times`; **`text-danger`** (`:430`) — ao contrário dos rodapés, o item de menu **tem** variante de cor; separado por um `dropdown-divider` (`:428`); confirma com `saveCancelPromise` (`plans.js:290`) |
| `PLN-COMP-GRIP` | "Mover para posição…: {shortname}" | grip de arrasto | `plans.mustache:440-445` | `data-region="drag-handle"`, `data-action="move-competency-to"` | `fa-arrows-up-down-left-right`. **Renderizado DEPOIS do kebab de propósito** (comentário em `:437-439`): o `aria-label` embute o nome e o seletor `"button"` do Behat pega o **primeiro hit em ordem de documento** — o `order: -1` do CSS (`styles.css:5343`) o pinta na **esquerda** mesmo assim. É a armadilha exata registrada no `CLAUDE.md`. Nasce com `opacity: 0` (`styles.css:5347`) e aparece no hover da linha (`:5356-5359`) — **mas continua interativo pro WebDriver**. **Não estava no mapa** |

## Ações do modelo — **sticky-footer da página** (`plans.mustache:462-488`)

O holder nasce `hidden` no servidor (`:462`) **com os `data-*` do modelo selecionado já embutidos**,
e `init` copia o `innerHTML` pro `#sticky-footer` e **remove o holder** (`plans.js:797-800`) — senão
um duplicado escondido, mais cedo em ordem de documento, sombrearia os cliques por nome do Behat (o
comentário em `:790-795` registra isso). Só com `{{#canmanage}}` (`:457`, fecha em `:489`). Padrão
cru do core (`btn py-0 d-flex flex-column`): ícone sobre rótulo centrado, **sem variante de cor**.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-EDIT` | Editar detalhes | botão rodapé | `plans.mustache:465-468` | `data-action="edit-template"` | `fa-pencil`; str `central_plans_editdetails` (**compartilhada com o `FWK`**); `openForm` com `FORM_CLASS` (`plans.js:732-738`) |
| `PLN-BROWSE` | Adicionar competência | botão rodapé | `plans.mustache:469-472` | `data-action="browse-frameworks"` | `fa-plus`; str `central_addcompetency`. Abre `MOD.BROWSER` (`plans.js:723`) — **absorveu o trabalho do aposentado `PLN-ADD`**; o `excludeids` (`dynamictabs/plans.php:328`) impede reoferecer o que já está no modelo. **Única porta** deste modal |
| `PLN-PARTICIPANTS` | Gerenciar participantes | botão rodapé | `plans.mustache:473-476` | `data-action="manage-participants"` | `fa-users`; abre `MOD.PART` (`plans.js:724`). **Única porta** — e é por dentro dele que o `MOD.ENROL` existe |
| `PLN-DUPLICATE` | Duplicar modelo | botão rodapé | `plans.mustache:477-480` | `data-action="duplicate-template"` | `fa-clone`; WS **do plugin** `local_dimensions_duplicate_template` (não o do core), que também copia os custom fields da área lp, os arquivos embutidos e as imagens de card (`plans.js:615-626`); **seleciona a cópia** gravando o novo id no dataset **antes** do reload (`:622-625`). **Não estava no mapa** |
| `PLN-DELETE` | Excluir modelo | botão rodapé | `plans.mustache:481-485` | `data-action="delete-template"` | `fa-trash`; carrega `data-name` e `data-plancount`; **dois caminhos** — ver as regras abaixo |

## Modais alcançados

| ID | Origem | Regra / notas |
| --- | --- | --- |
| `MOD.BROWSER` | `competency_browser.js:106` | ← `PLN-BROWSE`. Ver [`mod-browser.md`](mod-browser.md) |
| `MOD.PART` | `participants_manager.js:144` | ← `PLN-PARTICIPANTS`. Ver [`mod-participants.md`](mod-participants.md) |
| `MOD.ENROL` | `enrol_methods.js:799` | montado **só** de dentro do `MOD.PART` (`participants_manager.js:33`). Ver [`mod-enrolmethods.md`](mod-enrolmethods.md) |
| `MOD.DELPLANS` | `plans.js:251-256` | ← `PLN-DELETE` **quando há planos**. Ver [`mod-delplans.md`](mod-delplans.md) |
| `MOD.MOVETO` | `plans.js:568-573` | ← `PLN-COMP-MOVETO` e `PLN-COMP-GRIP`. Template `local_dimensions/central/move_competency_modal` — **o mesmo do `EST`** (`structure.js:987`); select `#local-dimensions-plans-move-position` (`plans.js:575`) |
| `MOD.DETAIL` | `competency_detail.js:277` | ← `PLN-COMP-NAME`. Também aberto pelo chip de relacionada do `EST` (`structure.js:1246`) — **nenhuma das duas portas é rodapé** |
| `MOD.TPLFORM` | `plans.js:212` | ← `PLN-EDIT` (rodapé), `PLN-NEW` (cabeçalho) e `PLN-COMP-EDIT` (kebab, com outra `formclass`) |

## Estados vazios

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-EMPTY-FILTERED` | "Nenhum plano de aprendizagem contém esta competência." | empty-state | `plans.mustache:247` | str `central_noplanswithcompetency` | `alert-warning`; com filtro e sem resultado |
| `PLN-EMPTY` | "Nenhum plano de aprendizagem encontrado." | empty-state | `plans.mustache:250` | str `noplans` | `alert-info`; sem filtro e sem modelos |
| `PLN-DETAIL-EMPTY` | "Nenhuma competência nesta estrutura" | empty-state | `plans.mustache:452` | str `nocompetencies` | `alert-warning`. **Ver a verruga de i18n abaixo** |

## Regras de negócio (verificadas no código)

- **A exclusão tem dois caminhos, e o gate é o servidor, não o dataset.** `deleteTemplate`
  (`plans.js:234-272`) **não** confia no `data-plancount` para decidir: ele pergunta ao WS
  `core_competency_template_has_related_data` (`:236-239`). Só se **o servidor** disser que há planos
  é que o `MOD.DELPLANS` abre (`:246-263`), e aí o `data-plancount` é usado **apenas** para exibir o
  número (`:249`). Sem planos, cai num `deleteCancelPromise` simples (`:265-271`). O radio escolhe
  entre desvincular (padrão) e apagar os planos do aluno (`:257-261`).
- **Reordenar tem três caminhos e todos são in-place — nenhum recarrega o pane.** `moveCompetency`
  (`plans.js:636-661`, in-place explícito no comentário `:653`), o `dragend` de `initDragReorder`
  (`:523-527`) e o save do `MOD.MOVETO` (`:588-598`). Os três terminam igual: `refreshMoveState` +
  `flashRow` (`:525-526`, `:596-597`, `:659-660`). **O `reloadKeepingScroll` só aparece no `.catch`**
  dos dois últimos (`:532`, `:603`) — restaurar a ordem do servidor a partir de uma falha, com o
  `eslint-disable-next-line promise/no-nesting` que o `CLAUDE.md` exige.
- **O `refreshMoveState` existe porque o in-place mente sobre `first`/`last`.** O servidor marca
  `first`/`last` no render (`dynamictabs/plans.php:229-232`) e o template usa isso pra desabilitar as
  setas (`plans.mustache:412`, `:418`). Como o reorder não recarrega, `refreshMoveState`
  (`plans.js:128-140`) recalcula o `disabled` de **todas** as linhas por índice — senão a primeira
  linha continuaria com "mover para cima" habilitado depois de um arrasto.
- **O core decide de que lado a linha cai, e os dois caminhos in-place espelham isso.** O
  `reorder_template_competency` põe a origem **depois** do destino ao descer e **antes** ao subir; o
  `dragend` deduz a referência do irmão novo (`plans.js:510-512`) e o `MOD.MOVETO` aplica
  `after`/`before` conforme a direção (`:589-595`). Errar isso desalinha DOM e servidor sem erro
  nenhum.
- **O rodapé é defendido contra corrida em dois pontos** (o `FWK` tem três): `init` só publica se a
  aba for a ativa (`plans.js:796`) e `dispatchPlansAction` ignora cliques se a aba saiu de foco
  (`:769-771`). Ambos existem porque as abas dinâmicas re-executam `init` de uma carga assíncrona
  fora de ordem.
- **O modelo selecionado é auto-escolhido, e prefere um visível.** Sem `templateid` válido, o
  servidor pega o **primeiro visível** e só cai no primeiro de todos se nenhum for
  (`dynamictabs/plans.php:143-156`) — assim o detalhe combina com a lista padrão, onde os ocultos
  começam escondidos.
- **`hashiddentemplates` é exportado e o template não o usa.** `dynamictabs/plans.php:285` o manda,
  mas `plans.mustache` decide pelo `{{#showhiddentoggle}}` (`:139`) — mesma chave morta que o
  `fwk-frameworks.md` registrou para o `hashiddenframeworks`. Os únicos consumidores do nome são o
  `structure.mustache`.
- **`canmanage` no `PLN-ROOT` viaja com outro nome.** `data-canmanageenrol="{{canmanage}}"`
  (`plans.mustache:133`) — o mesmo valor de `canmanage` (`dynamictabs/plans.php:329`) exposto sob um
  nome diferente para o modal de participantes. Não existe `data-canmanage`.
- **i18n · a verruga do estado vazio.** `PLN-DETAIL-EMPTY` usa a str `nocompetencies`, que é
  **"Nenhuma competência nesta estrutura"** / "No competencies in this structure"
  (`lang/pt_br:460`, `lang/en:460`) — mas o container aqui é um **modelo de plano**, não uma
  estrutura. A string é compartilhada com o `EST` (`structure.mustache:225`, onde está correta) e nem
  a variante de alerta bate: `alert-info` no `EST`, `alert-warning` no `PLN`. Um modelo vazio anuncia
  ao usuário a entidade errada.
- **a11y · o cabeçalho é a única superfície do hub cujas cores o admin escolhe — e o que é medido não
  é o que é pintado.** O par vem de dois custom fields (`constants::CFIELD_CUSTOMBGCOLOR` /
  `CFIELD_CUSTOMTEXTCOLOR`), com padrão `#0f6cbf` + `#ffffff` (`dynamictabs/plans.php:271-272`). O
  plugin **não é omisso**: os dois forms que editam esse par (`template_dynamic_form.php:220-223` e
  `competency_dynamic_form.php:237-240`) montam um painel WCAG **em tempo real** — razão, veredito,
  badges AA/AAA e **até dois consertos de um clique** quando reprova (`contrast.js:16-28`, limiares
  em `:43`: AA 4.5, AAA 7). Mas ele **aconselha, não bloqueia**: o próprio módulo diz que "nunca toca
  em como o form salva" (`contrast.js:22-23`), e a `validation()` do form só checa `shortname` e o
  SCSS (`template_dynamic_form.php:319-337`) — nunca o par.
  **A lacuna real, porém, é outra:** o painel gradua **texto vs fundo**, e o cabeçalho **não pinta
  esse par** — pinta três stops derivados (`darken_hex` 0.16/0.34) e chips **translúcidos** por cima
  deles. Esses derivados ninguém gradua. Medido para o padrão `#0f6cbf`: o texto branco dá **5,36:1**
  (passa), mas os `-chip-glass` (branco a 13%, `styles.css`) dão **4,22:1 sobre o stop 0** —
  **abaixo** do mínimo AA — subindo para 5,22:1 no stop 48 e 6,71:1 no stop 100. Ou seja: o mesmo
  chip passa ou reprova conforme **onde cai no gradiente**. Os fixos passam com folga: `chip-accent`
  e a pílula de contagem usam `#495057` + `#fff` (**8,18:1**), `status.is-enabled` `#217a37`
  (**5,38:1**), `status.is-disabled` `#6a737b` (**4,83:1**).
- **Uma query de contagem por linha.** `PLN-TPL-COUNT` chama
  `api::count_competencies_in_template($id)` dentro do laço (`dynamictabs/plans.php:173`), sem
  batching — ao contrário de `count_plans_by_template`/`count_cohorts_by_template`, que aceitam array
  (`:319-324`) mas são chamadas só para o selecionado. Com N modelos, N queries.

## to-be

### IMP-03 (`mtube: carregando`) — **o alvo é `reloadPane`, não a troca de aba**

> **Correção medida** (idêntica à do `est-structure.md` e à do `fwk-frameworks.md`, re-verificada
> aqui de forma independente). O plano descreve o IMP-03 como "loading na troca de aba". **A troca de
> aba já tem loading, e vem do core**: `dynamic_tabs.js:92-97` ouve `shown.bs.tab` → `loadTab`, e
> `loadTab` abre com `addIconToContainer(tab)` (`:153`). Antes disso, `show.bs.tab` **esvazia** o
> pane anterior (`:88`).
>
> A lacuna é o **`reloadPane` do plugin** (`tabs.js:51-66`), que refaz o caminho do `loadTab`
> **sem** o `addIconToContainer` — e é ele que roda nas **23** chamadas em 5 módulos (`structure` 9,
> `frameworks` 6, `plans` 6, `competency_browser` 1, `context` 1). Uma linha no `reloadPane` e os 23
> sítios ganham juntos.
>
> **Precisão que só esta aba dá:** como o `PLN` **nunca nasce ativo** (`central.php:105`), o pane
> dele **nunca** é renderizado no servidor no load — a primeira pintura *sempre* passa pelo `loadTab`,
> que **já** mostra o ícone. Ou seja, nesta aba a lacuna é **exclusivamente** o `reloadPane`.

**As 6 chamadas desta aba** são `plans.js:101` (dentro do `reloadKeepingScroll`), `:244` (excluir),
`:625` (duplicar), `:679` (limpar filtro), `:684` (remover chip do filtro) e `:836` (adicionar
competência ao filtro).

> **Ressalva medida — e ela não é a que o plano supunha.** O `reloadKeepingScroll`
> (`plans.js:92-108`) **não é um caminho in-place**: ele **aguarda `reloadPane` na `:101`**, ou seja,
> é um reload de pane inteiro que apenas **captura o `scrollTop` antes (`:95-100`) e o restaura
> depois (`:102-107`)**. Um spinner no `reloadPane` **cobre o `reloadKeepingScroll` de graça** e isso
> é **correto** — é reload, merece spinner. O que **seria** regressão é pôr spinner nos **três
> caminhos in-place de verdade** — `moveCompetency` (`:636-661`), o `dragend` (`:523-527`) e o save
> do `MOD.MOVETO` (`:588-598`) — que não recarregam nada e já se confirmam com `flashRow`.
>
> A regra da casa vale sem emenda: **pane recarregado → spinner; linha trocada → flash.** Esta aba é
> a que mais claramente exibe as duas metades: **6** reloads e **3** flashes, no mesmo arquivo.

Forma de referência: o `alert alert-info` + `spinner-border spinner-border-sm` do `FWK-IMP-BANNER`
(`frameworks.js:231-237`). Copiar a forma **visual**, **não** o ARIA do `makeSpinner()` (que põe
`role="status"` e `aria-hidden="true"` no mesmo elemento e não anuncia nada — ver
`fwk-frameworks.md`); marcar o pane com `aria-busy="true"` e o nome no container, como em
`states.html`.

### IMP-05 (`mtube: atualizar`) — controle de atualizar na contextbar

Ver `bar-contextbar.md` (a decisão e as verificações moram lá). Precisão que este mapa confirma de
forma independente: **não** é verdade que "nada expõe `reloadPane`" — ele tem **23 chamadas em 5
módulos**. O que é verdade é que **nenhum controle de UI** o dispara; as 23 são refresh automático
pós-ação. Aqui são as 6 de `plans.js` listadas acima.

### IMP-10 (`mtube: ícones nas abas`) — ícones + indicador nas abas

Ver `hierarchy-nav.html`. O que esta aba confirma: `central.php:114` passa `displayname` e o
`core/dynamic_tabs.mustache:53` faz **triple-stash**, então o ícone entra pelo rótulo **sem** mudar
template do core. O rótulo desta aba vem de `central.php:101` (str `learningplans`), e como o `PLN`
**não** nasce ativo, ele é o caso que exercita o **estado inativo** do indicador no primeiro paint.

## IDs aposentados

> Não reutilizar. Um ID pendurado é pior que uma aposentadoria registrada.

| ID | Situação | Substituto | Nota |
| --- | --- | --- | --- |
| `PLN-ADD` | **Aposentado** (2026-07-14) | `PLN-BROWSE` → `MOD.BROWSER` | Era o autocomplete `data-region="competency-add"` no painel de detalhe, para adicionar competência sem sair da aba. **Não existe em nenhum template nem em `plans.js`** (verificado por busca em `templates/` e `amd/src/`). Adicionar competência agora é só pelo `MOD.BROWSER`, lançado do rodapé — o `excludeids` que alimentava o `data-exclude` dele sobreviveu e hoje alimenta o modal (`dynamictabs/plans.php:328`) |
| `PLN-FILTER-BADGE` | **Aposentado** (2026-07-14) | `PLN-FILTER-CHIP` | Era o badge único "Filtrado por: X". O filtro virou **multi**-competência: um chip removível por competência (`plans.mustache:199-206`) mais um botão de adicionar (`:208-211`). A flag `filteredbycompetency` sobreviveu, mas hoje só gateia o `PLN-FILTER-CLEAR` (`:191`) |
