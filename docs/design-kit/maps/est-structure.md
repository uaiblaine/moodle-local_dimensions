# Mapa de Campos — `EST` · Aba Estrutura (as-is)

Master-detail: sub-cabeçalho (seletor de framework + contador + "Adicionar competência"
sensível ao nível) sobre dois painéis. O painel esquerdo é um card branco com **barra de
ferramentas** (expandir/contrair + engrenagem), **opções de exibição** em painel colapsável,
**busca** e a árvore lazy. Um **divisor de 22px** redimensiona os painéis. O painel direito
veste cabeçalho gradiente (título + taxonomia + chips) sobre corpo branco com os três cards de
métrica, a descrição e as competências referenciadas; é `position: sticky`.

**O CRUD do nó não mora no pane** — mora no sticky-footer da página, injetado por `selectRow`
(`structure.js:550-556`) e roteado de volta por `dispatchStructureAction` (`structure.js:1297-1304`).

- **Mustache:** [`templates/central/structure.mustache`](../../../templates/central/structure.mustache), [`structure_node.mustache`](../../../templates/central/structure_node.mustache), [`structure_detail_content.mustache`](../../../templates/central/structure_detail_content.mustache), [`structure_footer_actions.mustache`](../../../templates/central/structure_footer_actions.mustache), [`showhidden_toggle.mustache`](../../../templates/central/showhidden_toggle.mustache)
- **AMD:** [`amd/src/central/structure.js`](../../../amd/src/central/structure.js) (1500 linhas), [`central/tabs.js`](../../../amd/src/central/tabs.js), [`central/action_footer.js`](../../../amd/src/central/action_footer.js)
- **To-be no DS:** `master-detail.html` (chips ricos no detalhe — hoje **converge**: o detalhe ganhou chips reais em `structure_detail_content.mustache:51-71`).

> **Resync 2026-07-14:** a versão anterior deste mapa congelou em `159a800` (2026-06-29). Desde então
> `structure.mustache` foi de **176 para 233** linhas e `structure_node.mustache` de **71 para 120** —
> e **as 23 de 23** refs de `structure.mustache` do mapa antigo resolviam para linhas **não
> relacionadas** (várias caíam dentro do *Example context* do docblock; `EST-FW-COUNT` apontava `:95`,
> que hoje é um `<script>` de JSON). Todas foram re-derivadas, não corrigidas pontualmente.
>
> O defeito maior, porém, era **ausência**: não estavam mapeados a barra de ferramentas, as opções de
> exibição, a busca, o resizer, o arrasto, as competências referenciadas, os chips do detalhe, duas
> das três métricas, os três `<script>` de JSON — e **todo** o comportamento de `structure.js`
> (o mapa tinha **zero** refs de JS).

## Raiz e dados de página

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-ROOT` | `[sem rótulo]` | região/raiz | `structure.mustache:92-94` | `data-region="structure"` | carrega `contexttype`, `categoryid`, `frameworkid`, `canmanage`; `init` lê tudo daqui (`structure.js:1331-1337`) |
| `EST-JSON-RULES` | `[sem rótulo]` | dados JSON | `structure.mustache:95` | `data-region="rules-modules"` | lido por `readJson` (`structure.js:123-133`); alimenta `MOD.RULE` |
| `EST-JSON-COURSEOUT` | `[sem rótulo]` | dados JSON | `structure.mustache:96` | `data-region="course-outcomes"` | outcomes de curso → `MOD.LINKS` |
| `EST-JSON-MODOUT` | `[sem rótulo]` | dados JSON | `structure.mustache:97` | `data-region="module-outcomes"` | outcomes de módulo → `MOD.LINKS` |

## Sub-cabeçalho e seletor

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-EMPTY-CAT` | "Escolha primeiro a categoria de curso…" | empty-state | `structure.mustache:100` | str `managecompetencies_selectcategory_help` | bloqueia a aba inteira até escolher categoria |
| `EST-SHOWHIDDEN` | Mostrar estruturas ocultas | switch | `showhidden_toggle.mustache:44-45`, chamado em `structure.mustache:105-107` | `data-action="{{action}}"` → `toggle-hidden` | **partial compartilhado** com `FWK`/`PLN`: o `data-action` é **variável** no template e o valor literal vem de `dynamictabs/structure.php:169` (contexto em `:166-171`; nulo → não renderiza), rótulo str `managecompetencies_showhiddenframeworks`. Estado em preferência (`Preferences.saveDisplay`, `structure.js:1491`), **não** no servidor |
| `EST-FW-LABEL` | Estrutura | label | `structure.mustache:112-114` | str `central_browseframeworks_framework` | `for="local-dimensions-central-framework"` |
| `EST-FW-SELECT` | Estrutura (select) | select | `structure.mustache:115` | `data-region="framework-select"` | `form-select`; `change` → grava `pane.dataset.frameworkid` + `reloadPane` (`structure.js:1464-1475`) |
| `EST-FW-OPTION` | `nome · idnumber · oculto` | option (loop) | `structure.mustache:117` | `frameworks` | `data-hidden="1"` nos ocultos; opções são snapshotadas em `init` (`structure.js:1477-1484`) para o filtro client-side |
| `EST-FW-COUNT` | "itens: N" | contador | `structure.mustache:121-123` | `competencycount` | str `managecompetencies_items`; **conta o framework selecionado** — é o 3º dos três contadores do hub (ver `bar-contextbar.md`) |
| `EST-ADDROOT` | Adicionar competência | botão | `structure.mustache:127-129` | `data-action="add"` | só com `canmanage`. **Sensível ao nível:** sem seleção cria raiz; com nó ativo cria filha dele (`structure.js:1424-1428`) |
| `EST-ADDHINT` | "Nova competência raiz" / "Nova filha de X" | dica | `structure.mustache:130-132` | `data-region="add-hint"` | str `managecompetencies_addhint_root`; reescrita por `selectRow` para `..._addhint_child` com o nome do nó (`structure.js:536-544`) |

