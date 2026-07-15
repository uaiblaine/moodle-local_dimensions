# Mapa de Campos — `FWK` · Aba Estruturas (as-is)

Lista de **cards** de estrutura (competency framework) do contexto resolvido. O card inteiro é um
botão de seleção: escolher um marca o card e publica as ações dele no **sticky-footer da página**.
A toolbar traz o contador ("· N ocultas") e **três** ações de cabeçalho (nova / importar / exportar).
O seletor Sistema/Categoria vem da contextbar (`BAR`).

**As ações do card não moram no card** — moram no sticky-footer da página, injetado por
`selectFramework` (`frameworks.js:468-476`) e roteado de volta por `dispatchFrameworksAction`
(`frameworks.js:431-449`).

- **Mustache:** [`templates/central/frameworks.mustache`](../../../templates/central/frameworks.mustache), [`frameworks_row.mustache`](../../../templates/central/frameworks_row.mustache), [`frameworks_footer_actions.mustache`](../../../templates/central/frameworks_footer_actions.mustache), [`frameworks_export.mustache`](../../../templates/central/frameworks_export.mustache), [`showhidden_toggle.mustache`](../../../templates/central/showhidden_toggle.mustache)
- **PHP:** [`classes/output/dynamictabs/frameworks.php`](../../../classes/output/dynamictabs/frameworks.php)
- **AMD:** [`amd/src/central/frameworks.js`](../../../amd/src/central/frameworks.js) (527 linhas), [`central/tabs.js`](../../../amd/src/central/tabs.js), [`central/action_footer.js`](../../../amd/src/central/action_footer.js)
- **To-be no DS:** sem componente dedicado — o card já **convergiu** (`fwcard` shipa nome + idnumber +
  descrição + pill de contagem, `frameworks_row.mustache:44-56`); o que falta é o loading do `reloadPane`.

> **Nota de nome (verificada).** O `FWK` é a **primeira** aba e nasce ativa (`central.php:104-105`), e
> o rótulo dela é `central_frameworks_tab` = **"Estruturas"** (`central.php:99`; pt-BR `lang/pt_br:157`).
> Quem se chama "Estrutura" no código (`dynamictabs/structure.php`, mapa `EST`) é a **segunda** aba, cujo
> rótulo é `managecompetencies_structure` = **"Competências"** (`central.php:100`; `lang/pt_br:439`).
> Os nomes internos e os rótulos visíveis estão **invertidos** — `bar-contextbar.md` já usa os rótulos
> visíveis ("toolbar da aba Estruturas"), e este mapa segue a mesma convenção.

> **Resync 2026-07-14:** a versão anterior deste mapa congelou em `159a800` (2026-06-29), junto com a do
> `EST`. Desde então `frameworks.mustache` foi de **84 para 107** linhas e `frameworks_row.mustache` de
> **65 para 57** — e **as 12 de 12** refs do mapa antigo resolviam para linhas **não relacionadas**:
> quatro caíam dentro do *Example context* do docblock (`FWK-EMPTY-CAT` apontava `:48`, hoje
> `"showhiddentoggle": {`), e `FWK-ROW-DEL` apontava `:60` num arquivo que **termina em 57**. Todas foram
> re-derivadas, não corrigidas pontualmente.
>
> O defeito maior, porém, era **ausência**: não estavam mapeados o import, o export, o contador de
> ocultas, o atalho de escalas, a raiz e seus `data-*`, o card em si (descrição, pill de contagem,
> botão de seleção) — e **todo** o comportamento de `frameworks.js` (o mapa tinha **zero** refs de JS).
> As quatro ações de linha estavam desenhadas como badges **dentro** da linha; são botões de
> **sticky-footer** desde `a9dcdb0`.

