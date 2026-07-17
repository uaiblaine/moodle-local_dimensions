# Mapa de Campos — `MOD.RELATED` · Modal competências referenciadas (as-is)

Modal aberto pelo botão **⇄ Competências referenciadas** do **sticky-footer** da aba Estrutura.
Lista as relações atuais de uma competência, remove uma a uma, e adiciona novas pelo **mesmo
browser de árvore** do modal "Consultar estruturas" (partial compartilhado), **menos** o seletor de
estrutura — uma relação só alcança competências da mesma estrutura. As linhas de relação e as da
árvore são **todas construídas em JS**; o Mustache é só a casca.

É um `core/modal_save_cancel`: a ação primária ("Adicionar selecionadas") é o **botão de salvar do
rodapé** (Cancelar rerrotulado "Fechar", porque o modal gerencia no lugar e não tem o que cancelar).
Foi o **caso de referência do IMP-06** — agora **shipado** (`0898acf`); a seção ao fim registra a
mecânica.

- **Mustache:** [`related_competencies.mustache`](../../../templates/central/related_competencies.mustache) (40, casca), [`competency_tree_browser.mustache`](../../../templates/central/competency_tree_browser.mustache) (44, partial compartilhado com o `MOD.BROWSER`)
- **AMD:** [`related_competencies.js`](../../../amd/src/central/related_competencies.js) (299) — monta o browser via [`competency_tree_browser.js`](../../../amd/src/central/competency_tree_browser.js) (510, `initBrowser`/`applyMode`/`getCheckedIds`/`destroyBrowser`), pisca a linha nova com o helper compartilhado [`flash.js`](../../../amd/src/central/flash.js) (import `:31`) e usa `errors.js` (`notifyError`)
- **WS:** `local_dimensions_list_related_competencies` (`db/services.php:133-134` → [`classes/external/list_related_competencies.php`](../../../classes/external/list_related_competencies.php), relações + caminho de ancestrais), `local_dimensions_browse_competencies` (`db/services.php:109-110`, a árvore/busca), core `core_competency_add_related_competency` e `core_competency_remove_related_competency` (escrever)
- **CSS:** [`styles.css:5685-5688`](../../../styles.css) (cap de 40vh da caixa da árvore, exclusivo deste modal), [`styles.css:5076-5145`](../../../styles.css) (os chips do detalhe)
- **Tela no DS:** [`screens/mod-related.html`](../screens/mod-related.html) (as-is ↔ histórico do IMP-06, com a demonstração de marcar→habilitar dirigida e medida)

