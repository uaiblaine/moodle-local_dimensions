# Mapa de Campos — `MOD.RELATED` · Modal competências referenciadas (as-is)

Modal aberto pelo botão **⇄ Competências referenciadas** do **sticky-footer** da aba Estrutura.
Lista as relações atuais de uma competência, remove uma a uma, e adiciona novas pelo **mesmo
browser de árvore** do modal "Consultar estruturas" (partial compartilhado), **menos** o seletor de
estrutura — uma relação só alcança competências da mesma estrutura. As linhas de relação e as da
árvore são **todas construídas em JS**; o Mustache é só a casca.

É um `core/modal` **sem rodapé**, e a ação primária ("Adicionar selecionadas") mora **no corpo** —
é o **caso de referência do IMP-06**, detalhado no fim deste mapa.

- **Mustache:** [`related_competencies.mustache`](../../../templates/central/related_competencies.mustache) (45, casca), [`competency_tree_browser.mustache`](../../../templates/central/competency_tree_browser.mustache) (44, partial compartilhado com o `MOD.BROWSER`)
- **AMD:** [`related_competencies.js`](../../../amd/src/central/related_competencies.js) (297) — monta o browser via [`competency_tree_browser.js`](../../../amd/src/central/competency_tree_browser.js) (510, `initBrowser`/`applyMode`/`getCheckedIds`/`destroyBrowser`) e usa `errors.js` (`notifyError`)
- **WS:** `local_dimensions_list_related_competencies` (`db/services.php:133-134` → [`classes/external/list_related_competencies.php`](../../../classes/external/list_related_competencies.php), relações + caminho de ancestrais), `local_dimensions_browse_competencies` (`db/services.php:109-110`, a árvore/busca), core `core_competency_add_related_competency` e `core_competency_remove_related_competency` (escrever)
- **CSS:** [`styles.css:5685-5688`](../../../styles.css) (cap de 40vh da caixa da árvore, exclusivo deste modal), [`styles.css:5076-5145`](../../../styles.css) (os chips do detalhe)
- **Tela no DS:** [`screens/mod-related.html`](../screens/mod-related.html) (as-is ↔ to-be, com a demonstração de marcar→habilitar dirigida e medida)