## Raiz e dados de página

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-ROOT` | `[sem rótulo]` | região/raiz | `frameworks.mustache:61-63` | `data-region="frameworks"` | carrega `contexttype`, `categoryid`, `contextid`, `canmanage`, `canscalespage`; `init` a resolve por seletor (`frameworks.js:485`) e guarda em `activeRegion`/`activePane` (`:490-491`) |
| `FWK-CANSCALES` | `[sem rótulo]` | flag | `frameworks.mustache:63` | `data-canscalespage` | `has_capability('moodle/course:managescales', system)` (`dynamictabs/frameworks.php:113`); **único** consumidor é `injectScalesLink` (`frameworks.js:144`) → `FWK-SCALES-LINK` |

## Cabeçalho e toolbar

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-EMPTY-CAT` | "Escolha primeiro a categoria de curso…" | empty-state | `frameworks.mustache:65-67` | str `managecompetencies_selectcategory_help` | bloqueia a aba inteira (o `{{^needscategoryselection}}` de `:69` embrulha todo o resto) |
| `FWK-SHOWHIDDEN` | Mostrar estruturas ocultas | switch | `showhidden_toggle.mustache:44-45`, chamado em `frameworks.mustache:70-72` | `data-action="{{action}}"` → `toggle-hidden` | **partial compartilhado** com `EST`/`PLN`: o `data-action` é **variável** no template e o valor literal vem de `dynamictabs/frameworks.php:135` (contexto em `:132-137`; **nulo quando não há nenhuma oculta** → não renderiza). Estado em preferência `frameworksshowhidden` (`frameworks.js:523`) **e** em `pane.dataset.showhidden` (`:522`), depois `reloadPane` (`:524`) |
| `FWK-TOOLBAR` | `[sem rótulo]` | contêiner | `frameworks.mustache:74` | `.local-dimensions-central-fwtoolbar` | `space-between`; contador à esquerda, ações à direita |
| `FWK-COUNT` | "Estruturas listadas: N" | contador | `frameworks.mustache:75-76` | `frameworkcount` | str `central_frameworks_listed`; conta as linhas **exibidas** (`count($rows)`, `dynamictabs/frameworks.php:125`) — é o **2º dos três contadores do hub** (ver `bar-contextbar.md`) |
| `FWK-HIDDENCOUNT` | "· N ocultas" | sufixo | `frameworks.mustache:76` | `hasexcluded` / `excludedcount` | str `central_frameworks_hiddencount`; `excludedcount = showhidden ? 0 : hiddencount` (`dynamictabs/frameworks.php:110`) — some quando o toggle está ligado, porque aí nada está escondido. Existe para manter `FWK-COUNT` **honesto** (comentário em `:108-109`) |
| `FWK-ACTIONS` | `[sem rótulo]` | grupo | `frameworks.mustache:79` | `.local-dimensions-central-fwactions` | todo o grupo é gated por `{{#canmanage}}` (`:78-92`) |
| `FWK-NEW` | Nova estrutura | botão | `frameworks.mustache:80-82` | `data-action="new"` | `fa-plus`; primário (`.local-dimensions-central-plans-new`); `createFramework` → modal com `contextid` da região (`frameworks.js:203-204`) |
| `FWK-IMPORT` | Importar | botão | `frameworks.mustache:83-85` | `data-action="import"` | `fa-upload`; outline; `openImportForm` (`frameworks.js:261-278`) → dynamic form com CSV. **Não estava no mapa** |
| `FWK-EXPORT` | Exportar | botão | `frameworks.mustache:86-90` | `data-action="export"` | `fa-download`; outline; **duplo gate** — `{{#canexport}}` (`:86`) aninhado dentro do `{{#canmanage}}`, e `canexport = canmanage && !empty($rows)` (`dynamictabs/frameworks.php:130`), então some quando não há nenhuma estrutura para exportar. **Não estava no mapa** |
| `FWK-LIST` | `[sem rótulo]` | contêiner | `frameworks.mustache:96` | `data-region="framework-rows"` | só com `hasframeworks`; recebe os `FWK-ROW` |
| `FWK-EMPTY` | "Nenhuma estrutura neste contexto." | empty-state | `frameworks.mustache:103-105` | str `central_frameworks_none` | `alert alert-info role="status"` |

## Card de estrutura (`frameworks_row`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-ROW` | `[sem rótulo]` | card (wrapper) | `frameworks_row.mustache:39-43` | `data-framework="{id}"` | carrega `frameworkid`, `name`, `count`, `visible`, `deletable`; a classe `is-hidden` (`:39`) dá `opacity: 0.6` (`styles.css:3700-3702`). O seletor de linha do JS é `[data-framework]` (`frameworks.js:44`) |
| `FWK-ROW-SELECT` | `[sem rótulo]` | botão | `frameworks_row.mustache:44` | `data-action="select-framework"` | **o card inteiro é um botão**: `selectFramework` marca `.active` e publica o rodapé (`frameworks.js:460-477`). O `data-action` é **decorativo** — o handler casa por `closest('[data-framework]')` (`:513-516`), não pelo action |
| `FWK-ROW-NAME` | nome | texto | `frameworks_row.mustache:47` | `shortname` | 17px/700 (`styles.css:3751-3756`) |
| `FWK-ROW-ID` | idnumber | chip mono | `frameworks_row.mustache:48` | `idnumber` | só se `idnumber`. **Não estava no mapa** |
| `FWK-ROW-HIDDEN` | "Oculto" | badge | `frameworks_row.mustache:49` | `^visible` | `fa-eye-slash` + str `hidden, tool_lp` |
| `FWK-ROW-DESC` | `[sem rótulo]` | descrição | `frameworks_row.mustache:51` | `description` | só se `description`; uma linha com reticências e o texto cheio no `title` (`styles.css:3781-3789`). Servidor achata para texto puro e corta em 300 (`helper.php:2225-2234`). **Não estava no mapa** |
| `FWK-ROW-COUNT` | "N competências" | pill | `frameworks_row.mustache:53-55` | `competencycount` | str `central_frameworks_competencieslabel`; pill accent à direita (`styles.css:3791-3809`) |

