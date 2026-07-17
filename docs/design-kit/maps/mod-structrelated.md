# Mapa de Campos — `MOD.STRUCTREL` · Modal de espiar competência referenciada (as-is)

Modal aberto ao **clicar um chip de competência referenciada** no painel de detalhe da aba
Estrutura. Mostra o **mesmo card de detalhe** que o painel inline (partial compartilhado
`structure_detail_content`), embrulhado para preencher o diálogo com o **seu próprio** botão de
fechar. É read-only: os contadores de métrica renderizam como números **não-interativos** e a
seção de referenciadas é **omitida** — as duas coisas para não abrir um `MOD.USAGE` nem outro
`MOD.STRUCTREL` por cima deste.

Não confundir com o **`MOD.RELATED`** (`related_competencies.mustache`), que é o modal de
**editar** referências (adicionar/remover). Este é o modal de **espiar** uma referência já
existente — o card, não o editor. É a superfície mais incomum do plugin: um diálogo **sem
cabeçalho**, onde **o card é o diálogo**.

Criado em `47677dd`; sem mapa até aqui (lacuna registrada na Seção 3 do design de 2026-07-14).

- **Mustache:** [`structure_related_modal.mustache`](../../../templates/central/structure_related_modal.mustache) (casca) + o partial compartilhado [`structure_detail_content.mustache`](../../../templates/central/structure_detail_content.mustache) (o card, o mesmo do painel inline)
- **JS:** [`competency_detail.js`](../../../amd/src/central/competency_detail.js) (`openCompetencyDetailModal`, 265-296) · [`structure.js`](../../../amd/src/central/structure.js) (gatilho, 64 + 1247)
- **CSS:** [`styles.css`](../../../styles.css) (5146-5190, o contrato do diálogo; + a exclusão em 3557)

## IDs

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.STRUCTREL-TRIGGER` | {nome da competência} | chip (gatilho) | chip renderizado no conteúdo de detalhe (partial `structure_detail_content`, seção de referenciadas), **só quando `showrelated` está ligado** no painel inline | `data-action="related"` · `data-id` | selector `structure.js:64`; o handler (`structure.js:1247`) lê `dataset.id` e chama `openCompetencyDetailModal`. **Não existe no próprio modal** — dentro dele `showrelated` é `false`, então um chip nunca abre outro modal. Só o painel **inline** dispara |
| `MOD.STRUCTREL-MODAL` | {nome} | `core/modal` | `competency_detail.js:277-283` | `title: data.name`, `body`, `large: true`, `show: true`, `removeOnClose: true` | `core/modal` **puro** — sem `footer`. O `title` **é** passado, mas o cabeçalho é `display:none` (ver CSS), então o título vive **só** no card. A classe `local-dimensions-related-modal` entra **depois**, em `:285` (`root.addClass`), e é ela que dispara todo o contrato de CSS. `removeOnClose` → o modal morre ao fechar, então os renders assíncronos de chip/descrição são guardados por `isConnected` (`:291`) |
| `MOD.STRUCTREL-CARD` | — | wrapper | `structure_related_modal.mustache:36` | `.local-dimensions-related-modal-card` | `position:relative;overflow:hidden` (`styles.css:5168-5171`) — âncora do botão absoluto e recorte do card. É o mesmo card do painel inline (`local-dimensions-central-plans-detail … structure-detail`), só com a classe extra do modal |
| `MOD.STRUCTREL-CONTENT` | — | conteúdo | `structure_related_modal.mustache:41-44` + `renderDetailInto` (`competency_detail.js:291`) | `data-region="detail-content"` · `detailconfig {linksclickable:false, showrelated:false}` | o **mesmo** partial `structure_detail_content` do inline, com **duas travas**: `linksclickable:false` deixa os contadores de métrica como número puro (senão um clique abriria `MOD.USAGE` **por cima** deste modal); `showrelated:false` **omite** a seção de referenciadas (senão um chip abriria outro `MOD.STRUCTREL`, recursão). Preenchido client-side a partir de `local_dimensions_get_structure_node` (`:267`) |
| `MOD.STRUCTREL-CLOSE` | Fechar | botão (só-ícone) | `structure_related_modal.mustache:37-40` | `data-action="close-related-modal"` · `aria-label` `{{#str}}closebuttontitle{{/str}}` | **o fechar real deste modal** — o `.btn-close` do core está `display:none` junto com o cabeçalho. `fa-times`; a **cor** é setada em JS pelo `data.textcolor` da competência (`competency_detail.js:294`, fallback `#fff`), para contrastar com o cabeçalho colorido do card. Fecha por `modal.hide()` (`:295`). Escopado em `.local-dimensions-related-modal-close` (`styles.css:5173-5190`): absoluto 18/18, 36×36, borda/fundo branco translúcido, `transition:background .15s` |

## O contrato de CSS — por que é o único diálogo assim

O `local-dimensions-related-modal` (a classe no `root`) reescreve a casca do `core/modal` inteira
para que **o card seja o diálogo**, sem moldura de modal em volta (`styles.css:5146-5166`):

| Regra | Valor | Por quê |
| --- | --- | --- |
| `.modal-dialog` | `max-width:620px` + `border-radius:24px` | o card cabe em 620; o core foca `.modal-dialog` (tabindex 0) ao abrir e o outline de foco abraça o card — arredondar em 24px casa com o card, já que corpo/conteúdo não têm padding (comentário em `:5149-5150`) |
| `.modal-header` | `display:none` | o cabeçalho colorido é **do card**, não do modal; o título do core seria redundante |
| `.modal-content` | `border:0;background:transparent;box-shadow:none` | tira a moldura do modal — o que se vê é só o card |
| `.modal-body` | `padding:0` | o card encosta na borda do diálogo |

**A exclusão que fecha o contrato:** o restyle global do `.btn-close` (o chip azul-claro que o
plugin aplica ao fechar de **todo** modal com conteúdo do plugin) traz um
`:not(.local-dimensions-related-modal)` em cada seletor (`styles.css:3557, 3571, 3581-3582`) —
ou seja, este modal é **explicitamente retirado** do restyle, porque ele tem o **seu** botão de
fechar (`MOD.STRUCTREL-CLOSE`), colorido pela competência, e o `.btn-close` do core está escondido
de qualquer forma. Sem essa exclusão, dois botões de fechar disputariam o canto.

## Resumo — o que este mapa fixa

| Fato | Onde |
| --- | --- |
| É **espiar**, não **editar** — o card, não o `MOD.RELATED` | `competency_detail.js:265` vs `related_competencies.mustache` |
| O gatilho vive **fora** do modal (só no painel inline, `showrelated` on) | `structure.js:64` + `:1247` |
| As duas travas (`linksclickable`/`showrelated` off) existem para **não empilhar** modais | `competency_detail.js:275` |
| O `title` é passado mas o cabeçalho é `display:none` — o nome vive no card | `:279` + `styles.css:5154` |
| É o único diálogo **sem cabeçalho** e **fora** do restyle global do `.btn-close` | `styles.css:3557` (`:not(...)`) + `5154` |
| Cor do fechar vem do `textcolor` da competência, em JS | `competency_detail.js:294` |