> **Resync 2026-07-15 — o mapa anterior descrevia um autocomplete que não existe mais, e o arquivo
> que o alimentava foi deletado.** Medido, não estimado:
>
> - **4 refs no mapa antigo; 4 quebradas (4/4).** Um
>   `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+(-[0-9]+)?'` no arquivo antigo devolve
>   **exatamente 4**, todas em `related_competencies.mustache` — e o arquivo tem **45 linhas**:
>   - `:36-38` (dito `MOD.RELATED-ADDLABEL`) resolve hoje para o **botão "Adicionar selecionadas"** —
>     o tipo pior de quebra, a mesma do `mod-participants`: aponta para um controle **real, de outra
>     ID**. Quem confere vê um elemento plausível e segue.
>   - `:39-44` (dito `MOD.RELATED-ADD`, o autocomplete) resolve para o `</button>`, o `<hr>`, a
>     região de linhas e o estado vazio — quatro IDs diferentes de uma vez.
>   - `:46` e `:47-49` (`MOD.RELATED-ROWS`, `MOD.RELATED-EMPTY`) apontam **depois do fim do
>     arquivo**. Não existem.
> - **Zero refs de JS**, como em todos os mapas anteriores. Oito IDs, e nenhuma linha apontava para
>   dentro dos módulos AMD: nada de `makeRow`, `loadRelations`, `updateAddButton`, `addToastRegion`,
>   `excludedsuffix`, `restoreFocus`.
> - **`related_datasource.js` não existe.** `ls` devolve nada; o `git log` do caminho mostra o
>   arquivo nascendo em `da4489a` e sendo **deletado em `44ac031`** ("related modal adds via the
>   shared framework tree browser", −61 linhas em `amd/src/central/related_datasource.js`). O bullet
>   **AMD** do mapa antigo linkava para ele.
> - **O WS do picker está trocado.** O mapa dizia `search_structure`; quem a árvore chama é
>   **`local_dimensions_browse_competencies`** (`competency_tree_browser.js:61-64`). O
>   `search_structure` continua existindo e continua sendo usado — mas pela **busca da própria aba
>   Estrutura** (`structure.js:382`), não por este modal.
> - **O gatilho não está onde o mapa dizia.** Ele apontava `structure.mustache`; um
>   `grep -n 'data-action="related"' templates/` devolve **`structure_footer_actions.mustache:57`**.
>   O `structure.mustache` só passa a flag `detailconfig.showrelated` (`:46`, `:51`) para o partial
>   de detalhe.
> - **Faltava inteiro:** os **chips de referenciadas no detalhe** da aba Estrutura
>   (`structure_detail_content.mustache:116-125` + `structure_related_chips.mustache`), com contador
>   e ação `open-related` que abre a competência referenciada em outro modal — um sub-recurso
>   completo que o mapa não mencionava; e **toda a árvore** (filtro com debounce, toggle de
>   caminhos, linhas travadas com sufixo, "Carregar mais", scroll infinito com sentinela).
> - **A relação é simétrica por uma razão mecânica que o mapa não registrava.** O core **normaliza**
>   o par: `related_competency::get_relation()` (`:107-130`) sempre grava o **id menor** como
>   `competencyid` (`:112-118`), porque o validador **exige** `competencyid < relatedcompetencyid`
>   (`:82-84`). Como a linha é gravada uma vez só, numa direção canônica, a leitura simétrica **tem
>   de** ser um `UNION ALL` dos dois sentidos — e é
>   (`related_competency::get_related_competencies()`, `:142-153`). O mapa antigo creditava o UNION à
>   `api::list_related_competencies`; ela existe (`api.php:3726`) mas delega.

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
> "Competências referenciadas" **não recebe classe nenhuma** no root — `related_competencies.js:248`
> é um `Modal.create` seco.
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
| `MOD.RELATED-TITLE` | Competências referenciadas — {nome} | título | `related_competencies.js:238` (str), `:248` (`Modal.create`) | str `central_related_title`, `$a` = nome | `core/modal` **puro** — sem `footer` no config. `setRemoveOnClose(true)` em `:249` |
| `MOD.RELATED-ROOT` | `[sem rótulo]` | região/raiz | `related_competencies.mustache:31` | `data-region="related-competencies"` · `.local-dimensions-central-related` | a classe é o gancho do cap de 40vh (`styles.css:5685`). O listener delegado de remover pousa aqui (`js:278-282`) |
| `MOD.RELATED-ADDLABEL` | Adicionar competência referenciada | rótulo | `related_competencies.mustache:32` | str `central_related_add` | é um `<div class="small fw-medium">`, **não** um `<label>` — não há `for`, porque o alvo é uma árvore, não um campo |
| `MOD.RELATED-SAMEFW` | Somente competências da mesma estrutura podem ser referenciadas. | nota | `related_competencies.mustache:33` | str `central_related_sameframework` | é a constraint do core em prosa: `competency::share_same_framework` (`related_competency.php:89-91`, via `competency.php:679`). Por isso o partial entra **sem** seletor de estrutura |
| `MOD.RELATED-TOAST` | `[sem rótulo]` | feedback | `related_competencies.js:269-272` | `addToastRegion(modal.getBody()[0])` no `ModalEvents.shown` | strs `central_related_added` / `central_related_removed`. Ver a seção do toast abaixo |

## A árvore (partial compartilhado com o `MOD.BROWSER`)

O `related_competencies.mustache:34` inclui o partial inteiro; quem o dirige é
`competency_tree_browser.js`, com o `state` montado pelo modal.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-FILTER` | Filtrar competências | campo de busca | `competency_tree_browser.mustache:31-35` | `data-region="filter"` · `aria-label` = mesmo str | str `central_browseframeworks_filter`. Debounce de **250 ms** (`browser.js:375-387`), mínimo de **2** caracteres (`SEARCH_MIN`, `:47`); abaixo disso volta para o modo árvore (`:383-384`) |
| `MOD.RELATED-PATHS` | Mostrar caminhos | switch | `competency_tree_browser.mustache:36-41` | `data-region="path-toggle"` · id com `{{uniqid}}` | str `central_browseframeworks_showpaths`. Em modo **busca** ele é forçado `checked` **e** `disabled` (`browser.js:327-328`), porque `pathsVisible` já é sempre verdadeiro ali (`:72`). Governa **só a árvore** — as linhas de relação mostram o caminho sempre |
| `MOD.RELATED-TREE` | `[sem rótulo]` | contêiner-JS | `competency_tree_browser.mustache:42-44` | `data-region="competency-list"` · `.local-dimensions-cb-scroll` | `styles.css:5685-5688` dá `max-height:40vh` + `overflow-y:auto` **só aqui** (o `MOD.BROWSER` deixa solto): é o que mantém as linhas de relação, abaixo, alcançáveis. A sentinela do scroll infinito é inserida **dentro** da caixa de propósito (`browser.js:486-490`) |
| `MOD.RELATED-ROW` | {nome} | linha (checkbox) | `competency_tree_browser.js:82-135` (`makeNode`; o checkbox em `:111-123`) | `input.form-check-input` + nome + caminho | **sem `for`**: a linha inteira é o alvo de clique (`:125-126`, `onListClick` `:432-437`), com seleção por intervalo no Shift (`:354-361`). A seleção é **persistente** (`state.checked`) e sobrevive a re-render (`:120-122`). Indenta `20px` por nível (`INDENT_STEP`, `:48`, aplicado em `:94`) |
| `MOD.RELATED-ROW-LOCK` | {nome} (Esta competência) / (Já referenciada) | linha travada | `competency_tree_browser.js:117-119`, `:130` | `checked` + `disabled` · sufixo no nome | o conjunto é `state.excluded`: a própria competência + as já referenciadas, reconstruído a cada `loadRelations` (`js:124-130`). O sufixo vem de `state.excludedsuffix` (`js:261`) → strs `central_related_self` / `central_related_alreadyrelated`. `getCheckedIds` filtra as excluídas (`browser.js:450-451`) |
| `MOD.RELATED-MORE` | Carregar mais | botão | `competency_tree_browser.js:186` | str `central_browseframeworks_loadmore` | página de **25** (`PAGE_SIZE`, `:46`) |
| `MOD.RELATED-TREE-EMPTY` | Nenhuma competência nesta estrutura. | estado vazio | `related_competencies.js:245` (str) | str `central_browseframeworks_empty` | passa no `state.emptylabel`; é o vazio **da árvore**, não o das relações |

## Ação e relações atuais

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RELATED-ADDBTN` | Adicionar selecionadas | botão primário | `related_competencies.mustache:35-39` | `data-action="add-selected"` · nasce `disabled` | str **`central_browseframeworks_add`** (reusada do `MOD.BROWSER`, não tem string própria). Mora **no corpo** — a verruga que o IMP-06 endereça. `updateAddButton` (`js:140-142`) o liga enquanto `getCheckedIds(state).length !== 0`; os listeners são registrados **depois** dos do browser, de propósito, para o toggle da linha já ter sido aplicado quando o estado é recalculado (`js:287-290`) |
| `MOD.RELATED-ROWS` | `[sem rótulo]` | contêiner-JS | `related_competencies.mustache:41` | `data-region="related-list"` | linhas por `makeRow` (`js:70-111`): nome + `idnumber` mono + caminho de ancestrais. O caminho vem do WS (`list_related_competencies` → `helper::competency_breadcrumbs`) e é renderizado **sempre** (`js:87-92`) — o `MOD.RELATED-PATHS` não o alcança |
| `MOD.RELATED-ROW-REMOVE` | Remover competência referenciada | botão (por linha) | `related_competencies.js:94-106` | `data-action="remove-related"` · `data-relatedid` na linha | ícone `fa fa-trash` + `.visually-hidden` com o rótulo. `removeRelated` (`js:169-194`): confirm `deleteCancelPromise` (strs `central_related_remove` / `central_related_remove_confirm`) → WS core → tira a linha → **re-renderiza a árvore** para a competência voltar a ser pickável (`:189`) → devolve o foco (`:192`), porque o confirm o tinha devolvido para um botão já destacado do DOM |
| `MOD.RELATED-EMPTY` | Nenhuma competência referenciada ainda. | estado vazio | `related_competencies.mustache:42-44` | `data-region="related-empty"` · nasce `hidden` | str `central_related_empty`. Alternado em `js:131` e `:187` |

**Fluxo do add (`addSelected`, `js:202-226`).** Dispara **N chamadas em paralelo** (uma
`core_competency_add_related_competency` por id marcado, `:209-212`). O `finally` (`:214-223`)
re-sincroniza linhas **e** árvore com o servidor **mesmo no erro**, com o motivo registrado no
próprio código: uma chamada que falha no meio do lote **não desfaz** as anteriores. As marcas ainda
pendentes são preservadas para o usuário repetir. Depois, cada linha nova pisca (`flash`, `js:53-61`,
`:224`) e sai um toast (`:225`).

## O toast — por que a região mora dentro do modal

`related_competencies.js:272` chama `addToastRegion(modal.getBody()[0])` no `ModalEvents.shown`.
É um dos **4** pontos do plugin com esse padrão (`participants_manager.js:167`,
`competency_links.js:808`, `frameworks.js:348`, e este) — contados com
`grep -rn 'addToastRegion(' amd/src/`, **com o parêntese**: sem ele o grep devolve **8**, porque soma
as 4 linhas de `import`.

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

## IMP-06 — a ação primária desce para o rodapé que o core já cria

**O rodapé não está faltando: está escondido.** A cadeia inteira, conferida linha a linha:

1. `lib/templates/modal.mustache:58-62` **sempre** renderiza o `div.modal-footer` com
   `data-region="footer"`, e um bloco `{{$footer}}` vazio por padrão.
2. `Modal.show()` (`lib/amd/src/modal.js:868`) pergunta `hasFooterContent()` — que é literalmente
   `this.getFooter().children().length ? true : false` (`:686`).
3. Com zero filhos, cai no `else` e chama `hideFooter()` (`:875-879`), que aplica a classe `.hidden`
   (`:695`).
4. `.hidden { display: none; }` (`theme/boost/scss/moodle/core.scss:417-419`) — o rodapé **colapsa**.
   Não é uma barra vazia: é barra nenhuma.

**Portanto: dar um filho ao rodapé faz o core revelá-lo sozinho** (`showFooter()`, `:704`). E é
exatamente isso que o `ModalSaveCancel` é — o **mesmo** `core/modal` com o bloco `{{$footer}}`
preenchido com Cancelar + Salvar (`lib/templates/modal_save_cancel.mustache:42-45`).

**Censo do garfo** (em `amd/src/central/`, pontos de construção): **7** × `Modal.create` — nenhum
passa `footer`; **5** × `ModalSaveCancel.create`; **1** × `ModalDeleteCancel.create`; **4** ×
`new ModalForm`. E `setSaveButtonText` aparece em **1** único ponto do plugin inteiro:
`competency_browser.js:107`. (Números conferidos com `grep -rn` em `amd/src/`; o `Modal.create` do
`accordion.js:1191` fica de fora por ser view de aluno, não hub — é o que faz o total do hub ser 7 e
não 8.)

**A troca é menos código, não mais.** O `configure()` do core aceita `buttons` e `removeOnClose` no
mesmo objeto (`lib/amd/src/modal.js:246-284`, o `buttons` é aplicado em `:284` via `setButtonText`),
então as duas linhas de hoje (`js:248-249`) viram uma:

```js
const modal = await ModalSaveCancel.create({
    title, body: html, removeOnClose: true,
    buttons: {save: addlabel, cancel: closelabel},
});
```

e o Mustache perde as **5** linhas do botão (`:35-39`). **Sem string nova:**
`central_browseframeworks_add` ("Adicionar selecionadas") já é a que o corpo usa (`:37`), e
"Fechar" é o `closebuttontitle` do core (`lang/en/moodle.php:280`).

**O desabilitado-até-marcar sobrevive** — e desde **2026-07-15** o precedente é de casa. O
`competency_browser.js` passou a fazer exatamente o que o IMP-06 propõe: `ModalSaveCancel` +
`setSaveButtonText` (`:107`) + um `modal.setButtonDisabled('save', true)` logo depois do
`setRemoveOnClose` (`:110`), com um `updateAddButton` (`:48-50`) que recalcula pela contagem a cada
`click`/`change` da lista (`:140-141`). Fora do plugin, o **`competency_picker` do format_mtube** já
chegava lá por outro caminho (um `_setSaveEnabled` que liga o botão pela contagem da seleção, via
`getFooter()` + `getActionSelector('save')` — os dois públicos no core, `modal.js:411` e `:1194`).

O caminho de casa é o mais curto dos dois: `setButtonDisabled` também é público e faz o `getFooter()`
+ `getActionSelector()` por dentro (`modal.js:1222-1223`). Então o nosso `updateAddButton`
(`js:140-142`) não só sobrevive como **encolhe** — perde o `state.addbtnEl` e o `querySelector` que o
alimenta (`js:277`).

> **A ressalva, e ela não é opcional.** `ModalSaveCancel.registerEventListeners()`
> (`modal_save_cancel.js:52-59`) chama `registerCloseOnSave()`, e o handler do core **fecha o
> diálogo** depois do `ModalEvents.save` — a menos que alguém chame `preventDefault()`
> (`modal.js:1100-1116`). Este modal **não pode fechar** no "Adicionar selecionadas": o toast, o
> `flash` da linha nova e o estado vazio acontecem **no lugar**, e o usuário volta à árvore. Logo, o
> `ModalEvents.save` daqui precisa de um `preventDefault()` **incondicional**.
>
> **Por isso o IMP-06 não é copiar o vizinho — e o vizinho mudou em 2026-07-15.** O
> `competency_browser.js` — dono da única chamada `setSaveButtonText` do plugin (`:107`) — ligava o
> save **sem `preventDefault` nenhum** e fechava sempre, inclusive quando **nada** estava marcado: o
> diálogo sumia e não adicionava coisa alguma. O `e14977c` fechou esse buraco, e a fiação de lá hoje
> é `addSelected(state, event)` (`:144`) com um `preventDefault()` **condicional**, que só dispara no
> caso vazio (`:64-68`) — atrás de um botão que já **nasce desabilitado** (`:110`). Ou seja: lá o
> `preventDefault` é **backstop**, não mecanismo. Num add real ele continua **fechando**, e continua
> certo para ele, que é picker de uma tacada.
>
> **A conclusão não muda; o contraste é que ficou mais limpo.** O `competency_browser` convergiu para
> a forma do `competency_picker` do mtube (fecha; `preventDefault` só para **barrar** seleção vazia)
> — hoje são **dois** exemplos shipados da **mesma** forma, não duas formas. O de referenciadas
> segue sendo o caso à parte, e o único: **gerencia**, escreve a cada clique e **fica**. Nele o
> `preventDefault` é **incondicional** e é o mecanismo, não o backstop — e é justamente disso que
> nenhum dos dois vizinhos precisa. A chamada `setSaveButtonText` se reusa; o
> desabilitar-até-marcar agora se reusa também
> (`:110` + `:48-50`); a **fiação do save, não**.

**O que o IMP-06 não conserta:** o cap de `40vh` (`styles.css:5685-5688`) existe por causa das
**linhas de relação** abaixo da árvore, não do botão — ele fica. E a sentinela continua dentro da
caixa (`browser.js:486-490`), então a paginação segue presa à rolagem dela.