## Ações da estrutura — **sticky-footer da página**, não o card

Renderizadas por `selectFramework` via `Templates.renderForPromise('…/frameworks_footer_actions')` e
entregues a `ActionFooter.show(html, dispatchFrameworksAction)` (`frameworks.js:468-474`). Só com
`canmanage` (senão `ActionFooter.hide()`, `:464-467`). Os botões **não carregam dataset**: agem sobre o
`activeFrameworkRow` de módulo (`frameworks.js:431-449`), por isso funcionam de fora da região da aba.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-ROW-EDIT` | Editar detalhes | botão rodapé | `frameworks_footer_actions.mustache:41-44` | `data-action="edit"` | `fa-pencil`; str `central_plans_editdetails` (**compartilhada com o `PLN`**, não tem string própria); abre o form com `MOD.SCALE` embutido (`framework_dynamic_form.php:192-194`); salvar → toast + `reloadPane` (`frameworks.js:179-182`) |
| `FWK-ROW-VIS` | Alternar visibilidade | botão rodapé | `frameworks_footer_actions.mustache:45-48` | `data-action="visibility"` | ícone **espelha o estado do card selecionado** — `fa-eye`/`fa-eye-slash` decidido no render do rodapé pelo `visible` que `selectFramework` passa (`:46`, `frameworks.js:470`); WS `set_framework_visibility` → `reloadPane` (`:371-377`) |
| `FWK-ROW-DUP` | Duplicar | botão rodapé | `frameworks_footer_actions.mustache:49-52` | `data-action="duplicate"` | `fa-copy`; WS **do core** `core_competency_duplicate_competency_framework` → `reloadPane` (`frameworks.js:386-388`) |
| `FWK-ROW-DEL` | Excluir | botão rodapé | `frameworks_footer_actions.mustache:53-56` | `data-action="delete"` | `fa-trash`; str `delete` do core. **Sem variante de cor** — o padrão cru do sticky-footer do core não usa `btn-outline-danger`. Gate em **dois** pontos, ver regras abaixo |

## Modal de import — a **forma de referência** do loading

O banner que `showImportLoading` injeta é o **único tratamento de loading em corpo** que o plugin shipa:
`alert alert-info` + `spinner-border spinner-border-sm`, prependado no corpo do modal
(`frameworks.js:231-237`).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-IMP-BANNER` | "Importando…" | banner (JS) | `frameworks.js:231-237` | `data-region="import-loading"` | str `central_frameworks_importing`; nasce no `SUBMIT_BUTTON_PRESSED` e é removido nos dois erros de validação (`:267-269`), porque o form volta e o banner ficaria girando para sempre. Guarda contra duplicata em `:227` |
| `FWK-IMP-DONE` | "Importação concluída: N competências processadas." | toast | `frameworks.js:270-276` | str `central_frameworks_import_done` | conta vem do `event.detail.competencycount` do form (`:271`); `reloadPane` + toast de sucesso |

> **Ressalva de ARIA (medida).** O banner e o loader do export **não** são dois tratamentos
> independentes: os dois chamam o mesmo `makeSpinner()` (`frameworks.js:211-217`). E esse helper põe
> `role="status"` (`:214`) **e** `aria-hidden="true"` (`:215`) **no mesmo elemento** — o `aria-hidden`
> anula o `role`, então o conjunto não anuncia nada. A forma **visual** é a referência; a forma **ARIA**
> correta está em `states.html` (nome no container, spinner `aria-hidden`).

