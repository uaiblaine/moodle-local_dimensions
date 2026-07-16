# Mapa de Campos — `MOD.BROWSER` · Modal procurar em estruturas (as-is)

Modal aberto pelo botão **+ Adicionar competência** do **sticky-footer** da aba Planos. Escolhe uma
**estrutura** num `<select>` e, abaixo dele, monta o **mesmo browser de árvore** do modal
"Competências referenciadas" (partial compartilhado) — filtro com debounce, toggle de caminhos,
linhas de checkbox, "Carregar mais" e scroll infinito. As marcadas entram no template do plano e o
pane recarrega.

É o **único** ponto do plugin inteiro que chama `setSaveButtonText` — e é, por isso, o
**contra-exemplo** do IMP-06: aqui o rodapé do `ModalSaveCancel` é reusado **e o fechar-no-save do
core está certo no caminho normal**. Detalhado no fim deste mapa.

- **Mustache:** [`competency_browser.mustache`](../../../templates/central/competency_browser.mustache) (56, seletor + casca) · [`competency_tree_browser.mustache`](../../../templates/central/competency_tree_browser.mustache) (44, partial compartilhado com o `MOD.RELATED`) · gatilho em [`plans.mustache`](../../../templates/central/plans.mustache) (`:469-472`)
- **AMD:** [`competency_browser.js`](../../../amd/src/central/competency_browser.js) (148) — monta o browser via [`competency_tree_browser.js`](../../../amd/src/central/competency_tree_browser.js) (510, `initBrowser`/`applyMode`/`getCheckedIds`/`destroyBrowser`); usa `errors.js` (`notifyError`) e `tabs.js` (`reloadPane`, import em `:34`)
- **WS:** `core_competency_list_competency_frameworks` (`js:87-90`, popula o seletor), `local_dimensions_browse_competencies` (`db/services.php:109-116` → [`classes/external/browse_competencies.php`](../../../classes/external/browse_competencies.php), a árvore/busca), core `core_competency_add_competency_to_template` (`js:61`, escrever)
- **CSS:** **nenhum**. Um `grep -n 'local-dimensions-cb\|competency-browser' styles.css` devolve **uma** linha — `:5681`, e ela é escopada em `.local-dimensions-central-related`. Ver a nota da caixa solta abaixo.
- **Tela no DS:** [`screens/mod-browser.html`](../screens/mod-browser.html) (as-is ↔ to-be, com o storyboard da troca de estrutura e a demonstração marcar→habilitar, ambos dirigidos e medidos). **A tela ficou para trás do código em 2026-07-15**: ela ainda rotula a demonstração marcar→habilitar como *to-be* (`:144`) e o painel as-is ainda afirma que o rodapé "continua **habilitado**" (`:298-299`) — o `e14977c` desmentiu as duas coisas. Fora do escopo desta passagem, fica registrado.

**Abreviações usadas nas tabelas** (o mapa do `MOD.RELATED` usa as mesmas): `js:` =
`amd/src/central/competency_browser.js` · `tree.js:` = `amd/src/central/competency_tree_browser.js`
· `tree.mustache:` = `templates/central/competency_tree_browser.mustache`. Caminhos que começam com
`lib/` são do **core**, relativos a `public/`.