## Barra de ferramentas e opções de exibição (painel esquerdo)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-TREE-PANE` | `[sem rótulo]` | painel/card | `structure.mustache:139` | `data-region="tree-pane"` | wrapper; largura controlada pelo `EST-RESIZER` |
| `EST-TOOL-EXPAND` | Expandir tudo | botão | `structure.mustache:142-144` | `data-action="expand-all"` | `expandAll` (`structure.js:615-629`); **teto de 200 nós** (`EXPAND_CAP`, `:109`) |
| `EST-TOOL-COLLAPSE` | Recolher tudo | botão | `structure.mustache:145-147` | `data-action="collapse-all"` | `collapseAll` (`structure.js:636-649`); puro DOM, sem rede |
| `EST-TOOL-GEAR` | `[só title/sr]` | botão ícone | `structure.mustache:148-153` | `data-action="display-options"` | `fa-cog`; alterna `EST-DISP-PANEL` e persiste em `Preferences.saveDisplay({panels:{structure}})` (`structure.js:1415-1421`) |
| `EST-DISP-PANEL` | Opções de exibição | grupo | `structure.mustache:155-156` | `data-region="display-options-panel"` | `role="group"`; estado restaurado por `applyPanelState` (`structure.js:319-328`) |
| `EST-DISP-TAX` | Exibir taxonomia | switch | `structure.mustache:159` | `data-display-toggle="tax"` | liga a classe `show-tax` no `EST-TREE` (`DISPLAY_CLASSES`, `structure.js:113`); **desligado** por padrão |
| `EST-DISP-ID` | Mostrar identificadores | switch | `structure.mustache:164` | `data-display-toggle="id"` | classe `show-id`; **desligado** por padrão |
| `EST-DISP-RULE` | Exibir regra de competência | switch | `structure.mustache:169` | `data-display-toggle="rule"` | classe `show-rule`; **ligado** por padrão (o `checked` no template e a classe já no `EST-TREE`, `:184`) |

## Busca (painel esquerdo)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-SEARCH` | Buscar por nome ou identificador | input search | `structure.mustache:179-180` | `data-region="structure-search"` | label `visually-hidden` (`:176-178`); **mínimo 2 caracteres**, debounce **250ms** (`structure.js:1450-1462`) |
| `EST-SEARCH-RESULTS` | `[sem rótulo]` | contêiner-JS | `structure.mustache:182` | `data-region="search-results"` | `hidden` por padrão; `renderSearchResults` (`structure.js:336-371`); clicar num resultado chama `revealNode`, que expande o caminho até o nó (`structure.js:440-465`, teto `REVEAL_CAP`=100, `:389`) |