## Modal de export

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-EXP-LABEL` | "Estrutura a exportar" | label | `frameworks_export.mustache:32-34` | str `central_frameworks_export_pick` | `for="local-dimensions-export-select"` |
| `FWK-EXP-SELECT` | `[sem rótulo]` | select | `frameworks_export.mustache:35` | `data-region="export-select"` | `form-select` (nunca `custom-select`); nasce **vazio** e é populado client-side a partir dos `FWK-ROW` da aba, no `ModalEvents.shown` (`frameworks.js:347-356`) — o modal só conhece o que a aba já listou |
| `FWK-EXP-DOWNLOAD` | Exportar | botão | `frameworks_export.mustache:38-40` | `data-action="download"` | `btn-primary`; WS `local_dimensions_export_framework` → `Blob` + `<a download>` (`frameworks.js:287-297`) |
| `FWK-EXP-LOADER` | `[sem rótulo]` | slot de spinner | `frameworks_export.mustache:41` | `data-region="export-loader"` | `hidden` por padrão; `downloadFramework` desabilita o botão, mostra o spinner e **restaura os dois num `finally`** (`frameworks.js:326-330`) — a disciplina que o `states.html` cobra |

O modal hospeda **região de toast própria** (`addToastRegion(modal.getBody()[0])`, `frameworks.js:348`):
o toast disparado de dentro dele renderiza **acima** do diálogo (padrão da casa; ver `CLAUDE.md`).

## Atalho de escalas no cabeçalho do form

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FWK-SCALES-LINK` | "Abrir página de escalas" | link (JS) | `frameworks.js:151-162` | str `central_frameworks_openscales` | injetado no `.modal-header` do form, **à esquerda do `.btn-close`** (`:162`), no evento `LOADED` (`:183`); `target="_blank"` + `rel="noopener noreferrer"`. Gated por `FWK-CANSCALES` (`:144`). Vem de `a2112fe`. **Não estava no mapa** |
| `FWK-SCALES-MODALCLASS` | `[sem rótulo]` | classe | `frameworks.js:142` | `.local-dimensions-headerlink-modal` | aplicada **mesmo sem o link** (`:140-143`): padroniza também o chip do botão de fechar, que senão só estiliza modais cujo corpo carrega classes do plugin |

## Regras de negócio (verificadas no código)

- **A exclusão tem dois gates, e o segundo não confia no primeiro.** `deleteFramework` recusa cedo se
  `data-deletable !== '1'` (`frameworks.js:399-402`), confirma com `deleteCancelPromise` (`:409`) e
  **ainda** trata `success === false` do WS do core como bloqueio (`:417-420`). O dataset é um retrato
  do render; entre render e clique a estrutura pode ter entrado em uso. `deletable` sai de
  `competency::can_all_be_deleted()` (`helper.php:2237`).
- **`hashiddenframeworks` é chave morta nesta aba.** `dynamictabs/frameworks.php:123` a exporta, mas
  `frameworks.mustache` **não a usa** em lugar nenhum — o gate do toggle virou "`showhiddentoggle` é
  nulo ou não" (`:132`). Os únicos consumidores do nome são o `structure.mustache` (`:36`, `:55`), que
  tem contexto próprio.
- **A delegação do scale-config é global e de uma vez por página** (`setupScaleConfigDelegation`,
  `frameworks.js:95-123`), em **fase de captura** (`:107`): o form nasce dentro de um `modalform` cujo
  ciclo de vida não roda o `init` da aba, então o clique é ouvido no documento. O select é resolvido
  **por `name`, não por `id`** (`:65`): `core_form\dynamic_form` sufixa os ids com um aleatório
  (`id_scaleid_c5fLCIS8ExDrcVf`), então `#id_scaleid` nunca casaria (comentário em `:63-64`).
- **Trocar a escala limpa a config de proficiência — exceto quando o select está `readonly`**
  (`frameworks.js:108-113`): framework já avaliado tem a escala congelada, e limpar ali apagaria uma
  config que o servidor vai repinar de qualquer jeito.
- **O rodapé é defendido contra corrida em três pontos** (mesmo padrão do `EST`): `selectFramework` só
  mostra se o card ainda está `.active` **e** a aba ainda é a ativa (`:472`); `dispatchFrameworksAction`
  ignora cliques se a aba saiu de foco (`:434-436`); e `init` só limpa o rodapé se a aba for a ativa
  (`:495-497`), porque as abas dinâmicas re-executam `init` de uma carga assíncrona fora de ordem.
- **Toda ação recarrega o pane.** As **6** chamadas de `reloadPane` desta aba (`frameworks.js:181`,
  `:272`, `:376`, `:388`, `:421`, `:524`) cobrem salvar, importar, alternar visibilidade, duplicar,
  excluir e alternar o toggle. **Não há caminho in-place aqui** — ao contrário do `EST`, que tem quatro.
  É por isso que o IMP-03 rende mais nesta aba: todo clique de ação reconstrói a lista sem aviso.