> **Resync 2026-07-17 — o IMP-06 shipou; o modal virou `ModalSaveCancel` e a ação desceu para o
> rodapé.** O que mudou desde a última varredura:
>
> - A casca deixou de ser `Modal.create` (corpo com botão) e virou `ModalSaveCancel.create`
>   (`related_competencies.js:239-244`), com a ação primária no **rodapé** e o `preventDefault`
>   **incondicional** no `ModalEvents.save` (`:290-296`). O ID `MOD.RELATED-ADDBTN` (botão no corpo,
>   `:35-39` do Mustache) foi **aposentado**; entra `MOD.RELATED-FOOT` (o mesmo ID que a tela já usava
>   para o to-be). O `<button data-action="add-selected">` e o `SELECTORS.addSelected` saíram.
> - O Mustache encolheu de 45 para **40** linhas (as 5 do botão), e o `.js` cresceu de 284 para **299**
>   (as duas strings novas — `central_browseframeworks_add` para o rótulo do save e `closebuttontitle`
>   do core para "Fechar" — mais a fiação do `ModalEvents.save`). **Todas** as refs de `.js` e de
>   `.mustache` abaixo foram re-varridas contra esse estado.
>
> **Resync 2026-07-15 (histórico) — o mapa anterior descrevia um autocomplete que não existe mais.**
> Os fatos ainda válidos daquela varredura, preservados:
>
> - **`related_datasource.js` não existe.** Nasceu em `da4489a` e foi **deletado em `44ac031`** ("related
>   modal adds via the shared framework tree browser", −61 linhas). A árvore chama
>   **`local_dimensions_browse_competencies`** (`competency_tree_browser.js:61-64`), não `search_structure`
>   (que segue vivo, mas para a **busca da própria aba Estrutura**, `structure.js:382`).
> - **O gatilho** é `structure_footer_actions.mustache:57` (`grep -n 'data-action="related"' templates/`),
>   não `structure.mustache` — este só passa a flag `detailconfig.showrelated` (`:46`, `:51`) ao partial de detalhe.
> - **Os chips de referenciadas no detalhe** da aba Estrutura (`structure_detail_content.mustache:116-125` +
>   `structure_related_chips.mustache`) são um sub-recurso completo, com contador e ação `open-related`.
> - **A relação é simétrica por uma razão mecânica.** O core **normaliza** o par:
>   `related_competency::get_relation()` (`:107-130`) sempre grava o **id menor** como `competencyid`
>   (`:112-118`), porque o validador **exige** `competencyid < relatedcompetencyid` (`:82-84`). Gravada uma
>   vez só, numa direção canônica, a leitura simétrica **tem de** ser um `UNION ALL` dos dois sentidos — e é
>   (`related_competency::get_related_competencies()`, `:142-153`). A `api::list_related_competencies` existe
>   (`api.php:3726`) mas delega.

## Gatilho e chips (na aba Estrutura, fora do modal)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-ACTION` | Competências referenciadas | botão (gatilho) | `structure_footer_actions.mustache:57-60` | `data-action="related"` · `fa fa-exchange` | str `central_related_button`. Mora no **sticky-footer** compartilhado da aba, não numa linha. `structure.js:1272-1277` chama `openRelatedModal({competencyid, competencyname, frameworkid})` (import em `:38`) com as `data-*` da linha ativa |
| `MOD.RELATED-CHIPS` | Competências referenciadas | seção do detalhe | `structure_detail_content.mustache:116-125` | `data-region="detail-related"` · nasce `hidden` | só sai sob `{{#showrelated}}`, e o partial inteiro só entra dentro de `{{#detailconfig}}` (`structure.mustache:215-217` — a seção **também troca o contexto**, e é por isso que `showrelated` resolve lá dentro). **Não existe no pane de modal**, e `populateRelated` (`structure.js:477-503`) sai calado quando a região não está lá (`:481-483`) |
| `MOD.RELATED-CHIPS-COUNT` | `[sem rótulo]` | contador | `structure_detail_content.mustache:121` | `data-region="detail-related-count"` | `structure.js:495-497`. Nasce `0` no Mustache e é pintado com `items.length` |
| `MOD.RELATED-CHIP` | {nome} | chip (botão) | `structure_related_chips.mustache:36-43` | `data-action="open-related"` · `data-id` | `title` = str `central_related_view` ("Ver detalhes"). `structure.js:1244-1248` abre a competência referenciada em **outro** modal (`openCompetencyDetailModal`, `competency_detail.js:265`) — que entra com `showrelated: false` (`:275`), então **não aninha** referenciadas dentro de referenciadas. A lista é resetada **antes** do fetch (`:485-486`) e re-checada depois (`isactive()`, `:492`, `:499`) para uma troca rápida de linha não pintar chips velhos |

> **Armadilha de nome — `.local-dimensions-related-modal` não é este modal.** A classe parece ser a
> deste mapa e **não é**: quem a aplica é `competency_detail.js:285`, no modal que **o chip** abre
> (`structure_related_modal.mustache`), cujo `.modal-header` é escondido de propósito
> (`styles.css:5158-5160`) porque o card traz o **próprio** botão de fechar
> (`data-action="close-related-modal"`, `structure_related_modal.mustache:37`). O modal
> "Competências referenciadas" **não recebe classe nenhuma** no root — `related_competencies.js:239`
> é um `ModalSaveCancel.create` seco.
>
> A consequência é fácil de ler ao contrário: o restyle do chip de fechar do hub é
> `.modal:not(.local-dimensions-related-modal):has(.modal-body [class*='local-dimensions-'])`
> (`styles.css:3557`), e o comentário acima dele (`:3554-3556`) diz que "a referenced-competency
> modal" está **excluída**. Ele fala do modal do **chip**, não deste. Este modal **casa** os dois
> lados do seletor (não tem a classe; o corpo tem `.local-dimensions-central-related`) e **ganha** o
> chip azul de `1.75rem` normalmente.

## Casca do modal

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-TITLE` | Competências referenciadas — {nome} | título | `related_competencies.js:224` (str), `:239-244` (`ModalSaveCancel.create`) | str `central_related_title`, `$a` = nome | rodapé Cancelar/Salvar preenchido pelo core; `removeOnClose: true` vai no próprio `configure()` (`:242`), e o save nasce desabilitado (`setButtonDisabled('save', true)`, `:246`) |
| `MOD.RELATED-ROOT` | `[sem rótulo]` | região/raiz | `related_competencies.mustache:31` | `data-region="related-competencies"` · `.local-dimensions-central-related` | a classe é o gancho do cap de 40vh (`styles.css:5685`). O listener delegado de remover pousa aqui (`js:274-278`) |
| `MOD.RELATED-ADDLABEL` | Adicionar competência referenciada | rótulo | `related_competencies.mustache:32` | str `central_related_add` | é um `<div class="small fw-medium">`, **não** um `<label>` — não há `for`, porque o alvo é uma árvore, não um campo |
| `MOD.RELATED-SAMEFW` | Somente competências da mesma estrutura podem ser referenciadas. | nota | `related_competencies.mustache:33` | str `central_related_sameframework` | é a constraint do core em prosa: `competency::share_same_framework` (`related_competency.php:89-91`, via `competency.php:679`). Por isso o partial entra **sem** seletor de estrutura |
| `MOD.RELATED-TOAST` | `[sem rótulo]` | feedback | `related_competencies.js:266-269` | `addToastRegion(modal.getBody()[0])` no `ModalEvents.shown` | strs `central_related_added` / `central_related_removed`. Ver a seção do toast abaixo |

## A árvore (partial compartilhado com o `MOD.BROWSER`)

O `related_competencies.mustache:34` inclui o partial inteiro; quem o dirige é
`competency_tree_browser.js`, com o `state` montado pelo modal.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-FILTER` | Filtrar competências | campo de busca | `competency_tree_browser.mustache:31-35` | `data-region="filter"` · `aria-label` = mesmo str | str `central_browseframeworks_filter`. Debounce de **250 ms** (`browser.js:375-387`), mínimo de **2** caracteres (`SEARCH_MIN`, `:47`); abaixo disso volta para o modo árvore (`:383-384`) |
| `MOD.RELATED-PATHS` | Mostrar caminhos | switch | `competency_tree_browser.mustache:36-41` | `data-region="path-toggle"` · id com `{{uniqid}}` | str `central_browseframeworks_showpaths`. Em modo **busca** ele é forçado `checked` **e** `disabled` (`browser.js:327-328`), porque `pathsVisible` já é sempre verdadeiro ali (`:72`). Governa **só a árvore** — as linhas de relação mostram o caminho sempre |
| `MOD.RELATED-TREE` | `[sem rótulo]` | contêiner-JS | `competency_tree_browser.mustache:42-44` | `data-region="competency-list"` · `.local-dimensions-cb-scroll` | `styles.css:5685-5688` dá `max-height:40vh` + `overflow-y:auto` **só aqui** (o `MOD.BROWSER` deixa solto): é o que mantém as linhas de relação, abaixo, alcançáveis. A sentinela do scroll infinito é inserida **dentro** da caixa de propósito (`browser.js:486-490`) |
| `MOD.RELATED-ROW` | {nome} | linha (checkbox) | `competency_tree_browser.js:82-135` (`makeNode`; o checkbox em `:111-123`) | `input.form-check-input` + nome + caminho | **sem `for`**: a linha inteira é o alvo de clique (`:125-126`, `onListClick` `:432-437`), com seleção por intervalo no Shift (`:354-361`). A seleção é **persistente** (`state.checked`) e sobrevive a re-render (`:120-122`). Indenta `20px` por nível (`INDENT_STEP`, `:48`, aplicado em `:94`) |
| `MOD.RELATED-ROW-LOCK` | {nome} (Esta competência) / (Já referenciada) | linha travada | `competency_tree_browser.js:117-119`, `:130` | `checked` + `disabled` · sufixo no nome | o conjunto é `state.excluded`: a própria competência + as já referenciadas, reconstruído a cada `loadRelations` (`js:110-117`). O sufixo vem de `state.excludedsuffix` (`js:258`) → strs `central_related_self` / `central_related_alreadyrelated`. `getCheckedIds` filtra as excluídas (`browser.js:450-451`) |
| `MOD.RELATED-MORE` | Carregar mais | botão | `competency_tree_browser.js:186` | str `central_browseframeworks_loadmore` | página de **25** (`PAGE_SIZE`, `:46`) |
| `MOD.RELATED-TREE-EMPTY` | Nenhuma competência nesta estrutura. | estado vazio | `related_competencies.js:233` (str), `:260` (state) | str `central_browseframeworks_empty` | passa no `state.emptylabel`; é o vazio **da árvore**, não o das relações |

## Ação e relações atuais

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-FOOT` | Adicionar selecionadas · Fechar | rodapé do modal | `related_competencies.js:239-244` (`buttons: {save, cancel}`), `:290-296` (`ModalEvents.save`) | save = str **`central_browseframeworks_add`** (reusada do `MOD.BROWSER`); cancel = str `closebuttontitle` do core ("Fechar") | o core revela o `.modal-footer` (sempre renderizado, escondido quando vazio) porque o `ModalSaveCancel` o preenche. `updateAddButton` (`js:125-127`) liga o save via `state.modal.setButtonDisabled('save', …)` enquanto `getCheckedIds(state).length !== 0`; os listeners são registrados **depois** dos do browser, de propósito (`js:284-285`). O `preventDefault()` do save é **incondicional** (`:294`) — ver a seção do IMP-06 |
| `MOD.RELATED-ROWS` | `[sem rótulo]` | contêiner-JS | `related_competencies.mustache:36` | `data-region="related-list"` | linhas por `makeRow` (`js:55-97`): nome + `idnumber` mono + caminho de ancestrais. O caminho vem do WS (`list_related_competencies` → `helper::competency_breadcrumbs`) e é renderizado **sempre** (`js:72-77`) — o `MOD.RELATED-PATHS` não o alcança |
| `MOD.RELATED-ROW-REMOVE` | Remover competência referenciada | botão (por linha) | `related_competencies.js:79-96` | `data-action="remove-related"` · `data-relatedid` na linha | ícone `fa fa-trash` + `.visually-hidden` com o rótulo. `removeRelated` (`js:154-180`): confirm `deleteCancelPromise` (`:162`, strs `central_related_remove` / `central_related_remove_confirm`) → WS core → tira a linha → **re-renderiza a árvore** para a competência voltar a ser pickável (`:174`) → devolve o foco (`:177`), porque o confirm o tinha devolvido para um botão já destacado do DOM |
| `MOD.RELATED-EMPTY` | Nenhuma competência referenciada ainda. | estado vazio | `related_competencies.mustache:37-39` | `data-region="related-empty"` · nasce `hidden` · `role="status"` | str `central_related_empty`. Alternado em `js:116` (loadRelations) e `:172` (removeRelated) |

**Fluxo do add (`addSelected`, `js:187-211`).** Dispara **N chamadas em paralelo** (uma
`core_competency_add_related_competency` por id marcado, `:194-197`). O `finally` (`:199-208`)
re-sincroniza linhas **e** árvore com o servidor **mesmo no erro**, com o motivo registrado no
próprio código: uma chamada que falha no meio do lote **não desfaz** as anteriores. As marcas ainda
pendentes são preservadas para o usuário repetir. Depois, cada linha nova pisca (`flashRow`, do
helper `flash.js`, `:209`) e sai um toast (`:210`).

## O toast — por que a região mora dentro do modal

`related_competencies.js:269` chama `addToastRegion(modal.getBody()[0])` no `ModalEvents.shown`.
É um dos **4** pontos do plugin com esse padrão (`participants_manager.js`, `competency_links.js`,
`frameworks.js`, e este) — contados com `grep -rn 'addToastRegion(' amd/src/`, **com o parêntese**:
sem ele o grep devolve **8**, porque soma as 4 linhas de `import`.

O motivo é aritmética de `z-index`, e vale conferir os dois números:

- `.toast-wrapper` da página: **`z-index: 1051`** (`theme/boost/scss/moodle/core.scss:2432`).
- `$zindex-modal`: **`1055`** (`theme/boost/scss/bootstrap/_variables.scss:1139`).

Um toast disparado de dentro do modal, sem região própria, pousaria na wrapper da página e ficaria
**atrás** do diálogo. Detalhe incômodo e verificado: o comentário do core, na linha **acima** do
`z-index: 1051`, diz que aquilo fica *"above any modals"* — e **envelheceu**. No Bootstrap 4
`$zindex-modal` era 1050 e a conta fechava; o salto para o BS5 subiu o modal para 1055 e deixou a
wrapper por baixo, sem que o comentário mudasse. O padrão da casa existe por causa desse descompasso.

O core remove a região sozinho ao fechar (`removeToastRegion` no `core/modal`), então não há
vazamento e **não** se mexe em `z-index` global.

## IMP-06 — a ação primária desceu para o rodapé que o core já cria (shipado, `0898acf`)

**O rodapé não estava faltando: estava escondido.** A cadeia inteira, conferida linha a linha:

1. `lib/templates/modal.mustache:58-62` **sempre** renderiza o `div.modal-footer` com
   `data-region="footer"`, e um bloco `{{$footer}}` vazio por padrão.
2. `Modal.show()` (`lib/amd/src/modal.js:868`) pergunta `hasFooterContent()` — que é literalmente
   `this.getFooter().children().length ? true : false` (`:686`).
3. Com zero filhos, cai no `else` e chama `hideFooter()` (`:875-879`), que aplica a classe `.hidden`.
4. `.hidden { display: none; }` (`theme/boost/scss/moodle/core.scss:417-419`) — o rodapé **colapsa**.

**Portanto: dar um filho ao rodapé faz o core revelá-lo sozinho** (`showFooter()`, `:704`). E é
exatamente isso que o `ModalSaveCancel` é — o **mesmo** `core/modal` com o bloco `{{$footer}}`
preenchido com Cancelar + Salvar (`lib/templates/modal_save_cancel.mustache:42-45`).

**A troca foi menos código, não mais.** O `configure()` do core aceita `buttons` e `removeOnClose` no
mesmo objeto (`lib/amd/src/modal.js:247-288`, o `buttons` é aplicado via `setButtonText`), então as
duas linhas de antes (`Modal.create({title, body})` + `setRemoveOnClose(true)`) viraram uma:

```js
const modal = await ModalSaveCancel.create({
    title, body: html, removeOnClose: true,
    buttons: {save: addlabel, cancel: closelabel},
});
```

(`related_competencies.js:239-244`) e o Mustache perdeu as **5** linhas do botão. **Sem string nova
para o botão:** `central_browseframeworks_add` ("Adicionar selecionadas") já era a que o corpo usava,
e "Fechar" é o `closebuttontitle` do core (`lang/en/moodle.php:280`).

**O desabilitado-até-marcar sobreviveu e encolheu.** O `setButtonDisabled(action, disabled)` é público
no core (`modal.js:1222-1223`) e faz o `getFooter()` + `getActionSelector()` por dentro. O
`updateAddButton` (`js:125-127`) virou uma linha —
`state.modal.setButtonDisabled('save', getCheckedIds(state).length === 0)` — e perdeu o `state.addbtnEl`
e o `querySelector` do corpo que o alimentavam. O `state.modal` guardado no `state` (`:252`) é o único
gancho.

> **A ressalva, e é o motivo de o IMP-06 não ter sido um copiar-e-colar do vizinho.** O
> `ModalSaveCancel.registerEventListeners()` (`modal_save_cancel.js:52-59`) chama
> `registerCloseOnSave()`, e o handler do core **fecha o diálogo** depois do `ModalEvents.save` — a
> menos que se chame `preventDefault()` **síncrono** (`modal.js:1100-1107`: ele dispara o
> `ModalEvents.save` e só fecha se `!saveEvent.isDefaultPrevented()`). Este modal **não pode fechar**
> no "Adicionar selecionadas": o toast, o `flash` da linha nova (`js:209`) e o estado vazio acontecem
> **no lugar**, e o usuário volta à árvore. Logo, o `ModalEvents.save` daqui (`js:290-296`) chama
> `event.preventDefault()` **incondicional** (`:294`) como primeira instrução, e só então dispara o
> `addSelected` assíncrono.
>
> **O contraste com os vizinhos, mais estreito depois do `e14977c`.** O `competency_browser.js`
> (`MOD.BROWSER`) e o `competency_picker` do mtube fazem o `preventDefault` **condicional** — só para
> **barrar** a seleção vazia; numa adição real, **fecham**, e está certo para eles, que são pickers de
> uma tacada. O de referenciadas é o terceiro caso, e o único: **gerencia**, escreve a cada clique e
> **fica**. Nele o `preventDefault` é **incondicional** e é o mecanismo, não o backstop. A chamada
> `ModalSaveCancel` + `setButtonDisabled` se reusa; a **fiação do save, não**.

**O que o IMP-06 não conserta:** o cap de `40vh` (`styles.css:5685-5688`) existe por causa das
**linhas de relação** abaixo da árvore, não do botão — ele fica. E a sentinela continua dentro da
caixa (`browser.js:486-490`), então a paginação segue presa à rolagem dela.