## Árvore (painel esquerdo)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-TREE` | `[sem rótulo]` | contêiner | `structure.mustache:184` | `data-region="competency-tree"` | recebe os `EST-NODE`; nasce com a classe `show-rule` |
| `EST-TREE-LOADMORE` | Carregar mais | botão | `structure.mustache:190-194` | `data-region="root-loadmore"` | só se `hasmoreroots`; `data-offset`/`data-total`; página de **25** (`PAGE_SIZE`, `structure.js:48`) |
| `EST-TREE-LOADMORE-HINT` | "Mostrando N de M" | dica | `structure.mustache:195` | `rootloadmorehint` | str `central_structure_loadmoreshown`, montada em `dynamictabs/structure.php:145-148` |
| `EST-RESIZER` | Redimensionar painéis | separator | `structure.mustache:202-206` | `data-region="structure-resizer"` | `role="separator"`, `tabindex="0"`; `initStructureResize` (`structure.js:1312-1325`) — arrasto, dblclick reseta, setas redimensionam, persiste em `localStorage` |

### Nó da árvore (`structure_node`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-NODE` | `[sem rótulo]` | linha (wrapper) | `structure_node.mustache:61` | `data-node="{id}"` | renderizado server-side nas raízes, client-side ao expandir |
| `EST-NODE-TOGGLE` | `[só aria-label]` | botão chevron | `structure_node.mustache:65-68` | `data-action="toggle"` | só se `haschildren`; `fa-chevron-right`; `aria-expanded`; `toggleNode` busca as filhas na 1ª abertura (`structure.js:570-606`) |
| `EST-NODE-ICON` | `[sem rótulo]` | bullet | `structure_node.mustache:70-72` | — | **folhas apenas**: `•` no lugar do chevron. *(Antes o mapa dizia `fa-folder-o`/`fa-circle-o` — nenhum dos dois existe; o ícone de nó-com-filhas é o chevron do `EST-NODE-TOGGLE`.)* |
| `EST-NODE-ROW` | nome da competência | botão | `structure_node.mustache:73-95` | `data-action="select"` | carrega **20** `data-*` de payload além do `data-action` (id, parentid, name, idnumber, taxonomy, scale, description, type, tag1, tag2, bgcolor, textcolor, courses, activities, templates, haschildren, ruletype, ruleoutcome, ruleconfig, rulelabel) — o detalhe é montado **só** deles, sem round-trip |
| `EST-NODE-NAME` | nome | texto | `structure_node.mustache:96` | `shortname` | — |
| `EST-NODE-TAX` | taxonomia | badge | `structure_node.mustache:97` | `taxonomy` | visível só com `show-tax` (`EST-DISP-TAX`) |
| `EST-NODE-ID` | idnumber | badge | `structure_node.mustache:99` | `idnumber` | só se `idnumber`; visível só com `show-id` |
| `EST-NODE-RULE` | rótulo da regra | badge | `structure_node.mustache:103` | `rulelabel` | **duplo gate**: só se `haschildren` **e** `ruletype` (`:101-105`) — folha não pode ter regra, então não exibe "nenhuma" |
| `EST-NODE-DRAG` | "Mover para posição…: {nome}" | botão ícone | `structure_node.mustache:111-116` | `data-region="node-drag-handle"` | só `canmanage`. Fica **depois** da linha no DOM e é puxado com `order:-1` no CSS: o `aria-label` embute o nome e o Behat clica no primeiro hit em ordem de documento (comentário no template, `:109-110`) |
| `EST-NODE-CHILDREN` | `[sem rótulo]` | contêiner-JS | `structure_node.mustache:119` | `data-children="{id}"` | `data-offset="0"`, `hidden`; `loadChildPage` pagina de 25 (`structure.js:227-246`) |

## Detalhe (painel direito) — `structure_detail_content`