- **`showhidden` tem duas fontes, e o arg ganha** (`dynamictabs/frameworks.php:88-90`): o arg do pane
  (escrito no toggle) vence; sem ele, cai na preferência `frameworksshowhidden`. Assim a escolha
  sobrevive a um reload de página inteira.
- **i18n · "1 ocultas".** `central_frameworks_hiddencount` é `'{$a} ocultas'` em pt-BR
  (`lang/pt_br:132`) e `'{$a} hidden'` em inglês (`lang/en:132`) — sem forma singular. Com
  `excludedcount = 1` o pt-BR renderiza "**1 ocultas**". O inglês não sofre porque "hidden" é invariável.
- **a11y · dois textos shipados reprovam no WCAG AA (medido).** `FWK-ROW-DESC` e `FWK-HIDDENCOUNT`
  usam ambos `#8b939b` sobre o `#fff` da card (`styles.css:3785` e `:3652`) — **3,11:1**, abaixo do
  mínimo de 4,5:1 para texto normal, e os dois carregam **conteúdo real** (a descrição da estrutura e a
  contagem de ocultas), não decoração. Para comparação, no mesmo arquivo o `FWK-COUNT` usa `#495057`
  (**8,18:1**, `:3642`) e o `FWK-NEW` usa `#fff` sobre `#0f6cbf` (**5,36:1**, `:4125-4126`) — ou seja, é
  desvio pontual destes dois, não o padrão da aba. O painel as-is da tela reproduz o desvio de
  propósito (**é o que está no ar**); a correção pertence à Camada 3.

## to-be

### IMP-03 (`mtube: carregando`) — **o alvo é `reloadPane`, não a troca de aba**

> **Correção medida** (idêntica à do `est-structure.md`, re-verificada aqui de forma independente).
> O plano descreve o IMP-03 como "loading na troca de aba". **A troca de aba já tem loading, e vem do
> core**: `dynamic_tabs.js:92-97` ouve `shown.bs.tab` → `loadTab`, e `loadTab` abre com
> `addIconToContainer(tab)` (`:153`). Antes disso, `show.bs.tab` **esvazia** o pane anterior (`:88`).
>
> A lacuna é o **`reloadPane` do plugin** (`tabs.js:51-66`), que refaz o caminho do `loadTab` **sem** o
> `addIconToContainer` — e é ele que roda nas **23** chamadas em 5 módulos (`structure` 9,
> `frameworks` 6, `plans` 6, `competency_browser` 1, `context` 1). Uma linha no `reloadPane` e os 23
> sítios ganham juntos.

Esta aba é o **melhor caso** da mudança: as 6 chamadas dela são 100% do CRUD da aba e **nenhuma** é
in-place, então não há a ressalva do `refreshNode` que restringe o `EST`. Regra da casa continua valendo:
**pane recarregado → spinner; linha trocada → flash.**

Forma de referência: o `alert alert-info` + `spinner-border spinner-border-sm` do `FWK-IMP-BANNER`
(`frameworks.js:231-237`) — **o hub já sabe fazer; só não faz onde importa**. Copiar a forma visual,
**não** o ARIA do `makeSpinner()` (ver a ressalva acima); marcar o pane com `aria-busy="true"` e o nome
no container, como em `states.html`.

### IMP-05 (`mtube: atualizar`) — controle de atualizar na contextbar

Ver `bar-contextbar.md` (a decisão e as verificações moram lá). Precisão que este mapa confirma de forma
independente: **não** é verdade que "nada expõe `reloadPane`" — ele tem **23 chamadas em 5 módulos**.
O que é verdade é que **nenhum controle de UI** o dispara; as 23 são refresh automático pós-ação.
Aqui na aba Estruturas são as 6 de `frameworks.js` (`:181`, `:272`, `:376`, `:388`, `:421`, `:524`).

### IMP-10 (`mtube: ícones nas abas`) — ícones + indicador nas abas

Ver `hierarchy-nav.html`. O que esta aba confirma: `central.php:114` passa `displayname` e o
`core/dynamic_tabs.mustache:53` faz **triple-stash** (`{{{displayname}}}`) — o ícone entra pelo rótulo,
**sem** mudar template do core. `central.php:57` já põe `local-dimensions-central-page` no `<body>`, que
é o escopo para o indicador `inset 0 -2px 0` não vazar para outros consumidores de `dynamic_tabs` do
site. Como o `FWK` é a aba que **nasce ativa** (`central.php:105`), é ela que exibe o indicador no
primeiro paint.