> **Resync 2026-07-15 — o mapa anterior descrevia um filtro client-side que não existe mais, um
> arquivo AMD que este modal não importa, e um rótulo que o plugin renomeou.** Medido, não estimado:
>
> - **6 refs; 4 quebradas (4/6).** Um `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+(-[0-9]+)?'`
>   no arquivo antigo devolve **exatamente 6**, todas em `competency_browser.mustache` — e o arquivo
>   tem **56 linhas**:
>   - `:40` (`MOD.BROWSER-FW-LABEL`) e `:43` (`MOD.BROWSER-FW`) **continuam certas**. Sobreviveram
>     porque o `44ac031` **tirou** linhas do topo do arquivo em vez de pôr: o filtro e o toggle
>     saíram para o partial e o que ficou acima do seletor não se mexeu.
>   - `:53-57` (dito `MOD.BROWSER-PATHTOGGLE`) é a pior quebra da série, e vale ler devagar: a
>     **primeira** linha do intervalo resolve hoje para o `{{#str}}` do
>     `central_browseframeworks_noframeworks` — ou seja, aponta para o conteúdo do
>     **`MOD.BROWSER-EMPTY`**, um controle **real, de outra ID** deste mesmo mapa. As três seguintes
>     são `</div>`, `{{/hasframeworks}}`, `</div>`, e a quinta (`:57`) cai **depois do fim do
>     arquivo**. Quem confere vê uma string plausível e segue.
>   - `:59` (`MOD.BROWSER-LIST`) e `:62` (`MOD.BROWSER-EMPTY`) apontam **depois do fim do arquivo**.
>     O mapa mandava o `EMPTY` para o vazio e o `PATHTOGGLE` para o `EMPTY` — as duas IDs trocadas
>     de lugar, e nenhuma no lugar certo.
>   - `:50` (`MOD.BROWSER-FILTER`) resolve para `{{/hasframeworks}}`.
> - **Zero refs de JS**, como em todos os mapas anteriores da série — e aqui isso apagava **10 dos
>   19** controles deste mapa: os que só existem numa linha de `.js` são o título, o salvar, a regra
>   da troca de estrutura, e a linha da árvore inteira (chevron, checkbox, caminho, trava, "Carregar
>   mais", vazio, sentinela). O próprio mapa antigo admitia a lacuna numa nota de rodapé (*"Injetado
>   via JS (detalhar ao inventariar `competency_browser.js`)"*) e propunha **uma** ID hipotética,
>   `MOD.BROWSER-ROW-*`. **O mapa antigo cobria 6 controles; este cobre 19** — contados com
>   `grep -oE '^\| \`MOD\.BROWSER-[A-Z-]+\`' | sort -u | wc -l`. (Eram 20 até o `29ffb41`
>   devolver o gatilho ao `pln-plans.md` como `PLN-BROWSE`; a contagem tinha ficado para trás.)
> - **`competency_datasource.js` não é deste modal.** O bullet **AMD** do mapa antigo o linkava; um
>   `grep -rn 'competency_datasource' amd/src/` devolve **dois** hits — a própria declaração
>   `@module` (`:19`) e **`plans.js:46`**, que o usa como `DATASOURCE` do autocomplete **da aba
>   Planos**. O `competency_browser.js` não o importa: os 8 `import` dele (`:27-34`) são `core/ajax`,
>   `core/modal_save_cancel`, `core/modal_events`, `errors`, `core/templates`, `core/str`,
>   `competency_tree_browser` e `tabs`.
> - **O rótulo envelheceu duas vezes.** O mapa e a tela diziam "framework"; o `f817430`
>   ("reorder + rebrand tabs", 2026-07-07) reescreveu as strings **sem** renomear as chaves:
>   `central_browseframeworks` = "Procurar em **estruturas**", `central_browseframeworks_framework`
>   = "**Estrutura**". As chaves ainda dizem `framework` — o usuário lê `estrutura`. (O
>   `README.md:40` deste kit ainda anuncia "Procurar em frameworks"; fora do escopo desta tarefa,
>   fica registrado.)
> - **O placeholder do filtro nunca foi "Buscar competência…".** É
>   `central_browseframeworks_filter` = "**Filtrar competências**", e serve de `placeholder` **e** de
>   `aria-label` (`tree.mustache:33-34`).
> - **O to-be do mapa antigo já está shipado — o as-is o ultrapassou.** O mapa dizia que o as-is era
>   *"filtro client-side sobre lista carregada"* e que o `paginated-picker.html` propunha
>   *"busca server-side paginada"* como divergência. Hoje o as-is **é** busca server-side paginada:
>   `local_dimensions_browse_competencies` com `limitfrom`/`limitnum`
>   (`browse_competencies.php:60-61`), página de **25** (`PAGE_SIZE`, `tree.js:46`), debounce de
>   **250 ms** e mínimo de **2** caracteres (`tree.js:379`, `:47`). O shipado usa **scroll infinito**
>   (`IntersectionObserver`, `tree.js:492-497`); o `paginated-picker.html` chegou a esboçar números de
>   página como divergência de **forma**, mas isso caiu quando ele virou o aviso de overflow do
>   form-autocomplete (outro controle, ver o card). A tela foi redesenhada em cima disso.

## Gatilho (na aba Planos, fora do modal)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-BROWSE` ↗ | Adicionar competência | botão (gatilho) — **ID de `pln-plans.md`, não deste mapa**: o gatilho pertence à superfície onde mora, e este mapa só o referencia (mesma convenção de `MOD.DELPLANS ← PLN-DELETE`) | `plans.mustache:469-472` | `data-action="browse-frameworks"` · `fa fa-plus` | str **`central_addcompetency`** — **não** `central_browseframeworks` (essa é o título do modal). Mora no holder `data-region="plans-footer-actions"` (`:462`), que nasce `hidden` e é movido para o `#sticky-footer` da página pelo `plans.js` (comentário em `:458-461`); só sai sob `{{#canmanage}}` (`:457`). `plans.js:723` chama `showCompetencyBrowser(pane, region)` (import em `:35`) |

## Casca do modal

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.BROWSER-TITLE` | Procurar em estruturas | título | `competency_browser.js:103` (str), `:106` (`ModalSaveCancel.create`) | str `central_browseframeworks` | `setRemoveOnClose(true)` em `:108`. É `ModalSaveCancel`, **não** `Modal` — o oposto do `MOD.RELATED` (`related_competencies.js:248`) |
| `MOD.BROWSER-ROOT` | `[sem rótulo]` | região/raiz | `competency_browser.mustache:37` | `data-region="competency-browser"` · `.local-dimensions-competency-browser` | **a classe não tem estilo nenhum**: um `grep -n 'local-dimensions-competency-browser' styles.css` não devolve nada. É gancho morto — sobrou de quando o modal era dono da árvore |
| `MOD.BROWSER-SAVE` | Adicionar selecionadas | botão primário (rodapé) | `competency_browser.js:107` (`setSaveButtonText`), `:102-105` (str) | str `central_browseframeworks_add` · `data-action="save"` (core) | **a única chamada `setSaveButtonText` do plugin** — `grep -rn 'setSaveButtonText' amd/src/` devolve 1 linha. **Nasce desabilitado** (`:110`) e segue a seleção desde 2026-07-15: `updateAddButton` (`:48-50`) o reabilita quando há pelo menos uma marcada. Ver a seção do save vazio abaixo |
| `MOD.BROWSER-CANCEL` | Cancelar | botão (rodapé) | `lib/templates/modal_save_cancel.mustache:43` | `data-action="cancel"` · str core `cancel` | vem de graça com o `ModalSaveCancel`; o plugin não o toca |
| `MOD.BROWSER-X` | Fechar | chip de fechar | core (`lib/templates/modal.mustache`) | — | ganha o restyle azul de `1.75rem` do hub (`styles.css:3557-3562`): o root não tem `.local-dimensions-related-modal` e o corpo casa `[class*='local-dimensions-']` — os dois lados do seletor. O casamento **não depende** da classe morta do `MOD.BROWSER-ROOT`: `.local-dimensions-cb-scroll` e `.local-dimensions-competency-browser-list` (`tree.mustache:42-43`) já bastariam. Mesmo caso do `MOD.RELATED`, e pelo mesmo seletor |

## Corpo — o seletor de estrutura e o vazio

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.BROWSER-FW-LABEL` | Estrutura | rótulo | `competency_browser.mustache:40-42` | str `central_browseframeworks_framework` | `<label>` de verdade, com `for="local-dimensions-cb-framework"` — o alvo é um campo, ao contrário do `MOD.RELATED-ADDLABEL`, que é um `<div>` porque aponta para uma árvore |
| `MOD.BROWSER-FW` | Estrutura (select) | select | `competency_browser.mustache:43-47` | `data-region="framework"` · `class="form-select"` | `form-select`, nunca `custom-select` (as classes BS5 são pontecadas no 4.5). Populado por `core_competency_list_competency_frameworks` (`js:87-90`) com `sort: 'shortname'`, `includes: 'parents'` (estruturas dos contextos-pai entram) e `onlyvisible: true`. A **primeira** vem marcada (`selected: index === 0`, `js:97`) e semeia `state.frameworkid` (`js:114`). **id fixo**, sem `{{uniqid}}` — só não colide porque o modal é `setRemoveOnClose(true)` e nunca há dois |
| `MOD.BROWSER-FWSWITCH` | `[sem rótulo]` | regra | `competency_browser.js:128-135` | listener de `change` | **trocar de estrutura limpa a seleção** (`state.checked.clear()`, `:132`) e recarrega a árvore do zero (`applyMode(state, 'tree', '')`, `:134`). O motivo está no próprio código (`:130-131`): manter as marcas atravessando a troca **adicionaria** competências de uma estrutura que saiu da tela. Desde 2026-07-15 a troca também recomputa o rodapé (`updateAddButton`, `:133`) — sem essa linha o "Adicionar selecionadas" ficaria habilitado sobre uma seleção recém-esvaziada. Continua sendo o **único `.clear()`** do `state.checked` neste modal: o que existe fora dele é **semeadura**, não limpeza — o literal do `state` cria o Set (`:115`) e o `initBrowser` o recria uma vez na abertura (`tree.js:462`) —, o clique de linha só adiciona/remove **um** id por vez (`tree.js:401-403`), e o `getCheckedIds` não consome nem zera |
| `MOD.BROWSER-EMPTY` | Nenhuma estrutura de competências disponível. | estado vazio | `competency_browser.mustache:51-55` (o `alert` em `:52-54`) | `.alert.alert-info` · `role="status"` | str `central_browseframeworks_noframeworks`. **Substitui o corpo inteiro** (`{{^hasframeworks}}`): sem seletor, sem árvore. E o JS acompanha — todo o bloco de fiação está sob `if (frameworks.length)` (`js:125-142`), então nem o listener nem o `initBrowser` rodam. **Mas o rodapé fica** — **desabilitado desde 2026-07-15**: o `setButtonDisabled('save', true)` de `:110` roda **antes** do `if`, e sem estruturas nada o reabilita (o `updateAddButton` só é ligado dentro do `if`). Até então "Adicionar selecionadas" ficava lá **habilitado** sobre um corpo sem nada para marcar — e clicá-lo **estourava um `TypeError`**: quem semeava o `state.checked` era o `initBrowser`, que o `if` pula, mas o save era ligado incondicionalmente, então o `getCheckedIds` chegava em `Array.from(undefined)`. O literal do `state` agora semeia o Set (`:115`), o que **elimina** a falha em vez de deixá-la inalcançável atrás do botão desabilitado |

## A árvore (partial compartilhado com o `MOD.RELATED`)

O `competency_browser.mustache:49` inclui o partial inteiro, **abaixo** do seletor; quem o dirige é
`competency_tree_browser.js`, com o `state` montado em `js:113-123`.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.BROWSER-FILTER` | Filtrar competências | campo de busca | `competency_tree_browser.mustache:31-35` | `data-region="filter"` · `placeholder` **e** `aria-label` = mesmo str | str `central_browseframeworks_filter`. Debounce de **250 ms** (`tree.js:379`), mínimo de **2** caracteres (`SEARCH_MIN`, `:47`); abaixo disso volta para o modo árvore (`:383-384`). A busca é **server-side** e **dentro da estrutura escolhida** (`frameworkid` vai no args, `:265`) — não atravessa estruturas |
| `MOD.BROWSER-PATHS` | Mostrar caminhos | switch | `competency_tree_browser.mustache:36-41` | `data-region="path-toggle"` · id com `{{uniqid}}` | str `central_browseframeworks_showpaths`. Em modo **busca** é forçado `checked` **e** `disabled` (`tree.js:327-328`), porque `pathsVisible` já é sempre verdadeiro ali (`:72`). O `{{uniqid}}` vem do **JS**, não do helper PHP: `Templates.renderForPromise` passa por `lib/amd/src/local/templates/renderer.js:444`, que faz `context.uniqid = (Renderer.uniqInstances++)` — um inteiro novo por render. É o que deixa os dois modais hospedarem o mesmo partial |
| `MOD.BROWSER-LIST` | `[sem rótulo]` | contêiner-JS | `competency_tree_browser.mustache:42-44` | `data-region="competency-list"` · `.local-dimensions-competency-browser-list` dentro de `.local-dimensions-cb-scroll` | **a caixa aqui é solta**: o `max-height:40vh` + `overflow-y:auto` do `styles.css:5681-5684` está escopado em `.local-dimensions-central-related`, e o comentário do core-do-plugin diz isso com todas as letras (`:5679`: *"the Browse frameworks modal leaves the box uncapped"*). Quem rola, aqui, é o `.modal-body` |
| `MOD.BROWSER-ROW` | {nome} | linha (checkbox) | `competency_tree_browser.js:82-156` (`makeNode`; o checkbox em `:111-123`) | `input.form-check-input` + nome + caminho | **sem `for`**: a linha inteira é o alvo de clique (`:125-126`, `onListClick` `:415-441`), com seleção por intervalo no Shift (`handleShiftSelect`, `:352-366`). A seleção é **persistente** (`state.checked`) e sobrevive a re-render (`:120-122`), então `getCheckedIds` devolve **também** o que o filtro atual não mostra. Indenta **20px** por nível (`INDENT_STEP`, `:48`, aplicado em `:94`) |
| `MOD.BROWSER-ROW-TOGGLE` | Ver mais: {nome} | chevron (por linha) | `competency_tree_browser.js:96-109` | `data-action="toggle"` · `aria-expanded` · `fa fa-chevron-right` | `aria-label` = str **`show_more`** ("Ver mais") + `: {nome}` (`:106`), semeado em `initBrowser` (`:463`). Sem filhos, o botão continua no DOM e leva `.invisible` (`:108`) — mantém o alinhamento das colunas. Filhos carregam **na primeira expansão** (`toggleNode` `:229-250` → `loadChildren` `:201-220`), também de 25 em 25 |
| `MOD.BROWSER-ROW-LOCK` | {nome} (Já neste plano) | linha travada | `competency_tree_browser.js:117-119`, `:130` | `checked` + `disabled` · sufixo no nome | o `state.excluded` sai de `region.dataset.excludeids` (`js:80`, `:116`) — os ids já no template, publicados pelo `plans.mustache:131` (`data-excludeids`, documentado em `:54`). O sufixo vem de `state.excludedsuffix` (`js:117`) → str `central_browseframeworks_alreadyadded` ("Já neste plano"); aqui é **constante**, enquanto o `MOD.RELATED` passa uma função que escolhe entre dois rótulos. `getCheckedIds` filtra as excluídas de novo na saída (`tree.js:451`) |
| `MOD.BROWSER-ROW-PATH` | `[sem rótulo]` | caminho de ancestrais | `competency_tree_browser.js:132-137` | `.local-dimensions-cb-path.text-muted.small` · `hidden` conforme `pathsVisible` | vem do WS (`browse_competencies.php:136` → `helper::competency_breadcrumbs`), **vazio para raízes** (`execute_returns`, `:176`). Alternado em massa por `applyPathVisibility` (`:337-342`) |
| `MOD.BROWSER-MORE` | Carregar mais | botão | `competency_tree_browser.js:180-192` | `data-role="load-more"` | str `central_browseframeworks_loadmore` (`js:83`). Aparece **só nos filhos** (`loadChildren` `:217-219`): o topo da lista não o usa — lá quem pagina é a sentinela. Some ao ser clicado (`:188`) |
| `MOD.BROWSER-TREE-EMPTY` | Nenhuma competência nesta estrutura. | estado vazio | `competency_tree_browser.js:306-311` (str em `competency_browser.js:84`) | `.text-muted.small` | str `central_browseframeworks_empty`. É o vazio **da árvore** (estrutura sem competências, ou busca sem acerto) — não confundir com o `MOD.BROWSER-EMPTY`, que é o vazio **de estruturas** e vem do Mustache |
| `MOD.BROWSER-SENTINEL` | `[sem rótulo]` | scroll infinito | `competency_tree_browser.js:489-497` | `<div>` vazio + `IntersectionObserver` | inserido **depois** da lista mas **dentro** da caixa de rolagem (`insertAdjacentElement('afterend')`, `:490`), com o motivo no comentário `:486-488`. Desconectado no `ModalEvents.hidden` (`js:145` → `destroyBrowser`, `tree.js:506-510`) |

## O add — e o que ele não faz

`addSelected` (`js:59-70`) dispara **N chamadas em paralelo**, uma
`core_competency_add_competency_to_template` por id marcado (`:60-63`), com o `templateid` lido do
**`pane.dataset`** (`:62`) — não do `region`, ao contrário do `contextid` (`:89`) e do `excludeids`
(`:80`). No sucesso, `reloadPane(state.pane)` (`:69`) redesenha a aba Planos inteira; no erro,
`notifyError`.

Duas ausências e uma guarda, todas verificadas:

- **Sem toast, sem `flash`.** O feedback é o pane recarregado com a competência na lista. Faz sentido
  aqui e não faria no `MOD.RELATED`: este modal **fecha**, então não há "lugar" para o qual o usuário
  volte. (Medido contra controle: `grep -c 'addToast\|flash(' competency_browser.js` devolve **0**, o
  mesmo grep no `related_competencies.js` devolve **5**.)
- **Sem desfazer parcial.** Como no `MOD.RELATED`, uma chamada que falha no meio do lote não desfaz
  as anteriores. Diferente do `MOD.RELATED`, aqui não há `finally` re-sincronizando — o `.catch`
  (`:69`) só notifica, e o pane **não** recarrega. O modal já fechou. (Controle: `grep -c finally`
  devolve **0** aqui e **1** no `related_competencies.js`.)
- **A guarda de seleção vazia passou a valer em 2026-07-15.** O `if (!calls.length)` (`:64-68`)
  chama `event.preventDefault()` (`:66`) **antes** do `return`, com o motivo no comentário de `:65`.
  Até então o `return` saía calado **dentro** do handler do `ModalEvents.save` — e era aí que a
  mecânica do core mordia. Ver a seção seguinte.

## Por que este é o contra-exemplo do IMP-06

`ModalSaveCancel.registerEventListeners()` (`lib/amd/src/modal_save_cancel.js:57`) chama
`registerCloseOnSave()`. O handler do core (`lib/amd/src/modal.js:1100-1116`) dispara o
`ModalEvents.save` e, **se ninguém chamou `preventDefault()`**, fecha o diálogo (`:1106-1112` —
`destroy()` quando `removeOnClose`, senão `hide()`).

Este modal liga o save em `js:144` com um `preventDefault` **condicional**: um `grep -n
'preventDefault' amd/src/central/competency_browser.js` devolve **uma** linha — `:66`, dentro da
guarda de seleção vazia. No caminho normal (pelo menos uma marcada) ninguém previne nada e ele
**fecha**, e está **certo**: é picker de uma tacada, o resultado aparece no pane atrás.

**É exatamente por isso que o IMP-06 não é copiar este vizinho** — e desde 2026-07-15 o motivo mudou
de lugar, então vale reler devagar. O `MOD.RELATED` **gerencia**: escreve a cada clique, dá toast,
pisca a linha nova, alterna o estado vazio e o usuário **fica**. Migrá-lo para `ModalSaveCancel` para
ganhar o rodapé exige um `preventDefault()` **incondicional** no `ModalEvents.save`. O argumento
**não** é mais "este modal não tem `preventDefault`" — ele tem. É que **condicional e incondicional
são opostos em intenção, não vizinhos em grau**: o `preventDefault` daqui existe para **não** fechar
num no-op e **preserva** o fechar-no-save do caminho normal, que é o comportamento desejado; o do
`MOD.RELATED` teria de vetar **todo** save — ou seja, pegaria o rodapé do `ModalSaveCancel` e
desligaria a única coisa que o `ModalSaveCancel` acrescenta ao `Modal` que ele já usa. **A chamada se
reusa; a fiação do save, não.**

> **A ponta solta que a comparação revelava · CORRIGIDA em 2026-07-15.** O `if (!calls.length)
> return` rodava **dentro** do handler, e o `return` não impedia o fechar do core: **clicar
> "Adicionar selecionadas" sem nada marcado fechava o modal, não adicionava nada e não dizia nada.**
> O save estava ligado como arrow de **zero argumentos**, então a guarda nem tinha o `event` para
> prevenir — estruturalmente não podia sair da mecânica do core. Hoje o botão **nasce desabilitado**
> (`js:110`) e segue a seleção: `updateAddButton` (`:48-50`) recomputa no `click` e no `change` da
> lista (`:140-141`, registrados **depois** do `initBrowser` para que o handler da árvore já tenha
> sincronizado o `state.checked` — o motivo está no comentário `:138-139`) e na troca de estrutura
> (`:133`). O `preventDefault` (`:66`) ficou de **backstop**. Um botão desabilitado não promete nada,
> então não há mensagem a dar — é a mesma resposta do `MOD.RELATED`, não uma segunda.
>
> **E aqui o `grep` mentia — vale como lição, não como nota de rodapé.** Este mapa concluía que "o
> botão nunca desabilita" citando `grep -n 'disabled' amd/src/central/competency_browser.js` →
> **zero**. Esse comando **devolve zero até hoje**, com o botão desabilitado por duas linhas: o
> `grep` é **sensível a maiúsculas** e o que o arquivo tem é `setButtonDisabled`, com **D**
> maiúsculo. `grep -in` devolve **duas** — `:49` e `:110`. A busca falhava, e a falha nunca foi
> prova de ausência. O único `disabled` minúsculo da árvore (`tree.js:119`) continua sendo o das
> linhas travadas: esse, reconferido, segue verdadeiro.
>
> **O precedente que o IMP-06 cita foi o caminho seguido — com uma economia de linha.** O
> `competency_picker` do **format_mtube** já fazia as duas coisas que faltavam:
> `_setSaveEnabled(this._selectedCompetencies.length > 0)` logo após o `setSaveButtonText`, e um
> `ModalEvents.save` que chama `event.preventDefault()` **só** quando a seleção está vazia, deixando
> o core fechar no caminho normal — que é, linha por linha, a forma que este modal tem hoje. A
> diferença: o `_setSaveEnabled` do mtube é três linhas na unha
> (`this._modal.getFooter().find(this._modal.getActionSelector('save')).prop('disabled', !enabled)`)
> sobre duas APIs públicas do core — e o core **já embrulha essas mesmas três linhas** em
> `setButtonDisabled(action, disabled)` (`lib/amd/src/modal.js:1222`, cujo corpo é o mesmo
> `getFooter().find(getActionSelector(action))`). Este modal chama o embrulho. O gêmeo **dentro do
> plugin** é o `updateAddButton` do `related_competencies.js` (`:140-142`) — mesmo nome, mesma regra
> (`getCheckedIds(state).length === 0`), mas lá o botão é do **corpo** e não do rodapé do core, então
> ele escreve `state.addbtnEl.disabled` direto e não tem embrulho a chamar.
>
> **Onde ler esse código, porque não é onde se espera.** O `format_mtube` **não tem `amd/src`** — um
> `ls` da raiz do plugin mostra `amd/` com **`build/` e mais nada**. O fonte só é recuperável pelo
> `sourcesContent` do sourcemap (`amd/build/features/competency_picker.min.js.map`, cujo
> `sources[0]` é `../../src/features/competency_picker.js`, 607 linhas). É de lá que saem os números
> acima: `ModalSaveCancel.create` em `:137-142` (com `removeOnClose: true` **no config**, o que
> confirma a economia de linha que o IMP-06 propõe), `setSaveButtonText` em `:145`, `_setSaveEnabled`
> em `:146` e `:470-475`, o `preventDefault` da seleção vazia em `:151-156`.

## Resumo das divergências as-is ↔ DS

| O que o DS/mapa antigo dizia | O que está no ar |
| --- | --- |
| "Procurar em frameworks" · rótulo "Framework" | "Procurar em estruturas" · rótulo "Estrutura" (`f817430` reescreveu as strings, manteve as chaves) |
| Filtro **client-side** sobre lista carregada | Busca **server-side** (`local_dimensions_browse_competencies`), debounce 250 ms, mínimo 2 chars |
| Placeholder "Buscar competência…" | "Filtrar competências" (`central_browseframeworks_filter`), também `aria-label` |
| Lista plana de checkboxes | **Árvore** lazy com chevron por linha, indent de 20px, filhos de 25 em 25 |
| `paginated-picker.html` chegou a esboçar paginação numerada — aposentada quando o card virou o aviso de overflow do form-autocomplete | **Scroll infinito** com sentinela + `IntersectionObserver` (a paginação já é server-side) |
| Sem menção a linhas travadas | `data-excludeids` trava as já no plano, com sufixo "(Já neste plano)" |
| `competency_datasource.js` como AMD deste modal | é o datasource do autocomplete da **aba Planos** (`plans.js:46`); este modal não o importa |
| `mod-browser.html`: marcar→habilitar como **to-be** (`:144`); rodapé "sempre habilitado" (`:298-299`) | **shipado** em 2026-07-15 (`e14977c`): o Adicionar nasce desabilitado (`js:110`), segue a seleção (`updateAddButton`) e o `preventDefault` (`:66`) é backstop — a tela é que está atrasada agora |