Partial **compartilhado** entre o pane inline e o modal de competência referenciada. Dois flags o
ajustam: `linksclickable` (métricas viram botões que abrem a modal de uso — inline) e `showrelated`
(seção de referenciadas — inline). Todos os valores nascem vazios e são preenchidos client-side.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-DETAIL-PANE` | `[sem rótulo]` | painel | `structure.mustache:208` | `data-region="detail-pane"` | `position: sticky` — fica à vista enquanto a árvore rola |
| `EST-DETAIL-EMPTY` | "Selecione uma linha na árvore ou na tabela…" | empty-state | `structure.mustache:210-213` | `data-region="detail-empty"` | visível até a 1ª seleção; `selectRow` o esconde (`structure.js:528`) |
| `EST-DETAIL-CONTENT` | `[sem rótulo]` | contêiner | `structure.mustache:214` | `data-region="detail-content"` | `hidden` até selecionar; hospeda o partial |
| `EST-DETAIL-TITLE` | `[sem rótulo]` | `h2` | `structure_detail_content.mustache:48` | `data-region="detail-title"` | vem de `data-name` do `EST-NODE-ROW` |
| `EST-DETAIL-TAXONOMY` | `[sem rótulo]` | badge | `structure_detail_content.mustache:49` | `data-region="detail-taxonomy"` | ao lado do título, no cabeçalho gradiente |
| `EST-DETAIL-RULECHIP` | `[sem rótulo]` | chip accent | `structure_detail_content.mustache:52-54` | `data-region="detail-rule-wrap"` | `hidden` até haver regra; `fa-check` |
| `EST-DETAIL-LABEL` | `[sem rótulo]` | chip glass | `structure_detail_content.mustache:55-57` | `data-region="detail-label-wrap"` | `fa-tag`; vem de `data-type` |
| `EST-DETAIL-IDNUMBER` | "Número de identificação:" | chip glass | `structure_detail_content.mustache:58-61` | `data-region="detail-idnumber"` | prefixo str `idnumber`; valor em `font-monospace` |
| `EST-DETAIL-SCALE` | `[sem rótulo]` | chip glass | `structure_detail_content.mustache:62-64` | `data-region="detail-scale"` | escala da competência |
| `EST-DETAIL-TAG1` | `[sem rótulo]` | chip glass | `structure_detail_content.mustache:65-67` | `data-region="detail-tag1"` | campo personalizado |
| `EST-DETAIL-TAG2` | `[sem rótulo]` | chip glass | `structure_detail_content.mustache:68-70` | `data-region="detail-tag2"` | campo personalizado |
| `EST-DETAIL-COURSES` | Cursos vinculados | card métrica | `structure_detail_content.mustache:79-82` (botão) · `:85` (texto) | `data-action="show-usage" data-usage="courses"` | com `linksclickable` é botão → `openUsageModal` (`structure.js:1207-1232`); sem, é texto inerte (evita modal sobre modal) |
| `EST-DETAIL-ACTIVITIES` | Atividades vinculadas | card métrica | `structure_detail_content.mustache:91-94` (botão) · `:97` (texto) | `data-usage="activities"` | idem |
| `EST-DETAIL-PLANS` | Planos vinculados | card métrica | `structure_detail_content.mustache:103-106` (botão) · `:109` (texto) | `data-usage="templates"` | idem; str `central_structure_linkedplans` |
| `EST-DETAIL-DESC` | `[sem rótulo]` | descrição | `structure_detail_content.mustache:113-114` | `data-region="detail-description-wrap"` | `hidden` se vazia |

### Competências referenciadas (só inline, `showrelated`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-REFS-PANEL` | Competências referenciadas | seção | `structure_detail_content.mustache:117` | `data-region="detail-related"` | `hidden` até haver referenciadas; `populateRelated` (`structure.js:477-503`) |
| `EST-REFS-COUNT` | `[sem rótulo]` | contador | `structure_detail_content.mustache:121` | `data-region="detail-related-count"` | nasce em `0` |
| `EST-REFS-LIST` | `[sem rótulo]` | contêiner-JS | `structure_detail_content.mustache:123` | `data-region="detail-related-list"` | chips `data-action="open-related"` → abre a competência referenciada em modal (`structure.js:1244-1248`) |

## Ações do nó — **sticky-footer da página**, não o pane

Renderizadas por `selectRow` via `Templates.renderForPromise('…/structure_footer_actions')` e
entregues a `ActionFooter.show(html, dispatchStructureAction)` (`structure.js:550-556`). Só com
`canmanage`. Os botões **não carregam dataset**: agem sobre o `activeRow` de módulo
(`structure.js:1297-1304`), por isso funcionam de fora da região da aba.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-DETAIL-EDIT` | Editar detalhes | botão rodapé | `structure_footer_actions.mustache:41-44` | `data-action="edit"` | `fa-pencil`; abre `openForm`; ao salvar chama `refreshNode` — atualização **in-place** (`structure.js:805`) |
| `EST-DETAIL-ADDCHILD` | Adicionar filha | botão rodapé | `structure_footer_actions.mustache:45-48` | `data-action="addchild"` | `fa-plus`; criar recarrega o pane (`structure.js:807`) |
| `EST-DETAIL-RULES` | Regra de competência | botão rodapé | `structure_footer_actions.mustache:49-52` | `data-action="rules"` | `fa-list`; str `competencyrule, tool_lp`; abre `MOD.RULE`; salvar grava **in-place** + flash (`persistRule`, `structure.js:847-879`) |
| `EST-DETAIL-LINKS` | Cursos e atividades | botão rodapé | `structure_footer_actions.mustache:53-56` | `data-action="links"` | `fa-link`; abre `MOD.LINKS`; ao fechar atualiza a contagem **in-place** (`updateCourseCount`, `structure.js:908-920`) |
| `EST-DETAIL-RELATED` | Competências referenciadas | botão rodapé | `structure_footer_actions.mustache:57-60` | `data-action="related"` | `fa-exchange`; abre `MOD.RELATED`. **Novo** desde o congelamento do mapa |
| `EST-DETAIL-MOVETO` | Mover para posição… | botão rodapé | `structure_footer_actions.mustache:61-64` | `data-action="moveto"` | `fa-arrows-up-down-left-right`; abre `MOD.MOVETO` (`openNodeMoveModal`, `structure.js:972-1007`). **Substitui** `EST-DETAIL-MOVEUP`/`-MOVEDOWN` |
| `EST-DETAIL-DELETE` | Excluir | botão rodapé | `structure_footer_actions.mustache:65-68` | `data-action="delete"` | `fa-trash`; `confirmDelete` (`structure.js:819-838`) → recarrega o pane. **Sem variante de cor** — o padrão cru do sticky-footer do core não usa `btn-outline-danger` |

## IDs aposentados

> Não reutilizar. Um ID pendurado é pior que uma aposentadoria registrada.

| ID | Situação | Substituto | Nota |
| --- | --- | --- | --- |
| `EST-DETAIL-MOVEUP` | **Aposentado** (2026-07-14) | `EST-DETAIL-MOVETO` → `MOD.MOVETO` | Era `data-action="moveup"`, botão-ícone de reordenar entre irmãos. Não existe em nenhum template nem em `structure.js` |
| `EST-DETAIL-MOVEDOWN` | **Aposentado** (2026-07-14) | `EST-DETAIL-MOVETO` → `MOD.MOVETO` | idem `movedown`. As duas setas viraram **um** botão que abre o modal de mover, e o arrasto direto (`EST-NODE-DRAG`) cobre o caso rápido |

## Estados vazios

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-EMPTY-COMP` | "Nenhuma competência nesta estrutura" | empty-state | `structure.mustache:225` | str `nocompetencies, local_dimensions` | framework sem competências; substitui o master-detail inteiro |
| `EST-EMPTY-FW` | "Nenhuma estrutura de competências encontrada" | empty-state | `structure.mustache:230` | str `noframeworks, local_dimensions` | contexto sem frameworks |

## Regras de negócio (verificadas no código)

- **O detalhe não faz round-trip.** `selectRow` (`structure.js:511-561`) monta o painel inteiro dos
  `data-*` do `EST-NODE-ROW`. A única chamada de rede da seleção é `populateRelated` (`:533`).
- **Quatro caminhos in-place deliberados**, todos existindo para não perder expansão nem seleção:
  `refreshNode` (edição, `:725-785`), `persistRule` (regra, `:847-879`), `updateCourseCount`
  (vínculos, `:908-920`) e `applyShowHidden` (filtro do dropdown, `:271-290`). Este último filtra as
  opções client-side justamente para **não** recarregar o pane e evitar o flash dos toggles
  (comentário em `:1475-1476`).
- **`refreshNode` degrada para `reloadPane` em três casos** (`:729`, `:738`, `:763`): nó sumiu, WS não
  achou, ou o nó foi **reparentado** na edição — a posição na árvore mudou e uma troca de linha
  in-place não representa isso; aí recarrega e chama `revealNode`.
- **`reloadPane` é o `loadTab` do core menos o ícone de carregando.** Compare `tabs.js:51-66` com
  `lib/amd/src/dynamic_tabs.js:140-166`: mesmo `getContent` → `renderForPromise` →
  `replaceNodeContents`; o core adiciona `addIconToContainer(tab)` (`:153`), `Pending` e
  `prependPageTitle`, e o do plugin troca isso por restauração de foco (`tabs.js:60-65`). **É essa
  diferença que o IMP-03 fecha** — ver abaixo.
- **O rodapé é defendido contra corrida em três pontos**: `selectRow` só mostra se a linha ainda está
  ativa **e** a aba ainda é a ativa (`:555`); `dispatchStructureAction` ignora cliques se a aba saiu
  de foco (`:1300-1302`); e `init` só limpa o rodapé se a aba for a ativa (`:1343-1345`), porque as
  abas dinâmicas re-executam `init` de uma carga assíncrona fora de ordem.
- **Clicar na linha de um nó com filhas também expande/contrai** — não só o chevron
  (`structure.js:1394-1403`).
- **Preferências**: exibição (`tax`/`id`/`rule`), painel da engrenagem aberto/fechado e `showhidden`
  vão para `Preferences.saveDisplay` (`:260-262`, `:1420`, `:1491`); o framework escolhido vai para
  `Preferences.saveNav` (`:1470`). A largura dos painéis vai para `localStorage` (`:1312-1325`).
- **Tetos**: página de 25 (`PAGE_SIZE`, `:48`), expandir-tudo para em 200 nós (`EXPAND_CAP`, `:109`),
  revelar caminho para em 100 (`REVEAL_CAP`, `:389`).

## to-be

### IMP-03 (`mtube: carregando`) — **o alvo é `reloadPane`, não a troca de aba**

> **Correção medida.** O plano descreve o IMP-03 como "loading na troca de aba". **A troca de aba já
> tem loading, e vem do core**: `dynamic_tabs.js:92-97` ouve `shown.bs.tab` → `loadTab`, e `loadTab`
> abre com `addIconToContainer(tab)` (`:153`), que injeta o template `core/loading` (`i/loading`,
> com `fadeIn(150)`). Antes disso, `show.bs.tab` **esvazia** o pane anterior (`:88`). Ou seja: numa
> troca de aba real o usuário já vê pane vazio + ícone girando.
>
> A lacuna é o **`reloadPane` do plugin** (`tabs.js:51-66`), que refaz o caminho do `loadTab` **sem**
> o `addIconToContainer` — e é ele que roda nas **23** chamadas em 5 módulos. Toda ação de CRUD do
> hub reconstrói o pane sem nenhuma indicação de espera. É aí que o IMP-03 entra: uma linha
> (`addIconToContainer(pane)`) no `reloadPane`, e os 23 sítios ganham juntos.

**Ressalva (obrigatória):** **não** aplicar em `refreshNode`. Ele é caminho in-place **deliberado**
(`structure.js:725-785`) — troca a linha e preserva expansão + seleção. Um spinner de pane inteiro
ali seria **regressão**: destruiria visualmente exatamente o estado que a função existe para
preservar. O mesmo vale para os outros três in-place (`persistRule`, `updateCourseCount`,
`applyShowHidden`), que já se confirmam com **flash** (`--mds-motion-flash`, via WAAPI). Regra:
**pane recarregado → spinner; linha trocada → flash.**

Forma de referência: o `alert alert-info` + `spinner-border spinner-border-sm` do modal de import
(ver `fwk-frameworks.md`). Marcar o pane com `aria-busy="true"` enquanto carrega (padrão do
`states.html`).

### IMP-05 (`mtube: atualizar`) — controle de atualizar na contextbar

Ver `bar-contextbar.md` (a decisão e as verificações moram lá). Precisão que este mapa confirma
de forma independente: `reloadPane` tem **23 chamadas em 5 módulos** — `structure` 9, `frameworks` 6,
`plans` 6, `competency_browser` 1, `context` 1. **Não** é verdade que "nada expõe `reloadPane`"; o
que é verdade é que **nenhum controle de UI** o dispara — as 23 são refresh automático pós-ação.
Aqui na aba Estrutura são as 9 de `structure.js` (`:729,738,747,763,807,835,959,1035,1471`).

### IMP-10 (`mtube: ícones nas abas`) — ícones + indicador nas abas

Ver `hierarchy-nav.html`. O que a aba Estrutura confirma: `central.php:114` passa `displayname` e o
`core/dynamic_tabs.mustache:53` faz **triple-stash** (`{{{displayname}}}`) — o ícone entra pelo
rótulo, **sem** mudar template do core. `central.php:57` já põe `local-dimensions-central-page` no
`<body>`, que é o escopo para o indicador `inset 0 -2px 0` não vazar para outros consumidores de
`dynamic_tabs` do site. **Não** portar o dropdown de overflow por `ResizeObserver` do mtube: 146
linhas de medição para três rótulos curtos (`central.php:98-102`) que nunca transbordam.
